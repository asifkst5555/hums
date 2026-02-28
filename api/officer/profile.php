<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

function ensure_officer_profile_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS officer_profile (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            designation VARCHAR(190) NOT NULL,
            join_date DATE NULL,
            telephone VARCHAR(64) NULL,
            mobile VARCHAR(64) NULL,
            email VARCHAR(190) NULL,
            photo_path VARCHAR(255) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = db()->prepare(
        "INSERT IGNORE INTO officer_profile (id, name, designation, join_date, telephone, mobile, email, photo_path)
         VALUES (1, :name, :designation, :join_date, :telephone, :mobile, :email, :photo_path)"
    );
    $stmt->execute([
        ':name' => 'জনাব মুহাম্মদ আব্দুল্লাহ আল মুমিন',
        ':designation' => 'উপজেলা নির্বাহী অফিসার',
        ':join_date' => '2025-07-28',
        ':telephone' => '031-2603191',
        ':mobile' => '01836-672980',
        ':email' => 'unohathazari@mopa.gov.bd',
        ':photo_path' => 'media/profile.jpeg',
    ]);
}

function fetch_profile(): array
{
    $stmt = db()->query(
        "SELECT id, name, designation, join_date, telephone, mobile, email, photo_path, updated_at
         FROM officer_profile
         WHERE id = 1
         LIMIT 1"
    );
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Officer profile not found', 404);
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'designation' => (string) $row['designation'],
        'joinDate' => (string) ($row['join_date'] ?? ''),
        'telephone' => (string) ($row['telephone'] ?? ''),
        'mobile' => (string) ($row['mobile'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'photoPath' => (string) ($row['photo_path'] ?? ''),
        'updatedAt' => (string) ($row['updated_at'] ?? ''),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
ensure_officer_profile_table();

if ($method === 'GET') {
    require_login();
    json_response(fetch_profile());
}

if ($method === 'POST') {
    require_role(['admin']);
    $body = request_body();

    $name = trim((string) ($body['name'] ?? ''));
    $designation = trim((string) ($body['designation'] ?? ''));
    $joinDateRaw = trim((string) ($body['joinDate'] ?? ''));
    $telephone = trim((string) ($body['telephone'] ?? ''));
    $mobile = trim((string) ($body['mobile'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));

    if ($name === '' || $designation === '') {
        json_error('Name and designation are required', 400);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email address', 400);
    }

    $joinDate = $joinDateRaw === '' ? null : $joinDateRaw;
    if ($joinDate !== null) {
        $dt = DateTime::createFromFormat('Y-m-d', $joinDate);
        if (!$dt || $dt->format('Y-m-d') !== $joinDate) {
            json_error('Invalid join date format. Use YYYY-MM-DD', 400);
        }
    }

    $stmt = db()->prepare(
        "UPDATE officer_profile
         SET name = :name,
             designation = :designation,
             join_date = :join_date,
             telephone = :telephone,
             mobile = :mobile,
             email = :email
         WHERE id = 1"
    );
    $stmt->execute([
        ':name' => $name,
        ':designation' => $designation,
        ':join_date' => $joinDate,
        ':telephone' => $telephone,
        ':mobile' => $mobile,
        ':email' => $email,
    ]);

    json_response(fetch_profile());
}

json_error('Method not allowed', 405);
