<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/response.php';


$user = requireAuth();


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse([
        'success' => false,
        'message' => 'Method not allowed.'
    ], 405);
}


try {

    /*
    |--------------------------------------------------------------------------
    | Companies
    |--------------------------------------------------------------------------
    */

    $companiesStmt = $pdo->query("
        SELECT
            id,
            name,
            legal_name,
            company_code
        FROM companies
        ORDER BY name ASC
    ");

    $companies = $companiesStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    */

    $currenciesStmt = $pdo->query("
        SELECT
            id,
            code,
            name,
            symbol
        FROM currencies
        ORDER BY code ASC
    ");

    $currencies = $currenciesStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Facility Types
    |--------------------------------------------------------------------------
    */

    $facilityTypes = [
        [
            'value' => 'LENDING',
            'label' => 'Lending'
        ],
        [
            'value' => 'BORROWING',
            'label' => 'Borrowing'
        ],
        [
            'value' => 'BRIDGING',
            'label' => 'Bridging'
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' => true,
        'companies' => $companies,
        'currencies' => $currencies,
        'facility_types' => $facilityTypes
    ]);
} catch (Throwable $e) {

    error_log(
        'financial facility meta: ' .
            $e->getMessage()
    );


    jsonResponse([
        'success' => false,
        'message' => 'Unable to load form data.'
    ], 500);
}
