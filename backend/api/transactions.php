<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

$stmt = $pdo->query("
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
");

jsonResponse([
    'success' => true,
    'transactions' => $stmt->fetchAll()
]);
