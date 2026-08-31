<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

$user = requireAuth();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($data)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid JSON request.'
    ], 400);
}

$type = strtoupper(trim($data['type'] ?? 'BUY'));

$fromCurrencyId = filter_var(
    $data['from_currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$toCurrencyId = filter_var(
    $data['to_currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$fromAmount = decimalValue(
    $data['from_amount'] ?? ''
);

$toAmount = decimalValue(
    $data['to_amount'] ?? ''
);

$exchangeRate = decimalValue(
    $data['exchange_rate'] ?? ''
);

$requestId =
    $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
    ?? trim($data['request_id'] ?? '');

if (!in_array($type, ['BUY', 'SELL'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Transaction type must be BUY or SELL.'
    ], 422);
}

if (!$fromCurrencyId || $fromCurrencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid source currency is required.'
    ], 422);
}

if (!$toCurrencyId || $toCurrencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid destination currency is required.'
    ], 422);
}

if ($fromCurrencyId === $toCurrencyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Source and destination currencies must be different.'
    ], 422);
}

if (bccomp($fromAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'From amount must be greater than zero.'
    ], 422);
}

if (bccomp($toAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'To amount must be greater than zero.'
    ], 422);
}

if (bccomp($exchangeRate, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Exchange rate must be greater than zero.'
    ], 422);
}

if (!preg_match('/^[a-f0-9-]{16,64}$/i', $requestId)) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid request ID is required.'
    ], 422);
}

try {

    $pdo->beginTransaction();

    /*
     * Prevent duplicate submissions.
     */
    $existingStmt = $pdo->prepare("
        SELECT id
        FROM transactions
        WHERE request_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $existingStmt->execute([
        $requestId
    ]);

    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'duplicate' => true,
            'transaction_id' => (int) $existing['id']
        ]);
    }

    /*
     * Lock source currency balance.
     */
    $sourceBalanceStmt = $pdo->prepare("
        SELECT
            ab.currency_id,
            ab.balance,
            c.code
        FROM account_balances ab
        INNER JOIN currencies c
            ON c.id = ab.currency_id
        WHERE ab.currency_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $sourceBalanceStmt->execute([
        $fromCurrencyId
    ]);

    $sourceBalance = $sourceBalanceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sourceBalance) {
        throw new RuntimeException(
            'Source currency balance does not exist.'
        );
    }

    if (bccomp(
        $sourceBalance['balance'],
        $fromAmount,
        6
    ) < 0) {

        throw new RuntimeException(
            "Insufficient {$sourceBalance['code']} balance."
        );
    }

    /*
     * Validate destination currency.
     */
    $destinationCurrencyStmt = $pdo->prepare("
        SELECT
            id,
            code
        FROM currencies
        WHERE id = ?
          AND active = 1
        LIMIT 1
    ");

    $destinationCurrencyStmt->execute([
        $toCurrencyId
    ]);

    $destinationCurrency =
        $destinationCurrencyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$destinationCurrency) {
        throw new RuntimeException(
            'Destination currency does not exist or is inactive.'
        );
    }

    /*
     * Create transaction.
     */
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions
        (
            request_id,
            type,
            from_currency_id,
            from_amount,
            to_currency_id,
            to_amount,
            exchange_rate,
            realized_profit,
            status,
            created_by
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, 0, 'COMPLETED', ?)
    ");

    $transactionStmt->execute([
        $requestId,
        $type,
        $fromCurrencyId,
        $fromAmount,
        $toCurrencyId,
        $toAmount,
        $exchangeRate,
        $user['id']
    ]);

    $transactionId = $pdo->lastInsertId();

    /*
     * Deduct source currency.
     */
    $deductStmt = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency_id = ?
    ");

    $deductStmt->execute([
        $fromAmount,
        $fromCurrencyId
    ]);

    /*
     * Add destination currency.
     *
     * If no balance row exists yet,
     * create one automatically.
     */
    $addStmt = $pdo->prepare("
        INSERT INTO account_balances
        (
            currency_id,
            balance
        )
        VALUES
        (?, ?)

        ON DUPLICATE KEY UPDATE
            balance = balance + VALUES(balance)
    ");

    $addStmt->execute([
        $toCurrencyId,
        $toAmount
    ]);

    /*
     * Ledger entry: money leaving.
     */
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            currency_id,
            amount,
            entry_type,
            transaction_id
        )
        VALUES
        (?, ?, 'TRADE', ?)
    ");

    $negativeAmount = bcmul(
        $fromAmount,
        '-1',
        6
    );

    $ledgerStmt->execute([
        $fromCurrencyId,
        $negativeAmount,
        $transactionId
    ]);

    /*
     * Ledger entry: money entering.
     */
    $ledgerStmt->execute([
        $toCurrencyId,
        $toAmount,
        $transactionId
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Currency exchange completed successfully.',
        'transaction' => [
            'id' => (int) $transactionId,
            'type' => $type,
            'from_currency_id' => $fromCurrencyId,
            'from_amount' => $fromAmount,
            'to_currency_id' => $toCurrencyId,
            'to_amount' => $toAmount,
            'exchange_rate' => $exchangeRate
        ]
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'exchange.php: ' . $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
