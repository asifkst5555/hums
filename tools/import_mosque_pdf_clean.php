<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

$path = __DIR__ . '/../tmp/mosque_pdf_rows_clean.json';
if (!is_file($path)) {
    fwrite(STDERR, "Missing cleaned JSON: {$path}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($path), true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid cleaned JSON\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->exec('DELETE FROM mosques');

    $sql = 'INSERT INTO mosques (
        name, union_name, ward_no, addr, mosque_type,
        khatib_name, khatib_phone, imam_name, imam_phone,
        muazzin_name, muazzin_phone, madrasa_present, madrasa_name,
        imam, phone
    ) VALUES (
        :name, :union_name, :ward_no, :addr, :mosque_type,
        :khatib_name, :khatib_phone, :imam_name, :imam_phone,
        :muazzin_name, :muazzin_phone, :madrasa_present, :madrasa_name,
        :imam, :phone
    )';

    $stmt = $pdo->prepare($sql);
    $inserted = 0;

    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $imamName = trim((string) ($row['imamName'] ?? ''));
        $imamPhone = trim((string) ($row['imamPhone'] ?? ''));
        $madrasaPresent = trim((string) ($row['madrasaPresent'] ?? 'no'));
        if (!in_array($madrasaPresent, ['yes', 'no'], true)) {
            $madrasaPresent = 'no';
        }

        $stmt->execute([
            ':name' => $name,
            ':union_name' => trim((string) ($row['union'] ?? '')),
            ':ward_no' => trim((string) ($row['wardNo'] ?? '')),
            ':addr' => trim((string) ($row['addr'] ?? '')),
            ':mosque_type' => trim((string) ($row['mosqueType'] ?? '')),
            ':khatib_name' => trim((string) ($row['khatibName'] ?? '')),
            ':khatib_phone' => trim((string) ($row['khatibPhone'] ?? '')),
            ':imam_name' => $imamName,
            ':imam_phone' => $imamPhone,
            ':muazzin_name' => trim((string) ($row['muazzinName'] ?? '')),
            ':muazzin_phone' => trim((string) ($row['muazzinPhone'] ?? '')),
            ':madrasa_present' => $madrasaPresent,
            ':madrasa_name' => trim((string) ($row['madrasaName'] ?? '')),
            ':imam' => $imamName,
            ':phone' => $imamPhone,
        ]);
        $inserted++;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM mosques')->fetchColumn();
    $pdo->commit();
    fwrite(STDOUT, json_encode(['inserted' => $inserted, 'count' => $count], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
