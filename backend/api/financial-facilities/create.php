<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';


$user = requireAdmin();


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}


$data = json_decode(
    file_get_contents('php://input'),
    true
);


if (!is_array($data)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid JSON request.'
    ], 400);
}


/*
|--------------------------------------------------------------------------
| Validate IDs
|--------------------------------------------------------------------------
*/

$lenderCompanyId = filter_var(
    $data['lender_company_id'] ?? null,
    FILTER_VALIDATE_INT
);

$borrowerCompanyId = filter_var(
    $data['borrower_company_id'] ?? null,
    FILTER_VALIDATE_INT
);

$currencyId = filter_var(
    $data['currency_id'] ?? null,
    FILTER_VALIDATE_INT
);


if (!$lenderCompanyId || $lenderCompanyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid lender company is required.'
    ], 422);
}


if (!$borrowerCompanyId || $borrowerCompanyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid borrower company is required.'
    ], 422);
}


if ($lenderCompanyId === $borrowerCompanyId) {
    jsonResponse([
        'success' => false,
        'message' => 'Lender and borrower cannot be the same company.'
    ], 422);
}


if (!$currencyId || $currencyId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid currency is required.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Validate Facility Type
|--------------------------------------------------------------------------
*/

$facilityType = strtoupper(
    trim($data['facility_type'] ?? '')
);


$allowedTypes = [
    'LENDING',
    'BORROWING',
    'BRIDGING'
];


if (!in_array($facilityType, $allowedTypes, true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Facility type must be LENDING, BORROWING, or BRIDGING.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Validate Principal Amount
|--------------------------------------------------------------------------
*/

$principalAmount = decimalValue(
    $data['principal_amount'] ?? ''
);


if (bccomp($principalAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Principal amount must be greater than zero.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Interest Rate
|--------------------------------------------------------------------------
*/

$interestRate = nonNegativeDecimalValue(
    $data['interest_rate'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Calculate Interest
|--------------------------------------------------------------------------
|
| Example:
|
| Principal: 10,000
| Interest Rate: 3%
|
| Interest:
| 10,000 × 3 / 100 = 300
|
| Total Outstanding:
| 10,000 + 300 = 10,300
|
*/

$interestAmount = bcdiv(
    bcmul(
        $principalAmount,
        $interestRate,
        6
    ),
    '100',
    6
);


$totalOutstandingAmount = bcadd(
    $principalAmount,
    $interestAmount,
    6
);


/*
|--------------------------------------------------------------------------
| Optional Fields
|--------------------------------------------------------------------------
*/

$requestDate = trim(
    $data['request_date'] ?? ''
);


$disbursementDate = !empty($data['disbursement_date'])
    ? $data['disbursement_date']
    : null;


$dueDate = !empty($data['due_date'])
    ? $data['due_date']
    : null;


$purpose = !empty($data['purpose'])
    ? trim($data['purpose'])
    : null;


/*
|--------------------------------------------------------------------------
| Validate Request Date
|--------------------------------------------------------------------------
*/

if (!$requestDate) {
    jsonResponse([
        'success' => false,
        'message' => 'Request date is required.'
    ], 422);
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Validate Lender Company
    |--------------------------------------------------------------------------
    */

    $companyStmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM companies
        WHERE id = ?
          AND status = 'ACTIVE'
        LIMIT 1
        FOR UPDATE
    ");


    $companyStmt->execute([
        $lenderCompanyId
    ]);


    $lenderCompany = $companyStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$lenderCompany) {
        throw new RuntimeException(
            'Lender company does not exist or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Borrower Company
    |--------------------------------------------------------------------------
    */

    $companyStmt->execute([
        $borrowerCompanyId
    ]);


    $borrowerCompany = $companyStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$borrowerCompany) {
        throw new RuntimeException(
            'Borrower company does not exist or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Currency
    |--------------------------------------------------------------------------
    */

    $currencyStmt = $pdo->prepare("
        SELECT
            id,
            code,
            name,
            symbol
        FROM currencies
        WHERE id = ?
          AND active = 1
        LIMIT 1
        FOR UPDATE
    ");


    $currencyStmt->execute([
        $currencyId
    ]);


    $currency = $currencyStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$currency) {
        throw new RuntimeException(
            'Currency does not exist or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Reference Number
    |--------------------------------------------------------------------------
    */

    $referenceNumber =
        'FAC-' .
        date('Ymd') .
        '-' .
        strtoupper(
            substr(
                bin2hex(random_bytes(4)),
                0,
                8
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Create Financial Facility
    |--------------------------------------------------------------------------
    */

    $facilityStmt = $pdo->prepare("
        INSERT INTO financial_facilities
        (
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
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'PENDING_APPROVAL',
            ?
        )
    ");


    $facilityStmt->execute([

        $referenceNumber,

        $facilityType,

        $lenderCompanyId,

        $borrowerCompanyId,

        $currencyId,

        /*
        | Principal amount
        */
        $principalAmount,

        /*
        | Principal + Interest
        */
        $totalOutstandingAmount,

        /*
        | Interest percentage
        */
        $interestRate,

        $requestDate,

        $disbursementDate,

        $dueDate,

        $purpose,

        $user['id']
    ]);


    $facilityId = $pdo->lastInsertId();


    $pdo->commit();


    jsonResponse([
        'success' => true,
        'message' => 'Financial facility created successfully.',

        'facility' => [
            'id' => (int) $facilityId,
            'reference_number' => $referenceNumber,
            'facility_type' => $facilityType,
            'lender_company' => $lenderCompany['name'],
            'borrower_company' => $borrowerCompany['name'],
            'currency' => $currency['code'],

            'principal_amount' => $principalAmount,

            'interest_rate' => $interestRate,

            'calculated_interest' => $interestAmount,

            'outstanding_amount' => $totalOutstandingAmount,

            'status' => 'PENDING_APPROVAL',
        ]
    ], 201);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
