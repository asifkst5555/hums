<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$op = $_GET['op'] ?? '';
if ($method === 'POST' && $op === 'delete') {
    $method = 'DELETE';
}
if ($method !== 'DELETE') {
    json_error('Method not allowed', 405);
}

$actor = require_role(['admin']);
$id = int_param('id');

if ((int) $actor['id'] === $id) {
    json_error('You cannot delete your own account', 400);
}

$stmt = db()->prepare('DELETE FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() === 0) {
    json_error('Not found', 404);
}

json_response(['ok' => true]);
