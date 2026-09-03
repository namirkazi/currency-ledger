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
    | Update Facility
    |--------------------------------------------------------------------------
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
    | Create Historical Ledger Entry
    |--------------------------------------------------------------------------
    |
    | amount:
    | Actual principal transferred
    |
    | interest_amount:
    | Total agreed/calculated interest
    |
    | outstanding_after:
    | Principal + Interest owed after disbursement
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

        // Actual cash disbursed
        $facility['principal_amount'],

        // Historical interest snapshot
        $facility['interest_amount'],

        // Historical outstanding balance
        $facility['outstanding_amount'],

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

            'interest_amount' => $facility['interest_amount'],

            'outstanding_after' => $facility['outstanding_amount'],

            'currency_id' => (int) $facility['currency_id'],
        ],

        'facility' => [
            'id' => (int) $facilityId,
            'reference_number' => $facility['reference_number'],
            'status' => 'DISBURSED',

            'principal_amount' => $facility['principal_amount'],

            'interest_rate' => $facility['interest_rate'],

            'interest_amount' => $facility['interest_amount'],

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
