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
        'UPDATE mosques SET name = :name, union_name = :union_name, ward_no = :ward_no, mosque_type = :mosque_type, khatib_name = :khatib_name, khatib_phone = :khatib_phone, imam_name = :imam_name, imam_phone = :imam_phone, muazzin_name = :muazzin_name, muazzin_phone = :muazzin_phone, madrasa_present = :madrasa_present, madrasa_name = :madrasa_name, imam = :imam, phone = :phone, addr = :addr WHERE id = :id'
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
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        $check = db()->prepare('SELECT id FROM mosques WHERE id = :id');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_error('Not found', 404);
        }
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    require_role(['admin']);
    $stmt = db()->prepare('DELETE FROM mosques WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        json_error('Not found', 404);
    }
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
