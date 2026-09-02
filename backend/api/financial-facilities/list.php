<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/response.php';


$user = requireAuth();


try {

    $stmt = $pdo->prepare("
        SELECT
            ff.id,
            ff.reference_number,
            ff.facility_type,

            ff.principal_amount,
            ff.outstanding_amount,
            ff.interest_rate,

            ff.request_date,
            ff.disbursement_date,
            ff.due_date,

            ff.purpose,
            ff.status,

            ff.created_at,
            ff.approved_at,

            lender.id AS lender_company_id,
            lender.name AS lender_company_name,
            lender.company_code AS lender_company_code,

            borrower.id AS borrower_company_id,
            borrower.name AS borrower_company_name,
            borrower.company_code AS borrower_company_code,

            c.id AS currency_id,
            c.code AS currency_code,
            c.name AS currency_name,
            c.symbol AS currency_symbol,

            requester.id AS requested_by_id,
            requester.name AS requested_by_name,

            approver.id AS approved_by_id,
            approver.name AS approved_by_name

        FROM financial_facilities ff

        INNER JOIN companies lender
            ON lender.id = ff.lender_company_id

        INNER JOIN companies borrower
            ON borrower.id = ff.borrower_company_id

        INNER JOIN currencies c
            ON c.id = ff.currency_id

        LEFT JOIN users requester
            ON requester.id = ff.requested_by

        LEFT JOIN users approver
            ON approver.id = ff.approved_by

        ORDER BY
            ff.created_at DESC,
            ff.id DESC
    ");


    $stmt->execute();


    $facilities = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    jsonResponse([
        'success' => true,
        'facilities' => $facilities
    ]);
} catch (Throwable $e) {

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
