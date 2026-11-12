<?php
// Standalone, read-only verification. Never boots Laravel or switches the active .env.
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

function finish(array $result, int $status): never
{
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    exit($status);
}

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    finish(['ok' => false, 'code' => 'pdo_pgsql_unavailable'], 2);
}
try {
    $config = Dotenv\Dotenv::createArrayBacked(dirname(__DIR__), '.env.staging')->load();
} catch (Throwable) {
    finish(['ok' => false, 'code' => 'staging_env_missing_or_invalid'], 2);
}
if (($config['APP_ENV'] ?? '') !== 'staging' || ($config['DB_CONNECTION'] ?? '') !== 'pgsql') {
    finish(['ok' => false, 'code' => 'explicit_staging_pgsql_required'], 2);
}
$missing = array_values(array_filter(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'],
    fn ($key) => !is_string($config[$key] ?? null) || trim($config[$key]) === ''
        || preg_match('/YOUR_|xxxx|PROJECT_REF|PROJECT-REF/i', $config[$key])));
if ($missing) finish(['ok' => false, 'code' => 'staging_not_configured', 'missing_fields' => $missing], 2);
$host = $config['DB_HOST'];
$database = $config['DB_DATABASE'];
$port = (string) ($config['DB_PORT'] ?? '5432');
if (!preg_match('/^(?:db\.[a-z0-9]+\.supabase\.co|[a-z0-9.-]+\.pooler\.supabase\.com)$/D', $host)
    || !preg_match('/^[a-zA-Z0-9_]+$/D', $database) || !in_array($port, ['5432', '6543'], true)) {
    finish(['ok' => false, 'code' => 'invalid_supabase_connection_parameters'], 2);
}
$ssl = $config['DB_SSLMODE'] ?? 'require';
if (!in_array($ssl, ['require', 'verify-ca', 'verify-full'], true)) {
    finish(['ok' => false, 'code' => 'encrypted_connection_required'], 2);
}
$emulate = filter_var($config['DB_EMULATE_PREPARES'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($port === '6543' && !$emulate) finish(['ok' => false, 'code' => 'transaction_pooler_requires_emulated_prepares'], 2);
$dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$ssl};connect_timeout=10";
$certificate = $config['DB_SSLROOTCERT'] ?? '';
if ($certificate !== '') {
    if (strpbrk($certificate, ";\r\n'") !== false || !is_file($certificate)) {
        finish(['ok' => false, 'code' => 'invalid_ssl_root_certificate'], 2);
    }
    $dsn .= ';sslrootcert='.$certificate;
}
$pdo = null;
try {
    $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate,
    ]);
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec("SET LOCAL statement_timeout = '10s'");
    $version = $pdo->query("SELECT current_setting('server_version_num')")->fetchColumn();
    $readonly = $pdo->query("SELECT current_setting('transaction_read_only')")->fetchColumn();
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach (['siswa', 'study_tracer', 'alumni_progress', 'users', 'migrations'] as $table) {
        $counts[$table] = in_array($table, $tables, true)
            ? (int) $pdo->query('SELECT count(*) FROM public."'.$table.'"')->fetchColumn() : null;
    }
    $pdo->rollBack();
    finish(['ok' => true, 'scope' => 'staging_read_only', 'driver' => 'pgsql',
        'server_version_num' => $version, 'transaction_read_only' => $readonly,
        'target_fingerprint' => substr(hash('sha256', $host.':'.$port.'/'.$database.'/'.$config['DB_USERNAME']), 0, 16),
        'public_table_count' => count($tables), 'table_rows' => $counts,
        'next_step' => 'Review target and migration/data-transfer plan before changing active .env.'], 0);
} catch (Throwable) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    // PDO messages may contain hosts, usernames or connection strings. Do not emit them.
    finish(['ok' => false, 'code' => 'staging_connection_or_read_check_failed',
        'hint' => 'Check Supabase Connect parameters, password, project availability, TLS certificate and network.'], 1);
}
