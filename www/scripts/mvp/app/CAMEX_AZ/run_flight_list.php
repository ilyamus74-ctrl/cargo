<?php

declare(strict_types=1);

use App\Forwarder\Config\ConnectorConfigRepository;
use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\FlightListService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/connector_config_loader.php';
require_once __DIR__ . '/flight_list_kernel.php';

function camex_az_flight_list_usage(): string
{
    return <<<'TXT'
Usage:
  php run_flight_list.php --connector-id=3 [options]

Options:
  --connector-id=ID          Load active connector by connectors.id.
  --connector-name=NAME      Load active connector by connectors.name.
  --connector-key=NAME       Load active connector by connectors.name.
  --target-table=TABLE       Target operation flight_list table (default: connector_camex_az_operation_flight_list).
  --page-path=PATH           Flight page path (default: /cadmin/usa/index.php?do=flight).
  --debug-dir=DIR            Optional directory for debug HTML snapshots.
  --dry-run=0|1              Parse without DB writes (default: 0).
  --limit=N                  Optional max rows to parse/write.
  --help                     Show this help.
TXT;
}

/** @param array<string, mixed> $payload */
function camex_az_flight_list_json(array $payload, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(camex_az_flight_list_mask($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($exitCode);
}

/** @param array<string, mixed> $value */
function camex_az_flight_list_mask(array $value): array
{
    $secretKeys = ['auth-password', 'http-auth-password', 'password', 'auth_password', 'http_auth_password', 'cookies', 'auth_cookies', 'set-cookie', 'authorization', 'cookie', 'web_password'];
    foreach ($value as $key => $item) {
        $normalized = strtolower(str_replace('_', '-', (string)$key));
        if (in_array($normalized, $secretKeys, true)) {
            $value[$key] = '***';
            continue;
        }
        if (is_array($item)) {
            $value[$key] = camex_az_flight_list_mask($item);
        }
    }

    return $value;
}

function camex_az_flight_list_bool_arg(array $args, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $args)) {
        return $default;
    }

    return in_array(strtolower(trim((string)$args[$key])), ['1', 'true', 'yes', 'on'], true);
}

$argv = $_SERVER['argv'] ?? [];
$args = ConnectorConfigRepository::cliArgs($argv);
if (isset($args['help']) || isset($args['h'])) {
    fwrite(STDOUT, camex_az_flight_list_usage() . PHP_EOL);
    exit(0);
}

$repoRoot = dirname(__DIR__, 5);

try {
    $connectorRow = ConnectorConfigRepository::loadRow($args, $repoRoot);
    $connectorDiagnostics = ConnectorConfigRepository::diagnostics($connectorRow);
    $overrides = ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
} catch (Throwable $e) {
    camex_az_flight_list_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'stage' => 'connector_lookup',
        'message' => $e->getMessage(),
        'http_status' => 0,
    ], 1);
}

$connectorId = (int)($connectorRow['id'] ?? ($args['connector-id'] ?? $args['connector_id'] ?? 0));
$targetTable = trim((string)($args['target-table'] ?? $args['target_table'] ?? 'connector_camex_az_operation_flight_list'));
$pagePath = FlightListService::normalizePagePath((string)($args['page-path'] ?? $args['page_path'] ?? '/cadmin/usa/index.php?do=flight'));
$debugDir = trim((string)($args['debug-dir'] ?? $args['debug_dir'] ?? ''));
$dryRun = camex_az_flight_list_bool_arg($args, 'dry-run', false) || camex_az_flight_list_bool_arg($args, 'dry_run', false);
$limit = max(0, (int)($args['limit'] ?? 0));

$config = new ForwarderConfig($overrides);
$connectorDiagnostics['base_url'] = $config->baseUrl();
$connectorDiagnostics['http_auth_enabled'] = $config->httpAuthEnabled();
$connectorDiagnostics['http_auth_type'] = $config->httpAuthType();

if ($connectorId <= 0) {
    camex_az_flight_list_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'stage' => 'connector_lookup', 'message' => 'Missing required --connector-id/--connector-name/--connector-key.', 'http_status' => 0], 2);
}
if ($config->baseUrl() === '') {
    camex_az_flight_list_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.base_url.', 'http_status' => 0], 2);
}
if ($config->webLogin() === '' || $config->webPassword() === '') {
    camex_az_flight_list_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.auth_username/auth_password.', 'http_status' => 0], 2);
}
if ($config->httpAuthEnabled() && ($config->httpAuthLogin() === '' || $config->httpAuthPassword() === '')) {
    camex_az_flight_list_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing HTTP auth username/password for enabled HTTP auth.', 'http_status' => 0], 2);
}

$correlationId = 'run-flight-list-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$sessionClient = new CamexSessionClient($config, new ForwarderHttpClient($config), new SessionManager(), $logger);
$service = new FlightListService($repoRoot, $sessionClient);

try {
    $result = $service->sync([
        'connector_id' => $connectorId,
        'target_table' => $targetTable,
        'page_path' => $pagePath,
        'debug_dir' => $debugDir,
        'dry_run' => $dryRun,
        'limit' => $limit,
    ]);
} catch (Throwable $e) {
    camex_az_flight_list_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'connector_id' => $connectorId,
        'target_table' => $targetTable,
        'page_path' => $pagePath,
        'stage' => 'flight_list_sync',
        'message' => $e->getMessage(),
        'http_status' => 0,
    ], 1);
}

$result['correlation_id'] = $correlationId;
$result['connector_config'] = $connectorDiagnostics;

camex_az_flight_list_json($result, (string)($result['status'] ?? 'error') === 'ok' ? 0 : 1);
