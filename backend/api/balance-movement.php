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

$currency = strtoupper(trim($data['currency'] ?? ''));
$type = strtoupper(trim($data['movement_type'] ?? ''));
$amount = trim((string) ($data['amount'] ?? ''));
$reason = trim($data['reason'] ?? '');

if (!in_array($currency, ['AED', 'USDT'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid currency.'
    ], 422);
}

if (!in_array($type, ['INFLOW', 'OUTFLOW'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid movement type.'
    ], 422);
}

if (
    $amount === '' ||
    !preg_match('/^\d+(\.\d{1,6})?$/', $amount) ||
    bccomp($amount, '0', 6) <= 0
) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid amount.'
    ], 422);
}

if (strlen($reason) > 255) {
    jsonResponse([
        'success' => false,
        'message' => 'Reason is too long.'
    ], 422);
}

try {
    $pdo->beginTransaction();

    /*
     * Lock the currency balance.
     */
    $balanceStmt = $pdo->prepare("
        SELECT balance
        FROM account_balances
        WHERE currency = ?
        FOR UPDATE
    ");

    $balanceStmt->execute([$currency]);

    $currentBalance = $balanceStmt->fetchColumn();

    if ($currentBalance === false) {
        throw new RuntimeException(
            "{$currency} balance record does not exist."
        );
    }

    /*
     * Prevent negative balances.
     */
    if (
        $type === 'OUTFLOW' &&
        bccomp($currentBalance, $amount, 6) < 0
    ) {
        throw new RuntimeException(
            "Insufficient {$currency} balance."
        );
    }

    /*
     * Create the movement record.
     */
    $movementStmt = $pdo->prepare("
        INSERT INTO balance_movements
        (
            currency,
            movement_type,
            amount,
            reason,
            created_by
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $movementStmt->execute([
        $currency,
        $type,
        $amount,
        $reason,
        $user['id']
    ]);

    $movementId = $pdo->lastInsertId();

    /*
     * Determine ledger amount.
     */
    $ledgerAmount = $type === 'INFLOW'
        ? $amount
        : bcmul($amount, '-1', 6);

    /*
     * Create ledger entry.
     *
     * If your ledger_entries table uses a different
     * entry_type value, use the value already defined
     * in your schema.
     */
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            transaction_id,
            entry_type,
            currency,
            amount
        )
        VALUES (NULL, 'BALANCE_MOVEMENT', ?, ?)
    ");

    $ledgerStmt->execute([
        $currency,
        $ledgerAmount
    ]);

    /*
     * Update synchronized balance projection.
     */
    if ($type === 'INFLOW') {

        $updateStmt = $pdo->prepare("
            UPDATE account_balances
            SET balance = balance + ?
            WHERE currency = ?
        ");
    } else {

        $updateStmt = $pdo->prepare("
            UPDATE account_balances
            SET balance = balance - ?
            WHERE currency = ?
        ");
    }

    $updateStmt->execute([
        $amount,
        $currency
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'movement_id' => $movementId,
        'currency' => $currency,
        'movement_type' => $type,
        'amount' => $amount,
        'reason' => $reason
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
