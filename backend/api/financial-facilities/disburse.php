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


/*
|--------------------------------------------------------------------------
| Validate Facility ID
|--------------------------------------------------------------------------
*/

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
            interest_rate
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
    | Update Facility
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We DO NOT touch outstanding_amount here.
    |
    | outstanding_amount was already calculated during creation:
    |
    | Principal + Interest
    |
    */

    $updateStmt = $pdo->prepare("
        UPDATE financial_facilities
        SET
            status = 'DISBURSED',
            disbursement_date = COALESCE(
                disbursement_date,
                CURDATE()
            )
        WHERE id = ?
    ");


    $updateStmt->execute([
        $facilityId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Create Facility Ledger Entry
    |--------------------------------------------------------------------------
    |
    | Only the PRINCIPAL is actually disbursed.
    |
    | Example:
    |
    | Principal: 10,000
    | Interest: 3%
    | Total Outstanding: 10,300
    |
    | Actual money transferred = 10,000
    |
    */

    $ledgerStmt = $pdo->prepare("
        INSERT INTO facility_ledger_entries (
            facility_id,
            entry_type,
            amount,
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
            ?
        )
    ");


    $ledgerStmt->execute([
        $facilityId,
        $facility['principal_amount'],
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
            'ledger_entry_id' => (int) $ledgerId,
            'facility_id' => (int) $facilityId,
            'entry_type' => 'DISBURSEMENT',
            'amount' => $facility['principal_amount'],
            'currency_id' => (int) $facility['currency_id'],
        ],

        'facility' => [
            'id' => (int) $facilityId,
            'reference_number' => $facility['reference_number'],
            'status' => 'DISBURSED',

            'principal_amount' => $facility['principal_amount'],

            'interest_rate' => $facility['interest_rate'],

            /*
            | This remains Principal + Interest
            */
            'outstanding_amount' => $facility['outstanding_amount']
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
