<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('POST');
require_login();

$body = request_body();
$value = trim((string) ($body['value'] ?? ''));
if ($value === '') {
    json_error('NID or phone value is required', 400);
}

$stmt = db()->prepare(
    'SELECT id, name, nid, program, union_name, phone, dob, father, mother, addr, status
     FROM beneficiaries
     WHERE nid = :nid_value OR phone = :phone_value
     ORDER BY id DESC'
);
$stmt->execute([
    ':nid_value' => $value,
    ':phone_value' => $value,
]);
$rows = $stmt->fetchAll();

$matches = array_map(
    static fn(array $r): array => [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'nid' => $r['nid'],
        'program' => $r['program'],
        'union' => $r['union_name'],
        'phone' => $r['phone'],
        'dob' => $r['dob'],
        'father' => $r['father'],
        'mother' => $r['mother'],
        'addr' => $r['addr'],
        'status' => $r['status'],
    ],
    $rows
);

json_response(['matches' => $matches]);
