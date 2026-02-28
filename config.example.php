<?php

declare(strict_types=1);

return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_name' => getenv('DB_NAME') ?: 'hums',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
];

