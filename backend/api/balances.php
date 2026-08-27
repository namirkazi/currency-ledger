<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/response.php';

requireAuth();

try {

    $stmt = $pdo->query("
        SELECT
            ab.currency_id,
            c.code,
            c.name,
            c.symbol,
            ab.balance

        FROM account_balances ab

        INNER JOIN currencies c
            ON c.id = ab.currency_id

        WHERE c.active = 1

        ORDER BY c.code ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $balances = [];

    foreach ($rows as $row) {

        $balances[] = [
            'currency_id' => (int) $row['currency_id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'symbol' => $row['symbol'],
            'balance' => $row['balance']
        ];
    }

    jsonResponse([
        'success' => true,
        'balances' => $balances
    ]);
} catch (Throwable $e) {

    jsonResponse([
        'success' => false,
        'message' => 'Unable to load balances.'
    ], 500);
}
