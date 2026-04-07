<?php
require __DIR__ . '/../api/bootstrap.php';
$pdo = db();
$rows = $pdo->query('SELECT id, khatib_name, khatib_phone, imam_name, imam_phone, muazzin_name, muazzin_phone FROM mosques ORDER BY id ASC')->fetchAll();
$update = $pdo->prepare('UPDATE mosques SET khatib_name = :khatib_name, khatib_phone = :khatib_phone, imam_name = :imam_name, imam_phone = :imam_phone, muazzin_name = :muazzin_name, muazzin_phone = :muazzin_phone, imam = :imam, phone = :phone WHERE id = :id');
$changed = 0;
$pdo->beginTransaction();
foreach ($rows as $row) {
    [$khatibName, $khatibPhone] = split_person_name_phone($row['khatib_name'] ?? '', $row['khatib_phone'] ?? '');
    [$imamName, $imamPhone] = split_person_name_phone($row['imam_name'] ?? '', $row['imam_phone'] ?? '');
    [$muazzinName, $muazzinPhone] = split_person_name_phone($row['muazzin_name'] ?? '', $row['muazzin_phone'] ?? '');

    if (
        $khatibName !== (string) ($row['khatib_name'] ?? '') ||
        $khatibPhone !== (string) ($row['khatib_phone'] ?? '') ||
        $imamName !== (string) ($row['imam_name'] ?? '') ||
        $imamPhone !== (string) ($row['imam_phone'] ?? '') ||
        $muazzinName !== (string) ($row['muazzin_name'] ?? '') ||
        $muazzinPhone !== (string) ($row['muazzin_phone'] ?? '')
    ) {
        $update->execute([
            ':id' => (int) $row['id'],
            ':khatib_name' => $khatibName,
            ':khatib_phone' => $khatibPhone,
            ':imam_name' => $imamName,
            ':imam_phone' => $imamPhone,
            ':muazzin_name' => $muazzinName,
            ':muazzin_phone' => $muazzinPhone,
            ':imam' => $imamName,
            ':phone' => $imamPhone,
        ]);
        $changed++;
    }
}
$pdo->commit();
echo json_encode(['changed' => $changed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
