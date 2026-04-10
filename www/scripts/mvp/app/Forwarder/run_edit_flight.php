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
function forwarder_edit_flight_read_cli_kv(array $argv): array
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

function forwarder_edit_flight_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_edit_flight_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_edit_flight_env_value(string $name): string
{
    $fromGetenv = getenv($name);
    if (is_string($fromGetenv) && trim($fromGetenv) !== '') {
        return trim($fromGetenv);
    }

    $fromEnv = $_ENV[$name] ?? '';
    if (is_string($fromEnv) && trim($fromEnv) !== '') {
        return trim($fromEnv);
    }

    return '';
}

/** @return array{base_url:string,login:string,password:string} */
function forwarder_edit_flight_resolve_config_from_carrier(string $carrier): array
{
    $carrierKey = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', trim($carrier)) ?? '');
    $carrierKey = trim($carrierKey, '_');
    if ($carrierKey === '') {
        return ['base_url' => '', 'login' => '', 'password' => ''];
    }

    return [
        'base_url' => forwarder_edit_flight_env_value($carrierKey . '_BASE_URL'),
        'login' => forwarder_edit_flight_env_value($carrierKey . '_LOGIN'),
        'password' => forwarder_edit_flight_env_value($carrierKey . '_PASSWORD'),
    ];
}

function forwarder_edit_flight_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_edit_flight_path_from_url(string $raw, string $defaultPath): string
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

function forwarder_edit_flight_extract_csrf_token(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

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
$args = forwarder_edit_flight_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_edit_flight_normalize_base_url(forwarder_edit_flight_arg($args, 'base-url', 'base_url'));

forwarder_edit_flight_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_edit_flight_set_env('DEV_COLIBRI_LOGIN', forwarder_edit_flight_arg($args, 'login'));
forwarder_edit_flight_set_env('DEV_COLIBRI_PASSWORD', forwarder_edit_flight_arg($args, 'password'));
forwarder_edit_flight_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_edit_flight_set_env('FORWARDER_LOGIN', forwarder_edit_flight_arg($args, 'login'));
forwarder_edit_flight_set_env('FORWARDER_PASSWORD', forwarder_edit_flight_arg($args, 'password'));
forwarder_edit_flight_set_env('FORWARDER_SESSION_FILE', forwarder_edit_flight_arg($args, 'session-file', 'session_file'));
forwarder_edit_flight_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_edit_flight_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_edit_flight_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/flights';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/flights';
}

$updatePathRaw = forwarder_edit_flight_arg($args, 'update-path', 'update_path', 'url');
$updatePath = forwarder_edit_flight_path_from_url($updatePathRaw, '/collector/flights/update');

$flightId = forwarder_edit_flight_arg($args, 'id', 'flight-id', 'flight_id', 'target-flight-id', 'target_flight_id');
$carrier = forwarder_edit_flight_arg($args, 'carrier');
$flightNumber = forwarder_edit_flight_arg($args, 'flight-number', 'flight_number');
$awb = forwarder_edit_flight_arg($args, 'awb');
$departure = forwarder_edit_flight_arg($args, 'departure');
$destination = forwarder_edit_flight_arg($args, 'destination');
$flightTime = forwarder_edit_flight_arg($args, 'flight-time', 'flight_time');
$csrfToken = forwarder_edit_flight_arg($args, '_token', 'token', 'csrf-token', 'csrf_token');

$carrierConfig = forwarder_edit_flight_resolve_config_from_carrier($carrier);
if ($normalizedBaseUrl === '' && $carrierConfig['base_url'] !== '') {
    $normalizedBaseUrl = forwarder_edit_flight_normalize_base_url($carrierConfig['base_url']);
    forwarder_edit_flight_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
    forwarder_edit_flight_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
}

if (forwarder_edit_flight_arg($args, 'login') === '' && $carrierConfig['login'] !== '') {
    forwarder_edit_flight_set_env('DEV_COLIBRI_LOGIN', $carrierConfig['login']);
    forwarder_edit_flight_set_env('FORWARDER_LOGIN', $carrierConfig['login']);
}

if (forwarder_edit_flight_arg($args, 'password') === '' && $carrierConfig['password'] !== '') {
    forwarder_edit_flight_set_env('DEV_COLIBRI_PASSWORD', $carrierConfig['password']);
    forwarder_edit_flight_set_env('FORWARDER_PASSWORD', $carrierConfig['password']);
}

if (
    $flightId === ''
    || $carrier === ''
    || $flightNumber === ''
    || $awb === ''
    || $departure === ''
    || $destination === ''
    || $flightTime === ''
) {
    fwrite(STDERR, "run_edit_flight: missing required args --id --carrier --flight-number --awb --departure --destination --flight-time\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_edit_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-edit-flight-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$tokenSource = 'cli';
if ($csrfToken === '') {
    $pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
    $pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
    if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
        $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
        fwrite(STDERR, 'run_edit_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
        exit(4);
    }

    $body = (string)($pageResponse['body'] ?? '');
    $csrfToken = forwarder_edit_flight_extract_csrf_token($body);
    $tokenSource = 'page';
}

if ($csrfToken === '') {
    fwrite(STDERR, "run_edit_flight: csrf token is empty (pass --_token=... or ensure page has token)\n");
    exit(5);
}

$payload = [
    '_token' => $csrfToken,
    'id' => $flightId,
    'carrier' => $carrier,
    'flight_number' => $flightNumber,
    'awb' => $awb,
    'departure' => $departure,
    'destination' => $destination,
    'flight_time' => $flightTime,
];


$authStep = $sessionClient->ensureSession();
if (empty($authStep['ok'])) {
    fwrite(STDERR, "run_edit_flight: login/session bootstrap failed\n");
    exit(6);
}

$headers = array_merge(
    $session->securityHeaders(true),
    [
        'X-CSRF-TOKEN' => $csrfToken,
        'Origin' => $config->baseUrl(),
        'Referer' => rtrim($config->baseUrl(), '/') . $pagePath,
    ]
);

$submitResponse = $httpClient->request(
    'POST',
    $updatePath,
    $payload,
    $headers,
    false,
    $session->cookieHeader()
);
$session->updateFromHeaders((string)($submitResponse['headers_raw'] ?? ''));
$session->updateFromHtml((string)($submitResponse['body'] ?? ''));
$submitStatusCode = (int)($submitResponse['status_code'] ?? 0);
$submitOk = !empty($submitResponse['ok']) && $submitStatusCode >= 200 && $submitStatusCode < 400;

$result = [
    'status' => $submitOk ? 'ok' : 'error',
    'message' => $submitOk ? 'Flight edit submitted via PHP session client' : 'Flight edit submit failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'submit_path' => $updatePath,
    'submit_method' => 'POST',
    'token_source' => $tokenSource,
    'payload' => $payload,
    'http_status' => $submitStatusCode,
    'error' => (string)($submitResponse['error'] ?? ''),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($submitOk ? 0 : 1);
