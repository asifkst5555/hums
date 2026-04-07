<?php

declare(strict_types=1);

function default_db_host(): string
{
    $envHost = getenv('DB_HOST');
    if (is_string($envHost) && trim($envHost) !== '') {
        return trim($envHost);
    }

    $procVersion = @file_get_contents('/proc/version');
    $isWsl = is_string($procVersion) && stripos($procVersion, 'microsoft') !== false;

    if (PHP_OS_FAMILY === 'Linux' && $isWsl) {
        $routeOutput = @shell_exec('ip route 2>/dev/null');
        if (is_string($routeOutput) && preg_match('/^default via\s+([0-9.]+)\s+/m', $routeOutput, $matches)) {
            return $matches[1];
        }

        $resolvConf = @file_get_contents('/etc/resolv.conf');
        if (is_string($resolvConf) && preg_match('/^nameserver\s+([0-9.]+)$/m', $resolvConf, $matches)) {
            return $matches[1];
        }
    }

    return '127.0.0.1';
}

return [
    'db_host' => default_db_host(),
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_name' => getenv('DB_NAME') ?: 'hums',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
];

