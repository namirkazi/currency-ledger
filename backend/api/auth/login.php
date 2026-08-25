<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    jsonResponse([
        'success' => false,
        'message' => 'Username and password are required.'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT id, name, username, password_hash, role
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$username]);

$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid credentials.'
    ], 401);
}

session_regenerate_id(true);

$_SESSION['user'] = [
    'id'       => $user['id'],
    'name'     => $user['name'],
    'username' => $user['username'],
    'role'     => $user['role'],
];

jsonResponse([
    'success' => true,
    'user' => $_SESSION['user']
]);
