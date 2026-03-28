<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sync_kernel.php';

/** @return array<string, string> */
function forwarder_sync_cli_kv(array $argv): array
{
    $result = [];
    foreach ($argv as $arg) {
        if (!is_string($arg) || !str_starts_with($arg, '--')) {
            continue;
        }

        $eqPos = strpos($arg, '=');
        if ($eqPos === false) {
            $key = substr($arg, 2);
            if ($key !== '') {
                $result[$key] = '1';
            }
            continue;
        }

        $key = trim(substr($arg, 2, $eqPos - 2));
        if ($key === '') {
            continue;
        }

        $result[$key] = (string)substr($arg, $eqPos + 1);
    }

    return $result;
}

function forwarder_sync_arg(array $args, string $primaryKey, string ...$aliases): string
{
    $keys = array_merge([$primaryKey], $aliases);
    foreach ($keys as $key) {
        if (!array_key_exists($key, $args)) {
            continue;
        }

        return trim((string)$args[$key]);
    }

    return '';
}

function forwarder_sync_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_sync_normalize_base_url(string $rawBaseUrl): string
{
    $value = trim($rawBaseUrl);
    if ($value === '') {
        return '';
    }

    $parts = @parse_url($value);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return rtrim($value, '/');
    }

    $base = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
    if (isset($parts['port']) && (int)$parts['port'] > 0) {
        $base .= ':' . (int)$parts['port'];
    }

    return rtrim($base, '/');
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_sync_cli_kv($argv);

$normalizedBaseUrl = forwarder_sync_normalize_base_url(forwarder_sync_arg($args, 'base-url', 'base_url'));

forwarder_sync_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_sync_set_env('DEV_COLIBRI_LOGIN', forwarder_sync_arg($args, 'login'));
forwarder_sync_set_env('DEV_COLIBRI_PASSWORD', forwarder_sync_arg($args, 'password'));
forwarder_sync_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_sync_set_env('FORWARDER_LOGIN', forwarder_sync_arg($args, 'login'));
forwarder_sync_set_env('FORWARDER_PASSWORD', forwarder_sync_arg($args, 'password'));
forwarder_sync_set_env('FORWARDER_SESSION_FILE', forwarder_sync_arg($args, 'session-file', 'session_file'));
forwarder_sync_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_sync_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$flightId = forwarder_sync_arg($args, 'flight-id', 'flight_id', 'target-flight-id', 'target_flight_id', 'external_id');
$connectorId = (int)forwarder_sync_arg($args, 'connector-id', 'connector_id');
$flightTable = forwarder_sync_arg($args, 'target-table', 'target_table', 'flight-table', 'flight_table');
$containersTable = forwarder_sync_arg($args, 'containers-table', 'containers_table');
$pagePath = forwarder_sync_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/containers';

if ($flightId === '') {
    fwrite(STDERR, "run_sync_flight_containers: missing required --flight-id/--flight_id\n");
    exit(2);
}
if ($connectorId <= 0) {
    fwrite(STDERR, "run_sync_flight_containers: missing required --connector-id/--connector_id (>0)\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_sync_flight_containers: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-sync-containers-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$syncResult = forwarder_sync_flight_containers_kernel([
    'repo_root' => dirname(__DIR__, 5),
    'session_client' => $sessionClient,
    'connector_id' => $connectorId,
    'flight_id' => $flightId,
    'flight_table' => $flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list',
    'containers_table' => $containersTable,
    'page_path' => $pagePath,
]);

$result = [
    'status' => (string)($syncResult['status'] ?? 'error'),
    'message' => (string)($syncResult['message'] ?? ''),
    'correlation_id' => $correlationId,
    'flight_id' => $flightId,
    'connector_id' => $connectorId,
    'flight_table' => (string)($syncResult['flight_table'] ?? ($flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list')),
    'containers_table' => (string)($syncResult['containers_table'] ?? $containersTable),
    'written' => (int)($syncResult['written'] ?? 0),
    'fetched' => (int)($syncResult['fetched'] ?? 0),
    'deactivated' => (int)($syncResult['deactivated'] ?? 0),
    'flight_row_id' => (int)($syncResult['flight_row_id'] ?? 0),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit(((string)($syncResult['status'] ?? 'error')) === 'ok' ? 0 : 9);