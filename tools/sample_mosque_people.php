<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$sql = "SELECT id, name, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone FROM mosques ORDER BY id ASC LIMIT 50";
$rows = $pdo->query($sql)->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
