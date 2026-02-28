<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$sql = "SELECT union_name AS name, COUNT(*) AS beneficiaries
        FROM beneficiaries
        WHERE union_name IS NOT NULL AND union_name <> ''
        GROUP BY union_name
        ORDER BY beneficiaries DESC, name ASC";
$rows = db()->query($sql)->fetchAll();
$data = array_map(
    static fn(array $r): array => [
        'name' => (string) $r['name'],
        'beneficiaries' => (int) $r['beneficiaries'],
    ],
    $rows
);
json_response($data);
