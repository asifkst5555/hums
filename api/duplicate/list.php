<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

require_method('GET');
require_login();

$rows = db()->query('SELECT id, name, nid, program, union_name, phone, dob, father, mother, addr, status FROM beneficiaries ORDER BY id DESC')->fetchAll();

$groups = [];
foreach ($rows as $r) {
    $nid = (string) $r['nid'];
    if (!isset($groups[$nid])) {
        $groups[$nid] = [];
    }
    $groups[$nid][] = [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'nid' => $r['nid'],
        'program' => $r['program'],
        'union' => $r['union_name'],
        'phone' => $r['phone'],
        'dob' => $r['dob'],
        'father' => $r['father'],
        'mother' => $r['mother'],
        'addr' => $r['addr'],
        'status' => $r['status'],
    ];
}

$duplicates = [];
foreach ($groups as $group) {
    if (count($group) > 1) {
        $duplicates[] = $group;
    }
}

json_response(['duplicates' => $duplicates]);

