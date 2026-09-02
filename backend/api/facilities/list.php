<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireAuth();

try {

    $stmt = $pdo->query("
        SELECT
            f.id,
            f.reference_number,
            f.facility_type,

            f.principal_amount,
            f.outstanding_amount,

            f.request_date,
            f.disbursement_date,
            f.due_date,

            f.status,

            lender.name AS lender_company,
            borrower.name AS borrower_company,

            c.code AS currency_code,
            c.name AS currency_name,

            requester.username AS requested_by_name,

            approver.username AS approved_by_name,

            f.approved_at,
            f.created_at

        FROM financial_facilities f

        INNER JOIN companies lender
            ON lender.id = f.lender_company_id

        INNER JOIN companies borrower
            ON borrower.id = f.borrower_company_id

        INNER JOIN currencies c
            ON c.id = f.currency_id

        INNER JOIN users requester
            ON requester.id = f.requested_by

        LEFT JOIN users approver
            ON approver.id = f.approved_by

        ORDER BY f.created_at DESC
    ");

    $facilities = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => $facilities
    ]);
} catch (Exception $e) {

    jsonResponse([
        'success' => false,
        'message' => 'Failed to fetch facilities.'
    ], 500);
}
