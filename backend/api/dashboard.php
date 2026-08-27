<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

try {

    // ---------------------------------------------------------
    // CURRENT BALANCES
    // ---------------------------------------------------------

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


    // ---------------------------------------------------------
    // TODAY'S BUY TOTALS
    // ---------------------------------------------------------

    $buyStmt = $pdo->query("
        SELECT
            COALESCE(SUM(currency_amount), 0) AS currency_amount,
            COALESCE(SUM(usd_amount), 0) AS usd_amount
        FROM transactions
        WHERE type = 'BUY'
          AND status = 'COMPLETED'
          AND DATE(created_at) = CURDATE()
    ");

    $todayBuy = $buyStmt->fetch(PDO::FETCH_ASSOC);


    // ---------------------------------------------------------
    // TODAY'S SELL TOTALS
    // ---------------------------------------------------------

    $sellStmt = $pdo->query("
        SELECT
            COALESCE(SUM(currency_amount), 0) AS currency_amount,
            COALESCE(SUM(usd_amount), 0) AS usd_amount,
            COALESCE(SUM(realized_profit), 0) AS realized_profit
        FROM transactions
        WHERE type = 'SELL'
          AND status = 'COMPLETED'
          AND DATE(created_at) = CURDATE()
    ");

    $todaySell = $sellStmt->fetch(PDO::FETCH_ASSOC);


    // ---------------------------------------------------------
    // MONTHLY PROFIT
    // PROFIT IS ALWAYS USD
    // ---------------------------------------------------------

    $monthStmt = $pdo->query("
        SELECT
            COALESCE(SUM(realized_profit), 0)
        FROM transactions
        WHERE type = 'SELL'
          AND status = 'COMPLETED'
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");

    $monthProfit = $monthStmt->fetchColumn();


    // ---------------------------------------------------------
    // RECENT TRANSACTIONS
    // ---------------------------------------------------------

    $recentStmt = $pdo->query("
        SELECT
            t.id,
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
            t.created_at,

            u.name AS user_name

        FROM transactions t

        INNER JOIN currencies c
            ON c.id = t.currency_id

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
            'buy_currency_amount' => $todayBuy['currency_amount'],
            'buy_usd' => $todayBuy['usd_amount'],

            'sell_currency_amount' => $todaySell['currency_amount'],
            'sell_usd' => $todaySell['usd_amount'],

            'profit_usd' => $todaySell['realized_profit']
        ],

        'month_profit_usd' => $monthProfit,

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
