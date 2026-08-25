<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireAdmin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$name = trim($data['name'] ?? '');
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = strtoupper(trim($data['role'] ?? 'USER'));

if ($name === '' || $username === '' || $password === '') {
    jsonResponse([
        'success' => false,
        'message' => 'All fields are required.'
    ], 422);
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid username.'
    ], 422);
}

if (strlen($password) < 8) {
    jsonResponse([
        'success' => false,
        'message' => 'Password must contain at least 8 characters.'
    ], 422);
}

if (!in_array($role, ['ADMIN', 'USER'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid role.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$username]);

if ($stmt->fetch()) {
    jsonResponse([
        'success' => false,
        'message' => 'Username already exists.'
    ], 409);
}

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare("
    INSERT INTO users (
        name,
        username,
        password_hash,
        role
    )
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $username,
    $passwordHash,
    $role
]);

jsonResponse([
    'success' => true,
    'message' => 'User created successfully.',
    'user_id' => (int) $pdo->lastInsertId()
], 201);
