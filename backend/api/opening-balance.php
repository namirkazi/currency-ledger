<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$user = requireAdmin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$currency = strtoupper(trim($data['currency'] ?? ''));
$amount = trim((string) ($data['amount'] ?? ''));

if (!in_array($currency, ['AED', 'USDT'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid currency.'
    ], 422);
}

if ($amount === '' || !is_numeric($amount)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid amount.'
    ], 422);
}

if ((float) $amount < 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Opening balance cannot be negative.'
    ], 422);
}

try {
    $pdo->beginTransaction();

    /*
     * Opening balances are only established once.
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM opening_balances
        WHERE currency = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$currency]);

    if ($stmt->fetch()) {
        $pdo->rollBack();

        jsonResponse([
            'success' => false,
            'message' => "An opening balance for {$currency} already exists."
        ], 409);
    }

    /*
     * Create the opening balance record.
     */
    $stmt = $pdo->prepare("
        INSERT INTO opening_balances (
            currency,
            amount,
            created_by
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $currency,
        $amount,
        $user['id']
    ]);

    $openingBalanceId = $pdo->lastInsertId();

    /*
     * Create the corresponding ledger entry.
     */
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entries (
            opening_balance_id,
            entry_type,
            currency,
            amount
        )
        VALUES (?, 'OPENING_BALANCE', ?, ?)
    ");

    $stmt->execute([
        $openingBalanceId,
        $currency,
        $amount
    ]);

    /*
     * Initialize the current balance.
     */
    $stmt = $pdo->prepare("
        INSERT INTO account_balances (
            currency,
            balance
        )
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
            balance = balance + VALUES(balance)
    ");

    $stmt->execute([
        $currency,
        $amount
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => "{$currency} opening balance created.",
        'currency' => $currency,
        'amount' => $amount
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'success' => false,
        'message' => 'Unable to create opening balance.'
    ], 500);
}
