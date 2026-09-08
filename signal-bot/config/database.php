<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'driver' => 'mysql',
    'host' => Env::string('DB_HOST', '127.0.0.1'),
    'port' => Env::int('DB_PORT', 3306),
    'database' => Env::string('DB_DATABASE', 'signal_bot'),
    'username' => Env::string('DB_USERNAME', 'signal_bot'),
    'password' => Env::string('DB_PASSWORD', ''),
    'charset' => Env::string('DB_CHARSET', 'utf8mb4'),
];
