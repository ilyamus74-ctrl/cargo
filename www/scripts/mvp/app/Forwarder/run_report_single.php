<?php

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/**
 * Single-package report entrypoint (without downloaded report file).
 *
 * Usage:
 *   php run_report_single.php --track=H1000844804054601044
 *   php run_report_single.php --base-url=https://dev-backend.colibri.az --login=... --password=... --track=...
 *   php run_report_single.php H1000844804054601044
 *   php run_report_single.php   (auth preflight mode)
 */

function forwarder_single_read_arg(string $name, array $argv): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            $value = substr($arg, strlen($name) + 3);
            return $value !== '' ? $value : null;
        }
    }

    return null;
}

/** @return array<string, string> */
function forwarder_single_cli_overrides(array $argv): array
{
    $map = [
        'base-url' => 'FORWARDER_BASE_URL',
        'login' => 'FORWARDER_LOGIN',
        'password' => 'FORWARDER_PASSWORD',
        'connector-id' => 'FORWARDER_CONNECTOR_ID',
    ];

    $overrides = [];
    foreach ($map as $argName => $envName) {
        $value = forwarder_single_read_arg($argName, $argv);
        if ($value === null) {
            continue;
        }
        $overrides[$envName] = trim($value);
    }

    return $overrides;
}

/** @param array<string, mixed> $payload */
function forwarder_single_find_package(array $payload, string $track): ?array
{
    $trackNorm = strtoupper(trim($track));

    $primary = $payload['package'] ?? null;
    if (is_array($primary)) {
        $candidate = strtoupper(trim((string)($primary['track'] ?? $primary['number'] ?? $primary['internal_id'] ?? '')));
        if ($candidate === $trackNorm) {
            return $primary;
        }
    }

    $packages = $payload['client_packages'] ?? [];
    if (is_array($packages)) {
        foreach ($packages as $item) {
            if (!is_array($item)) {
                continue;
            }
            $candidate = strtoupper(trim((string)($item['track'] ?? $item['number'] ?? $item['internal_id'] ?? '')));
            if ($candidate === $trackNorm) {
                return $item;
            }
        }
    }

    return is_array($primary) ? $primary : null;
}

/** @param array<string, mixed>|null $package */
function forwarder_single_package_summary(?array $package): array
{
    if (!is_array($package)) {
        return [];
    }

    return [
        'track' => $package['track'] ?? $package['number'] ?? null,
        'internal_id' => $package['internal_id'] ?? null,
        'status' => $package['status'] ?? null,
        'status_id' => $package['status_id'] ?? null,
        'position' => $package['position'] ?? null,
        'gross_weight' => $package['gross_weight'] ?? null,
        'volume_weight' => $package['volume_weight'] ?? null,
        'amount' => $package['amount'] ?? null,
        'currency' => $package['currency'] ?? null,
        'destination' => $package['destination'] ?? null,
        'client_name' => $package['client_name'] ?? null,
        'seller' => $package['seller'] ?? null,
    ];
}

$argv = $_SERVER['argv'] ?? [];
$overrides = forwarder_single_cli_overrides($argv);
foreach ($overrides as $envName => $value) {
    putenv($envName . '=' . $value);
    $_ENV[$envName] = $value;
    $_SERVER[$envName] = $value;
}

$track = forwarder_single_read_arg('track', $argv) ?? ($argv[1] ?? null) ?? getenv('FORWARDER_TRACK') ?: null;
$track = trim((string)$track);
$hasTrack = $track !== '';
$result = [];

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    if ($hasTrack) {
        fwrite(STDERR, "Forwarder run_report_single: missing env config (FORWARDER_BASE_URL/FORWARDER_LOGIN/FORWARDER_PASSWORD)\n");
        exit(3);
    }

    $result = [
        'status' => 'AUTH_SKIPPED',
        'message' => 'Forwarder auth preflight skipped: missing env config (FORWARDER_BASE_URL/FORWARDER_LOGIN/FORWARDER_PASSWORD)',
        'mode' => 'auth_preflight',
        'track' => null,
    ];
}

if ($result === [] && !$config->isFlowEnabled()) {
    fwrite(STDERR, "Forwarder run_report_single: flow disabled by FORWARDER_FLOW_ENABLED\n");
    exit(4);
}

$correlationId = 'run-report-single-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
if ($result !== []) {
    $result['correlation_id'] = $result['correlation_id'] ?? $correlationId;
}

if ($result === []) {
    $httpClient = new ForwarderHttpClient($config);
    $session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
    $loginService = new LoginService($config, $httpClient, $session, $logger);

    if ($hasTrack) {
        $sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);
        $containerService = new ContainerService($config, $sessionClient);
        $response = $containerService->checkPackageSingle($track)->toArray();

        $raw = $response['payload']['raw'] ?? [];
        $raw = is_array($raw) ? $raw : [];
        $exists = (bool)($raw['package_exist'] ?? false);
        $matchedPackage = forwarder_single_find_package($raw, $track);

        $result = [
            'status' => $response['ok'] ? ($exists ? 'ACCEPTED' : 'NOT_FOUND') : 'TEMP_ERROR',
            'mode' => 'single_package_check',
            'track' => $track,
            'package_exist' => $exists,
            'matched_track_found' => $matchedPackage !== null,
            'package_summary' => forwarder_single_package_summary($matchedPackage),
            'response_meta' => [
                'http_status' => (int)($response['status_code'] ?? 0),
                'latency_ms' => (int)($response['latency_ms'] ?? 0),
                'client_packages_count' => is_array($raw['client_packages'] ?? null) ? count($raw['client_packages']) : 0,
            ],
            'connector_id' => $overrides['FORWARDER_CONNECTOR_ID'] ?? getenv('FORWARDER_CONNECTOR_ID') ?: null,
            'correlation_id' => $correlationId,
        ];
    } else {
        $auth = $loginService->ensureAuthenticated()->toArray();
        $result = [
            'status' => !empty($auth['ok']) ? 'AUTH_OK' : 'SESSION_EXPIRED',
            'message' => !empty($auth['ok'])
                ? 'Forwarder auth preflight passed (track not provided)'
                : 'Forwarder auth preflight failed',
            'mode' => 'auth_preflight',
            'track' => null,
            'correlation_id' => $correlationId,
        ];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

$status = strtoupper(trim((string)($result['status'] ?? 'TEMP_ERROR')));
$okStatuses = ['ACCEPTED', 'NOT_FOUND', 'AUTH_OK', 'AUTH_SKIPPED'];
exit(in_array($status, $okStatuses, true) ? 0 : 1);
