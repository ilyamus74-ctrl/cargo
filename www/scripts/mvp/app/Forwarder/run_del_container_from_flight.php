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
function forwarder_del_container_read_cli_kv(array $argv): array
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

function forwarder_del_container_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_del_container_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_del_container_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_del_container_extract_csrf_token(string $html): string
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return '';
    }

    $xpath = new DOMXPath($dom);
    $metaToken = $xpath->query('//meta[@name="csrf-token"]')->item(0);
    if ($metaToken instanceof DOMElement) {
        $value = trim((string)$metaToken->getAttribute('content'));
        if ($value !== '') {
            return $value;
        }
    }

    $inputToken = $xpath->query('//input[@name="_token"]')->item(0);
    if ($inputToken instanceof DOMElement) {
        $value = trim((string)$inputToken->getAttribute('value'));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_del_container_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_del_container_normalize_base_url(
    forwarder_del_container_arg($args, 'base-url', 'base_url')
);

forwarder_del_container_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_del_container_set_env('DEV_COLIBRI_LOGIN', forwarder_del_container_arg($args, 'login'));
forwarder_del_container_set_env('DEV_COLIBRI_PASSWORD', forwarder_del_container_arg($args, 'password'));
forwarder_del_container_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_del_container_set_env('FORWARDER_LOGIN', forwarder_del_container_arg($args, 'login'));
forwarder_del_container_set_env('FORWARDER_PASSWORD', forwarder_del_container_arg($args, 'password'));
forwarder_del_container_set_env('FORWARDER_SESSION_FILE', forwarder_del_container_arg($args, 'session-file', 'session_file'));
forwarder_del_container_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_del_container_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_del_container_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/containers';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/containers';
}

$deletePath = forwarder_del_container_arg($args, 'delete-path', 'delete_path', 'url');
$deletePath = $deletePath !== '' ? $deletePath : '/collector/containers/delete';
if (!str_starts_with($deletePath, '/')) {
    $deletePath = '/collector/containers/delete';
}

$containerId = forwarder_del_container_arg($args, 'container-id', 'container_id', 'target-container-id', 'target_container_id', 'id');
$flightId = forwarder_del_container_arg($args, 'flight-id', 'flight_id', 'target-flight-id', 'target_flight_id', 'external_id');
$connectorId = (int)forwarder_del_container_arg($args, 'connector-id', 'connector_id');
$flightTable = forwarder_del_container_arg($args, 'target-table', 'target_table', 'flight-table', 'flight_table');
$containersTable = forwarder_del_container_arg($args, 'containers-table', 'containers_table');

if ($containerId === '') {
    fwrite(STDERR, "run_del_container_from_flight: missing required --container-id/--container_id/--id\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_del_container_from_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-del-container-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_del_container_from_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$pageHtml = (string)($pageResponse['body'] ?? '');
$csrfToken = forwarder_del_container_extract_csrf_token($pageHtml);
if ($csrfToken === '') {
    fwrite(STDERR, "run_del_container_from_flight: csrf token not found on page\n");
    exit(5);
}

$deleteMethod = 'DELETE';
$payload = [
    'id' => $containerId,
    '_token' => $csrfToken,
];

$submitResponse = $sessionClient->requestWithSession($deleteMethod, $deletePath, $payload, false);
$submitStatusCode = (int)($submitResponse['status_code'] ?? 0);
$submitOk = !empty($submitResponse['ok']) && $submitStatusCode >= 200 && $submitStatusCode < 400;
$submitBody = (string)($submitResponse['body'] ?? '');
$submitJson = json_decode($submitBody, true);
$submitCase = is_array($submitJson) ? trim((string)($submitJson['case'] ?? '')) : '';
$submitCaseNormalized = mb_strtolower($submitCase);
$submitCaseOk = $submitCaseNormalized === '' || in_array($submitCaseNormalized, ['success', 'warning', 'ok'], true);
$submitSuccessRaw = is_array($submitJson) ? ($submitJson['success'] ?? null) : null;
$submitSuccessOk = $submitSuccessRaw === null
    || $submitSuccessRaw === true
    || (is_string($submitSuccessRaw) && in_array(mb_strtolower(trim($submitSuccessRaw)), ['1', 'true', 'success', 'ok'], true));
$overallOk = $submitOk && $submitCaseOk && $submitSuccessOk;
$syncResult = [
    'status' => 'skipped',
    'message' => 'sync disabled: connector_id/flight_id is not provided',
    'written' => 0,
    'fetched' => 0,
    'deactivated' => 0,
];
if ($overallOk && $connectorId > 0 && $flightId !== '') {
    $syncResult = forwarder_sync_flight_containers_kernel([
        'repo_root' => dirname(__DIR__, 5),
        'session_client' => $sessionClient,
        'connector_id' => $connectorId,
        'flight_id' => $flightId,
        'flight_table' => $flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list',
        'containers_table' => $containersTable,
        'page_path' => $pagePath,
        'csrf_token' => $csrfToken,
        'allow_empty_result_deactivate' => true,
    ]);
}

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Container delete submitted via PHP session client'
        : 'Container delete request failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'csrf_token_found' => $csrfToken !== '',
    'delete_path' => $deletePath,
    'delete_method' => $deleteMethod,
    'container_id' => $containerId,
    'flight_id' => $flightId,
    'submit_case' => $submitCase,
    'submit_success' => $submitSuccessRaw,
    'http_status' => $submitStatusCode,
    'error' => (string)($submitResponse['error'] ?? ''),
    'sync_db_status' => (string)($syncResult['status'] ?? 'skipped'),
    'sync_db_message' => (string)($syncResult['message'] ?? ''),
    'sync_db_written' => (int)($syncResult['written'] ?? 0),
    'sync_db_fetched' => (int)($syncResult['fetched'] ?? 0),
    'sync_db_deactivated' => (int)($syncResult['deactivated'] ?? 0),
    'sync_db_flight_table' => (string)($syncResult['flight_table'] ?? ($flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list')),
    'sync_db_containers_table' => (string)($syncResult['containers_table'] ?? $containersTable),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 9);
