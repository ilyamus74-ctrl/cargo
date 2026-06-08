<?php

declare(strict_types=1);

use App\Forwarder\Config\ConnectorConfigRepository;
use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\ClientLookupService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/connector_config_loader.php';

function camex_az_client_lookup_usage(): string
{
    return <<<'TXT'
Usage:
  php run_lookup_client.php --connector-id=3 --client-id=155155 [options]

Options:
  --connector-id=ID             Load active connector by connectors.id.
  --connector-name=NAME         Load active connector by connectors.name.
  --connector-key=NAME          Load active connector by connectors.name.
  --client-id=ID                CAMEX client id (C155155, AS155155, or 155155 accepted).
  --receiver-address=TEXT       Receiver address/cell text to extract CAMEX client id from.
  --write-cache=0|1             Write successful lookup to connector_clients (default: 0).
  --dry-run=0|1                 Do not write DB cache even when --write-cache=1 (default: 0).
  --debug-dir=DIR               Optional directory for debug HTML snapshots.
  --timeout-sec=N               HTTP timeout in seconds (default: 5).
  --help                        Show this help.
TXT;
}

/** @param array<string, mixed> $payload */
function camex_az_client_lookup_json(array $payload, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(camex_az_client_lookup_mask($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($exitCode);
}

/** @param array<string, mixed> $value */
function camex_az_client_lookup_mask(array $value): array
{
    $secretKeys = ['auth-password', 'http-auth-password', 'password', 'auth_password', 'http_auth_password', 'cookies', 'auth_cookies', 'set-cookie', 'authorization', 'cookie', 'web_password'];
    foreach ($value as $key => $item) {
        $normalized = strtolower(str_replace('_', '-', (string)$key));
        if (in_array($normalized, $secretKeys, true)) {
            $value[$key] = '***';
            continue;
        }
        if (is_array($item)) {
            $value[$key] = camex_az_client_lookup_mask($item);
        }
    }

    return $value;
}

function camex_az_client_lookup_bool_arg(array $args, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $args)) {
        return $default;
    }

    return in_array(strtolower(trim((string)$args[$key])), ['1', 'true', 'yes', 'on'], true);
}

$argv = $_SERVER['argv'] ?? [];
$args = ConnectorConfigRepository::cliArgs($argv);
if (isset($args['help']) || isset($args['h'])) {
    fwrite(STDOUT, camex_az_client_lookup_usage() . PHP_EOL);
    exit(0);
}

$clientId = ClientLookupService::extractNumericClientId(
    (string)($args['client-id'] ?? $args['client_id'] ?? ''),
    (string)($args['receiver-address'] ?? $args['receiver_address'] ?? '')
);
if ($clientId === '') {
    camex_az_client_lookup_json([
        'status' => 'no_client_id',
        'message' => 'No numeric client id found',
    ], 0);
}

if (isset($args['timeout-sec']) && !isset($args['timeout'])) {
    $args['timeout'] = (string)$args['timeout-sec'];
}
if (isset($args['timeout_sec']) && !isset($args['timeout'])) {
    $args['timeout'] = (string)$args['timeout_sec'];
}
if (!isset($args['timeout'])) {
    $args['timeout'] = '5';
}

$repoRoot = dirname(__DIR__, 5);

try {
    $connectorRow = ConnectorConfigRepository::loadRow($args, $repoRoot);
    $connectorDiagnostics = ConnectorConfigRepository::diagnostics($connectorRow);
    $overrides = ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
} catch (Throwable $e) {
    camex_az_client_lookup_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'stage' => 'connector_lookup',
        'message' => $e->getMessage(),
        'client_id' => $clientId,
    ], 1);
}

$connectorId = (int)($connectorRow['id'] ?? ($args['connector-id'] ?? $args['connector_id'] ?? 0));
$writeCache = camex_az_client_lookup_bool_arg($args, 'write-cache', false) || camex_az_client_lookup_bool_arg($args, 'write_cache', false);
$dryRun = camex_az_client_lookup_bool_arg($args, 'dry-run', false) || camex_az_client_lookup_bool_arg($args, 'dry_run', false);
$debugDir = trim((string)($args['debug-dir'] ?? $args['debug_dir'] ?? ''));

$config = new ForwarderConfig($overrides);
$connectorDiagnostics['base_url'] = $config->baseUrl();
$connectorDiagnostics['http_auth_enabled'] = $config->httpAuthEnabled();
$connectorDiagnostics['http_auth_type'] = $config->httpAuthType();

if ($connectorId <= 0) {
    camex_az_client_lookup_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'stage' => 'connector_lookup', 'message' => 'Missing required --connector-id/--connector-name/--connector-key.', 'client_id' => $clientId], 2);
}
if ($config->baseUrl() === '') {
    camex_az_client_lookup_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.base_url.', 'client_id' => $clientId], 2);
}
if ($config->webLogin() === '' || $config->webPassword() === '') {
    camex_az_client_lookup_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.auth_username/auth_password.', 'client_id' => $clientId], 2);
}
if ($config->httpAuthEnabled() && ($config->httpAuthLogin() === '' || $config->httpAuthPassword() === '')) {
    camex_az_client_lookup_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing HTTP auth username/password for enabled HTTP auth.', 'client_id' => $clientId], 2);
}

$correlationId = 'run-client-lookup-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$sessionClient = new CamexSessionClient($config, new ForwarderHttpClient($config), new SessionManager(), $logger);
$service = new ClientLookupService($repoRoot, $sessionClient);

try {
    $result = $service->lookup([
        'connector_id' => $connectorId,
        'client_id' => $clientId,
        'receiver_address' => (string)($args['receiver-address'] ?? $args['receiver_address'] ?? ''),
        'write_cache' => $writeCache,
        'dry_run' => $dryRun,
        'debug_dir' => $debugDir,
    ]);
} catch (Throwable $e) {
    camex_az_client_lookup_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'connector_id' => $connectorId,
        'stage' => 'db_write',
        'message' => $e->getMessage(),
        'client_id' => $clientId,
    ], 1);
}

$result['correlation_id'] = $correlationId;
$result['connector_config'] = $connectorDiagnostics;

camex_az_client_lookup_json($result, (string)($result['status'] ?? 'error') === 'error' ? 1 : 0);

