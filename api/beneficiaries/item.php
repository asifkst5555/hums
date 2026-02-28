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
    $existingStmt = db()->prepare(
        'SELECT mis_number, name, name_en, gender, nid, program, union_name, phone, dob, father_en, father, mother_en, mother, spouse_name_en, spouse_name_bn, bank_mfs, account_number, age, division_name, district_name, upazila_name, ward_name, addr, status
         FROM beneficiaries
         WHERE id = :id'
    );
    $existingStmt->execute([':id' => $id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        json_error('Not found', 404);
    }

    $getValue = static function (string $bodyKey, string $dbKey) use ($body, $existing): mixed {
        return array_key_exists($bodyKey, $body) ? $body[$bodyKey] : $existing[$dbKey];
    };

    $name = trim((string) $getValue('name', 'name'));
    $nid = trim((string) $getValue('nid', 'nid'));
    if ($name === '' || $nid === '') {
        json_error('Name and NID are required', 400);
    }

    $ageValue = $getValue('age', 'age');
    $age = ($ageValue === '' || $ageValue === null) ? null : (int) $ageValue;
    $dobValue = (string) $getValue('dob', 'dob');
    $dob = trim($dobValue) === '' ? null : $dobValue;

    $stmt = db()->prepare(
        'UPDATE beneficiaries
         SET mis_number = :mis_number,
             name = :name,
             name_en = :name_en,
             gender = :gender,
             nid = :nid,
             program = :program,
             union_name = :union_name,
             phone = :phone,
             dob = :dob,
             father_en = :father_en,
             father = :father,
             mother_en = :mother_en,
             mother = :mother,
             spouse_name_en = :spouse_name_en,
             spouse_name_bn = :spouse_name_bn,
             bank_mfs = :bank_mfs,
             account_number = :account_number,
             age = :age,
             division_name = :division_name,
             district_name = :district_name,
             upazila_name = :upazila_name,
             ward_name = :ward_name,
             addr = :addr,
             status = :status
         WHERE id = :id'
    );
    $stmt->execute([
        ':mis_number' => (string) $getValue('misNumber', 'mis_number'),
        ':name' => $name,
        ':name_en' => (string) $getValue('nameEn', 'name_en'),
        ':gender' => (string) $getValue('gender', 'gender'),
        ':nid' => $nid,
        ':program' => (string) $getValue('program', 'program'),
        ':union_name' => (string) $getValue('union', 'union_name'),
        ':phone' => (string) $getValue('phone', 'phone'),
        ':dob' => $dob,
        ':father_en' => (string) $getValue('fatherEn', 'father_en'),
        ':father' => (string) $getValue('father', 'father'),
        ':mother_en' => (string) $getValue('motherEn', 'mother_en'),
        ':mother' => (string) $getValue('mother', 'mother'),
        ':spouse_name_en' => (string) $getValue('spouseNameEn', 'spouse_name_en'),
        ':spouse_name_bn' => (string) $getValue('spouseNameBn', 'spouse_name_bn'),
        ':bank_mfs' => (string) $getValue('bankMfs', 'bank_mfs'),
        ':account_number' => (string) $getValue('accountNumber', 'account_number'),
        ':age' => $age,
        ':division_name' => (string) $getValue('division', 'division_name'),
        ':district_name' => (string) $getValue('district', 'district_name'),
        ':upazila_name' => (string) $getValue('upazila', 'upazila_name'),
        ':ward_name' => (string) $getValue('ward', 'ward_name'),
        ':addr' => (string) $getValue('addr', 'addr'),
        ':status' => (string) $getValue('status', 'status'),
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        $check = db()->prepare('SELECT id FROM beneficiaries WHERE id = :id');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_error('Not found', 404);
        }
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    require_role(['admin']);
    $stmt = db()->prepare('DELETE FROM beneficiaries WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        json_error('Not found', 404);
    }
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
