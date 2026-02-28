<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_role(['admin']);
    $rows = db()->query('SELECT id, name, username, role, union_name, status FROM users ORDER BY id ASC')->fetchAll();
    $data = array_map(
        static fn(array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'uname' => $r['username'],
            'role' => $r['role'],
            'union' => $r['union_name'],
            'status' => $r['status'],
        ],
        $rows
    );
    json_response($data);
}

if ($method === 'POST') {
    require_role(['admin']);
    $body = request_body();

    $name = trim((string) ($body['name'] ?? ''));
    $uname = trim((string) ($body['uname'] ?? $body['username'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $role = (string) ($body['role'] ?? 'viewer');
    $union = (string) ($body['union'] ?? 'all');

    if ($name === '' || $uname === '' || $password === '') {
        json_error('Name, username and password are required', 400);
    }
    if (!in_array($role, ['admin', 'viewer', 'operator'], true)) {
        json_error('Invalid role', 400);
    }

    $exists = db()->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $exists->execute([':username' => $uname]);
    if ($exists->fetch()) {
        json_error('Username already exists', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, username, password_hash, role, union_name, status) VALUES (:name, :username, :password_hash, :role, :union_name, :status)'
    );
    $stmt->execute([
        ':name' => $name,
        ':username' => $uname,
        ':password_hash' => $hash,
        ':role' => $role,
        ':union_name' => $union,
        ':status' => 'active',
    ]);

    json_response(['id' => (int) db()->lastInsertId()], 201);
}

json_error('Method not allowed', 405);

