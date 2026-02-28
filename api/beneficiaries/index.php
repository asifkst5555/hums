<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = max(10, min(200, (int) ($_GET['pageSize'] ?? 50)));
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

    $countStmt = db()->prepare("SELECT COUNT(*) AS c FROM beneficiaries {$whereSql}");
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetch()['c'] ?? 0);

    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT id, mis_number, name, name_en, gender, nid, program, union_name, phone, dob, father_en, father, mother_en, mother, spouse_name_en, spouse_name_bn, bank_mfs, account_number, age, division_name, district_name, upazila_name, ward_name, addr, status
            FROM beneficiaries
            {$whereSql}
            ORDER BY id DESC
            LIMIT {$pageSize} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = array_map(
        static fn(array $r): array => [
            'id' => (int) $r['id'],
            'misNumber' => $r['mis_number'],
            'name' => $r['name'],
            'nameEn' => $r['name_en'],
            'gender' => $r['gender'],
            'nid' => $r['nid'],
            'program' => $r['program'],
            'union' => $r['union_name'],
            'phone' => $r['phone'],
            'dob' => $r['dob'],
            'fatherEn' => $r['father_en'],
            'father' => $r['father'],
            'motherEn' => $r['mother_en'],
            'mother' => $r['mother'],
            'spouseNameEn' => $r['spouse_name_en'],
            'spouseNameBn' => $r['spouse_name_bn'],
            'bankMfs' => $r['bank_mfs'],
            'accountNumber' => $r['account_number'],
            'age' => $r['age'],
            'division' => $r['division_name'],
            'district' => $r['district_name'],
            'upazila' => $r['upazila_name'],
            'ward' => $r['ward_name'],
            'addr' => $r['addr'],
            'status' => $r['status'],
        ],
        $rows
    );
    json_response([
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
        'totalPages' => max(1, (int) ceil($total / $pageSize)),
    ]);
}

if ($method === 'POST') {
    require_role(['admin', 'operator']);
    $body = request_body();
    $name = trim((string) ($body['name'] ?? ''));
    $nid = trim((string) ($body['nid'] ?? ''));

    if ($name === '' || $nid === '') {
        json_error('Name and NID are required', 400);
    }

    $stmt = db()->prepare(
        'INSERT INTO beneficiaries (mis_number, name, name_en, gender, nid, program, union_name, phone, dob, father_en, father, mother_en, mother, spouse_name_en, spouse_name_bn, bank_mfs, account_number, age, division_name, district_name, upazila_name, ward_name, addr, status) VALUES (:mis_number, :name, :name_en, :gender, :nid, :program, :union_name, :phone, :dob, :father_en, :father, :mother_en, :mother, :spouse_name_en, :spouse_name_bn, :bank_mfs, :account_number, :age, :division_name, :district_name, :upazila_name, :ward_name, :addr, :status)'
    );
    $ageValue = $body['age'] ?? null;
    $age = ($ageValue === '' || $ageValue === null) ? null : (int) $ageValue;
    $dobValue = trim((string) ($body['dob'] ?? ''));
    $stmt->execute([
        ':mis_number' => (string) ($body['misNumber'] ?? ''),
        ':name' => $name,
        ':name_en' => (string) ($body['nameEn'] ?? ''),
        ':gender' => (string) ($body['gender'] ?? ''),
        ':nid' => $nid,
        ':program' => (string) ($body['program'] ?? ''),
        ':union_name' => (string) ($body['union'] ?? ''),
        ':phone' => (string) ($body['phone'] ?? ''),
        ':dob' => $dobValue === '' ? null : $dobValue,
        ':father_en' => (string) ($body['fatherEn'] ?? ''),
        ':father' => (string) ($body['father'] ?? ''),
        ':mother_en' => (string) ($body['motherEn'] ?? ''),
        ':mother' => (string) ($body['mother'] ?? ''),
        ':spouse_name_en' => (string) ($body['spouseNameEn'] ?? ''),
        ':spouse_name_bn' => (string) ($body['spouseNameBn'] ?? ''),
        ':bank_mfs' => (string) ($body['bankMfs'] ?? ''),
        ':account_number' => (string) ($body['accountNumber'] ?? ''),
        ':age' => $age,
        ':division_name' => (string) ($body['division'] ?? ''),
        ':district_name' => (string) ($body['district'] ?? ''),
        ':upazila_name' => (string) ($body['upazila'] ?? ''),
        ':ward_name' => (string) ($body['ward'] ?? ''),
        ':addr' => (string) ($body['addr'] ?? ''),
        ':status' => (string) ($body['status'] ?? 'active'),
    ]);

    json_response(['id' => (int) db()->lastInsertId()], 201);
}

json_error('Method not allowed', 405);
