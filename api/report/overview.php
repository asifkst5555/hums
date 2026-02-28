<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$pdo = db();

$totals = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status='active') AS active_count,
        SUM(status='inactive') AS inactive_count,
        SUM(status='pending') AS pending_count,
        COUNT(DISTINCT program) AS unique_programs
     FROM beneficiaries"
)->fetch();

$programs = $pdo->query(
    "SELECT program, COUNT(*) AS c
     FROM beneficiaries
     GROUP BY program
     ORDER BY c DESC
     LIMIT 6"
)->fetchAll();

$unions = $pdo->query(
    "SELECT union_name AS union_name, COUNT(*) AS c
     FROM beneficiaries
     GROUP BY union_name
     ORDER BY c DESC
     LIMIT 6"
)->fetchAll();

$ageBands = $pdo->query(
    "SELECT
        SUM(CASE WHEN dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 18 THEN 1 ELSE 0 END) AS under_18,
        SUM(CASE WHEN dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 40 THEN 1 ELSE 0 END) AS age_18_40,
        SUM(CASE WHEN dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 41 AND 60 THEN 1 ELSE 0 END) AS age_41_60,
        SUM(CASE WHEN dob IS NOT NULL AND TIMESTAMPDIFF(YEAR, dob, CURDATE()) > 60 THEN 1 ELSE 0 END) AS over_60
     FROM beneficiaries"
)->fetch();

$quality = $pdo->query(
    "SELECT
        SUM(CASE WHEN phone IS NOT NULL AND phone <> '' THEN 1 ELSE 0 END) AS with_phone,
        SUM(CASE WHEN dob IS NOT NULL THEN 1 ELSE 0 END) AS with_dob,
        SUM(CASE WHEN addr IS NOT NULL AND addr <> '' THEN 1 ELSE 0 END) AS with_address
     FROM beneficiaries"
)->fetch();

json_response([
    'totals' => [
        'total' => (int) ($totals['total'] ?? 0),
        'active' => (int) ($totals['active_count'] ?? 0),
        'inactive' => (int) ($totals['inactive_count'] ?? 0),
        'pending' => (int) ($totals['pending_count'] ?? 0),
        'uniquePrograms' => (int) ($totals['unique_programs'] ?? 0),
    ],
    'topPrograms' => array_map(
        static fn(array $r): array => ['name' => (string) $r['program'], 'count' => (int) $r['c']],
        $programs
    ),
    'topUnions' => array_map(
        static fn(array $r): array => ['name' => (string) $r['union_name'], 'count' => (int) $r['c']],
        $unions
    ),
    'ageBands' => [
        'under18' => (int) ($ageBands['under_18'] ?? 0),
        'age18to40' => (int) ($ageBands['age_18_40'] ?? 0),
        'age41to60' => (int) ($ageBands['age_41_60'] ?? 0),
        'over60' => (int) ($ageBands['over_60'] ?? 0),
    ],
    'dataQuality' => [
        'withPhone' => (int) ($quality['with_phone'] ?? 0),
        'withDob' => (int) ($quality['with_dob'] ?? 0),
        'withAddress' => (int) ($quality['with_address'] ?? 0),
    ],
]);

