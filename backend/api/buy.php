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
     * Idempotency check.
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
     * Make sure the currency exists and is active.
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
     * USD is the settlement/profit currency.
     * Trading USD against USD makes no sense.
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
     * USD amount = currency amount × USD rate.
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
     *
     * We always lock the two currencies together so
     * concurrent trades cannot spend the same USD.
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

    if (!isset($balances[(int) $usdCurrencyId])) {
        throw new RuntimeException(
            'USD balance record does not exist.'
        );
    }

    /*
     * BUY requires sufficient USD.
     */
    if (
        bccomp(
            $balances[(int) $usdCurrencyId],
            $usdAmount,
            6
        ) < 0
    ) {
        throw new RuntimeException(
            'Insufficient USD balance.'
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
            currency_amount,
            rate,
            usd_amount,
            realized_profit,
            status,
            created_by,
            currency_id
        )
        VALUES
        (?, 'BUY', ?, ?, ?, 0, 'COMPLETED', ?, ?)
    ");

    $transactionStmt->execute([
        $requestId,
        $currencyAmount,
        $rate,
        $usdAmount,
        $user['id'],
        $currencyId
    ]);

    $transactionId = $pdo->lastInsertId();

    /*
     * Ledger entries:
     *
     * Selected currency enters.
     * USD leaves.
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
        $currencyAmount
    ]);

    $ledgerStmt->execute([
        $usdCurrencyId,
        $transactionId,
        bcmul($usdAmount, '-1', 6)
    ]);

    /*
     * Create inventory lot.
     *
     * Acquisition rate is always USD per unit
     * of the traded currency.
     */
    $inventoryStmt = $pdo->prepare("
        INSERT INTO inventory_lots
        (
            source_transaction_id,
            original_amount,
            remaining_amount,
            acquisition_rate,
            currency_id
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");

    $inventoryStmt->execute([
        $transactionId,
        $currencyAmount,
        $currencyAmount,
        $rate,
        $currencyId
    ]);

    /*
     * USD decreases.
     */
    $updateUsd = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance - ?
        WHERE currency_id = ?
    ");

    $updateUsd->execute([
        $usdAmount,
        $usdCurrencyId
    ]);

    /*
     * Traded currency increases.
     */
    $updateCurrency = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance + ?
        WHERE currency_id = ?
    ");

    $updateCurrency->execute([
        $currencyAmount,
        $currencyId
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'transaction_id' => $transactionId,
        'type' => 'BUY',
        'currency_id' => (int) $currencyId,
        'currency_code' => $currency['code'],
        'currency_amount' => $currencyAmount,
        'rate' => $rate,
        'usd_amount' => $usdAmount,
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
