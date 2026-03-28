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
function forwarder_add_container_read_cli_kv(array $argv): array
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

function forwarder_add_container_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_add_container_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_add_container_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_add_container_path_from_url(string $raw, string $defaultPath): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return $defaultPath;
    }

    if (str_starts_with($raw, '/')) {
        return $raw;
    }

    $parts = @parse_url($raw);
    if (!is_array($parts)) {
        return $defaultPath;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        $path = $defaultPath;
    }

    if (isset($parts['query']) && trim((string)$parts['query']) !== '') {
        $path .= '?' . (string)$parts['query'];
    }

    return str_starts_with($path, '/') ? $path : ('/' . ltrim($path, '/'));
}

/** @return array{ok:bool,action:string,method:string,payload_defaults:array<string,string>,error:string} */
function forwarder_add_container_extract_add_form(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();

    if (!$loaded) {
        return ['ok' => false, 'action' => '', 'method' => 'POST', 'payload_defaults' => [], 'error' => 'invalid_html'];
    }

    $xpath = new DOMXPath($dom);
    $formNode = $xpath->query('//form[@id="form" and (contains(@action,"/collector/containers/add") or contains(@action,"collector/containers/add"))]')->item(0);
    if (!($formNode instanceof DOMElement)) {
        $formNode = $xpath->query('//form[contains(@action,"collector/containers/add")]')->item(0);
    }
    if (!($formNode instanceof DOMElement)) {
        return ['ok' => false, 'action' => '', 'method' => 'POST', 'payload_defaults' => [], 'error' => 'add_form_not_found'];
    }

    $action = trim((string)$formNode->getAttribute('action'));
    $method = strtoupper(trim((string)$formNode->getAttribute('method')));
    if ($method === '') {
        $method = 'POST';
    }

    $payloadDefaults = [];

    foreach ($xpath->query('.//input[@name]', $formNode) as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }

        $name = trim((string)$node->getAttribute('name'));
        if ($name === '' || array_key_exists($name, $payloadDefaults)) {
            continue;
        }

        $type = strtolower(trim((string)$node->getAttribute('type')));
        if (in_array($type, ['submit', 'button', 'file'], true)) {
            continue;
        }

        $payloadDefaults[$name] = (string)$node->getAttribute('value');
    }

    foreach ($xpath->query('.//select[@name]', $formNode) as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }

        $name = trim((string)$node->getAttribute('name'));
        if ($name === '' || array_key_exists($name, $payloadDefaults)) {
            continue;
        }

        $selected = $xpath->query('.//option[@selected]', $node)->item(0);
        if (!($selected instanceof DOMElement)) {
            $selected = $xpath->query('.//option', $node)->item(0);
        }

        $payloadDefaults[$name] = $selected instanceof DOMElement
            ? (string)$selected->getAttribute('value')
            : '';
    }

    return [
        'ok' => true,
        'action' => $action,
        'method' => $method,
        'payload_defaults' => $payloadDefaults,
        'error' => '',
    ];
}

function forwarder_add_container_extract_csrf_token(string $html): string
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
$args = forwarder_add_container_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_add_container_normalize_base_url(
    forwarder_add_container_arg($args, 'base-url', 'base_url')
);

forwarder_add_container_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_add_container_set_env('DEV_COLIBRI_LOGIN', forwarder_add_container_arg($args, 'login'));
forwarder_add_container_set_env('DEV_COLIBRI_PASSWORD', forwarder_add_container_arg($args, 'password'));
forwarder_add_container_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_add_container_set_env('FORWARDER_LOGIN', forwarder_add_container_arg($args, 'login'));
forwarder_add_container_set_env('FORWARDER_PASSWORD', forwarder_add_container_arg($args, 'password'));
forwarder_add_container_set_env('FORWARDER_SESSION_FILE', forwarder_add_container_arg($args, 'session-file', 'session_file'));
forwarder_add_container_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_add_container_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_add_container_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/containers';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/containers';
}

$flightId = forwarder_add_container_arg($args, 'flight-id', 'flight_id', 'target-flight-id', 'target_flight_id', 'external_id');

$connectorId = (int)forwarder_add_container_arg($args, 'connector-id', 'connector_id');
$flightTable = forwarder_add_container_arg($args, 'target-table', 'target_table', 'flight-table', 'flight_table');
$containersTable = forwarder_add_container_arg($args, 'containers-table', 'containers_table');

if ($flightId === '') {
    fwrite(STDERR, "run_add_container_to_flight: missing required --flight-id/--flight_id\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_add_container_to_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-add-container-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_add_container_to_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$pageHtml = (string)($pageResponse['body'] ?? '');
$csrfToken = forwarder_add_container_extract_csrf_token($pageHtml);
if ($csrfToken === '') {
    fwrite(STDERR, "run_add_container_to_flight: csrf token not found on page\n");
    exit(5);
}

$containersPath = '/collector/get-containers';
$beforeContainersPayload = [
    'flight_id' => $flightId,
    '_token' => $csrfToken,
];
$beforeContainersResponse = $sessionClient->requestWithSession('GET', $containersPath, $beforeContainersPayload, false);
$beforeContainersStatusCode = (int)($beforeContainersResponse['status_code'] ?? 0);
$beforeContainersBody = (string)($beforeContainersResponse['body'] ?? '');
$beforeContainersOk = !empty($beforeContainersResponse['ok']) && $beforeContainersStatusCode >= 200 && $beforeContainersStatusCode < 400;
if (!$beforeContainersOk) {
    $error = trim((string)($beforeContainersResponse['error'] ?? 'containers_list_request_failed'));
    fwrite(STDERR, 'run_add_container_to_flight: get-containers failed: status=' . $beforeContainersStatusCode . ' error=' . $error . "\n");
    exit(6);
}

$submitPath = '/collector/add-new-container';
$submitMethod = 'POST';
$payload = [
    'flight' => $flightId,
    '_token' => $csrfToken,
];

$submitResponse = $sessionClient->requestWithSession($submitMethod, $submitPath, $payload, false);
$submitStatusCode = (int)($submitResponse['status_code'] ?? 0);
$submitOk = !empty($submitResponse['ok']) && $submitStatusCode >= 200 && $submitStatusCode < 400;
$submitBody = (string)($submitResponse['body'] ?? '');
$submitJson = json_decode($submitBody, true);
$submitCase = is_array($submitJson) ? trim((string)($submitJson['case'] ?? '')) : '';
$createdContainerId = is_array($submitJson) ? trim((string)($submitJson['container'] ?? '')) : '';
$submitCaseNormalized = mb_strtolower($submitCase);
$submitCaseOk = in_array($submitCaseNormalized, ['success', 'warning'], true);
if (!$submitOk || !$submitCaseOk) {
    $error = trim((string)($submitResponse['error'] ?? 'submit_failed'));
    fwrite(STDERR, 'run_add_container_to_flight: add submit failed: status=' . $submitStatusCode . ' case=' . $submitCase . ' error=' . $error . "\n");
    exit(8);
}

$searchPath = '/collector/containers?search=1&flight=' . rawurlencode($flightId);
$verifyResponse = $sessionClient->requestWithSession('GET', $searchPath, [], false);
$verifyStatusCode = (int)($verifyResponse['status_code'] ?? 0);
$verifyOk = !empty($verifyResponse['ok']) && $verifyStatusCode >= 200 && $verifyStatusCode < 400;
$afterContainersResponse = $sessionClient->requestWithSession('GET', $containersPath, $beforeContainersPayload, false);
$afterContainersStatusCode = (int)($afterContainersResponse['status_code'] ?? 0);
$afterContainersBody = (string)($afterContainersResponse['body'] ?? '');
$afterContainersOk = !empty($afterContainersResponse['ok']) && $afterContainersStatusCode >= 200 && $afterContainersStatusCode < 400;
$containersListChanged = $beforeContainersBody !== '' && $afterContainersBody !== '' && $beforeContainersBody !== $afterContainersBody;
$containerVerifiedById = $createdContainerId !== '' && str_contains($afterContainersBody, $createdContainerId);
$containerVerified = $containerVerifiedById || $containersListChanged;
$verifyStatus = ($verifyOk && $afterContainersOk && $containerVerified) ? 'passed' : 'failed';
$overallOk = $submitOk && $submitCaseOk;
$syncResult = [
    'status' => 'skipped',
    'message' => 'sync disabled: connector_id is not provided',
    'written' => 0,
    'fetched' => 0,
    'deactivated' => 0,
];
if ($overallOk && $connectorId > 0) {
    $syncResult = forwarder_sync_flight_containers_kernel([
        'repo_root' => dirname(__DIR__, 5),
        'session_client' => $sessionClient,
        'connector_id' => $connectorId,
        'flight_id' => $flightId,
        'flight_table' => $flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list',
        'containers_table' => $containersTable,
        'page_path' => $pagePath,
        'csrf_token' => $csrfToken,
    ]);
}
$fallbackUsed = false;
$fallbackHttpStatus = 0;
$fallbackError = '';

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Container add submitted via PHP session client'
        : 'Container add request failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'csrf_token_found' => $csrfToken !== '',
    'containers_path' => $containersPath,
    'containers_before_http_status' => $beforeContainersStatusCode,
    'submit_path' => $submitPath,
    'submit_method' => $submitMethod,
    'search_path' => $searchPath,
    'flight_id' => $flightId,
    'submit_case' => $submitCase,
    'created_container_id' => $createdContainerId,
    'http_status' => $submitStatusCode,
    'error' => (string)($submitResponse['error'] ?? ''),
    'verify_status' => $verifyStatus,
    'verify_http_status' => $verifyStatusCode,
    'verify_error' => (string)($verifyResponse['error'] ?? ''),
    'containers_after_http_status' => $afterContainersStatusCode,
    'containers_after_error' => (string)($afterContainersResponse['error'] ?? ''),
    'containers_list_changed' => $containersListChanged,
    'container_verified_by_id' => $containerVerifiedById,
    'container_verified_in_list' => $containerVerified,
    'sync_db_status' => (string)($syncResult['status'] ?? 'skipped'),
    'sync_db_message' => (string)($syncResult['message'] ?? ''),
    'sync_db_written' => (int)($syncResult['written'] ?? 0),
    'sync_db_fetched' => (int)($syncResult['fetched'] ?? 0),
    'sync_db_deactivated' => (int)($syncResult['deactivated'] ?? 0),
    'sync_db_flight_table' => (string)($syncResult['flight_table'] ?? ($flightTable !== '' ? $flightTable : 'connector_dev_colibri_operation_flight_list')),
    'sync_db_containers_table' => (string)($syncResult['containers_table'] ?? $containersTable),
    'fallback_used' => $fallbackUsed,
    'fallback_http_status' => $fallbackHttpStatus,
    'fallback_error' => $fallbackError,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 9);
