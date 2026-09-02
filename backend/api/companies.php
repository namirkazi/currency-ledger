<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';

requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {

    /*
    |--------------------------------------------------------------------------
    | GET COMPANIES
    |--------------------------------------------------------------------------
    */

    if ($method === 'GET') {

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                legal_name,
                company_code,
                status,
                created_at,
                updated_at
            FROM companies
            WHERE status = 'ACTIVE'
            ORDER BY name ASC
        ");

        echo json_encode([
            'status' => 'ok',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE COMPANY
    |--------------------------------------------------------------------------
    */

    if ($method === 'POST') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        $name = trim($input['name'] ?? '');
        $legalName = trim($input['legal_name'] ?? '');
        $companyCode = strtoupper(trim($input['company_code'] ?? ''));

        if ($name === '') {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Company name is required'
            ]);

            exit;
        }


        // Generate code if not supplied
        if ($companyCode === '') {

            $companyCode = strtoupper(
                preg_replace(
                    '/[^A-Za-z0-9]/',
                    '',
                    substr($name, 0, 6)
                )
            );
        }


        // Check duplicate name
        $check = $pdo->prepare("
            SELECT id
            FROM companies
            WHERE name = ?
            LIMIT 1
        ");

        $check->execute([$name]);

        if ($check->fetch()) {

            http_response_code(409);

            echo json_encode([
                'status' => 'error',
                'message' => 'A company with this name already exists'
            ]);

            exit;
        }


        $stmt = $pdo->prepare("
            INSERT INTO companies (
                name,
                legal_name,
                company_code,
                status
            )
            VALUES (?, ?, ?, 'ACTIVE')
        ");

        $stmt->execute([
            $name,
            $legalName ?: null,
            $companyCode
        ]);


        echo json_encode([
            'status' => 'ok',
            'message' => 'Company created successfully',
            'data' => [
                'id' => $pdo->lastInsertId(),
                'name' => $name
            ]
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | METHOD NOT ALLOWED
    |--------------------------------------------------------------------------
    */

    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
}
