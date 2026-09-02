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
        'message' => 'A rejection reason is required.'
    ], 422);
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock facility
    |--------------------------------------------------------------------------
    */

    $facilityStmt = $pdo->prepare("
        SELECT
            id,
            reference_number,
            status
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
    | Validate status
    |--------------------------------------------------------------------------
    */

    if ($facility['status'] !== 'PENDING_APPROVAL') {
        throw new RuntimeException(
            'Only pending facilities can be rejected.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update facility
    |--------------------------------------------------------------------------
    */

    $rejectStmt = $pdo->prepare("
        UPDATE financial_facilities
        SET
            status = 'REJECTED',
            rejected_by = ?,
            rejected_at = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");

    $rejectStmt->execute([
        $user['id'],
        $remarks,
        $facilityId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Record permanent history
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
            'REJECTED',
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
        'message' => 'Financial facility rejected.',
        'facility' => [
            'id' => $facilityId,
            'reference_number' => $facility['reference_number'],
            'status' => 'REJECTED'
        ]
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
