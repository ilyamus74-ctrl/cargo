<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/** @return array<string, string> */
function forwarder_close_flight_read_cli_kv(array $argv): array
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

function forwarder_close_flight_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_close_flight_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_close_flight_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_close_flight_extract_csrf_token(string $html): string
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
$args = forwarder_close_flight_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_close_flight_normalize_base_url(
    forwarder_close_flight_arg($args, 'base-url', 'base_url')
);

forwarder_close_flight_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_close_flight_set_env('DEV_COLIBRI_LOGIN', forwarder_close_flight_arg($args, 'login'));
forwarder_close_flight_set_env('DEV_COLIBRI_PASSWORD', forwarder_close_flight_arg($args, 'password'));
forwarder_close_flight_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_close_flight_set_env('FORWARDER_LOGIN', forwarder_close_flight_arg($args, 'login'));
forwarder_close_flight_set_env('FORWARDER_PASSWORD', forwarder_close_flight_arg($args, 'password'));
forwarder_close_flight_set_env('FORWARDER_SESSION_FILE', forwarder_close_flight_arg($args, 'session-file', 'session_file'));
forwarder_close_flight_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_close_flight_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_close_flight_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/flights';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/flights';
}

$closePath = forwarder_close_flight_arg($args, 'close-path', 'close_path', 'url');
$closePath = $closePath !== '' ? $closePath : '/collector/flights/close';
if (!str_starts_with($closePath, '/')) {
    $closePath = '/collector/flights/close';
}

$flightId = forwarder_close_flight_arg(
    $args,
    'flight-id',
    'flight_id',
    'id',
    'flight',
    'data-flight',
    'data-flight-id',
    'target-flight-id',
    'target_flight_id'
);
$connectorId = forwarder_close_flight_arg($args, 'connector-id', 'connector_id', 'data-connector-id');
$flightName = forwarder_close_flight_arg($args, 'flight-name', 'flight_name', 'data-flight-name');
$flightRecordId = forwarder_close_flight_arg($args, 'flight-record-id', 'flight_record_id', 'data-flight-record-id');
$operation = forwarder_close_flight_arg($args, 'operation', 'data-operation');
$refreshOperation = forwarder_close_flight_arg($args, 'refresh-operation', 'refresh_operation', 'data-refresh-operation');
$statusTarget = forwarder_close_flight_arg($args, 'status-target', 'status_target', 'data-status-target');
$busyLabel = forwarder_close_flight_arg($args, 'busy-label', 'busy_label', 'data-busy-label');
$successMessage = forwarder_close_flight_arg($args, 'success-message', 'success_message', 'data-success-message');
if ($flightId === '') {
    fwrite(STDERR, "run_close_flight: missing required --flight-id/--flight/--id\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_close_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-close-flight-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_close_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$pageHtml = (string)($pageResponse['body'] ?? '');
$csrfToken = forwarder_close_flight_extract_csrf_token($pageHtml);
if ($csrfToken === '') {
    fwrite(STDERR, "run_close_flight: csrf token not found on page\n");
    exit(5);
}

$closeMethod = 'POST';
$payload = [
    'id' => $flightId,
    '_token' => $csrfToken,
];

$submitResponse = $sessionClient->requestWithSession($closeMethod, $closePath, $payload, false);
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

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Flight close submitted via PHP session client'
        : 'Flight close request failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'csrf_token_found' => $csrfToken !== '',
    'close_path' => $closePath,
    'close_method' => $closeMethod,
    'flight_id' => $flightId,
    'flight_name' => $flightName,
    'flight_record_id' => $flightRecordId,
    'connector_id' => $connectorId,
    'operation' => $operation !== '' ? $operation : 'close_flight',
    'refresh_operation' => $refreshOperation !== '' ? $refreshOperation : 'flight_list',
    'status_target' => $statusTarget,
    'busy_label' => $busyLabel,
    'success_message' => $successMessage,
    'submit_case' => $submitCase,
    'submit_success' => $submitSuccessRaw,
    'response_json' => is_array($submitJson) ? $submitJson : null,
    'response_body' => $submitBody,
    'http_status' => $submitStatusCode,
    'error' => (string)($submitResponse['error'] ?? ''),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 9);

