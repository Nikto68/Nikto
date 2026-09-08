#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Application;
use App\Database\Database;
use App\Database\Migration;
use App\Logger\LoggerFactory;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

$migration = new Migration(
    $app->get(Database::class),
    $app->get(LoggerFactory::class)->channel('app'),
    dirname(__DIR__) . '/database/migrations',
);

$applied = $migration->run();

if ($applied === []) {
    fwrite(STDOUT, "Database schema already up to date.\n");
    exit(0);
}

fwrite(STDOUT, "Applied migrations:\n");
foreach ($applied as $name) {
    fwrite(STDOUT, "  - {$name}\n");
}
exit(0);
