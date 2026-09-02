<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireAuth();

try {
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            legal_name,
            company_code,
            status,
            created_at
        FROM companies
        ORDER BY name ASC
    ");

    jsonResponse([
        'success' => true,
        'data' => $stmt->fetchAll()
    ]);
} catch (PDOException $e) {

    jsonResponse([
        'success' => false,
        'message' => 'Failed to fetch companies.'
    ], 500);
}
