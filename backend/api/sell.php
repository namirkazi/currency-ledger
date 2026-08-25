<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

$user = requireAuth();

$data = json_decode(file_get_contents('php://input'), true);

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

$aed = bcmul($usdt, $rate, 6);

try {

    $pdo->beginTransaction();

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
     * Lock USDT balance before checking it.
     */
    $balanceStmt = $pdo->prepare("
        SELECT balance
        FROM account_balances
        WHERE currency = 'USDT'
        FOR UPDATE
    ");

    $balanceStmt->execute();

    $balance = $balanceStmt->fetchColumn();

    if ($balance === false) {
        throw new RuntimeException(
            'USDT balance record does not exist.'
        );
    }

    if (bccomp($balance, $usdt, 6) < 0) {
        throw new RuntimeException(
            'Insufficient USDT balance.'
        );
    }

    /*
     * Get inventory lots in acquisition order.
     */
    $lotsStmt = $pdo->prepare("
        SELECT
            id,
            remaining_amount,
            acquisition_rate
        FROM inventory_lots
        WHERE remaining_amount > 0
        ORDER BY acquired_at ASC, id ASC
        FOR UPDATE
    ");

    $lotsStmt->execute();

    $lots = $lotsStmt->fetchAll();

    $remaining = $usdt;
    $cost = '0.000000';

    foreach ($lots as $lot) {

        if (bccomp($remaining, '0', 6) <= 0) {
            break;
        }

        $take = bccomp(
            $lot['remaining_amount'],
            $remaining,
            6
        ) <= 0
            ? $lot['remaining_amount']
            : $remaining;

        $lotCost = bcmul(
            $take,
            $lot['acquisition_rate'],
            6
        );

        $cost = bcadd(
            $cost,
            $lotCost,
            6
        );

        $remaining = bcsub(
            $remaining,
            $take,
            6
        );
    }

    if (bccomp($remaining, '0', 6) > 0) {
        throw new RuntimeException(
            'Insufficient inventory for this sale.'
        );
    }

    $profit = bcsub(
        $aed,
        $cost,
        6
    );

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
        (?, 'SELL_USDT', ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $requestId,
        $usdt,
        $rate,
        $aed,
        $profit,
        $user['id']
    ]);

    $transactionId = $pdo->lastInsertId();

    /*
     * Consume inventory lots.
     */
    $remaining = $usdt;

    foreach ($lots as $lot) {

        if (bccomp($remaining, '0', 6) <= 0) {
            break;
        }

        $take = bccomp(
            $lot['remaining_amount'],
            $remaining,
            6
        ) <= 0
            ? $lot['remaining_amount']
            : $remaining;

        $stmt = $pdo->prepare("
            INSERT INTO sale_allocations
            (
                transaction_id,
                inventory_lot_id,
                usdt_amount,
                acquisition_rate,
                cost_amount
            )
            VALUES
            (?, ?, ?, ?, ?)
        ");

        $lotCost = bcmul(
            $take,
            $lot['acquisition_rate'],
            6
        );

        $stmt->execute([
            $transactionId,
            $lot['id'],
            $take,
            $lot['acquisition_rate'],
            $lotCost
        ]);

        $stmt = $pdo->prepare("
            UPDATE inventory_lots
            SET remaining_amount = remaining_amount - ?
            WHERE id = ?
        ");

        $stmt->execute([
            $take,
            $lot['id']
        ]);

        $remaining = bcsub(
            $remaining,
            $take,
            6
        );
    }

    $ledger = $pdo->prepare("
        INSERT INTO ledger_entries
            (transaction_id, entry_type, currency, amount)
        VALUES
            (?, 'TRADE', ?, ?)
    ");

    $ledger->execute([
        $transactionId,
        'AED',
        $aed
    ]);

    $ledger->execute([
        $transactionId,
        'USDT',
        bcmul($usdt, '-1', 6)
    ]);

    $update = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance + ?
        WHERE currency = 'AED'
    ");

    $update->execute([$aed]);

    $update = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency = 'USDT'
    ");

    $update->execute([$usdt]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'transaction_id' => $transactionId,
        'type' => 'SELL_USDT',
        'usdt_amount' => $usdt,
        'rate' => $rate,
        'aed_amount' => $aed,
        'cost' => $cost,
        'profit' => $profit
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
