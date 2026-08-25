<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAuth();

$stmt = $pdo->query("
    SELECT
        bm.id,
        bm.currency,
        bm.movement_type,
        bm.amount,
        bm.reason,
        bm.created_at,
        u.name AS user_name
    FROM balance_movements bm
    INNER JOIN users u
        ON u.id = bm.created_by
    ORDER BY bm.created_at DESC, bm.id DESC
");

jsonResponse([
    'success' => true,
    'movements' => $stmt->fetchAll()
]);
