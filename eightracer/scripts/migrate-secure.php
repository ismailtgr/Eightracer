<?php
// Operator credentials arrive via stdin only and are never persisted in the web application.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $credentials = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
    if (! is_string($credentials['username'] ?? null) || ! is_string($credentials['password'] ?? null)) { throw new RuntimeException; }
    config(['database.connections.mysql.username' => $credentials['username'], 'database.connections.mysql.password' => $credentials['password']]);
    unset($credentials);
    Illuminate\Support\Facades\DB::purge('mysql');
    $input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'migrate']);
    $status = $kernel->handle($input, new Symfony\Component\Console\Output\ConsoleOutput);
    $kernel->terminate($input, $status);
    exit($status);
} catch (Throwable) {
    fwrite(STDERR, "Akun migrasi tidak dapat dipakai. Nilai kredensial tidak ditampilkan.\n");
    exit(1);
}