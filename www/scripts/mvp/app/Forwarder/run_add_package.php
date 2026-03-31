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
function forwarder_add_package_read_cli_kv(array $argv): array
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

function forwarder_add_package_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_add_package_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

function forwarder_add_package_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_add_package_path_from_url(string $raw, string $defaultPath): string
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
function forwarder_add_package_extract_form(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();

    if (!$loaded) {
        return ['ok' => false, 'action' => '', 'method' => 'POST', 'payload_defaults' => [], 'error' => 'invalid_html'];
    }

    $xpath = new DOMXPath($dom);
    $formNode = $xpath->query('//form[@id="form" and (contains(@action,"/collector/add") or contains(@action,"collector/add"))]')->item(0);
    if (!($formNode instanceof DOMElement)) {
        $formNode = $xpath->query('//form[contains(@action,"collector/add")]')->item(0);
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

    foreach ($xpath->query('.//textarea[@name]', $formNode) as $node) {
        if (!($node instanceof DOMElement)) {
            continue;
        }

        $name = trim((string)$node->getAttribute('name'));
        if ($name === '' || array_key_exists($name, $payloadDefaults)) {
            continue;
        }

        $payloadDefaults[$name] = trim((string)$node->textContent);
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

function forwarder_add_package_extract_csrf_token(string $html): string
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

function forwarder_add_package_is_non_zero_client_id(string $clientId): bool
{
    $normalized = trim($clientId);
    if ($normalized === '' || $normalized === '0') {
        return false;
    }

    return ctype_digit($normalized);
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_add_package_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_add_package_normalize_base_url(
    forwarder_add_package_arg($args, 'base-url', 'base_url')
);

forwarder_add_package_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_set_env('DEV_COLIBRI_LOGIN', forwarder_add_package_arg($args, 'login'));
forwarder_add_package_set_env('DEV_COLIBRI_PASSWORD', forwarder_add_package_arg($args, 'password'));
forwarder_add_package_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_set_env('FORWARDER_LOGIN', forwarder_add_package_arg($args, 'login'));
forwarder_add_package_set_env('FORWARDER_PASSWORD', forwarder_add_package_arg($args, 'password'));
forwarder_add_package_set_env('FORWARDER_SESSION_FILE', forwarder_add_package_arg($args, 'session-file', 'session_file'));
forwarder_add_package_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_add_package_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_add_package_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector';
}

$submitPathRaw = forwarder_add_package_arg($args, 'submit-path', 'submit_path', 'add-path', 'add_path', 'url');
$submitPath = forwarder_add_package_path_from_url($submitPathRaw, '/collector/add');

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_add_package: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-add-package-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_add_package: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$pageHtml = (string)($pageResponse['body'] ?? '');
$form = forwarder_add_package_extract_form($pageHtml);
$formFound = !empty($form['ok']);
$formAction = $formFound ? trim((string)($form['action'] ?? '')) : '';
$formMethod = $formFound ? strtoupper(trim((string)($form['method'] ?? 'POST'))) : 'POST';
if ($formMethod === '') {
    $formMethod = 'POST';
}
$payload = $formFound && is_array($form['payload_defaults'] ?? null) ? $form['payload_defaults'] : [];
$csrfToken = forwarder_add_package_extract_csrf_token($pageHtml);
if ($csrfToken !== '' && !array_key_exists('_token', $payload)) {
    $payload['_token'] = $csrfToken;
}

if ($formAction !== '' && $submitPathRaw === '') {
    $submitPath = forwarder_add_package_path_from_url($formAction, '/collector/add');
}

$payloadOverrides = [
    'total_images' => forwarder_add_package_arg($args, 'total-images', 'total_images'),
    'number' => forwarder_add_package_arg($args, 'number', 'track', 'tracking', 'tracking_number'),
    'length' => forwarder_add_package_arg($args, 'length'),
    'height' => forwarder_add_package_arg($args, 'height'),
    'width' => forwarder_add_package_arg($args, 'width'),
    'client_id' => forwarder_add_package_arg($args, 'client-id', 'client_id'),
    'client_name_surname' => forwarder_add_package_arg($args, 'client-name-surname', 'client_name_surname', 'client'),
    'seller' => forwarder_add_package_arg($args, 'seller'),
    'destination' => forwarder_add_package_arg($args, 'destination'),
    'gross_weight' => forwarder_add_package_arg($args, 'gross-weight', 'gross_weight', 'weight'),
    'currency' => forwarder_add_package_arg($args, 'currency'),
    'category' => forwarder_add_package_arg($args, 'category'),
    'invoice' => forwarder_add_package_arg($args, 'invoice'),
    'quantity' => forwarder_add_package_arg($args, 'quantity'),
    'container_id' => forwarder_add_package_arg($args, 'container-id', 'container_id'),
    'position' => forwarder_add_package_arg($args, 'position'),
    'tracking_internal_same' => forwarder_add_package_arg($args, 'tracking-internal-same', 'tracking_internal_same'),
    'status_id' => forwarder_add_package_arg($args, 'status-id', 'status_id'),
    'description' => forwarder_add_package_arg($args, 'description'),
    'tariff_type_id' => forwarder_add_package_arg($args, 'tariff-type-id', 'tariff_type_id'),
    'is_legal_entity' => forwarder_add_package_arg($args, 'is-legal-entity', 'is_legal_entity'),
    'invoice_status' => forwarder_add_package_arg($args, 'invoice-status', 'invoice_status'),
    'title' => forwarder_add_package_arg($args, 'title'),
    'subCat' => forwarder_add_package_arg($args, 'sub-cat', 'sub_cat', 'subCat'),
];

foreach ($payloadOverrides as $key => $value) {
    if ($value !== '') {
        $payload[$key] = $value;
    }
}

if (!array_key_exists('total_images', $payload) || trim((string)$payload['total_images']) === '') {
    $payload['total_images'] = '0';
}

$clientIdRaw = trim((string)($payload['client_id'] ?? '0'));
$clientIsSelected = forwarder_add_package_is_non_zero_client_id($clientIdRaw);
$statusOriginal = trim((string)($payload['status_id'] ?? ''));
$statusExpected = $clientIsSelected ? '37' : '36';
$statusWasAdjusted = $statusOriginal !== '' && $statusOriginal !== $statusExpected;
$payload['status_id'] = $statusExpected;

$requiredFields = ['number', 'destination', 'gross_weight', 'currency', 'quantity', 'status_id'];
$missingFields = [];
foreach ($requiredFields as $requiredField) {
    $value = trim((string)($payload[$requiredField] ?? ''));
    if ($value === '') {
        $missingFields[] = $requiredField;
    }
}

if (!$clientIsSelected) {
    $clientName = trim((string)($payload['client_name_surname'] ?? ''));
    if ($clientName === '') {
        $missingFields[] = 'client_name_surname';
    }
}

if ($missingFields !== []) {
    fwrite(STDERR, 'run_add_package: missing required payload fields: ' . implode(', ', $missingFields) . "\n");
    exit(6);
}

$submitResponse = $sessionClient->requestWithSession($formMethod, $submitPath, $payload, false);
$submitStatusCode = (int)($submitResponse['status_code'] ?? 0);
$submitOk = !empty($submitResponse['ok']) && $submitStatusCode >= 200 && $submitStatusCode < 400;
$submitBody = (string)($submitResponse['body'] ?? '');
$submitJson = json_decode($submitBody, true);
$submitCase = is_array($submitJson) ? trim((string)($submitJson['case'] ?? '')) : '';
$submitCaseNormalized = mb_strtolower($submitCase);
$submitCaseOk = in_array($submitCaseNormalized, ['success', 'warning'], true);
$overallOk = $submitOk && ($submitCase === '' || $submitCaseOk);

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Package add submitted via PHP session client'
        : 'Package add request failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'submit_path' => $submitPath,
    'submit_method' => $formMethod,
    'form_found' => $formFound,
    'form_error' => $formFound ? '' : (string)($form['error'] ?? 'unknown'),
    'csrf_token_found' => $csrfToken !== '',
    'client_id_mode' => $clientIsSelected ? 'selected_client' : 'manual_client_name',
    'status_id_original' => $statusOriginal,
    'status_id_effective' => $payload['status_id'],
    'status_id_was_adjusted' => $statusWasAdjusted,
    'http_status' => $submitStatusCode,
    'submit_case' => $submitCase,
    'error' => (string)($submitResponse['error'] ?? ''),
    'internal_id' => is_array($submitJson) ? (string)($submitJson['internal_id'] ?? '') : '',
    'amount_response' => is_array($submitJson) ? ($submitJson['amount_response'] ?? null) : null,
    'payload' => $payload,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 9);
