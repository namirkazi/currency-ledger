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
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);


if (!$facilityId || $facilityId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Valid facility ID is required.'
    ], 422);
}


try {

    /*
    |--------------------------------------------------------------------------
    | Get Facility
    |--------------------------------------------------------------------------
    */

    $facilityStmt = $pdo->prepare("
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
            lender.legal_name AS lender_legal_name,
            lender.company_code AS lender_company_code,

            borrower.id AS borrower_company_id,
            borrower.name AS borrower_company_name,
            borrower.legal_name AS borrower_legal_name,
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

        WHERE ff.id = ?

        LIMIT 1
    ");


    $facilityStmt->execute([
        $facilityId
    ]);


    $facility = $facilityStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$facility) {
        jsonResponse([
            'success' => false,
            'message' => 'Financial facility not found.'
        ], 404);
    }


    /*
    |--------------------------------------------------------------------------
    | Get Facility Ledger
    |--------------------------------------------------------------------------
    */

    $ledgerStmt = $pdo->prepare("
        SELECT
            fle.id,
            fle.facility_id,
            fle.entry_type,
            fle.amount,
            fle.remarks,
            fle.created_at,

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


    $ledgerStmt->execute([
        $facilityId
    ]);


    $ledgerEntries = $ledgerStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Calculate Summary
    |--------------------------------------------------------------------------
    */

    $totalDisbursed = '0';
    $totalRepaid = '0';
    $totalInterest = '0';
    $totalAdjustments = '0';


    foreach ($ledgerEntries as $entry) {

        switch ($entry['entry_type']) {

            case 'DISBURSEMENT':

                $totalDisbursed = bcadd(
                    $totalDisbursed,
                    $entry['amount'],
                    6
                );

                break;


            case 'REPAYMENT':

                $totalRepaid = bcadd(
                    $totalRepaid,
                    $entry['amount'],
                    6
                );

                break;


            case 'INTEREST':

                $totalInterest = bcadd(
                    $totalInterest,
                    $entry['amount'],
                    6
                );

                break;


            case 'ADJUSTMENT':

                $totalAdjustments = bcadd(
                    $totalAdjustments,
                    $entry['amount'],
                    6
                );

                break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' => true,

        'facility' => $facility,

        'ledger_entries' => $ledgerEntries,

        'summary' => [

            'principal_amount' =>
            $facility['principal_amount'],

            'outstanding_amount' =>
            $facility['outstanding_amount'],

            'total_disbursed' =>
            $totalDisbursed,

            'total_repaid' =>
            $totalRepaid,

            'total_interest' =>
            $totalInterest,

            'total_adjustments' =>
            $totalAdjustments,

            'ledger_entry_count' =>
            count($ledgerEntries)
        ]
    ]);
} catch (Throwable $e) {

    error_log(
        'financial facility details: ' .
            $e->getMessage()
    );

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
