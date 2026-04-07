<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$fields = ['name','addr','mosque_type','khatib_name','imam_name','muazzin_name','madrasa_name','imam'];
foreach ($fields as $field) {
    $pdo->exec("UPDATE mosques SET {$field} = TRIM(REPLACE(REPLACE(REPLACE({$field}, '  ', ' '), '  ', ' '), '  ', ' '))");
}
echo 'done';
