<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$rows = $pdo->query("SELECT id, name FROM mosques ORDER BY id ASC")->fetchAll();
$chars = [];
foreach ($rows as $row) {
    $name = $row['name'];
    $len = mb_strlen($name, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($name, $i, 1, 'UTF-8');
        $code = strtoupper(bin2hex(mb_convert_encoding($ch, 'UTF-32BE', 'UTF-8')));
        if (preg_match('/^[\x{0980}-\x{09FF}A-Za-z0-9\s,.;:()\-\/]+$/u', $ch)) {
            continue;
        }
        if (!isset($chars[$code])) {
            $chars[$code] = ['char' => $ch, 'count' => 0, 'sample' => $name];
        }
        $chars[$code]['count']++;
    }
}
uksort($chars, function ($a, $b) use ($chars) {
    return $chars[$b]['count'] <=> $chars[$a]['count'];
});
echo json_encode(array_slice($chars, 0, 20, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
