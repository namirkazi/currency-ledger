<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ledger.php';

requireAuth();

jsonResponse([
    'success' => true,
    'balances' => [
        'AED' => getBalance($pdo, 'AED'),
        'USDT' => getBalance($pdo, 'USDT')
    ]
]);
