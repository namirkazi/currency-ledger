<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

$stmt = $pdo->query("
    SELECT
        id,
        code,
        name,
        symbol,
        active,
        created_at
    FROM currencies
    ORDER BY
        active DESC,
        code ASC
");

jsonResponse([
    'success' => true,
    'currencies' => $stmt->fetchAll()
]);
