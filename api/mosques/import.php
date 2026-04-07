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
    $value = preg_replace('/\x{FEFF}/u', '', $value) ?? $value;
    return strtr($value, ['?'=>'0','?'=>'1','?'=>'2','?'=>'3','?'=>'4','?'=>'5','?'=>'6','?'=>'7','?'=>'8','?'=>'9']);
};

$headerRow = fgetcsv($handle);
if (!is_array($headerRow) || $headerRow === []) {
    fclose($handle);
    json_error('CSV header not found', 400);
}

$aliases = [
    'id' => 'id',
    'name' => 'name',
    'mosquename' => 'name',
    'union' => 'union_name',
    'unionname' => 'union_name',
    'ward' => 'ward_no',
    'wardno' => 'ward_no',
    'mosquetype' => 'mosque_type',
    'type' => 'mosque_type',
    'khatibname' => 'khatib_name',
    'khatibphone' => 'khatib_phone',
    'imam' => 'imam_name',
    'imamname' => 'imam_name',
    'imamphone' => 'imam_phone',
    'phone' => 'imam_phone',
    'mobile' => 'imam_phone',
    'muazzinname' => 'muazzin_name',
    'muazzinphone' => 'muazzin_phone',
    'madrasapresent' => 'madrasa_present',
    'madrasaname' => 'madrasa_name',
    'addr' => 'addr',
    'address' => 'addr',
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
    'INSERT INTO mosques (name, union_name, ward_no, mosque_type, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone, madrasa_present, madrasa_name, imam, phone, addr) VALUES (:name, :union_name, :ward_no, :mosque_type, :khatib_name, :khatib_phone, :imam_name, :imam_phone, :muazzin_name, :muazzin_phone, :madrasa_present, :madrasa_name, :imam, :phone, :addr)'
);
$updateStmt = db()->prepare(
    'UPDATE mosques SET name = :name, union_name = :union_name, ward_no = :ward_no, mosque_type = :mosque_type, khatib_name = :khatib_name, khatib_phone = :khatib_phone, imam_name = :imam_name, imam_phone = :imam_phone, muazzin_name = :muazzin_name, muazzin_phone = :muazzin_phone, madrasa_present = :madrasa_present, madrasa_name = :madrasa_name, imam = :imam, phone = :phone, addr = :addr WHERE id = :id'
);
$findStmt = db()->prepare('SELECT id FROM mosques WHERE name = :name AND union_name = :union_name AND addr = :addr LIMIT 1');

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
            'name' => '',
            'union_name' => '',
            'ward_no' => '',
            'mosque_type' => '',
            'khatib_name' => '',
            'khatib_phone' => '',
            'imam_name' => '',
            'imam_phone' => '',
            'muazzin_name' => '',
            'muazzin_phone' => '',
            'madrasa_present' => 'no',
            'madrasa_name' => '',
            'addr' => '',
        ];

        foreach ($headerMap as $index => $field) {
            $value = $normalizeValue($row[$index] ?? '');
            if ($field === 'madrasa_present') {
                $payload['madrasa_present'] = $value === 'yes' ? 'yes' : ($value === 'no' ? 'no' : (in_array(mb_strtolower($value, 'UTF-8'), ['?????', 'ha', 'yes'], true) ? 'yes' : 'no'));
                continue;
            }
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $value;
            }
        }

        [$payload['khatib_name'], $payload['khatib_phone']] = split_person_name_phone($payload['khatib_name'], $payload['khatib_phone']);
        [$payload['imam_name'], $payload['imam_phone']] = split_person_name_phone($payload['imam_name'], $payload['imam_phone']);
        [$payload['muazzin_name'], $payload['muazzin_phone']] = split_person_name_phone($payload['muazzin_name'], $payload['muazzin_phone']);

        if ($payload['name'] === '' && $payload['union_name'] === '' && $payload['addr'] === '') {
            $stats['skipped'] += 1;
            continue;
        }
        if ($payload['name'] === '' || $payload['union_name'] === '') {
            $stats['skipped'] += 1;
            $stats['errors'][] = "Line {$line}: mosque name or union missing";
            continue;
        }

        $findStmt->execute([
            ':name' => $payload['name'],
            ':union_name' => $payload['union_name'],
            ':addr' => $payload['addr'],
        ]);
        $existing = $findStmt->fetch();

        if ($existing && isset($existing['id'])) {
            $updateStmt->execute([
                ':id' => (int) $existing['id'],
                ':name' => $payload['name'],
                ':union_name' => $payload['union_name'],
                ':ward_no' => $payload['ward_no'],
                ':mosque_type' => $payload['mosque_type'],
                ':khatib_name' => $payload['khatib_name'],
                ':khatib_phone' => $payload['khatib_phone'],
                ':imam_name' => $payload['imam_name'],
                ':imam_phone' => $payload['imam_phone'],
                ':muazzin_name' => $payload['muazzin_name'],
                ':muazzin_phone' => $payload['muazzin_phone'],
                ':madrasa_present' => $payload['madrasa_present'],
                ':madrasa_name' => $payload['madrasa_name'],
                ':imam' => $payload['imam_name'],
                ':phone' => $payload['imam_phone'],
                ':addr' => $payload['addr'],
            ]);
            $stats['updated'] += 1;
            continue;
        }

        $insertStmt->execute([
            ':name' => $payload['name'],
            ':union_name' => $payload['union_name'],
            ':ward_no' => $payload['ward_no'],
            ':mosque_type' => $payload['mosque_type'],
            ':khatib_name' => $payload['khatib_name'],
            ':khatib_phone' => $payload['khatib_phone'],
            ':imam_name' => $payload['imam_name'],
            ':imam_phone' => $payload['imam_phone'],
            ':muazzin_name' => $payload['muazzin_name'],
            ':muazzin_phone' => $payload['muazzin_phone'],
            ':madrasa_present' => $payload['madrasa_present'],
            ':madrasa_name' => $payload['madrasa_name'],
            ':imam' => $payload['imam_name'],
            ':phone' => $payload['imam_phone'],
            ':addr' => $payload['addr'],
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
