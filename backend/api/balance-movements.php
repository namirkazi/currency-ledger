<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

try {

    $stmt = $pdo->query("
        SELECT
            bm.id,

            bm.currency_id,
            c.code AS currency_code,
            c.name AS currency_name,
            c.symbol AS currency_symbol,

            bm.movement_type,
            bm.amount,

            bm.created_by,
            u.name AS user_name,

            bm.created_at

        FROM balance_movements bm

        INNER JOIN currencies c
            ON c.id = bm.currency_id

        INNER JOIN users u
            ON u.id = bm.created_by

        ORDER BY
            bm.created_at DESC,
            bm.id DESC
    ");

    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'movements' => $movements
    ]);
} catch (Throwable $e) {

    error_log(
        'balance-movements.php: ' . $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => 'Unable to load balance movements.'
    ], 500);
}
