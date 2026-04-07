<?php
declare(strict_types=1);
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$count = (int) $pdo->query('SELECT COUNT(*) FROM mosques')->fetchColumn();
$rows = $pdo->query("SELECT id, name, union_name, ward_no, imam_name, imam_phone, muazzin_name, madrasa_present FROM mosques ORDER BY id ASC LIMIT 10")->fetchAll();
echo json_encode(['count' => $count, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
