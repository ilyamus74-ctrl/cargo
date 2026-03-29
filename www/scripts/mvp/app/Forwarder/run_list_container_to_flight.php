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
function forwarder_list_container_cli_kv(array $argv): array
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

function forwarder_list_container_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_list_container_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_list_container_normalize_base_url(string $rawBaseUrl): string
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


/** @param array<string,mixed> $payload
 *  @return array<int,array<string,mixed>> */
function forwarder_list_container_extract_rows(array $payload): array
{
    $containers = $payload['containers'] ?? null;
    if (is_array($containers)) {
        $rows = [];
        foreach ($containers as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $rows = forwarder_sync_kernel_extract_containers_rows($payload);
    if (count($rows) === 1 && isset($rows[0]) && is_array($rows[0]) && array_is_list($rows[0])) {
        $flattened = [];
        foreach ($rows[0] as $nestedRow) {
            if (is_array($nestedRow)) {
                $flattened[] = $nestedRow;
            }
        }
        if ($flattened !== []) {
            return $flattened;
        }
    }

    return $rows;
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_list_container_cli_kv($argv);

$normalizedBaseUrl = forwarder_list_container_normalize_base_url(forwarder_list_container_arg($args, 'base-url', 'base_url'));

forwarder_list_container_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_list_container_set_env('DEV_COLIBRI_LOGIN', forwarder_list_container_arg($args, 'login'));
forwarder_list_container_set_env('DEV_COLIBRI_PASSWORD', forwarder_list_container_arg($args, 'password'));
forwarder_list_container_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_list_container_set_env('FORWARDER_LOGIN', forwarder_list_container_arg($args, 'login'));
forwarder_list_container_set_env('FORWARDER_PASSWORD', forwarder_list_container_arg($args, 'password'));
forwarder_list_container_set_env('FORWARDER_SESSION_FILE', forwarder_list_container_arg($args, 'session-file', 'session_file'));
forwarder_list_container_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_list_container_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$flightId = forwarder_list_container_arg($args, 'flight-id', 'flight_id', 'target-flight-id', 'target_flight_id', 'external_id');
$pagePath = forwarder_list_container_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/containers';

if ($flightId === '') {
    fwrite(STDERR, "run_list_container_to_flight: missing required --flight-id/--flight_id\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_list_container_to_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-list-containers-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_list_container_to_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$pageHtml = (string)($pageResponse['body'] ?? '');
$csrfToken = forwarder_sync_kernel_extract_csrf_token($pageHtml);
if ($csrfToken === '') {
    fwrite(STDERR, "run_list_container_to_flight: csrf token not found on page\n");
    exit(5);
}

$containersPath = '/collector/get-containers';
$listPayload = [
    'flight_id' => $flightId,
    '_token' => $csrfToken,
];

$listResponse = $sessionClient->requestWithSession('GET', $containersPath, $listPayload, false);
$listStatusCode = (int)($listResponse['status_code'] ?? 0);
if (empty($listResponse['ok']) || $listStatusCode < 200 || $listStatusCode >= 400) {
    $error = trim((string)($listResponse['error'] ?? 'containers_list_request_failed'));
    fwrite(STDERR, 'run_list_container_to_flight: get-containers failed: status=' . $listStatusCode . ' error=' . $error . "\n");
    exit(6);
}

$body = (string)($listResponse['body'] ?? '');
$payload = json_decode($body, true);
$parseError = '';
$rows = [];
if (is_array($payload)) {
    $rows = forwarder_list_container_extract_rows($payload);
} else {
    $parseError = 'json_decode_failed';
}

$result = [
    'status' => $parseError === '' ? 'ok' : 'warning',
    'message' => $parseError === ''
        ? 'Containers list fetched via PHP session client'
        : 'Containers list fetched but payload could not be parsed as JSON',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'containers_path' => $containersPath,
    'flight_id' => $flightId,
    'http_status' => $listStatusCode,
    'error' => (string)($listResponse['error'] ?? ''),
    'csrf_token_found' => $csrfToken !== '',
    'parse_error' => $parseError,
    'response_case' => is_array($payload) ? (string)($payload['case'] ?? '') : '',
    'response_title' => is_array($payload) ? (string)($payload['title'] ?? '') : '',
    'count' => count($rows),
    'containers' => $rows,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($parseError === '' ? 0 : 7);