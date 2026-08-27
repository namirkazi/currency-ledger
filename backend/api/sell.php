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

$currencyId = filter_var(
    $data['currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$currencyAmount = decimalValue(
    $data['currency_amount'] ?? ''
);

$rate = decimalValue(
    $data['rate'] ?? ''
);

$requestId =
    $_SERVER['HTTP_X_IDEMPOTENCY_KEY']
    ?? trim($data['request_id'] ?? '');

if (!$currencyId || $currencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid currency is required.'
    ], 422);
}

if (
    !preg_match(
        '/^[a-f0-9-]{16,64}$/i',
        $requestId
    )
) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid request ID is required.'
    ], 422);
}

if (
    bccomp($currencyAmount, '0', 6) <= 0
) {
    jsonResponse([
        'success' => false,
        'message' => 'Currency amount must be greater than zero.'
    ], 422);
}

if (
    bccomp($rate, '0', 6) <= 0
) {
    jsonResponse([
        'success' => false,
        'message' => 'Rate must be greater than zero.'
    ], 422);
}

try {

    $pdo->beginTransaction();

    /*
     * Idempotency.
     */
    $existingStmt = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE request_id = ?
        FOR UPDATE
    ");

    $existingStmt->execute([$requestId]);

    $existing = $existingStmt->fetch();

    if ($existing) {

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'duplicate' => true,
            'transaction' => $existing
        ]);
    }

    /*
     * Currency must exist and be active.
     */
    $currencyStmt = $pdo->prepare("
        SELECT
            id,
            code,
            name
        FROM currencies
        WHERE id = ?
          AND active = 1
        LIMIT 1
    ");

    $currencyStmt->execute([$currencyId]);

    $currency = $currencyStmt->fetch();

    if (!$currency) {
        throw new RuntimeException(
            'Currency does not exist or is inactive.'
        );
    }

    /*
     * Get USD.
     */
    $usdStmt = $pdo->query("
        SELECT id
        FROM currencies
        WHERE code = 'USD'
        LIMIT 1
    ");

    $usdCurrencyId = $usdStmt->fetchColumn();

    if (!$usdCurrencyId) {
        throw new RuntimeException(
            'USD currency is not configured.'
        );
    }

    if ((int) $currencyId === (int) $usdCurrencyId) {
        throw new RuntimeException(
            'USD cannot be traded against itself.'
        );
    }

    /*
     * USD proceeds.
     */
    $usdAmount = bcmul(
        $currencyAmount,
        $rate,
        6
    );

    /*
     * Ensure balance rows exist.
     */
    $ensureBalance = $pdo->prepare("
        INSERT IGNORE INTO account_balances
            (currency_id, balance)
        VALUES
            (?, 0)
    ");

    $ensureBalance->execute([$currencyId]);
    $ensureBalance->execute([$usdCurrencyId]);

    /*
     * Lock both balances.
     */
    $balanceStmt = $pdo->prepare("
        SELECT
            currency_id,
            balance
        FROM account_balances
        WHERE currency_id IN (?, ?)
        ORDER BY currency_id
        FOR UPDATE
    ");

    $balanceStmt->execute([
        $currencyId,
        $usdCurrencyId
    ]);

    $balances = [];

    while ($row = $balanceStmt->fetch()) {
        $balances[(int) $row['currency_id']] = $row['balance'];
    }

    if (!isset($balances[(int) $currencyId])) {
        throw new RuntimeException(
            'Currency balance record does not exist.'
        );
    }

    /*
     * SELL requires sufficient traded currency.
     */
    if (
        bccomp(
            $balances[(int) $currencyId],
            $currencyAmount,
            6
        ) < 0
    ) {
        throw new RuntimeException(
            "Insufficient {$currency['code']} balance."
        );
    }

    /*
     * Get FIFO inventory for THIS currency only.
     */
    $lotsStmt = $pdo->prepare("
        SELECT
            id,
            remaining_amount,
            acquisition_rate
        FROM inventory_lots
        WHERE currency_id = ?
          AND remaining_amount > 0
        ORDER BY acquired_at ASC, id ASC
        FOR UPDATE
    ");

    $lotsStmt->execute([
        $currencyId
    ]);

    $lots = $lotsStmt->fetchAll();

    /*
     * Calculate acquisition cost.
     */
    $remaining = $currencyAmount;
    $cost = '0.000000';

    foreach ($lots as $lot) {

        if (
            bccomp($remaining, '0', 6) <= 0
        ) {
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

    if (
        bccomp($remaining, '0', 6) > 0
    ) {
        throw new RuntimeException(
            'Insufficient inventory for this sale.'
        );
    }

    /*
     * Profit is ALWAYS USD.
     */
    $profit = bcsub(
        $usdAmount,
        $cost,
        6
    );

    /*
     * Create transaction.
     */
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions
        (
            request_id,
            type,
            currency_amount,
            rate,
            usd_amount,
            realized_profit,
            status,
            created_by,
            currency_id
        )
        VALUES
        (?, 'SELL', ?, ?, ?, ?, 'COMPLETED', ?, ?)
    ");

    $transactionStmt->execute([
        $requestId,
        $currencyAmount,
        $rate,
        $usdAmount,
        $profit,
        $user['id'],
        $currencyId
    ]);

    $transactionId = $pdo->lastInsertId();

    /*
     * Consume FIFO inventory.
     */
    $remaining = $currencyAmount;

    $allocationStmt = $pdo->prepare("
        INSERT INTO sale_allocations
        (
            transaction_id,
            inventory_lot_id,
            currency_amount,
            acquisition_rate,
            cost_amount
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");

    $updateLotStmt = $pdo->prepare("
        UPDATE inventory_lots
        SET remaining_amount = remaining_amount - ?
        WHERE id = ?
    ");

    foreach ($lots as $lot) {

        if (
            bccomp($remaining, '0', 6) <= 0
        ) {
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

        $allocationStmt->execute([
            $transactionId,
            $lot['id'],
            $take,
            $lot['acquisition_rate'],
            $lotCost
        ]);

        $updateLotStmt->execute([
            $take,
            $lot['id']
        ]);

        $remaining = bcsub(
            $remaining,
            $take,
            6
        );
    }

    /*
     * Ledger:
     *
     * Traded currency leaves.
     * USD enters.
     */
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            currency_id,
            transaction_id,
            entry_type,
            amount
        )
        VALUES
        (?, ?, 'TRADE', ?)
    ");

    $ledgerStmt->execute([
        $currencyId,
        $transactionId,
        bcmul($currencyAmount, '-1', 6)
    ]);

    $ledgerStmt->execute([
        $usdCurrencyId,
        $transactionId,
        $usdAmount
    ]);

    /*
     * Traded currency decreases.
     */
    $updateCurrency = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency_id = ?
    ");

    $updateCurrency->execute([
        $currencyAmount,
        $currencyId
    ]);

    /*
     * USD increases.
     */
    $updateUsd = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance + ?
        WHERE currency_id = ?
    ");

    $updateUsd->execute([
        $usdAmount,
        $usdCurrencyId
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'transaction_id' => $transactionId,
        'type' => 'SELL',
        'currency_id' => (int) $currencyId,
        'currency_code' => $currency['code'],
        'currency_amount' => $currencyAmount,
        'rate' => $rate,
        'usd_amount' => $usdAmount,
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
