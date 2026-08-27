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

$type = strtoupper(
    trim($data['movement_type'] ?? '')
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

if (!in_array($type, ['INFLOW', 'OUTFLOW'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid movement type.'
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

    // Lock the balance row.
    $balanceStmt = $pdo->prepare("
        SELECT balance
        FROM account_balances
        WHERE currency_id = ?
        FOR UPDATE
    ");

    $balanceStmt->execute([
        $currencyId
    ]);

    $balance = $balanceStmt->fetchColumn();

    if ($balance === false) {
        throw new RuntimeException(
            'Balance record does not exist for this currency.'
        );
    }

    // Currency.
    $currencyStmt = $pdo->prepare("
        SELECT id, code, name, symbol
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

    // Prevent negative balances.
    if (
        $type === 'OUTFLOW' &&
        bccomp($balance, $amount, 6) < 0
    ) {
        throw new RuntimeException(
            "Insufficient {$currency['code']} balance."
        );
    }

    // Create movement.
    $movementStmt = $pdo->prepare("
        INSERT INTO balance_movements
        (
            currency_id,
            movement_type,
            amount,
            created_by
        )
        VALUES (?, ?, ?, ?)
    ");

    $movementStmt->execute([
        $currencyId,
        $type,
        $amount,
        $user['id']
    ]);

    $movementId = $pdo->lastInsertId();

    // Ledger amount.
    $ledgerAmount =
        $type === 'INFLOW'
        ? $amount
        : bcmul($amount, '-1', 6);

    // Ledger.
    $ledgerStmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (
            currency_id,
            balance_movement_id,
            entry_type,
            amount
        )
        VALUES (?, ?, 'ADJUSTMENT', ?)
    ");

    $ledgerStmt->execute([
        $currencyId,
        $movementId,
        $ledgerAmount
    ]);

    // Update balance.
    if ($type === 'INFLOW') {

        $updateStmt = $pdo->prepare("
            UPDATE account_balances
            SET balance = balance + ?
            WHERE currency_id = ?
        ");
    } else {

        $updateStmt = $pdo->prepare("
            UPDATE account_balances
            SET balance = balance - ?
            WHERE currency_id = ?
        ");
    }

    $updateStmt->execute([
        $amount,
        $currencyId
    ]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'movement_id' => $movementId,
        'currency_id' => (int) $currencyId,
        'currency_code' => $currency['code'],
        'movement_type' => $type,
        'amount' => $amount
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'balance-movement.php: ' . $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
