<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('POST');

$body = request_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($username === '' || $password === '') {
    json_error('Username and password are required', 400);
}

$stmt = db()->prepare(
    'SELECT id, name, username, password_hash, role, union_name, status FROM users WHERE username = :username LIMIT 1'
);
$stmt->execute([':username' => $username]);
$row = $stmt->fetch();

if (!$row || $row['status'] !== 'active' || !password_verify($password, (string) $row['password_hash'])) {
    json_error('Invalid credentials', 401);
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) $row['id'],
    'name' => (string) $row['name'],
    'username' => (string) $row['username'],
    'role' => (string) $row['role'],
    'union' => (string) $row['union_name'],
];

json_response(['user' => $_SESSION['user']]);

