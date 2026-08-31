<?php

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

        session_start();
    }
}

startSecureSession();

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(): array
{
    $user = currentUser();

    if (!$user) {
        jsonResponse([
            'success' => false,
            'message' => 'Authentication required.'
        ], 401);
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireAuth();

    if ($user['role'] !== 'ADMIN') {
        jsonResponse([
            'success' => false,
            'message' => 'Administrator access required.'
        ], 403);
    }

    return $user;
}
