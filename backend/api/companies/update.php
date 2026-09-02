<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? null;
$name = trim($input['name'] ?? '');
$legalName = trim($input['legal_name'] ?? '');
$companyCode = strtoupper(trim($input['company_code'] ?? ''));
$status = strtoupper(trim($input['status'] ?? 'ACTIVE'));

if (!$id) {
    jsonResponse([
        'success' => false,
        'message' => 'Company ID is required.'
    ], 422);
}

if ($name === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Company name is required.'
    ], 422);
}

if ($companyCode === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Company code is required.'
    ], 422);
}

if (!in_array($status, ['ACTIVE', 'INACTIVE'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid company status.'
    ], 422);
}

try {

    $check = $pdo->prepare("
        SELECT id
        FROM companies
        WHERE company_code = :company_code
        AND id != :id
    ");

    $check->execute([
        ':company_code' => $companyCode,
        ':id' => $id
    ]);

    if ($check->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Company code already exists.'
        ], 409);
    }

    $stmt = $pdo->prepare("
        UPDATE companies
        SET
            name = :name,
            legal_name = :legal_name,
            company_code = :company_code,
            status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':name' => $name,
        ':legal_name' => $legalName ?: null,
        ':company_code' => $companyCode,
        ':status' => $status,
        ':id' => $id
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Company updated successfully.'
    ]);
} catch (PDOException $e) {

    jsonResponse([
        'success' => false,
        'message' => 'Failed to update company.'
    ], 500);
}
