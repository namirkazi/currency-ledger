<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/response.php';


requireAuth();


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}


$facilityId = filter_var(
    $_GET['facility_id'] ?? null,
    FILTER_VALIDATE_INT
);


if (!$facilityId || $facilityId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid facility ID is required.'
    ], 422);
}


try {

    $stmt = $pdo->prepare("
        SELECT
            fle.id,
            fle.facility_id,
            fle.entry_type,
            fle.amount,
            fle.interest_amount,
            fle.outstanding_after,
            fle.remarks,
            fle.created_at,

            c.id AS currency_id,
            c.code AS currency_code,
            c.symbol AS currency_symbol,

            u.id AS performed_by_id,
            u.name AS performed_by_name

        FROM facility_ledger_entries fle

        INNER JOIN currencies c
            ON c.id = fle.currency_id

        LEFT JOIN users u
            ON u.id = fle.performed_by

        WHERE fle.facility_id = ?

        ORDER BY
            fle.created_at DESC,
            fle.id DESC
    ");


    $stmt->execute([
        $facilityId
    ]);


    $entries = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    jsonResponse([
        'success' => true,
        'entries' => $entries
    ]);
} catch (Throwable $e) {

    error_log(
        'facility ledger: ' .
            $e->getMessage()
    );


    jsonResponse([
        'success' => false,
        'message' => 'Unable to load facility ledger.'
    ], 500);
}
