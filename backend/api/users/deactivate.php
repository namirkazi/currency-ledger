<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

$admin = requireAdmin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$userId = (int) ($data['user_id'] ?? 0);

if ($userId <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'Invalid user ID.'
    ], 422);
}

if ($userId === (int) $admin['id']) {
    jsonResponse([
        'success' => false,
        'message' => 'You cannot deactivate your own account.'
    ], 422);
}

$stmt = $pdo->prepare("
    UPDATE users
    SET is_active = 0
    WHERE id = ?
");

$stmt->execute([$userId]);

if ($stmt->rowCount() === 0) {
    jsonResponse([
        'success' => false,
        'message' => 'User not found.'
    ], 404);
}

jsonResponse([
    'success' => true,
    'message' => 'User deactivated.'
]);
