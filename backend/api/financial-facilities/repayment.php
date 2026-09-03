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
| Validate Facility ID
|--------------------------------------------------------------------------
*/

$facilityId = filter_var(
    $data['facility_id'] ?? null,
    FILTER_VALIDATE_INT
);


if (!$facilityId || $facilityId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid facility ID is required.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Validate Repayment Amount
|--------------------------------------------------------------------------
*/

$repaymentAmount = decimalValue(
    $data['amount'] ?? ''
);


if (bccomp($repaymentAmount, '0', 6) <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Repayment amount must be greater than zero.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| Optional Fields
|--------------------------------------------------------------------------
*/

$remarks = !empty($data['remarks'])
    ? trim($data['remarks'])
    : 'Facility repayment';


$referenceNumber = !empty($data['reference_number'])
    ? trim($data['reference_number'])
    : null;


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
            interest_rate,
            interest_amount,
            outstanding_amount
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

    $allowedStatuses = [
        'DISBURSED',
        'PARTIALLY_REPAID'
    ];


    if (!in_array(
        $facility['status'],
        $allowedStatuses,
        true
    )) {
        throw new RuntimeException(
            'Only disbursed or partially repaid facilities can receive repayments.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Overpayment
    |--------------------------------------------------------------------------
    */

    if (
        bccomp(
            $repaymentAmount,
            $facility['outstanding_amount'],
            6
        ) > 0
    ) {
        throw new RuntimeException(
            'Repayment amount cannot exceed outstanding amount.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate New Outstanding
    |--------------------------------------------------------------------------
    */

    $newOutstanding = bcsub(
        $facility['outstanding_amount'],
        $repaymentAmount,
        6
    );


    if (
        bccomp(
            $newOutstanding,
            '0',
            6
        ) === 0
    ) {
        $newOutstanding = '0.000000';
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Status
    |--------------------------------------------------------------------------
    */

    if (
        bccomp(
            $newOutstanding,
            '0',
            6
        ) === 0
    ) {

        $newStatus = 'SETTLED';
    } else {

        $newStatus = 'PARTIALLY_REPAID';
    }


    /*
    |--------------------------------------------------------------------------
    | Ledger Remarks
    |--------------------------------------------------------------------------
    */

    $ledgerRemarks = $remarks;


    if ($referenceNumber) {
        $ledgerRemarks .=
            ' | Reference: ' .
            $referenceNumber;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Facility FIRST
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE financial_facilities
        SET
            outstanding_amount = ?,
            status = ?
        WHERE id = ?
    ");


    $updateStmt->execute([
        $newOutstanding,
        $newStatus,
        $facilityId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Create Historical Ledger Entry
    |--------------------------------------------------------------------------
    |
    | interest_amount is preserved as a snapshot of
    | the facility's agreed interest.
    |
    | outstanding_after records the exact balance
    | immediately after this repayment.
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
            'REPAYMENT',
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

        $repaymentAmount,

        // Historical interest snapshot
        $facility['interest_amount'],

        // Balance after this repayment
        $newOutstanding,

        $facility['currency_id'],

        $ledgerRemarks,

        $user['id']
    ]);


    $ledgerId = $pdo->lastInsertId();


    $pdo->commit();


    jsonResponse([
        'success' => true,
        'message' => 'Repayment recorded successfully.',

        'repayment' => [
            'ledger_entry_id' => (int) $ledgerId,

            'facility_id' => (int) $facilityId,

            'entry_type' => 'REPAYMENT',

            'amount' => $repaymentAmount,

            'interest_amount' => $facility['interest_amount'],

            'remaining_outstanding' => $newOutstanding,

            'outstanding_after' => $newOutstanding,

            'status' => $newStatus
        ]
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'facility repayment: ' .
            $e->getMessage()
    );


    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
