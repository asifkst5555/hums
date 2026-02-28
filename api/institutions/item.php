<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$id = int_param('id');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$op = $_GET['op'] ?? '';
if ($method === 'POST' && $op === 'update') {
    $method = 'PUT';
}
if ($method === 'POST' && $op === 'delete') {
    $method = 'DELETE';
}

if ($method === 'PUT') {
    require_role(['admin', 'operator']);
    $body = request_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_error('Institution name is required', 400);
    }

    $stmt = db()->prepare(
        'UPDATE institutions SET name = :name, type = :type, union_name = :union_name, students = :students, head = :head, phone = :phone, addr = :addr WHERE id = :id'
    );
    $stmt->execute([
        ':name' => $name,
        ':type' => (string) ($body['type'] ?? ''),
        ':union_name' => (string) ($body['union'] ?? ''),
        ':students' => (int) ($body['students'] ?? 0),
        ':head' => (string) ($body['head'] ?? ''),
        ':phone' => (string) ($body['phone'] ?? ''),
        ':addr' => (string) ($body['addr'] ?? ''),
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        $check = db()->prepare('SELECT id FROM institutions WHERE id = :id');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_error('Not found', 404);
        }
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    require_role(['admin']);
    $stmt = db()->prepare('DELETE FROM institutions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        json_error('Not found', 404);
    }
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
