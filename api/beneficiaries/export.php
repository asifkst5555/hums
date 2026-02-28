<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_login();
require_method('GET');

$q = trim((string) ($_GET['q'] ?? ''));
$program = trim((string) ($_GET['program'] ?? ''));

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE :q_name OR name_en LIKE :q_name_en OR nid LIKE :q_nid OR phone LIKE :q_phone)';
    $qLike = '%' . $q . '%';
    $params[':q_name'] = $qLike;
    $params[':q_name_en'] = $qLike;
    $params[':q_nid'] = $qLike;
    $params[':q_phone'] = $qLike;
}
if ($program !== '') {
    $where[] = 'program = :program';
    $params[':program'] = $program;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare(
    "SELECT id, mis_number, name, name_en, gender, nid, program, union_name, phone, dob, father_en, father, mother_en, mother, spouse_name_en, spouse_name_bn, bank_mfs, account_number, age, division_name, district_name, upazila_name, ward_name, addr, status
     FROM beneficiaries
     {$whereSql}
     ORDER BY id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'beneficiaries_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'wb');
if ($out === false) {
    http_response_code(500);
    exit;
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'id',
    'mis_number',
    'name',
    'name_en',
    'gender',
    'nid',
    'program',
    'union_name',
    'phone',
    'dob',
    'father_en',
    'father',
    'mother_en',
    'mother',
    'spouse_name_en',
    'spouse_name_bn',
    'bank_mfs',
    'account_number',
    'age',
    'division_name',
    'district_name',
    'upazila_name',
    'ward_name',
    'addr',
    'status',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        (string) ($row['id'] ?? ''),
        (string) ($row['mis_number'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) ($row['name_en'] ?? ''),
        (string) ($row['gender'] ?? ''),
        (string) ($row['nid'] ?? ''),
        (string) ($row['program'] ?? ''),
        (string) ($row['union_name'] ?? ''),
        (string) ($row['phone'] ?? ''),
        (string) ($row['dob'] ?? ''),
        (string) ($row['father_en'] ?? ''),
        (string) ($row['father'] ?? ''),
        (string) ($row['mother_en'] ?? ''),
        (string) ($row['mother'] ?? ''),
        (string) ($row['spouse_name_en'] ?? ''),
        (string) ($row['spouse_name_bn'] ?? ''),
        (string) ($row['bank_mfs'] ?? ''),
        (string) ($row['account_number'] ?? ''),
        $row['age'] === null ? '' : (string) $row['age'],
        (string) ($row['division_name'] ?? ''),
        (string) ($row['district_name'] ?? ''),
        (string) ($row['upazila_name'] ?? ''),
        (string) ($row['ward_name'] ?? ''),
        (string) ($row['addr'] ?? ''),
        (string) ($row['status'] ?? ''),
    ]);
}

fclose($out);
exit;

