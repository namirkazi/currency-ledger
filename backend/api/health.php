<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query('SELECT 1');

    echo json_encode([
        'success' => true,
        'message' => 'Currency Ledger API is connected.',
        'database' => 'connected'
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);
}
