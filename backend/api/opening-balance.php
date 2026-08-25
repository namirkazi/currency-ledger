<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

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

if (
    $amount === '' ||
    !preg_match('/^\d+(\.\d{1,6})?$/', $amount) ||
    bccomp($amount, '0', 6) < 0
) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid amount.'
    ], 422);
}

try {

    $pdo->beginTransaction();

    /*
     * Opening balance is only allowed once
     * for each individual currency.
     */
    $check = $pdo->prepare("
        SELECT id
        FROM opening_balances
        WHERE currency = ?
        LIMIT 1
    ");

    $check->execute([$currency]);

    if ($check->fetch()) {
        throw new RuntimeException(
            "{$currency} opening balance already exists."
        );
    }

    /*
     * Create opening balance record.
     */
    $stmt = $pdo->prepare("
        INSERT INTO opening_balances
        (
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

    /*
     * Opening balance is a ledger movement,
     * NOT a trade.
     */
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            transaction_id,
            entry_type,
            currency,
            amount
        )
        VALUES (NULL, 'OPENING_BALANCE', ?, ?)
    ");

    $ledgerStmt->execute([
        $currency,
        $amount
    ]);

    /*
     * Synchronize balance projection.
     */
    $update = $pdo->prepare("
        UPDATE account_balances
        SET balance = balance + ?
        WHERE currency = ?
    ");

    $update->execute([
        $amount,
        $currency
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'currency' => $currency,
        'amount' => $amount
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
