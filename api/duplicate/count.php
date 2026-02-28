<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$sql = 'SELECT COUNT(*) AS c FROM (SELECT nid FROM beneficiaries GROUP BY nid HAVING COUNT(*) > 1) t';
$count = (int) (db()->query($sql)->fetch()['c'] ?? 0);
json_response(['count' => $count]);

