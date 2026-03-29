<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/** @return array<string,string> */
function forwarder_add_package_to_container_cli_kv(array $argv): array
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

function forwarder_add_package_to_container_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_add_package_to_container_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_add_package_to_container_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_add_package_to_container_as_bool(string $value): bool
{
    $value = mb_strtolower(trim($value));
    return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_add_package_to_container_cli_kv($argv);

$normalizedBaseUrl = forwarder_add_package_to_container_normalize_base_url(
    forwarder_add_package_to_container_arg($args, 'base-url', 'base_url')
);

forwarder_add_package_to_container_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_to_container_set_env('DEV_COLIBRI_LOGIN', forwarder_add_package_to_container_arg($args, 'login'));
forwarder_add_package_to_container_set_env('DEV_COLIBRI_PASSWORD', forwarder_add_package_to_container_arg($args, 'password'));
forwarder_add_package_to_container_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_to_container_set_env('FORWARDER_LOGIN', forwarder_add_package_to_container_arg($args, 'login'));
forwarder_add_package_to_container_set_env('FORWARDER_PASSWORD', forwarder_add_package_to_container_arg($args, 'password'));
forwarder_add_package_to_container_set_env('FORWARDER_SESSION_FILE', forwarder_add_package_to_container_arg($args, 'session-file', 'session_file'));
forwarder_add_package_to_container_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_add_package_to_container_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$track = forwarder_add_package_to_container_arg($args, 'track', 'number', 'tracking', 'tracking_number');
$position = forwarder_add_package_to_container_arg($args, 'position', 'container', 'container_id');
$checkPath = forwarder_add_package_to_container_arg($args, 'check-path', 'check_path');
$changePath = forwarder_add_package_to_container_arg($args, 'change-path', 'change_path');
$verifyPath = forwarder_add_package_to_container_arg($args, 'verify-path', 'verify_path');
$verifyRequested = forwarder_add_package_to_container_as_bool(
    forwarder_add_package_to_container_arg($args, 'verify-check-package', 'verify_check_package')
);

$checkPath = $checkPath !== '' ? $checkPath : '/collect/check-position';
$changePath = $changePath !== '' ? $changePath : '/collect/change-position';
$verifyPath = $verifyPath !== '' ? $verifyPath : '/collector/check-package';

if ($track === '') {
    fwrite(STDERR, "run_add_package_to_container: missing required --track\n");
    exit(2);
}

if ($position === '') {
    fwrite(STDERR, "run_add_package_to_container: missing required --position\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_add_package_to_container: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-add-package-to-container-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$checkPayload = ['position' => $position];
$checkResponse = $sessionClient->requestWithSession('POST', $checkPath, $checkPayload, true);
$checkStatusCode = (int)($checkResponse['status_code'] ?? 0);
$checkJson = is_array($checkResponse['json'] ?? null)
    ? $checkResponse['json']
    : json_decode((string)($checkResponse['body'] ?? ''), true);
$checkCase = is_array($checkJson) ? mb_strtolower(trim((string)($checkJson['case'] ?? ''))) : '';
$checkBusinessOk = is_array($checkJson) && in_array($checkCase, ['success', 'warning'], true);
$checkOk = !empty($checkResponse['ok']) && $checkStatusCode >= 200 && $checkStatusCode < 400 && $checkBusinessOk;

$changePayload = [
    'track' => $track,
    'position' => $position,
];

$changeResponse = $sessionClient->requestWithSession('POST', $changePath, $changePayload, false);
$changeStatusCode = (int)($changeResponse['status_code'] ?? 0);
$changeJson = is_array($changeResponse['json'] ?? null)
    ? $changeResponse['json']
    : json_decode((string)($changeResponse['body'] ?? ''), true);
$changeCase = is_array($changeJson) ? mb_strtolower(trim((string)($changeJson['case'] ?? ''))) : '';
$changeBusinessOk = is_array($changeJson) && in_array($changeCase, ['success', 'warning'], true);
$changeOk = !empty($changeResponse['ok']) && $changeStatusCode >= 200 && $changeStatusCode < 400 && $changeBusinessOk;

$verifyResponsePayload = null;
if ($verifyRequested) {
    $verifyNumber = forwarder_add_package_to_container_arg($args, 'verify-number', 'verify_number', 'check-number', 'check_number');
    if ($verifyNumber === '') {
        $verifyNumber = $track;
    }

    $verifyResponse = $sessionClient->requestWithSession('POST', $verifyPath, ['number' => $verifyNumber], true);
    $verifyResponsePayload = [
        'http_ok' => !empty($verifyResponse['ok']),
        'http_status' => (int)($verifyResponse['status_code'] ?? 0),
        'error' => (string)($verifyResponse['error'] ?? ''),
        'json' => is_array($verifyResponse['json'] ?? null)
            ? $verifyResponse['json']
            : json_decode((string)($verifyResponse['body'] ?? ''), true),
        'raw_body' => (string)($verifyResponse['body'] ?? ''),
    ];
}

$overallOk = $checkOk && $changeOk;

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Package was added to container position'
        : 'Failed to add package to container position',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'check_path' => $checkPath,
    'change_path' => $changePath,
    'track' => $track,
    'position' => $position,
    'check' => [
        'http_ok' => !empty($checkResponse['ok']),
        'http_status' => $checkStatusCode,
        'case' => is_array($checkJson) ? (string)($checkJson['case'] ?? '') : '',
        'change' => is_array($checkJson) ? ($checkJson['change'] ?? null) : null,
        'content' => is_array($checkJson) ? (string)($checkJson['content'] ?? '') : '',
        'sum' => is_array($checkJson) ? ($checkJson['sum'] ?? null) : null,
        'error' => (string)($checkResponse['error'] ?? ''),
        'json' => $checkJson,
        'raw_body' => (string)($checkResponse['body'] ?? ''),
    ],
    'change' => [
        'http_ok' => !empty($changeResponse['ok']),
        'http_status' => $changeStatusCode,
        'case' => is_array($changeJson) ? (string)($changeJson['case'] ?? '') : '',
        'change' => is_array($changeJson) ? ($changeJson['change'] ?? null) : null,
        'content' => is_array($changeJson) ? (string)($changeJson['content'] ?? '') : '',
        'track' => is_array($changeJson) ? (string)($changeJson['track'] ?? '') : '',
        'weight' => is_array($changeJson) ? (string)($changeJson['weight'] ?? '') : '',
        'error' => (string)($changeResponse['error'] ?? ''),
        'json' => $changeJson,
        'raw_body' => (string)($changeResponse['body'] ?? ''),
    ],
    'verification' => $verifyResponsePayload,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 8);
