<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

$user = requireAdmin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$currencyId = filter_var(
    $data['currency_id'] ?? null,
    FILTER_VALIDATE_INT
);

$amount = decimalValue(
    $data['amount'] ?? ''
);

if (!$currencyId || $currencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid currency is required.'
    ], 422);
}

if (bccomp($amount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Amount must be greater than zero.'
    ], 422);
}

try {

    $pdo->beginTransaction();

    /*
     * Make sure the currency exists and is active.
     */
    $currencyStmt = $pdo->prepare("
        SELECT
            id,
            code,
            name,
            symbol
        FROM currencies
        WHERE id = ?
          AND active = 1
        LIMIT 1
    ");

    $currencyStmt->execute([
        $currencyId
    ]);

    $currency = $currencyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currency) {
        throw new RuntimeException(
            'Currency does not exist or is inactive.'
        );
    }

    /*
     * Prevent multiple opening balances for the same currency.
     */
    $existingStmt = $pdo->prepare("
        SELECT id
        FROM opening_balances
        WHERE currency_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $existingStmt->execute([
        $currencyId
    ]);

    if ($existingStmt->fetch()) {
        throw new RuntimeException(
            "An opening balance already exists for {$currency['code']}."
        );
    }

    /*
     * Create opening balance record.
     */
    $openingStmt = $pdo->prepare("
        INSERT INTO opening_balances
        (
            currency_id,
            amount,
            created_by
        )
        VALUES
        (?, ?, ?)
    ");

    $openingStmt->execute([
        $currencyId,
        $amount,
        $user['id']
    ]);

    $openingBalanceId = $pdo->lastInsertId();

    /*
     * Ensure account balance exists.
     */
    $balanceStmt = $pdo->prepare("
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

    $balanceStmt->execute([
        $currencyId,
        $amount
    ]);

    /*
     * Create ledger entry.
     */
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            currency_id,
            opening_balance_id,
            entry_type,
            amount
        )
        VALUES
        (?, ?, 'OPENING_BALANCE', ?)
    ");

    $ledgerStmt->execute([
        $currencyId,
        $openingBalanceId,
        $amount
    ]);

    /*
     * If this is a non-USD currency, it becomes
     * available inventory at opening cost of zero.
     *
     * We will refine opening inventory/cost basis
     * later if required.
     */

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Opening balance created successfully.',
        'opening_balance' => [
            'id' => $openingBalanceId,
            'currency_id' => (int) $currencyId,
            'currency_code' => $currency['code'],
            'amount' => $amount
        ]
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
