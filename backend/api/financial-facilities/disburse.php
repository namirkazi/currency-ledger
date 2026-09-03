<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/response.php';


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


$facilityId = filter_var(
    $data['facility_id'] ?? null,
    FILTER_VALIDATE_INT
);


$remarks = trim(
    $data['remarks'] ?? ''
);


if (!$facilityId || $facilityId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid facility ID is required.'
    ], 422);
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Facility
    |--------------------------------------------------------------------------
    */

    $facilityStmt = $pdo->prepare("
        SELECT
            id,
            reference_number,
            status,
            currency_id,
            principal_amount,
            outstanding_amount,
            interest_rate,
            interest_amount
        FROM financial_facilities
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");


    $facilityStmt->execute([
        $facilityId
    ]);


    $facility = $facilityStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$facility) {
        throw new RuntimeException(
            'Financial facility not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if ($facility['status'] !== 'APPROVED') {
        throw new RuntimeException(
            'Only approved facilities can be disbursed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Financial Calculation
    |--------------------------------------------------------------------------
    |
    | Principal: 200,000
    | Interest: 5%
    |
    | Interest Amount:
    | 200,000 × 5 / 100 = 10,000
    |
    | Total Outstanding:
    | 200,000 + 10,000 = 210,000
    |
    */

    $principalAmount = bcadd(
        (string)$facility['principal_amount'],
        '0',
        6
    );


    $interestRate = bcadd(
        (string)$facility['interest_rate'],
        '0',
        6
    );


    /*
    |--------------------------------------------------------------------------
    | Calculate Interest
    |--------------------------------------------------------------------------
    |
    | If interest_amount was already calculated and stored,
    | preserve that value.
    |
    | Otherwise calculate it from the interest rate.
    */

    if (
        isset($facility['interest_amount']) &&
        bccomp(
            (string)$facility['interest_amount'],
            '0',
            6
        ) > 0
    ) {

        $interestAmount = bcadd(
            (string)$facility['interest_amount'],
            '0',
            6
        );
    } else {

        $interestAmount = bcdiv(
            bcmul(
                $principalAmount,
                $interestRate,
                6
            ),
            '100',
            6
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Total Facility Obligation
    |--------------------------------------------------------------------------
    */

    $totalFacilityAmount = bcadd(
        $principalAmount,
        $interestAmount,
        6
    );


    /*
    |--------------------------------------------------------------------------
    | Update Facility
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | outstanding_amount becomes the TOTAL amount owed,
    | including interest.
    |
    */

    $updateStmt = $pdo->prepare("
        UPDATE financial_facilities
        SET
            status = 'DISBURSED',

            disbursement_date = COALESCE(
                disbursement_date,
                CURDATE()
            ),

            interest_amount = ?,

            outstanding_amount = ?

        WHERE id = ?
    ");


    $updateStmt->execute([
        $interestAmount,
        $totalFacilityAmount,
        $facilityId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Create Historical Ledger Entry
    |--------------------------------------------------------------------------
    |
    | This stores the exact financial state at disbursement.
    |
    | Example:
    |
    | amount             = 200,000
    | interest_amount    = 10,000
    | outstanding_after  = 210,000
    |
    */

    $ledgerStmt = $pdo->prepare("
        INSERT INTO facility_ledger_entries (
            facility_id,
            entry_type,
            amount,
            interest_amount,
            outstanding_after,
            currency_id,
            remarks,
            performed_by
        )
        VALUES (
            ?,
            'DISBURSEMENT',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $ledgerStmt->execute([
        $facilityId,

        $principalAmount,

        $interestAmount,

        $totalFacilityAmount,

        $facility['currency_id'],

        $remarks ?: 'Facility disbursed',

        $user['id']
    ]);


    $ledgerId = $pdo->lastInsertId();


    $pdo->commit();


    jsonResponse([
        'success' => true,
        'message' => 'Facility disbursed successfully.',

        'transaction' => [
            'ledger_entry_id' => (int)$ledgerId,

            'facility_id' => (int)$facilityId,

            'entry_type' => 'DISBURSEMENT',

            'amount' => $principalAmount,

            'interest_amount' => $interestAmount,

            'outstanding_after' => $totalFacilityAmount,

            'currency_id' => (int)$facility['currency_id']
        ],

        'facility' => [
            'id' => (int)$facilityId,

            'reference_number' =>
            $facility['reference_number'],

            'status' => 'DISBURSED',

            'principal_amount' =>
            $principalAmount,

            'interest_rate' =>
            $interestRate,

            'interest_amount' =>
            $interestAmount,

            'outstanding_amount' =>
            $totalFacilityAmount
        ]
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'facility disbursement: ' .
            $e->getMessage()
    );


    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
