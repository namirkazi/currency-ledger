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

            t.from_currency_id,
            fc.code AS from_currency_code,
            fc.name AS from_currency_name,
            fc.symbol AS from_currency_symbol,

            t.from_amount,

            t.to_currency_id,
            tc.code AS to_currency_code,
            tc.name AS to_currency_name,
            tc.symbol AS to_currency_symbol,

            t.to_amount,
            t.exchange_rate,
            t.realized_profit,

            t.status,

            t.created_by,
            u.name AS user_name,

            t.created_at

        FROM transactions t

        INNER JOIN currencies fc
            ON fc.id = t.from_currency_id

        INNER JOIN currencies tc
            ON tc.id = t.to_currency_id

        INNER JOIN users u
            ON u.id = t.created_by

        ORDER BY
            t.created_at DESC,
            t.id DESC
    ");

    $transactions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

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
