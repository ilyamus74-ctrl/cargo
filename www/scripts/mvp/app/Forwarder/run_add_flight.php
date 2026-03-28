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
function forwarder_add_flight_read_cli_kv(array $argv): array
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

function forwarder_add_flight_arg(array $args, string $primaryKey, string ...$aliases): string
{
    $candidates = array_merge([$primaryKey], $aliases);
    foreach ($candidates as $key) {
        if (!array_key_exists($key, $args)) {
            continue;
        }

        return trim((string)$args[$key]);
    }

    return '';
}

function forwarder_add_flight_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_add_flight_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_add_flight_path_from_url(string $raw, string $defaultPath): string
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

/** @return array<string,mixed> */
function forwarder_add_flight_extract_form(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return [
            'ok' => false,
            'error' => 'empty_html',
        ];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return [
            'ok' => false,
            'error' => 'invalid_html',
        ];
    }

    $xpath = new DOMXPath($dom);
    $formNode = $xpath->query('//form[@id="form"]')->item(0);
    if (!($formNode instanceof DOMElement)) {
        return [
            'ok' => false,
            'error' => 'form_not_found',
        ];
    }

    $action = trim((string)$formNode->getAttribute('action'));
    $method = strtoupper(trim((string)$formNode->getAttribute('method')));
    if ($method === '') {
        $method = 'POST';
    }

    $payloadDefaults = [];
    foreach ($xpath->query('.//input[@name]', $formNode) as $inputNode) {
        if (!($inputNode instanceof DOMElement)) {
            continue;
        }

        $name = trim((string)$inputNode->getAttribute('name'));
        if ($name === '') {
            continue;
        }

        $type = strtolower(trim((string)$inputNode->getAttribute('type')));
        if (in_array($type, ['submit', 'button', 'file'], true)) {
            continue;
        }

        if (array_key_exists($name, $payloadDefaults)) {
            continue;
        }

        $payloadDefaults[$name] = (string)$inputNode->getAttribute('value');
    }

    return [
        'ok' => true,
        'action' => $action,
        'method' => $method,
        'payload_defaults' => $payloadDefaults,
    ];
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_add_flight_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_add_flight_normalize_base_url(forwarder_add_flight_arg($args, 'base-url', 'base_url'));

forwarder_add_flight_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_add_flight_set_env('DEV_COLIBRI_LOGIN', forwarder_add_flight_arg($args, 'login'));
forwarder_add_flight_set_env('DEV_COLIBRI_PASSWORD', forwarder_add_flight_arg($args, 'password'));
forwarder_add_flight_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_add_flight_set_env('FORWARDER_LOGIN', forwarder_add_flight_arg($args, 'login'));
forwarder_add_flight_set_env('FORWARDER_PASSWORD', forwarder_add_flight_arg($args, 'password'));
forwarder_add_flight_set_env('FORWARDER_SESSION_FILE', forwarder_add_flight_arg($args, 'session-file', 'session_file'));
forwarder_add_flight_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_add_flight_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_add_flight_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/flights';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/flights';
}

$flightNumber = forwarder_add_flight_arg($args, 'flight-number', 'flight_number', 'set_date');
$awb = forwarder_add_flight_arg($args, 'awb', 'add_flight');

if ($flightNumber === '' || $awb === '') {
    fwrite(STDERR, "run_add_flight: missing args --flight-number and/or --awb\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_add_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-add-flight-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_add_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$body = (string)($pageResponse['body'] ?? '');
$form = forwarder_add_flight_extract_form($body);
if (empty($form['ok'])) {
    $error = trim((string)($form['error'] ?? 'form_parse_failed'));
    fwrite(STDERR, 'run_add_flight: add form parse failed: ' . $error . "\n");
    exit(5);
}

$formAction = (string)($form['action'] ?? '');
$formMethod = strtoupper(trim((string)($form['method'] ?? 'POST')));
$payload = isset($form['payload_defaults']) && is_array($form['payload_defaults']) ? $form['payload_defaults'] : [];
$payload['flight_number'] = $flightNumber;
$payload['awb'] = $awb;

$submitPath = forwarder_add_flight_path_from_url($formAction, $pagePath);
if ($submitPath === '') {
    $submitPath = $pagePath;
}

$submitResponse = $sessionClient->requestWithSession($formMethod === 'GET' ? 'GET' : 'POST', $submitPath, $payload, false);
$submitStatusCode = (int)($submitResponse['status_code'] ?? 0);
$submitOk = !empty($submitResponse['ok']) && $submitStatusCode >= 200 && $submitStatusCode < 400;

$result = [
    'status' => $submitOk ? 'ok' : 'error',
    'message' => $submitOk ? 'Flight add submitted via PHP session client' : 'Flight add submit failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'submit_path' => $submitPath,
    'submit_method' => $formMethod === 'GET' ? 'GET' : 'POST',
    'flight_number' => $flightNumber,
    'awb' => $awb,
    'http_status' => $submitStatusCode,
    'error' => (string)($submitResponse['error'] ?? ''),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($submitOk ? 0 : 1);
