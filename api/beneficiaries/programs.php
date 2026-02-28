<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

function ensure_programs_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS programs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    ensure_programs_table();

    $rows = db()->query(
        "SELECT p.name, COALESCE(b.c, 0) AS c
         FROM (
            SELECT name FROM programs
            UNION
            SELECT DISTINCT program AS name FROM beneficiaries WHERE program <> ''
         ) p
         LEFT JOIN (
            SELECT program, COUNT(*) AS c
            FROM beneficiaries
            GROUP BY program
         ) b ON b.program = p.name
         ORDER BY c DESC, p.name ASC"
    )->fetchAll();

    $data = array_map(
        static fn(array $r): array => [
            'name' => (string) $r['name'],
            'count' => (int) $r['c'],
        ],
        $rows
    );
    json_response($data);
}

if ($method === 'POST') {
    require_role(['admin']);
    ensure_programs_table();

    $body = request_body();
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_error('Program name is required', 400);
    }
    if (mb_strlen($name) > 190) {
        json_error('Program name is too long', 400);
    }

    $stmt = db()->prepare('INSERT IGNORE INTO programs (name) VALUES (:name)');
    $stmt->execute([':name' => $name]);

    json_response([
        'ok' => true,
        'created' => $stmt->rowCount() > 0,
        'name' => $name,
    ], 201);
}

json_error('Method not allowed', 405);
