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


if ($remarks === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Cancellation reason is required.'
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
    | Only approved facilities can be cancelled
    |--------------------------------------------------------------------------
    |
    | Pending facilities should be rejected.
    | Active facilities cannot be cancelled because
    | money has already moved.
    |
    */

    if ($facility['status'] !== 'APPROVED') {
        throw new RuntimeException(
            'Only approved facilities that have not been disbursed can be cancelled.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cancel Facility
    |--------------------------------------------------------------------------
    */

    $cancelStmt = $pdo->prepare("
        UPDATE financial_facilities
        SET
            status = 'CANCELLED'
        WHERE id = ?
    ");


    $cancelStmt->execute([
        $facilityId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Record History
    |--------------------------------------------------------------------------
    */

    $historyStmt = $pdo->prepare("
        INSERT INTO facility_approvals (
            facility_id,
            action,
            performed_by,
            remarks
        )
        VALUES (
            ?,
            'CANCELLED',
            ?,
            ?
        )
    ");


    $historyStmt->execute([
        $facilityId,
        $user['id'],
        $remarks
    ]);


    $pdo->commit();


    jsonResponse([
        'success' => true,
        'message' => 'Financial facility cancelled successfully.',
        'facility' => [
            'id' => (int) $facilityId,
            'reference_number' => $facility['reference_number'],
            'status' => 'CANCELLED'
        ]
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'facility cancellation: ' .
            $e->getMessage()
    );


    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
