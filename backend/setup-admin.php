<?php

require_once __DIR__ . '/config/database.php';

$name = 'Administrator';
$username = 'admin';
$password = 'admin123';
$role = 'ADMIN';

$check = $pdo->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    LIMIT 1
");

$check->execute([$username]);

if ($check->fetch()) {
    exit('Admin user already exists.');
}

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare("
    INSERT INTO users
    (name, username, password_hash, role)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $username,
    $passwordHash,
    $role
]);

echo 'Admin created successfully.';
