<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$rows = db()->query(
    "SELECT program, COUNT(*) AS c
     FROM beneficiaries
     GROUP BY program
     ORDER BY c DESC, program ASC"
)->fetchAll();

$data = array_map(
    static fn(array $r): array => [
        'name' => (string) $r['program'],
        'count' => (int) $r['c'],
    ],
    $rows
);

json_response($data);

