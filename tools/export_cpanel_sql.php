<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

$pdo = db();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($dbName) || $dbName === '') {
    fwrite(STDERR, "Could not determine current database\n");
    exit(1);
}

$outFile = __DIR__ . '/../hums_cpanel_export.sql';
$fh = fopen($outFile, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Could not open output file\n");
    exit(1);
}

fwrite($fh, "-- HUMS cPanel-friendly SQL export\n");
fwrite($fh, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($fh, "START TRANSACTION;\n");
fwrite($fh, "SET time_zone = \"+00:00\";\n");
fwrite($fh, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($fh, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
fwrite($fh, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
fwrite($fh, "/*!40101 SET NAMES utf8mb4 */;\n\n");

$tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
if (!is_array($tables)) {
    fwrite(STDERR, "Could not read table list\n");
    fclose($fh);
    exit(1);
}

foreach ($tables as $tableRow) {
    $table = (string) ($tableRow[0] ?? '');
    if ($table === '') {
        continue;
    }

    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    fwrite($fh, "-- --------------------------------------------------------\n");
    fwrite($fh, "-- Table structure for table {$quotedTable}\n");
    fwrite($fh, "-- --------------------------------------------------------\n\n");
    fwrite($fh, "DROP TABLE IF EXISTS {$quotedTable};\n");

    $createStmt = $pdo->query("SHOW CREATE TABLE {$quotedTable}");
    $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!is_array($createRow)) {
        fwrite(STDERR, "Could not read CREATE TABLE for {$table}\n");
        fclose($fh);
        exit(1);
    }
    $createSql = (string) ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '');
    fwrite($fh, $createSql . ";\n\n");

    $count = (int) $pdo->query("SELECT COUNT(*) FROM {$quotedTable}")->fetchColumn();
    if ($count === 0) {
        continue;
    }

    fwrite($fh, "-- Dumping data for table {$quotedTable}\n\n");
    $rowsStmt = $pdo->query("SELECT * FROM {$quotedTable}", PDO::FETCH_ASSOC);
    if (!$rowsStmt) {
        fwrite(STDERR, "Could not read rows for {$table}\n");
        fclose($fh);
        exit(1);
    }

    $firstRow = $rowsStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($firstRow)) {
        continue;
    }
    $columns = array_keys($firstRow);
    $quotedColumns = array_map(static fn(string $col): string => '`' . str_replace('`', '``', $col) . '`', $columns);
    $insertPrefix = "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ") VALUES\n";

    $buffer = [];
    $flush = static function ($handle, string $prefix, array $rows): void {
        if ($rows === []) {
            return;
        }
        fwrite($handle, $prefix . implode(",\n", $rows) . ";\n\n");
    };

    $encodeRow = static function (array $row) use ($pdo, $columns): string {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null) {
                $values[] = 'NULL';
                continue;
            }
            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }
            $values[] = $pdo->quote((string) $value);
        }
        return '(' . implode(', ', $values) . ')';
    };

    $buffer[] = $encodeRow($firstRow);
    while (($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $buffer[] = $encodeRow($row);
        if (count($buffer) >= 100) {
            $flush($fh, $insertPrefix, $buffer);
            $buffer = [];
        }
    }
    $flush($fh, $insertPrefix, $buffer);
}

fwrite($fh, "COMMIT;\n");
fwrite($fh, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
fwrite($fh, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
fwrite($fh, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");

fclose($fh);
echo json_encode([
    'database' => $dbName,
    'file' => realpath($outFile) ?: $outFile,
    'size' => filesize($outFile),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
