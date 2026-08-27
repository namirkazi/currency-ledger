<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

$user = requireAdmin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$code = strtoupper(trim($data['code'] ?? ''));
$name = trim($data['name'] ?? '');
$symbol = trim($data['symbol'] ?? '');

if (!preg_match('/^[A-Z]{3}$/', $code)) {
    jsonResponse([
        'success' => false,
        'message' => 'Currency code must contain exactly 3 letters.'
    ], 422);
}

if ($name === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Currency name is required.'
    ], 422);
}

if (strlen($name) > 100) {
    jsonResponse([
        'success' => false,
        'message' => 'Currency name is too long.'
    ], 422);
}

try {

    $stmt = $pdo->prepare("
        INSERT INTO currencies
        (
            code,
            name,
            symbol
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $code,
        $name,
        $symbol !== '' ? $symbol : null
    ]);

    jsonResponse([
        'success' => true,
        'currency' => [
            'id' => $pdo->lastInsertId(),
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol
        ]
    ], 201);
} catch (PDOException $e) {

    if ($e->errorInfo[1] ?? null === 1062) {
        jsonResponse([
            'success' => false,
            'message' => 'That currency already exists.'
        ], 409);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Unable to create currency.'
    ], 500);
}
