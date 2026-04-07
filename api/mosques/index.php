<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';


function clean_mosque_text(?string $value): string
{
    $value = (string) ($value ?? '');
    $value = str_replace(["\u{221A}", "\u{F050}"], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    $rows = db()->query('SELECT id, name, union_name, ward_no, mosque_type, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone, madrasa_present, madrasa_name, imam, phone, addr FROM mosques ORDER BY id DESC')->fetchAll();
    $data = array_map(
        static fn(array $r): array => [
            'id' => (int) $r['id'],
            'name' => clean_mosque_text($r['name']),
            'union' => clean_mosque_text($r['union_name']),
            'wardNo' => clean_mosque_text($r['ward_no']),
            'mosqueType' => clean_mosque_text($r['mosque_type']),
            'khatibName' => clean_mosque_text($r['khatib_name']),
            'khatibPhone' => normalize_phone_digits($r['khatib_phone']),
            'imamName' => clean_mosque_text($r['imam_name'] ?: $r['imam']),
            'imamPhone' => normalize_phone_digits($r['imam_phone'] ?: $r['phone']),
            'muazzinName' => clean_mosque_text($r['muazzin_name']),
            'muazzinPhone' => normalize_phone_digits($r['muazzin_phone']),
            'madrasaPresent' => $r['madrasa_present'],
            'madrasaName' => clean_mosque_text($r['madrasa_name']),
            'imam' => clean_mosque_text($r['imam_name'] ?: $r['imam']),
            'phone' => normalize_phone_digits($r['imam_phone'] ?: $r['phone']),
            'addr' => clean_mosque_text($r['addr']),
        ],
        $rows
    );
    json_response($data);
}

if ($method === 'POST') {
    require_role(['admin', 'operator']);
    $body = request_body();
    $name = trim((string) ($body['name'] ?? ''));
    $union = trim((string) ($body['union'] ?? ''));

    if ($name === '' || $union === '') {
        json_error('Mosque name and union are required', 400);
    }

    $wardNo = trim((string) ($body['wardNo'] ?? ''));
    $mosqueType = trim((string) ($body['mosqueType'] ?? ''));
    [$khatibName, $khatibPhone] = split_person_name_phone(
        trim((string) ($body['khatibName'] ?? '')),
        trim((string) ($body['khatibPhone'] ?? ''))
    );
    [$imamName, $imamPhone] = split_person_name_phone(
        trim((string) ($body['imamName'] ?? ($body['imam'] ?? ''))),
        trim((string) ($body['imamPhone'] ?? ($body['phone'] ?? '')))
    );
    [$muazzinName, $muazzinPhone] = split_person_name_phone(
        trim((string) ($body['muazzinName'] ?? '')),
        trim((string) ($body['muazzinPhone'] ?? ''))
    );
    $madrasaPresent = trim((string) ($body['madrasaPresent'] ?? 'no')) === 'yes' ? 'yes' : 'no';
    $madrasaName = trim((string) ($body['madrasaName'] ?? ''));
    $addr = trim((string) ($body['addr'] ?? ''));

    $stmt = db()->prepare(
        'INSERT INTO mosques (name, union_name, ward_no, mosque_type, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone, madrasa_present, madrasa_name, imam, phone, addr) VALUES (:name, :union_name, :ward_no, :mosque_type, :khatib_name, :khatib_phone, :imam_name, :imam_phone, :muazzin_name, :muazzin_phone, :madrasa_present, :madrasa_name, :imam, :phone, :addr)'
    );
    $stmt->execute([
        ':name' => $name,
        ':union_name' => $union,
        ':ward_no' => $wardNo,
        ':mosque_type' => $mosqueType,
        ':khatib_name' => $khatibName,
        ':khatib_phone' => $khatibPhone,
        ':imam_name' => $imamName,
        ':imam_phone' => $imamPhone,
        ':muazzin_name' => $muazzinName,
        ':muazzin_phone' => $muazzinPhone,
        ':madrasa_present' => $madrasaPresent,
        ':madrasa_name' => $madrasaName,
        ':imam' => $imamName,
        ':phone' => $imamPhone,
        ':addr' => $addr,
    ]);

    json_response(['id' => (int) db()->lastInsertId()], 201);
}

json_error('Method not allowed', 405);
