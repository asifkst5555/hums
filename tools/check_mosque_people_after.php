<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$rows = $pdo->query("SELECT id, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone FROM mosques WHERE id IN (2510,2514,2503,2509) ORDER BY id ASC")->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
