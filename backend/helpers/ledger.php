<?php

function getBalance(PDO $pdo, string $currency): string
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS balance
        FROM ledger_entries
        WHERE currency = ?
    ");

    $stmt->execute([$currency]);

    return (string) $stmt->fetchColumn();
}

function ensureBalance(PDO $pdo, string $currency, string $required): void
{
    $balance = getBalance($pdo, $currency);

    if (bccomp($balance, $required, 6) < 0) {
        throw new RuntimeException(
            "Insufficient {$currency} balance."
        );
    }
}

function calculateAed(string $usdt, string $rate): string
{
    return bcmul($usdt, $rate, 6);
}
