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
    | Calculate Facility Interest
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Principal: 200,000
    | Interest Rate: 5%
    |
    | Interest: 10,000
    | Total Facility: 210,000
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


    $interestAmount = bcdiv(
        bcmul(
            $principalAmount,
            $interestRate,
            6
        ),
        '100',
        6
    );


    $totalFacilityAmount = bcadd(
        $principalAmount,
        $interestAmount,
        6
    );


    /*
    |--------------------------------------------------------------------------
    | Get Facility Ledger Chronologically
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We fetch ASC here because we need to calculate the historical
    | balance step-by-step.
    |
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
            fle.created_at ASC,
            fle.id ASC
    ");


    $ledgerStmt->execute([
        $facilityId
    ]);


    $ledgerEntries = $ledgerStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Calculate Historical Balance For Every Transaction
    |--------------------------------------------------------------------------
    |
    | This creates a permanent snapshot in the API response.
    |
    | Example:
    |
    | Facility Total = 210,000
    |
    | DISBURSEMENT 200,000
    | Outstanding After = 210,000
    |
    | REPAYMENT 100,000
    | Outstanding After = 110,000
    |
    | REPAYMENT 50,000
    | Outstanding After = 60,000
    |
    | Old receipts will therefore always know the correct balance
    | at that specific transaction.
    |
    */

    $runningOutstanding = '0';

    $facilityActivated = false;

    $totalDisbursed = '0';
    $totalRepaid = '0';
    $totalLedgerInterest = '0';
    $totalAdjustments = '0';


    foreach ($ledgerEntries as &$entry) {

        $entryType = strtoupper(
            (string)$entry['entry_type']
        );


        $entryAmount = bcadd(
            (string)$entry['amount'],
            '0',
            6
        );


        /*
        |--------------------------------------------------------------------------
        | DISBURSEMENT
        |--------------------------------------------------------------------------
        |
        | The facility obligation becomes:
        |
        | Principal + calculated interest
        |
        | We activate this only once.
        |
        */

        if (
            $entryType === 'DISBURSEMENT' &&
            !$facilityActivated
        ) {

            $runningOutstanding = $totalFacilityAmount;

            $facilityActivated = true;


            $totalDisbursed = bcadd(
                $totalDisbursed,
                $entryAmount,
                6
            );
        }


        /*
        |--------------------------------------------------------------------------
        | REPAYMENT / SETTLEMENT
        |--------------------------------------------------------------------------
        */ elseif (
            $entryType === 'REPAYMENT' ||
            $entryType === 'SETTLEMENT'
        ) {

            $runningOutstanding = bcsub(
                $runningOutstanding,
                $entryAmount,
                6
            );


            $totalRepaid = bcadd(
                $totalRepaid,
                $entryAmount,
                6
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Additional Interest
        |--------------------------------------------------------------------------
        |
        | Only ledger interest entries are added here.
        |
        | The original facility interest is ALREADY included in
        | totalFacilityAmount.
        |
        */ elseif ($entryType === 'INTEREST') {

            $runningOutstanding = bcadd(
                $runningOutstanding,
                $entryAmount,
                6
            );


            $totalLedgerInterest = bcadd(
                $totalLedgerInterest,
                $entryAmount,
                6
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Adjustment
        |--------------------------------------------------------------------------
        */ elseif ($entryType === 'ADJUSTMENT') {

            $runningOutstanding = bcadd(
                $runningOutstanding,
                $entryAmount,
                6
            );


            $totalAdjustments = bcadd(
                $totalAdjustments,
                $entryAmount,
                6
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Prevent Negative Balance
        |--------------------------------------------------------------------------
        */

        if (bccomp($runningOutstanding, '0', 6) < 0) {
            $runningOutstanding = '0.000000';
        }


        /*
        |--------------------------------------------------------------------------
        | Historical Snapshot
        |--------------------------------------------------------------------------
        */

        $entry['interest_amount'] =
            $interestAmount;

        $entry['total_facility_amount'] =
            $totalFacilityAmount;

        $entry['outstanding_after'] =
            $runningOutstanding;
    }


    unset($entry);


    /*
    |--------------------------------------------------------------------------
    | Current Outstanding
    |--------------------------------------------------------------------------
    |
    | Use calculated ledger balance when transactions exist.
    |
    */

    $calculatedOutstanding =
        $runningOutstanding;


    /*
    |--------------------------------------------------------------------------
    | Return Ledger In DESC Order For Frontend Display
    |--------------------------------------------------------------------------
    */

    $displayLedgerEntries =
        array_reverse($ledgerEntries);


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' => true,

        'facility' => array_merge(
            $facility,
            [
                'calculated_interest_amount' =>
                $interestAmount,

                'total_facility_amount' =>
                $totalFacilityAmount,

                'calculated_outstanding_amount' =>
                $calculatedOutstanding
            ]
        ),

        'ledger_entries' =>
        $displayLedgerEntries,

        'summary' => [

            'principal_amount' =>
            $principalAmount,

            'interest_rate' =>
            $interestRate,

            'interest_amount' =>
            $interestAmount,

            'total_facility_amount' =>
            $totalFacilityAmount,

            'outstanding_amount' =>
            $calculatedOutstanding,

            'total_disbursed' =>
            $totalDisbursed,

            'total_repaid' =>
            $totalRepaid,

            'total_interest' =>
            $totalLedgerInterest,

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
