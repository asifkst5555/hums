<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['db_host'],
        $cfg['db_port'],
        $cfg['db_name']
    );

    try {
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        json_error(
            'Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, and make sure MySQL is running.',
            500
        );
    }

    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status): void
{
    json_response(['error' => $message], $status);
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_error('Invalid JSON payload', 400);
    }

    return $decoded;
}

function require_method(string ...$methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        json_error('Method not allowed', 405);
    }
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        json_error('Unauthorized', 401);
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        json_error('Forbidden', 403);
    }
    return $user;
}

function normalize_phone_digits(?string $value): string
{
    $value = (string) ($value ?? '');
    $value = str_replace(["\u{221A}", "\u{F050}"], ' ', $value);
    $value = preg_replace_callback('/[\x{09E6}-\x{09EF}]/u', static function (array $m): string {
        return match ($m[0]) {
            "\u{09E6}" => '0',
            "\u{09E7}" => '1',
            "\u{09E8}" => '2',
            "\u{09E9}" => '3',
            "\u{09EA}" => '4',
            "\u{09EB}" => '5',
            "\u{09EC}" => '6',
            "\u{09ED}" => '7',
            "\u{09EE}" => '8',
            "\u{09EF}" => '9',
            default => $m[0],
        };
    }, $value) ?? $value;

    preg_match_all('/(?:88)?01\d{9}/', $value, $matches);
    $phones = [];
    foreach (($matches[0] ?? []) as $match) {
        $normalized = str_starts_with($match, '88') ? substr($match, 2) : $match;
        if ($normalized !== '' && !in_array($normalized, $phones, true)) {
            $phones[] = $normalized;
        }
    }

    if ($phones === []) {
        $digitsOnly = preg_replace('/\D+/', '', $value) ?? '';
        if ($digitsOnly !== '') {
            for ($i = 0, $len = strlen($digitsOnly) - 10; $i < $len; $i += 1) {
                $candidate = substr($digitsOnly, $i, 11);
                if (preg_match('/^01\d{9}$/', $candidate) && !in_array($candidate, $phones, true)) {
                    $phones[] = $candidate;
                }
            }
        }
    }

    return implode(', ', $phones);
}

function clean_person_name(?string $value): string
{
    $value = (string) ($value ?? '');
    $value = str_replace(["\u{221A}", "\u{F050}"], ' ', $value);
    $value = preg_replace('/(?:88)?01[0-9\x{09E6}-\x{09EF}]{9}/u', ' ', $value) ?? $value;
    $value = preg_replace('/[0-9\x{09E6}-\x{09EF}]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s*[-,]+\s*$/u', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value, " \t\n\r\0\x0B-.,");
}

function split_person_name_phone(?string $name, ?string $phone): array
{
    $combinedName = (string) ($name ?? '');
    $rawPhone = (string) ($phone ?? '');
    $mergedPhone = normalize_phone_digits($combinedName . ' ' . $rawPhone);
    $combinedPhone = normalize_phone_digits($rawPhone);
    $phoneFromName = normalize_phone_digits($combinedName);

    $phones = [];
    foreach (array_merge(
        $mergedPhone !== '' ? explode(', ', $mergedPhone) : [],
        $combinedPhone !== '' ? explode(', ', $combinedPhone) : [],
        $phoneFromName !== '' ? explode(', ', $phoneFromName) : []
    ) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '' && !in_array($candidate, $phones, true)) {
            $phones[] = $candidate;
        }
    }

    return [clean_person_name($combinedName), implode(', ', $phones)];
}

function int_param(string $name): int
{
    $value = $_GET[$name] ?? null;
    if ($value === null || !is_numeric($value)) {
        json_error("Missing or invalid parameter: {$name}", 400);
    }
    return (int) $value;
}

