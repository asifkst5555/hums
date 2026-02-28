<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$rows = db()->query(
    "SELECT type, COUNT(*) AS c
     FROM institutions
     WHERE type IS NOT NULL AND type <> ''
     GROUP BY type
     ORDER BY c DESC, type ASC"
)->fetchAll();

$data = array_map(
    static fn(array $r): array => [
        'name' => (string) $r['type'],
        'count' => (int) $r['c'],
    ],
    $rows
);

json_response($data);

