<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ledger.php';

requireAuth();

$aed = getBalance($pdo, 'AED');
$usdt = getBalance($pdo, 'USDT');

$todayBuy = $pdo->query("
    SELECT
        COALESCE(SUM(usdt_amount), 0) AS usdt,
        COALESCE(SUM(aed_amount), 0) AS aed
    FROM transactions
    WHERE type = 'BUY_USDT'
      AND DATE(created_at) = CURDATE()
")->fetch();

$todaySell = $pdo->query("
    SELECT
        COALESCE(SUM(usdt_amount), 0) AS usdt,
        COALESCE(SUM(aed_amount), 0) AS aed,
        COALESCE(SUM(realized_profit), 0) AS profit
    FROM transactions
    WHERE type = 'SELL_USDT'
      AND DATE(created_at) = CURDATE()
")->fetch();

$monthProfit = $pdo->query("
    SELECT COALESCE(SUM(realized_profit), 0)
    FROM transactions
    WHERE type = 'SELL_USDT'
      AND YEAR(created_at) = YEAR(CURDATE())
      AND MONTH(created_at) = MONTH(CURDATE())
")->fetchColumn();

$recent = $pdo->query("
    SELECT
        t.id,
        t.type,
        t.usdt_amount,
        t.rate,
        t.aed_amount,
        t.realized_profit,
        t.created_at,
        u.name AS user_name
    FROM transactions t
    JOIN users u ON u.id = t.created_by
    ORDER BY t.created_at DESC
    LIMIT 10
")->fetchAll();

jsonResponse([
    'success' => true,
    'balances' => [
        'AED' => $aed,
        'USDT' => $usdt
    ],
    'today' => [
        'buy_usdt' => $todayBuy['usdt'],
        'buy_aed' => $todayBuy['aed'],
        'sell_usdt' => $todaySell['usdt'],
        'sell_aed' => $todaySell['aed'],
        'profit' => $todaySell['profit']
    ],
    'month_profit' => $monthProfit,
    'recent' => $recent
]);
