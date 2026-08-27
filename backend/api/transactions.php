<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

try {

    $stmt = $pdo->query("
        SELECT
            t.id,
            t.request_id,
            t.type,

            t.currency_id,
            c.code AS currency_code,
            c.name AS currency_name,
            c.symbol AS currency_symbol,

            t.currency_amount,
            t.rate,
            t.usd_amount,
            t.realized_profit,

            t.status,
            t.created_by,
            u.name AS user_name,
            t.created_at

        FROM transactions t

        INNER JOIN currencies c
            ON c.id = t.currency_id

        INNER JOIN users u
            ON u.id = t.created_by

        ORDER BY
            t.created_at DESC,
            t.id DESC
    ");

    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'transactions' => $transactions
    ]);
} catch (Throwable $e) {

    error_log(
        'transactions.php: ' . $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => 'Unable to load transactions.'
    ], 500);
}
