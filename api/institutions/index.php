<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    $rows = db()->query('SELECT id, name, type, union_name, students, head, phone, addr FROM institutions ORDER BY id DESC')->fetchAll();
    $data = array_map(
        static fn(array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'type' => $r['type'],
            'union' => $r['union_name'],
            'students' => (int) $r['students'],
            'head' => $r['head'],
            'phone' => $r['phone'],
            'addr' => $r['addr'],
        ],
        $rows
    );
    json_response($data);
}

if ($method === 'POST') {
    require_role(['admin', 'operator']);
    $body = request_body();
    $name = trim((string) ($body['name'] ?? ''));

    if ($name === '') {
        json_error('Institution name is required', 400);
    }

    $stmt = db()->prepare(
        'INSERT INTO institutions (name, type, union_name, students, head, phone, addr) VALUES (:name, :type, :union_name, :students, :head, :phone, :addr)'
    );
    $stmt->execute([
        ':name' => $name,
        ':type' => (string) ($body['type'] ?? ''),
        ':union_name' => (string) ($body['union'] ?? ''),
        ':students' => (int) ($body['students'] ?? 0),
        ':head' => (string) ($body['head'] ?? ''),
        ':phone' => (string) ($body['phone'] ?? ''),
        ':addr' => (string) ($body['addr'] ?? ''),
    ]);

    json_response(['id' => (int) db()->lastInsertId()], 201);
}

json_error('Method not allowed', 405);

