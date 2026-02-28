<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_role(['admin', 'operator']);
require_method('POST');

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    json_error('CSV file is required', 400);
}

$file = $_FILES['file'];
$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    json_error('File upload failed', 400);
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    json_error('Invalid uploaded file', 400);
}

$handle = fopen($tmpPath, 'rb');
if ($handle === false) {
    json_error('Could not read uploaded file', 400);
}

$normalizeHeader = static function (string $value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }
    $value = preg_replace('/[\s_\-:]+/u', '', $value) ?? $value;
    return $value;
};

$normalizeValue = static function (mixed $value): string {
    $value = trim((string) $value);
    return preg_replace('/\x{FEFF}/u', '', $value) ?? $value;
};

$parseAge = static function (string $value): ?int {
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $age = (int) $value;
    return $age < 0 ? null : $age;
};

$parseDob = static function (string $value): ?string {
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
};

$headerRow = fgetcsv($handle);
if (!is_array($headerRow) || $headerRow === []) {
    fclose($handle);
    json_error('CSV header not found', 400);
}

$aliases = [
    'id' => 'id',
    'misnumber' => 'mis_number',
    'misno' => 'mis_number',
    'mis' => 'mis_number',
    'name' => 'name',
    'নাম' => 'name',
    'namebn' => 'name',
    'nameen' => 'name_en',
    'gender' => 'gender',
    'লিঙ্গ' => 'gender',
    'nid' => 'nid',
    'nidnumber' => 'nid',
    'nidনম্বর' => 'nid',
    'program' => 'program',
    'কার্যক্রম' => 'program',
    'union' => 'union_name',
    'unionname' => 'union_name',
    'ইউনিয়ন' => 'union_name',
    'phone' => 'phone',
    'ফোন' => 'phone',
    'dob' => 'dob',
    'dateofbirth' => 'dob',
    'জন্মতারিখ' => 'dob',
    'father' => 'father',
    'fathername' => 'father',
    'fatheren' => 'father_en',
    'mother' => 'mother',
    'mothername' => 'mother',
    'motheren' => 'mother_en',
    'spousenameen' => 'spouse_name_en',
    'spousenamebn' => 'spouse_name_bn',
    'bankmfs' => 'bank_mfs',
    'accountnumber' => 'account_number',
    'age' => 'age',
    'division' => 'division_name',
    'divisionname' => 'division_name',
    'district' => 'district_name',
    'districtname' => 'district_name',
    'upazila' => 'upazila_name',
    'upazilaname' => 'upazila_name',
    'ward' => 'ward_name',
    'wardname' => 'ward_name',
    'addr' => 'addr',
    'address' => 'addr',
    'status' => 'status',
    'অবস্থা' => 'status',
];

$headerMap = [];
foreach ($headerRow as $index => $header) {
    $normalized = $normalizeHeader((string) $header);
    if ($normalized === '') {
        continue;
    }
    if (isset($aliases[$normalized])) {
        $headerMap[(int) $index] = $aliases[$normalized];
    }
}

if ($headerMap === []) {
    fclose($handle);
    json_error('Unsupported CSV header format', 400);
}

$insertStmt = db()->prepare(
    'INSERT INTO beneficiaries (mis_number, name, name_en, gender, nid, program, union_name, phone, dob, father_en, father, mother_en, mother, spouse_name_en, spouse_name_bn, bank_mfs, account_number, age, division_name, district_name, upazila_name, ward_name, addr, status)
     VALUES (:mis_number, :name, :name_en, :gender, :nid, :program, :union_name, :phone, :dob, :father_en, :father, :mother_en, :mother, :spouse_name_en, :spouse_name_bn, :bank_mfs, :account_number, :age, :division_name, :district_name, :upazila_name, :ward_name, :addr, :status)'
);
$updateStmt = db()->prepare(
    'UPDATE beneficiaries
     SET mis_number = :mis_number,
         name = :name,
         name_en = :name_en,
         gender = :gender,
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
         status = :status,
         union_name = :union_name
     WHERE id = :id'
);
$findStmt = db()->prepare('SELECT id FROM beneficiaries WHERE nid = :nid AND program = :program LIMIT 1');

$stats = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => [],
];

$line = 1;
db()->beginTransaction();
try {
    while (($row = fgetcsv($handle)) !== false) {
        $line += 1;
        if ($row === [null] || $row === []) {
            $stats['skipped'] += 1;
            continue;
        }

        $payload = [
            'mis_number' => '',
            'name' => '',
            'name_en' => '',
            'gender' => '',
            'nid' => '',
            'program' => '',
            'union_name' => '',
            'phone' => '',
            'dob' => null,
            'father_en' => '',
            'father' => '',
            'mother_en' => '',
            'mother' => '',
            'spouse_name_en' => '',
            'spouse_name_bn' => '',
            'bank_mfs' => '',
            'account_number' => '',
            'age' => null,
            'division_name' => '',
            'district_name' => '',
            'upazila_name' => '',
            'ward_name' => '',
            'addr' => '',
            'status' => 'active',
        ];

        foreach ($headerMap as $index => $field) {
            $value = $normalizeValue($row[$index] ?? '');
            if ($field === 'age') {
                $payload['age'] = $parseAge($value);
                continue;
            }
            if ($field === 'dob') {
                $payload['dob'] = $parseDob($value);
                continue;
            }
            if ($field === 'status') {
                $payload['status'] = $value === 'inactive' ? 'inactive' : 'active';
                continue;
            }
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $value;
            }
        }

        if ($payload['name'] === '' && $payload['nid'] === '' && $payload['program'] === '') {
            $stats['skipped'] += 1;
            continue;
        }
        if ($payload['name'] === '' || $payload['nid'] === '') {
            $stats['skipped'] += 1;
            $stats['errors'][] = "Line {$line}: name or NID missing";
            continue;
        }

        $findStmt->execute([
            ':nid' => $payload['nid'],
            ':program' => $payload['program'],
        ]);
        $existing = $findStmt->fetch();

        if ($existing && isset($existing['id'])) {
            $updateStmt->execute([
                ':id' => (int) $existing['id'],
                ':mis_number' => $payload['mis_number'],
                ':name' => $payload['name'],
                ':name_en' => $payload['name_en'],
                ':gender' => $payload['gender'],
                ':phone' => $payload['phone'],
                ':dob' => $payload['dob'],
                ':father_en' => $payload['father_en'],
                ':father' => $payload['father'],
                ':mother_en' => $payload['mother_en'],
                ':mother' => $payload['mother'],
                ':spouse_name_en' => $payload['spouse_name_en'],
                ':spouse_name_bn' => $payload['spouse_name_bn'],
                ':bank_mfs' => $payload['bank_mfs'],
                ':account_number' => $payload['account_number'],
                ':age' => $payload['age'],
                ':division_name' => $payload['division_name'],
                ':district_name' => $payload['district_name'],
                ':upazila_name' => $payload['upazila_name'],
                ':ward_name' => $payload['ward_name'],
                ':addr' => $payload['addr'],
                ':status' => $payload['status'],
                ':union_name' => $payload['union_name'],
            ]);
            $stats['updated'] += 1;
            continue;
        }

        $insertStmt->execute([
            ':mis_number' => $payload['mis_number'],
            ':name' => $payload['name'],
            ':name_en' => $payload['name_en'],
            ':gender' => $payload['gender'],
            ':nid' => $payload['nid'],
            ':program' => $payload['program'],
            ':union_name' => $payload['union_name'],
            ':phone' => $payload['phone'],
            ':dob' => $payload['dob'],
            ':father_en' => $payload['father_en'],
            ':father' => $payload['father'],
            ':mother_en' => $payload['mother_en'],
            ':mother' => $payload['mother'],
            ':spouse_name_en' => $payload['spouse_name_en'],
            ':spouse_name_bn' => $payload['spouse_name_bn'],
            ':bank_mfs' => $payload['bank_mfs'],
            ':account_number' => $payload['account_number'],
            ':age' => $payload['age'],
            ':division_name' => $payload['division_name'],
            ':district_name' => $payload['district_name'],
            ':upazila_name' => $payload['upazila_name'],
            ':ward_name' => $payload['ward_name'],
            ':addr' => $payload['addr'],
            ':status' => $payload['status'],
        ]);
        $stats['inserted'] += 1;
    }
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    fclose($handle);
    json_error('Import failed: ' . $e->getMessage(), 500);
}

fclose($handle);

json_response([
    'ok' => true,
    'inserted' => $stats['inserted'],
    'updated' => $stats['updated'],
    'skipped' => $stats['skipped'],
    'errors' => array_slice($stats['errors'], 0, 30),
]);

