<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;

function camex_az_probe_usage(): string
{
    return <<<'TXT'
Usage:
  php run_probe_login.php --base-url="https://FORWARDER_HOST" --http-auth-type=basic --http-auth-login="HTACCESS_USER" --http-auth-password="HTACCESS_PASS" --login="WEB_USER" --password="WEB_PASS" [options]

Options:
  --base-url=URL                    Forwarder base URL.
  --http-auth-type=basic|digest|none HTTP htaccess auth type (default: none).
  --http-auth-login=LOGIN           HTTP htaccess auth login.
  --http-auth-password=PASSWORD     HTTP htaccess auth password.
  --login=LOGIN                     Web form login.
  --password=PASSWORD               Web form password.
  --login-path=/login               Login page path (default: /login).
  --dashboard-path=/                Dashboard path checked after login (default: /).
  --session-file=/tmp/file          Session state file (default: /tmp/camex_az_cookie.txt).
  --session-ttl-seconds=3600        Session TTL in seconds (default: 3600).
  --debug-dir=/tmp/camex_az_debug   Optional directory for debug HTML snapshots.
  --insecure=0|1                    Disable TLS verification through existing config flag.
  --timeout=30                      Total request timeout in seconds.
  --help                            Show this help.
TXT;
}

/** @return array<string, string> */
function camex_az_probe_args(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = '1';
            continue;
        }

        if (strncmp($arg, '--', 2) !== 0) {
            continue;
        }

        $pair = explode('=', substr($arg, 2), 2);
        $args[$pair[0]] = $pair[1] ?? '1';
    }

    return $args;
}

function camex_az_probe_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/';
    }

    return $path[0] === '/' ? $path : '/' . $path;
}

/** @return array{name: string, value: string} */
function camex_az_probe_extract_csrf(string $html): array
{
    if (
        preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $metaMatch) === 1
        && isset($metaMatch[1])
    ) {
        return ['name' => '_token', 'value' => trim((string)$metaMatch[1])];
    }

    foreach (['_token', 'csrf_token', 'csrf'] as $fieldName) {
        $quotedName = preg_quote($fieldName, '/');
        if (
            preg_match('/<input[^>]+name=["\']' . $quotedName . '["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $inputMatch) === 1
            && isset($inputMatch[1])
        ) {
            return ['name' => $fieldName, 'value' => trim((string)$inputMatch[1])];
        }
    }

    return ['name' => '', 'value' => ''];
}

function camex_az_probe_save_debug(string $debugDir, string $fileName, string $html): void
{
    if ($debugDir === '') {
        return;
    }

    if (!is_dir($debugDir)) {
        @mkdir($debugDir, 0775, true);
    }

    if (is_dir($debugDir)) {
        @file_put_contents(rtrim($debugDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName, $html);
    }
}

/** @param array<string, mixed> $value */
function camex_az_probe_mask(array $value): array
{
    $secretKeys = ['http-auth-password', 'password', 'cookies', 'set-cookie', 'authorization', 'cookie'];
    foreach ($value as $key => $item) {
        $normalized = strtolower(str_replace('_', '-', (string)$key));
        if (in_array($normalized, $secretKeys, true)) {
            $value[$key] = '***';
            continue;
        }

        if (is_array($item)) {
            $value[$key] = camex_az_probe_mask($item);
        }
    }

    return $value;
}

/** @param array<string, mixed> $payload */
function camex_az_probe_json(array $payload, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(camex_az_probe_mask($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($exitCode);
}

/** @param array<string, mixed> $response */
function camex_az_probe_error(string $stage, string $message, array $response = [], int $exitCode = 1): void
{
    camex_az_probe_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'stage' => $stage,
        'message' => $message,
        'http_status' => (int)($response['status_code'] ?? 0),
        'curl_errno' => (int)($response['curl_errno'] ?? 0),
        'curl_error' => (string)($response['curl_error'] ?? ''),
    ], $exitCode);
}

$args = camex_az_probe_args($argv);
if (isset($args['help'])) {
    fwrite(STDOUT, camex_az_probe_usage() . PHP_EOL);
    exit(0);
}

$httpAuthType = strtolower(trim($args['http-auth-type'] ?? 'none'));
if (!in_array($httpAuthType, ['basic', 'digest', 'none'], true)) {
    camex_az_probe_error('http_auth', 'Invalid --http-auth-type value. Expected basic, digest, or none.');
}

$baseUrl = rtrim((string)($args['base-url'] ?? ''), '/');
$login = (string)($args['login'] ?? '');
$password = (string)($args['password'] ?? '');
$loginPath = camex_az_probe_path((string)($args['login-path'] ?? '/login'));
$dashboardPath = camex_az_probe_path((string)($args['dashboard-path'] ?? '/'));
$sessionFile = (string)($args['session-file'] ?? '/tmp/camex_az_cookie.txt');
$debugDir = (string)($args['debug-dir'] ?? '');
$timeout = max(1, (int)($args['timeout'] ?? 30));
$sessionTtlSeconds = max(60, (int)($args['session-ttl-seconds'] ?? 3600));

if ($baseUrl === '') {
    camex_az_probe_error('login_page', 'Missing required --base-url.');
}
if ($login === '' || $password === '') {
    camex_az_probe_error('web_login', 'Missing required --login or --password.');
}
if ($httpAuthType !== 'none' && ((string)($args['http-auth-login'] ?? '') === '' || (string)($args['http-auth-password'] ?? '') === '')) {
    camex_az_probe_error('http_auth', 'Missing required --http-auth-login or --http-auth-password for enabled HTTP auth.');
}

$config = new ForwarderConfig([
    'base_url' => $baseUrl,
    'http_auth_enabled' => $httpAuthType !== 'none',
    'http_auth_type' => $httpAuthType,
    'http_auth_login' => (string)($args['http-auth-login'] ?? ''),
    'http_auth_password' => (string)($args['http-auth-password'] ?? ''),
    'web_login' => $login,
    'web_password' => $password,
    'login_path' => $loginPath,
    'login_post_path' => $loginPath,
    'dashboard_path' => $dashboardPath,
    'session_file' => $sessionFile,
    'session_ttl_seconds' => $sessionTtlSeconds,
    'insecure' => (string)($args['insecure'] ?? '0'),
    'timeout' => $timeout,
]);
$session = new SessionManager();
$httpClient = new ForwarderHttpClient($config);

$loginPage = $httpClient->get($config->loginPath());
$session->updateFromHeaders((string)($loginPage['headers_raw'] ?? ''));
$session->updateFromHtml((string)($loginPage['body'] ?? ''));
camex_az_probe_save_debug($debugDir, '01_login_page.html', (string)($loginPage['body'] ?? ''));

$loginPageStatus = (int)($loginPage['status_code'] ?? 0);
if ($loginPageStatus === 401 || $loginPageStatus === 403) {
    camex_az_probe_error('http_auth', 'HTTP authentication failed while loading login page.', $loginPage);
}
if ($loginPageStatus < 200 || $loginPageStatus >= 400) {
    camex_az_probe_error('login_page', 'Unable to load login page.', $loginPage);
}

$csrf = camex_az_probe_extract_csrf((string)($loginPage['body'] ?? ''));
if ($csrf['value'] === '') {
    camex_az_probe_error('csrf', 'Unable to find CSRF token on login page.', $loginPage);
}

$payload = [
    'username' => $config->webLogin(),
    'login' => $config->webLogin(),
    'password' => $config->webPassword(),
    $csrf['name'] => $csrf['value'],
];
$headers = array_merge(
    $session->securityHeaders(true),
    [
        'Origin' => $config->baseUrl(),
        'Referer' => $config->loginUrl(),
    ]
);
$loginPost = $httpClient->post($config->loginPostPath(), $payload, $headers, false, $session->cookieHeader());
$session->updateFromHeaders((string)($loginPost['headers_raw'] ?? ''));
$session->updateFromHtml((string)($loginPost['body'] ?? ''));
camex_az_probe_save_debug($debugDir, '02_login_post_response.html', (string)($loginPost['body'] ?? ''));

$loginPostStatus = (int)($loginPost['status_code'] ?? 0);
if ($loginPostStatus === 401 || $loginPostStatus === 403) {
    camex_az_probe_error('web_login', 'Web login form authentication failed.', $loginPost);
}
if ($loginPostStatus < 200 || $loginPostStatus >= 400) {
    camex_az_probe_error('web_login', 'Unexpected web login response status.', $loginPost);
}

$dashboard = $httpClient->get($config->dashboardPath(), $session->securityHeaders(false), $session->cookieHeader());
$session->updateFromHeaders((string)($dashboard['headers_raw'] ?? ''));
$session->updateFromHtml((string)($dashboard['body'] ?? ''));
camex_az_probe_save_debug($debugDir, '03_dashboard.html', (string)($dashboard['body'] ?? ''));

$dashboardStatus = (int)($dashboard['status_code'] ?? 0);
if ($dashboardStatus === 401 || $dashboardStatus === 403) {
    camex_az_probe_error('dashboard', 'Dashboard request was not authenticated.', $dashboard);
}
if ($dashboardStatus < 200 || $dashboardStatus >= 400) {
    camex_az_probe_error('dashboard', 'Unexpected dashboard response status.', $dashboard);
}

$sessionDir = dirname($config->sessionCookieFile());
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}
@file_put_contents($config->sessionCookieFile(), json_encode([
    'expires_at' => time() + $config->sessionTtlSeconds(),
    'session' => $session->exportState(),
], JSON_UNESCAPED_UNICODE));

camex_az_probe_json([
    'status' => 'ok',
    'connector' => 'CAMEX_AZ',
    'http_auth' => [
        'enabled' => $config->httpAuthEnabled(),
        'type' => $config->httpAuthType(),
        'login_page_status' => $loginPageStatus,
    ],
    'web_login' => [
        'status' => 'ok',
        'csrf_found' => true,
        'login_post_status' => $loginPostStatus,
        'dashboard_status' => $dashboardStatus,
    ],
    'session' => [
        'session_file' => $config->sessionCookieFile(),
        'cookie_file_exists' => is_file($config->sessionCookieFile()),
    ],
]);
