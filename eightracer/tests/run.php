<?php

use PHPUnit\TextUI\Application;

// Run the backend suite against a disposable in-memory SQLite database only.
require __DIR__.'/../vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
exit((new Application)->run(['phpunit', '--no-configuration', '--do-not-cache-result', ...array_slice($argv, 1), __DIR__.'/Unit', __DIR__.'/Feature']));
