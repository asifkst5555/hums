<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$sql = "SELECT COUNT(*) AS total, SUM(khatib_name REGEXP '[0-9?-?]') AS k_digits, SUM(imam_name REGEXP '[0-9?-?]') AS i_digits, SUM(muazzin_name REGEXP '[0-9?-?]') AS m_digits FROM mosques";
$row = $pdo->query($sql)->fetch();
echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
