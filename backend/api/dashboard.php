<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

try {

    /*
     * CURRENT BALANCES
     */

    $balanceStmt = $pdo->query("
        SELECT
            ab.currency_id,
            c.code,
            c.name,
            c.symbol,
            ab.balance

        FROM account_balances ab

        INNER JOIN currencies c
            ON c.id = ab.currency_id

        WHERE c.active = 1

        ORDER BY c.code ASC
    ");

    $balances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);


    /*
     * TODAY'S EXCHANGE COUNT
     */

    $todayStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_transactions

        FROM transactions

        WHERE status = 'COMPLETED'
          AND DATE(created_at) = CURDATE()
    ");

    $today = $todayStmt->fetch(PDO::FETCH_ASSOC);


    /*
     * THIS MONTH'S EXCHANGE COUNT
     */

    $monthStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_transactions

        FROM transactions

        WHERE status = 'COMPLETED'
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");

    $month = $monthStmt->fetch(PDO::FETCH_ASSOC);


    /*
     * RECENT TRANSACTIONS
     */

    $recentStmt = $pdo->query("
        SELECT
            t.id,
            t.request_id,
            t.type,

            fc.code AS from_currency_code,
            fc.name AS from_currency_name,
            fc.symbol AS from_currency_symbol,

            t.from_amount,

            tc.code AS to_currency_code,
            tc.name AS to_currency_name,
            tc.symbol AS to_currency_symbol,

            t.to_amount,
            t.exchange_rate,
            t.realized_profit,

            t.status,
            t.created_at,

            u.name AS user_name

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

        LIMIT 10
    ");

    $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);


    jsonResponse([
        'success' => true,

        'balances' => $balances,

        'today' => [
            'total_transactions' =>
            (int) $today['total_transactions']
        ],

        'month' => [
            'total_transactions' =>
            (int) $month['total_transactions']
        ],

        'recent' => $recent
    ]);
} catch (Throwable $e) {

    error_log(
        'dashboard.php: ' . $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => 'Unable to load dashboard.'
    ], 500);
}
