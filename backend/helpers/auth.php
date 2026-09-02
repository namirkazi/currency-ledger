<?php

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {

        $isLocalhost =
            isset($_SERVER['HTTP_HOST']) &&
            (
                str_contains($_SERVER['HTTP_HOST'], 'localhost') ||
                str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')
            );

        if ($isLocalhost) {

            // Local development
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'secure' => false,
                'samesite' => 'Lax',
            ]);
        } else {

            // Production
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '.vmsambitiousgroup.com',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

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
