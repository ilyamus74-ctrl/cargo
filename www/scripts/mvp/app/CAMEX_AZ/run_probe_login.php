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
  --dashboard-path=/path            Dashboard path checked after login (default: /cadmin/usa/index.php?do=index).
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

/** @return array<string, string> */
function camex_az_extract_html_attributes(string $tag): array
{
    $attributes = [];
    if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            $name = strtolower((string)$match[1]);
            $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
            $attributes[$name] = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $attributes;
}

/**
 * @return array{action: string, method: string, input_names: list<string>, password_fields: list<string>}
 */
function camex_az_extract_login_form_metadata(string $html): array
{
    $metadata = [
        'action' => '',
        'method' => 'get',
        'input_names' => [],
        'password_fields' => [],
    ];

    if ($html === '') {
        return $metadata;
    }

    if (class_exists('DOMDocument')) {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded) {
            $forms = $dom->getElementsByTagName('form');
            $selectedForm = null;
            foreach ($forms as $form) {
                foreach ($form->getElementsByTagName('input') as $input) {
                    if (strcasecmp((string)$input->getAttribute('type'), 'password') === 0) {
                        $selectedForm = $form;
                        break 2;
                    }
                }
            }
            if ($selectedForm === null && $forms->length > 0) {
                $selectedForm = $forms->item(0);
            }

            if ($selectedForm !== null) {
                $metadata['action'] = html_entity_decode(trim((string)$selectedForm->getAttribute('action')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $method = strtolower(trim((string)$selectedForm->getAttribute('method')));
                $metadata['method'] = $method !== '' ? $method : 'get';

                foreach ($selectedForm->getElementsByTagName('input') as $input) {
                    $name = trim((string)$input->getAttribute('name'));
                    if ($name === '') {
                        continue;
                    }
                    if (!in_array($name, $metadata['input_names'], true)) {
                        $metadata['input_names'][] = $name;
                    }
                    if (strcasecmp((string)$input->getAttribute('type'), 'password') === 0 && !in_array($name, $metadata['password_fields'], true)) {
                        $metadata['password_fields'][] = $name;
                    }
                }

                return $metadata;
            }
        }
    }

    return camex_az_extract_login_form_metadata_regex($html, $metadata);
}

/**
 * @param array{action: string, method: string, input_names: list<string>, password_fields: list<string>} $default
 * @return array{action: string, method: string, input_names: list<string>, password_fields: list<string>}
 */
function camex_az_extract_login_form_metadata_regex(string $html, array $default): array
{
    $forms = [];
    if (preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/is', $html, $matches, PREG_SET_ORDER) !== false) {
        $forms = $matches;
    }
    if ($forms === [] && preg_match('/<form\b([^>]*)>(.*)$/is', $html, $singleMatch) === 1) {
        $forms = [$singleMatch];
    }

    $selected = null;
    foreach ($forms as $form) {
        if (preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b[^>]*>/i', (string)($form[2] ?? '')) === 1) {
            $selected = $form;
            break;
        }
    }
    if ($selected === null && $forms !== []) {
        $selected = $forms[0];
    }
    if ($selected === null) {
        return $default;
    }

    $formAttributes = camex_az_extract_html_attributes((string)($selected[1] ?? ''));
    $default['action'] = trim((string)($formAttributes['action'] ?? ''));
    $method = strtolower(trim((string)($formAttributes['method'] ?? '')));
    $default['method'] = $method !== '' ? $method : 'get';

    if (preg_match_all('/<input\b[^>]*>/i', (string)($selected[2] ?? ''), $inputMatches) !== false) {
        foreach ($inputMatches[0] as $inputTag) {
            $inputAttributes = camex_az_extract_html_attributes((string)$inputTag);
            $name = trim((string)($inputAttributes['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (!in_array($name, $default['input_names'], true)) {
                $default['input_names'][] = $name;
            }
            if (strcasecmp((string)($inputAttributes['type'] ?? ''), 'password') === 0 && !in_array($name, $default['password_fields'], true)) {
                $default['password_fields'][] = $name;
            }
        }
    }

    return $default;
}

function camex_az_resolve_form_action_path(string $loginPath, string $action): string
{
    $loginPath = camex_az_probe_path($loginPath);
    $action = trim(html_entity_decode($action, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($action === '') {
        return $loginPath;
    }

    $action = explode('#', $action, 2)[0];
    if ($action === '') {
        return $loginPath;
    }

    if (preg_match('#^https?://#i', $action) === 1) {
        $path = (string)(parse_url($action, PHP_URL_PATH) ?: '/');
        $query = parse_url($action, PHP_URL_QUERY);
        return camex_az_probe_path($path) . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    if ($action[0] === '/') {
        return $action;
    }

    if ($action[0] === '?') {
        $path = explode('?', $loginPath, 2)[0];
        return $path . $action;
    }

    $directory = rtrim(str_replace('\\', '/', dirname($loginPath)), '/');
    if ($directory === '') {
        $directory = '/';
    }

    return rtrim($directory, '/') . '/' . $action;
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

function camex_az_extract_location(array $response): string
{
    $headersRaw = (string)($response['headers_raw'] ?? '');
    if ($headersRaw === '') {
        return '';
    }

    $location = '';
    foreach (preg_split('/\r\n|\r|\n/', $headersRaw) ?: [] as $line) {
        if (stripos((string)$line, 'Location:') === 0) {
            $location = trim(substr((string)$line, strlen('Location:')));
        }
    }

    return $location;
}

function camex_az_location_contains(string $location, string $needle): bool
{
    return $location !== '' && stripos($location, $needle) !== false;
}

function camex_az_resolve_redirect_path(string $currentPath, string $location): string
{
    $location = trim(html_entity_decode($location, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($location === '') {
        return camex_az_probe_path($currentPath);
    }

    $location = explode('#', $location, 2)[0];
    if (preg_match('#^https?://#i', $location) === 1) {
        $path = (string)(parse_url($location, PHP_URL_PATH) ?: '/');
        $query = parse_url($location, PHP_URL_QUERY);
        return camex_az_probe_path($path) . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    if ($location[0] === '/') {
        return $location;
    }

    if ($location[0] === '?') {
        $path = explode('?', camex_az_probe_path($currentPath), 2)[0];
        return $path . $location;
    }

    $pathOnly = explode('?', camex_az_probe_path($currentPath), 2)[0];
    $directory = rtrim(str_replace('\\', '/', dirname($pathOnly)), '/');
    if ($directory === '') {
        $directory = '/';
    }

    return rtrim($directory, '/') . '/' . $location;
}

function camex_az_effective_url(string $baseUrl, string $path): string
{
    return rtrim($baseUrl, '/') . camex_az_probe_path($path);
}

function camex_az_looks_like_login_page(string $html): bool
{
    if ($html === '') {
        return false;
    }

    $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
    $hasLoginAction = stripos($html, 'login.php?auth=do') !== false;
    $hasLoginTitle = stripos($html, 'Camara Express Login Form') !== false;

    return $hasPassword && $hasLoginAction && $hasLoginTitle;
}

function camex_az_looks_like_admin_page(string $html): bool
{
    if ($html === '') {
        return false;
    }

    $markers = [
        'index.php?do=logout',
        'LogOut',
        'Camara Express Admin Panel',
        'index.php?do=newaddpre',
        'index.php?do=flight',
        'index.php?do=tracking_search',
        'index.php?do=show_orders_global',
        'index.php?do=searchtracking',
        'index.php?do=track2box',
        'index.php?do=box4track',
    ];

    foreach ($markers as $marker) {
        if (stripos($html, $marker) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{response: array<string, mixed>, location: string, effective_path: string, effective_url: string}
 */
function camex_az_fetch_dashboard_following_redirects(
    ForwarderHttpClient $httpClient,
    ForwarderConfig $config,
    SessionManager $session
): array {
    $path = $config->dashboardPath();
    $firstLocation = '';
    $response = [];

    for ($redirects = 0; $redirects <= 5; $redirects++) {
        $response = $httpClient->get($path, $session->securityHeaders(false), $session->cookieHeader());
        $session->updateFromHeaders((string)($response['headers_raw'] ?? ''));
        $session->updateFromHtml((string)($response['body'] ?? ''));

        $status = (int)($response['status_code'] ?? 0);
        $location = camex_az_extract_location($response);
        if ($firstLocation === '' && $location !== '') {
            $firstLocation = $location;
        }

        if ($status < 300 || $status >= 400 || $location === '') {
            break;
        }

        $path = camex_az_resolve_redirect_path($path, $location);
    }

    return [
        'response' => $response,
        'location' => $firstLocation,
        'effective_path' => $path,
        'effective_url' => camex_az_effective_url($config->baseUrl(), $path),
    ];
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

/**
 * @param array<string, mixed> $response
 * @param array<string, mixed> $extra
 */
function camex_az_probe_error(string $stage, string $message, array $response = [], int $exitCode = 1, array $extra = []): void
{
    camex_az_probe_json(array_merge([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'stage' => $stage,
        'message' => $message,
        'http_status' => (int)($response['status_code'] ?? 0),
        'curl_errno' => (int)($response['curl_errno'] ?? 0),
        'curl_error' => (string)($response['curl_error'] ?? ''),
    ], $extra), $exitCode);
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
$dashboardPath = camex_az_probe_path((string)($args['dashboard-path'] ?? '/cadmin/usa/index.php?do=index'));
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

$loginHtml = (string)($loginPage['body'] ?? '');
$csrf = camex_az_probe_extract_csrf($loginHtml);
$csrfFound = is_string($csrf['value']) && $csrf['value'] !== '';
$formMetadata = camex_az_extract_login_form_metadata($loginHtml);
$loginPostPath = camex_az_resolve_form_action_path($config->loginPath(), $formMetadata['action']);
$formDiagnostics = array_merge($formMetadata, [
    'resolved_action_path' => $loginPostPath,
]);

$payload = [
    'user' => $config->webLogin(),
    'login' => $config->webLogin(),
    'username' => $config->webLogin(),
    'email' => $config->webLogin(),
    'password' => $config->webPassword(),
];
if ($csrfFound) {
    foreach (['_token', 'csrf_token', 'csrf'] as $csrfAlias) {
        $payload[$csrfAlias] = $csrf['value'];
    }
}
$headers = array_merge(
    $session->securityHeaders(true),
    [
        'Origin' => $config->baseUrl(),
        'Referer' => $config->loginUrl(),
    ]
);
$loginPost = $httpClient->post($loginPostPath, $payload, $headers, false, $session->cookieHeader());
$session->updateFromHeaders((string)($loginPost['headers_raw'] ?? ''));
$session->updateFromHtml((string)($loginPost['body'] ?? ''));
camex_az_probe_save_debug($debugDir, '02_login_post_response.html', (string)($loginPost['body'] ?? ''));

$loginPostStatus = (int)($loginPost['status_code'] ?? 0);
$loginPostLocation = camex_az_extract_location($loginPost);
$loginSuccessCandidate = camex_az_location_contains($loginPostLocation, 'index.php?do=index');
if ($loginPostStatus === 401 || $loginPostStatus === 403) {
    camex_az_probe_error('web_login', 'Web login form authentication failed.', $loginPost, 1, [
        'web_login' => [
            'csrf_found' => $csrfFound,
            'login_post_status' => $loginPostStatus,
            'login_post_location' => $loginPostLocation,
        ],
        'login_form' => $formDiagnostics,
    ]);
}
if (camex_az_location_contains($loginPostLocation, 'login.php')) {
    camex_az_probe_error('web_login', 'Web login redirects to login page.', $loginPost, 1, [
        'web_login' => [
            'csrf_found' => $csrfFound,
            'login_post_status' => $loginPostStatus,
            'login_post_location' => $loginPostLocation,
        ],
        'login_form' => $formDiagnostics,
    ]);
}
if ($loginPostStatus < 200 || $loginPostStatus >= 400) {
    if ($loginPostStatus !== 302 || !$loginSuccessCandidate) {
        camex_az_probe_error('web_login', 'Unexpected web login response status.', $loginPost, 1, [
            'web_login' => [
                'csrf_found' => $csrfFound,
                'login_post_status' => $loginPostStatus,
                'login_post_location' => $loginPostLocation,
            ],
            'login_form' => $formDiagnostics,
        ]);
    }
}

$dashboardResult = camex_az_fetch_dashboard_following_redirects($httpClient, $config, $session);
$dashboard = $dashboardResult['response'];
$dashboardLocation = (string)$dashboardResult['location'];
$dashboardEffectiveUrl = (string)$dashboardResult['effective_url'];
camex_az_probe_save_debug($debugDir, '03_dashboard.html', (string)($dashboard['body'] ?? ''));

$dashboardStatus = (int)($dashboard['status_code'] ?? 0);
$dashboardHtml = (string)($dashboard['body'] ?? '');
$dashboardLooksLikeLogin = camex_az_looks_like_login_page($dashboardHtml)
    || (stripos($dashboardHtml, '<input type="password"') !== false && stripos($dashboardHtml, 'login.php?auth=do') !== false);
$dashboardLooksLikeAdmin = camex_az_looks_like_admin_page($dashboardHtml);
$webLoginDiagnostics = [
    'status' => $dashboardLooksLikeAdmin ? 'ok' : 'error',
    'csrf_found' => $csrfFound,
    'login_post_status' => $loginPostStatus,
    'login_post_location' => $loginPostLocation,
    'dashboard_status' => $dashboardStatus,
    'dashboard_location' => $dashboardLocation,
    'dashboard_effective_url' => $dashboardEffectiveUrl,
    'dashboard_looks_like_login' => $dashboardLooksLikeLogin,
    'dashboard_looks_like_admin' => $dashboardLooksLikeAdmin,
];

if ($dashboardStatus === 401 || $dashboardStatus === 403) {
    camex_az_probe_error('dashboard', 'Dashboard request was not authenticated.', $dashboard, 1, [
        'web_login' => $webLoginDiagnostics,
        'login_form' => $formDiagnostics,
    ]);
}
if (camex_az_location_contains($dashboardLocation, 'login.php')) {
    camex_az_probe_error('web_login', 'Dashboard redirects to login page after web login', $dashboard, 1, [
        'web_login' => $webLoginDiagnostics,
        'login_form' => $formDiagnostics,
    ]);
}
if ($dashboardStatus < 200 || $dashboardStatus >= 400) {
    camex_az_probe_error('dashboard', 'Unexpected dashboard response status.', $dashboard, 1, [
        'web_login' => $webLoginDiagnostics,
        'login_form' => $formDiagnostics,
    ]);
}
if ($dashboardLooksLikeLogin) {
    camex_az_probe_error('web_login', 'Dashboard still looks like login page after web login', $dashboard, 1, [
        'web_login' => $webLoginDiagnostics,
        'login_form' => $formDiagnostics,
    ]);
}
if (!$dashboardLooksLikeAdmin) {
    camex_az_probe_error('web_login', 'Dashboard does not look like CAMEX_AZ admin page after web login.', $dashboard, 1, [
        'web_login' => $webLoginDiagnostics,
        'login_form' => $formDiagnostics,
    ]);
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
    'web_login' => $webLoginDiagnostics,
    'login_form' => $formDiagnostics,
    'session' => [
        'session_file' => $config->sessionCookieFile(),
        'cookie_file_exists' => is_file($config->sessionCookieFile()),
    ],
]);
