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

$facilityType = strtoupper(trim($input['facility_type'] ?? ''));

$lenderCompanyId = (int) ($input['lender_company_id'] ?? 0);
$borrowerCompanyId = (int) ($input['borrower_company_id'] ?? 0);

$currencyId = (int) ($input['currency_id'] ?? 0);

$principalAmount = (float) ($input['principal_amount'] ?? 0);

$requestDate = $input['request_date'] ?? date('Y-m-d');
$dueDate = !empty($input['due_date']) ? $input['due_date'] : null;

$purpose = trim($input['purpose'] ?? '');
$notes = trim($input['notes'] ?? '');


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

$allowedTypes = ['LOAN', 'BRIDGE', 'ADVANCE'];

if (!in_array($facilityType, $allowedTypes, true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid facility type.'
    ], 422);
}

if (!$lenderCompanyId || !$borrowerCompanyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Lender and borrower companies are required.'
    ], 422);
}

if ($lenderCompanyId === $borrowerCompanyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Lender and borrower cannot be the same company.'
    ], 422);
}

if (!$currencyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Currency is required.'
    ], 422);
}

if ($principalAmount <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Amount must be greater than zero.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Create Facility
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Generate Reference Number
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT id
        FROM financial_facilities
        ORDER BY id DESC
        LIMIT 1
    ");

    $lastFacility = $stmt->fetch();

    $nextNumber = $lastFacility
        ? ((int)$lastFacility['id'] + 1)
        : 1;

    $referenceNumber = 'FAC-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);


    /*
    |--------------------------------------------------------------------------
    | Insert Facility
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO financial_facilities (
            reference_number,
            facility_type,
            lender_company_id,
            borrower_company_id,
            currency_id,
            principal_amount,
            outstanding_amount,
            request_date,
            due_date,
            purpose,
            status,
            requested_by,
            notes
        )
        VALUES (
            :reference_number,
            :facility_type,
            :lender_company_id,
            :borrower_company_id,
            :currency_id,
            :principal_amount,
            :outstanding_amount,
            :request_date,
            :due_date,
            :purpose,
            'PENDING_APPROVAL',
            :requested_by,
            :notes
        )
    ");

    $stmt->execute([
        ':reference_number' => $referenceNumber,
        ':facility_type' => $facilityType,
        ':lender_company_id' => $lenderCompanyId,
        ':borrower_company_id' => $borrowerCompanyId,
        ':currency_id' => $currencyId,
        ':principal_amount' => $principalAmount,
        ':outstanding_amount' => $principalAmount,
        ':request_date' => $requestDate,
        ':due_date' => $dueDate,
        ':purpose' => $purpose ?: null,
        ':requested_by' => $user['id'],
        ':notes' => $notes ?: null
    ]);

    $facilityId = $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Approval History
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO facility_approvals (
            facility_id,
            action,
            performed_by,
            remarks
        )
        VALUES (
            :facility_id,
            'REQUESTED',
            :performed_by,
            :remarks
        )
    ");

    $stmt->execute([
        ':facility_id' => $facilityId,
        ':performed_by' => $user['id'],
        ':remarks' => 'Facility submitted for approval.'
    ]);


    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Facility created and submitted for approval.',
        'data' => [
            'id' => (int)$facilityId,
            'reference_number' => $referenceNumber,
            'status' => 'PENDING_APPROVAL'
        ]
    ], 201);
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'success' => false,
        'message' => 'Failed to create facility.'
    ], 500);
}
