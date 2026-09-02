<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$user = requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

try {

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid request data.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | GET INPUT
    |--------------------------------------------------------------------------
    */

    $facilityType = strtoupper(trim(
        $input['facility_type'] ?? ''
    ));

    $lenderCompanyId = (int)(
        $input['lender_company_id'] ?? 0
    );

    $borrowerCompanyId = (int)(
        $input['borrower_company_id'] ?? 0
    );

    $currencyId = (int)(
        $input['currency_id'] ?? 0
    );

    $principalAmount = (float)(
        $input['principal_amount'] ?? 0
    );

    $interestRate = (float)(
        $input['interest_rate'] ?? 0
    );

    $requestDate = $input['request_date']
        ?? date('Y-m-d');

    $disbursementDate =
        $input['disbursement_date'] ?? null;

    $dueDate =
        $input['due_date'] ?? null;

    $purpose = trim(
        $input['purpose'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedTypes = [
        'LENDING',
        'BORROWING',
        'BRIDGING'
    ];

    if (!in_array($facilityType, $allowedTypes, true)) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid facility type.'
        ], 400);
    }


    if (!$lenderCompanyId || !$borrowerCompanyId) {
        jsonResponse([
            'success' => false,
            'message' => 'Both companies are required.'
        ], 400);
    }


    if ($lenderCompanyId === $borrowerCompanyId) {
        jsonResponse([
            'success' => false,
            'message' => 'Lender and borrower cannot be the same company.'
        ], 400);
    }


    if (!$currencyId) {
        jsonResponse([
            'success' => false,
            'message' => 'Currency is required.'
        ], 400);
    }


    if ($principalAmount <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Principal amount must be greater than zero.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY COMPANIES EXIST
    |--------------------------------------------------------------------------
    */

    $companyCheck = $pdo->prepare("
        SELECT id
        FROM companies
        WHERE id IN (?, ?)
        AND status = 'ACTIVE'
    ");

    $companyCheck->execute([
        $lenderCompanyId,
        $borrowerCompanyId
    ]);

    if ($companyCheck->rowCount() !== 2) {
        jsonResponse([
            'success' => false,
            'message' => 'One or both selected companies are invalid.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY CURRENCY EXISTS
    |--------------------------------------------------------------------------
    */

    $currencyCheck = $pdo->prepare("
        SELECT id
        FROM currencies
        WHERE id = ?
        AND active = 1
        LIMIT 1
    ");

    $currencyCheck->execute([$currencyId]);

    if (!$currencyCheck->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid currency.'
        ], 400);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE REFERENCE NUMBER
    |--------------------------------------------------------------------------
    */

    $referenceNumber =
        'FAC-' .
        date('Ymd') .
        '-' .
        strtoupper(substr(uniqid(), -6));


    /*
    |--------------------------------------------------------------------------
    | CREATE FACILITY
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        INSERT INTO financial_facilities (
            reference_number,
            facility_type,
            lender_company_id,
            borrower_company_id,
            currency_id,
            principal_amount,
            outstanding_amount,
            interest_rate,
            request_date,
            disbursement_date,
            due_date,
            purpose,
            status,
            requested_by
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_APPROVAL', ?
        )
    ");


    $stmt->execute([
        $referenceNumber,
        $facilityType,
        $lenderCompanyId,
        $borrowerCompanyId,
        $currencyId,
        $principalAmount,
        $principalAmount,
        $interestRate,
        $requestDate,
        $disbursementDate,
        $dueDate,
        $purpose ?: null,
        $user['id']
    ]);


    $facilityId = (int)$pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | CREATE AUDIT / APPROVAL HISTORY
    |--------------------------------------------------------------------------
    */

    $approvalStmt = $pdo->prepare("
        INSERT INTO facility_approvals (
            facility_id,
            action,
            performed_by,
            remarks
        )
        VALUES (?, 'REQUESTED', ?, ?)
    ");


    $approvalStmt->execute([
        $facilityId,
        $user['id'],
        'Facility created and awaiting approval'
    ]);


    $pdo->commit();


    jsonResponse([
        'success' => true,
        'message' => 'Facility created successfully.',
        'data' => [
            'id' => $facilityId,
            'reference_number' => $referenceNumber,
            'status' => 'PENDING_APPROVAL'
        ]
    ]);
} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse([
        'success' => false,
        'message' => 'Unable to create facility.'
    ], 500);
}
