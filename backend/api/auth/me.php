<?php

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

$user = currentUser();

if (!$user) {
    jsonResponse([
        'success' => false,
        'message' => 'Not authenticated.'
    ], 401);
}

jsonResponse([
    'success' => true,
    'user' => $user
]);
