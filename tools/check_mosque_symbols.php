<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$count = (int) $pdo->query("SELECT COUNT(*) FROM mosques WHERE name LIKE '%?%' OR addr LIKE '%?%' OR imam_name LIKE '%?%'")->fetchColumn();
$rows = $pdo->query("SELECT id, name FROM mosques ORDER BY id ASC LIMIT 5")->fetchAll();
echo json_encode(['count' => $count, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
