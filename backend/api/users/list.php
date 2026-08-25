<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT
        id,
        name,
        username,
        role,
        is_active,
        created_at
    FROM users
    ORDER BY created_at DESC
");

jsonResponse([
    'success' => true,
    'users' => $stmt->fetchAll()
]);
