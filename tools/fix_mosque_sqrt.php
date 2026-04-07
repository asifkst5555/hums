<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$fields = ['name','addr','mosque_type','khatib_name','imam_name','muazzin_name','madrasa_name','imam'];
foreach ($fields as $field) {
    $sql = "UPDATE mosques SET {$field} = TRIM(REPLACE({$field}, 0xE2889A, '')) WHERE INSTR({$field}, 0xE2889A) > 0";
    $pdo->exec($sql);
}
echo 'done';
