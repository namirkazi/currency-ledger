<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$legalName = trim($input['legal_name'] ?? '');
$companyCode = trim($input['company_code'] ?? '');

if ($name === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Company name is required.'
    ], 422);
}

try {

    $stmt = $pdo->prepare("
        INSERT INTO companies (
            name,
            legal_name,
            company_code,
            status
        )
        VALUES (
            :name,
            :legal_name,
            :company_code,
            'ACTIVE'
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':legal_name' => $legalName ?: null,
        ':company_code' => $companyCode ?: null
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Company created successfully.',
        'company_id' => $pdo->lastInsertId()
    ], 201);
} catch (PDOException $e) {

    jsonResponse([
        'success' => false,
        'message' => 'Failed to create company.'
    ], 500);
}
