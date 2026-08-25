<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';
require_once __DIR__ . '/../helpers/ledger.php';

$user = requireAuth();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$usdt = decimalValue($data['usdt_amount'] ?? '');
$rate = decimalValue($data['rate'] ?? '');

$requestId =
    $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
    ?? trim($data['request_id'] ?? '');

if (!preg_match(
    '/^[a-f0-9-]{16,64}$/i',
    $requestId
)) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid request ID is required.'
    ], 422);
}

$aed = calculateAed($usdt, $rate);

try {

    $pdo->beginTransaction();

    /*
     * Idempotency check.
     */
    $existing = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE request_id = ?
        FOR UPDATE
    ");

    $existing->execute([$requestId]);

    $existingTransaction = $existing->fetch();

    if ($existingTransaction) {
        $pdo->commit();

        jsonResponse([
            'success' => true,
            'duplicate' => true,
            'transaction' => $existingTransaction
        ]);
    }

    /*
     * Lock AED balance.
     */
    $balanceStmt = $pdo->prepare("
        SELECT balance
        FROM account_balances
        WHERE currency = 'AED'
        FOR UPDATE
    ");

    $balanceStmt->execute();

    $balance = $balanceStmt->fetchColumn();

    if ($balance === false) {
        throw new RuntimeException(
            'AED balance record does not exist.'
        );
    }

    if (bccomp($balance, $aed, 6) < 0) {
        throw new RuntimeException(
            'Insufficient AED balance.'
        );
    }

    /*
     * Create transaction.
     */
    $stmt = $pdo->prepare("
        INSERT INTO transactions
        (
            request_id,
            type,
            usdt_amount,
            rate,
            aed_amount,
            realized_profit,
            created_by
        )
        VALUES
        (?, 'BUY_USDT', ?, ?, ?, 0, ?)
    ");

    $stmt->execute([
        $requestId,
        $usdt,
        $rate,
        $aed,
        $user['id']
    ]);

    $transactionId = $pdo->lastInsertId();

    /*
     * Create ledger entries.
     */
    $ledger = $pdo->prepare("
        INSERT INTO ledger_entries
            (transaction_id, entry_type, currency, amount)
        VALUES
            (?, 'TRADE', ?, ?)
    ");

    // AED leaves the business.
    $ledger->execute([
        $transactionId,
        'AED',
        bcmul($aed, '-1', 6)
    ]);

    // USDT enters the business.
    $ledger->execute([
        $transactionId,
        'USDT',
        $usdt
    ]);

    /*
     * Create USDT inventory lot.
     *
     * This is what allows SELL to determine
     * the acquisition cost and realized profit.
     */
    $inventory = $pdo->prepare("
        INSERT INTO inventory_lots
        (
            source_transaction_id,
            original_amount,
            remaining_amount,
            acquisition_rate
        )
        VALUES
        (?, ?, ?, ?)
    ");

    $inventory->execute([
        $transactionId,
        $usdt,
        $usdt,
        $rate
    ]);

    /*
     * Update AED balance.
     */
    $update = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency = 'AED'
    ");

    $update->execute([$aed]);

    /*
     * Update USDT balance.
     */
    $update = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance + ?
        WHERE currency = 'USDT'
    ");

    $update->execute([$usdt]);

    /*
     * Everything succeeded.
     */
    $pdo->commit();

    jsonResponse([
        'success' => true,
        'transaction_id' => $transactionId,
        'type' => 'BUY_USDT',
        'usdt_amount' => $usdt,
        'rate' => $rate,
        'aed_amount' => $aed,
        'profit' => '0.000000'
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
