<?php

declare(strict_types=1);

use App\Forwarder\Config\ConnectorConfigRepository;
use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\PackagePrepareService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/connector_config_loader.php';

function camex_az_prepare_package_usage(): string
{
    return <<<'TXT'
Usage:
  php run_prepare_package.php --connector-id=3 --tracking=123123 [options]

Options:
  --connector-id=ID             Load active connector by connectors.id.
  --connector-name=NAME         Load active connector by connectors.name.
  --connector-key=NAME          Load active connector by connectors.name.
  --country-code=AZ             Country code (default: single value from connector.countries when available).
  --tracking=TEXT               Package tracking number (required).
  --client-id=ID                Optional CAMEX client id hint.
  --receiver-address=TEXT       Optional receiver address/cell hint.
  --flight-no=TEXT              Future submit reisi value.
  --box-code=TEXT               Future submit box code; resolved against dim_storage options.
  --box-id=ID                   Future submit dim_storage value.
  --weight=N                    Future submit p_wona value.
  --length=N                    Future submit X value.
  --width=N                     Future submit Y value.
  --height=N                    Future submit Z value.
  --invoice-price=N             Future submit shen value.
  --invoice-currency=CCY        Future submit invoice_ccy value.
  --shop=TEXT                   Future submit shop value.
  --item-count=N                Future submit item_count value.
  --package-type-id=ID          Future submit package_type_id value.
  --parfume=0|1                 Future submit storage checkbox.
  --comment=TEXT                Future submit comment value.
  --debug-dir=DIR               Optional directory for debug HTML snapshots.
  --dry-run=0|1                 Always dry-run in this version (default: 1).
  --allow-system-box=0|1        Allow BOX100 system box for diagnostics (default: 0).
  --help                        Show this help.
TXT;
}

/** @param array<string, mixed> $payload */
function camex_az_prepare_package_json(array $payload, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(camex_az_prepare_package_mask($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($exitCode);
}

/** @param array<string, mixed> $value */
function camex_az_prepare_package_mask(array $value): array
{
    $secretKeys = ['auth-password', 'http-auth-password', 'password', 'auth_password', 'http_auth_password', 'cookies', 'auth_cookies', 'set-cookie', 'authorization', 'cookie', 'web_password'];
    foreach ($value as $key => $item) {
        $normalized = strtolower(str_replace('_', '-', (string)$key));
        if (in_array($normalized, $secretKeys, true)) {
            $value[$key] = '***';
            continue;
        }
        if (is_array($item)) {
            $value[$key] = camex_az_prepare_package_mask($item);
        }
    }

    return $value;
}

function camex_az_prepare_package_arg(array $args, string $dashKey, string $underscoreKey = ''): string
{
    $underscoreKey = $underscoreKey !== '' ? $underscoreKey : str_replace('-', '_', $dashKey);
    return trim((string)($args[$dashKey] ?? $args[$underscoreKey] ?? ''));
}

function camex_az_prepare_package_has_arg(array $args, string $dashKey, string $underscoreKey = ''): bool
{
    $underscoreKey = $underscoreKey !== '' ? $underscoreKey : str_replace('-', '_', $dashKey);
    return array_key_exists($dashKey, $args) || array_key_exists($underscoreKey, $args);
}

function camex_az_prepare_package_raw_arg(array $args, string $dashKey, string $underscoreKey = ''): string
{
    $underscoreKey = $underscoreKey !== '' ? $underscoreKey : str_replace('-', '_', $dashKey);
    if (array_key_exists($dashKey, $args)) {
        return (string)$args[$dashKey];
    }
    if (array_key_exists($underscoreKey, $args)) {
        return (string)$args[$underscoreKey];
    }

    return '';
}

function camex_az_prepare_package_country_code(array $args, ?array $connectorRow): string
{
    $countryCode = camex_az_prepare_package_arg($args, 'country-code');
    if ($countryCode !== '') {
        return strtoupper($countryCode);
    }

    $countries = trim((string)($connectorRow['countries'] ?? ''));
    if ($countries === '') {
        return '';
    }
    $decoded = json_decode($countries, true);
    if (is_array($decoded) && count($decoded) === 1) {
        return strtoupper(trim((string)reset($decoded)));
    }
    $parts = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $countries) ?: []), static fn (string $value): bool => $value !== ''));
    return count($parts) === 1 ? strtoupper($parts[0]) : '';
}

$argv = $_SERVER['argv'] ?? [];
$args = ConnectorConfigRepository::cliArgs($argv);
if (isset($args['help']) || isset($args['h'])) {
    fwrite(STDOUT, camex_az_prepare_package_usage() . PHP_EOL);
    exit(0);
}

$tracking = camex_az_prepare_package_arg($args, 'tracking');
if ($tracking === '') {
    camex_az_prepare_package_json([
        'status' => 'error',
        'stage' => 'validation',
        'message' => 'Missing required --tracking.',
        'tracking' => '',
    ], 2);
}

if (isset($args['timeout-sec']) && !isset($args['timeout'])) {
    $args['timeout'] = (string)$args['timeout-sec'];
}
if (isset($args['timeout_sec']) && !isset($args['timeout'])) {
    $args['timeout'] = (string)$args['timeout_sec'];
}

$repoRoot = dirname(__DIR__, 5);
try {
    $connectorRow = ConnectorConfigRepository::loadRow($args, $repoRoot);
    $overrides = ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
} catch (Throwable $e) {
    camex_az_prepare_package_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'stage' => 'connector_lookup',
        'message' => $e->getMessage(),
        'tracking' => $tracking,
    ], 1);
}

$connectorId = (int)($connectorRow['id'] ?? ($args['connector-id'] ?? $args['connector_id'] ?? 0));
$config = new ForwarderConfig($overrides);
if ($connectorId <= 0) {
    camex_az_prepare_package_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'stage' => 'connector_lookup', 'message' => 'Missing required --connector-id/--connector-name/--connector-key.', 'tracking' => $tracking], 2);
}
if ($config->baseUrl() === '') {
    camex_az_prepare_package_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.base_url.', 'tracking' => $tracking], 2);
}
if ($config->webLogin() === '' || $config->webPassword() === '') {
    camex_az_prepare_package_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing connectors.auth_username/auth_password.', 'tracking' => $tracking], 2);
}
if ($config->httpAuthEnabled() && ($config->httpAuthLogin() === '' || $config->httpAuthPassword() === '')) {
    camex_az_prepare_package_json(['status' => 'error', 'connector' => 'CAMEX_AZ', 'connector_id' => $connectorId, 'stage' => 'connector_lookup', 'message' => 'Missing HTTP auth username/password for enabled HTTP auth.', 'tracking' => $tracking], 2);
}

$logger = new ForwarderLogger('camex_az_prepare_' . bin2hex(random_bytes(4)));
$service = new PackagePrepareService(
    new CamexSessionClient($config, new ForwarderHttpClient($config), new SessionManager(), $logger),
    $logger
);

$options = [
    'connector_id' => $connectorId,
    'country_code' => camex_az_prepare_package_country_code($args, $connectorRow),
    'tracking' => $tracking,
    'prepare_mode' => camex_az_prepare_package_arg($args, 'prepare-mode') !== '' ? camex_az_prepare_package_arg($args, 'prepare-mode') : 'auto',
    'client_id' => camex_az_prepare_package_arg($args, 'client-id'),
    'receiver_address' => camex_az_prepare_package_arg($args, 'receiver-address'),
    'flight_no' => camex_az_prepare_package_arg($args, 'flight-no'),
    'box_code' => camex_az_prepare_package_arg($args, 'box-code'),
    'box_id' => camex_az_prepare_package_arg($args, 'box-id'),
    'weight' => camex_az_prepare_package_arg($args, 'weight') !== '' ? camex_az_prepare_package_arg($args, 'weight') : '1.000',
    'length' => camex_az_prepare_package_arg($args, 'length') !== '' ? camex_az_prepare_package_arg($args, 'length') : '0',
    'width' => camex_az_prepare_package_arg($args, 'width') !== '' ? camex_az_prepare_package_arg($args, 'width') : '0',
    'height' => camex_az_prepare_package_arg($args, 'height') !== '' ? camex_az_prepare_package_arg($args, 'height') : '0',
    'invoice_price' => camex_az_prepare_package_has_arg($args, 'invoice-price') ? camex_az_prepare_package_raw_arg($args, 'invoice-price') : '',
    'invoice_currency' => camex_az_prepare_package_arg($args, 'invoice-currency') !== '' ? camex_az_prepare_package_arg($args, 'invoice-currency') : 'EUR',
    'shop' => camex_az_prepare_package_arg($args, 'shop') !== '' ? camex_az_prepare_package_arg($args, 'shop') : 'amazon.de',
    'item_count' => camex_az_prepare_package_has_arg($args, 'item-count') ? camex_az_prepare_package_raw_arg($args, 'item-count') : '',
    'package_type_id' => camex_az_prepare_package_arg($args, 'package-type-id') !== '' ? camex_az_prepare_package_arg($args, 'package-type-id') : '0',
    'parfume' => camex_az_prepare_package_arg($args, 'parfume') !== '' ? camex_az_prepare_package_arg($args, 'parfume') : '0',
    'comment' => camex_az_prepare_package_has_arg($args, 'comment') ? camex_az_prepare_package_raw_arg($args, 'comment') : '',
    'debug_dir' => camex_az_prepare_package_arg($args, 'debug-dir'),
    'allow_system_box' => camex_az_prepare_package_arg($args, 'allow-system-box') !== '' ? camex_az_prepare_package_arg($args, 'allow-system-box') : '0',
    'dry_run' => '1',
    'page_path' => '/cadmin/usa/index.php?do=newaddpre',
];

$result = $service->prepare($options);
if (($result['status'] ?? '') === 'error') {
    camex_az_prepare_package_json($result, 1);
}

camex_az_prepare_package_json($result, 0);
