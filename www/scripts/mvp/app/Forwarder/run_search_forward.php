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
function forwarder_search_read_cli_kv(array $argv): array
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

function forwarder_search_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_search_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

function forwarder_search_normalize_base_url(string $rawBaseUrl): string
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

/** @return array<string, string> */
function forwarder_search_build_query(array $args): array
{
    $query = [
        'code' => forwarder_search_arg($args, 'code', 'track', 'number'),
        'internal_id' => forwarder_search_arg($args, 'internal-id', 'internal_id'),
        'client' => forwarder_search_arg($args, 'client', 'client-name', 'client_name'),
        'seller' => forwarder_search_arg($args, 'seller'),
        'from_date' => forwarder_search_arg($args, 'from-date', 'from_date'),
        'to_date' => forwarder_search_arg($args, 'to-date', 'to_date'),
        'page' => forwarder_search_arg($args, 'page'),
    ];

    if ($query['page'] === '' || !ctype_digit($query['page']) || (int)$query['page'] <= 0) {
        $query['page'] = '1';
    }

    return $query;
}

/** @return array<string, mixed>|null */
function forwarder_search_find_exact_match(array $rows, array $query): ?array
{
    $code = strtoupper(trim((string)($query['code'] ?? '')));
    $internalId = strtoupper(trim((string)($query['internal_id'] ?? '')));

    if ($rows === [] || ($code === '' && $internalId === '')) {
        return null;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rowCode = strtoupper(trim((string)($row['number'] ?? '')));
        $rowInternalId = strtoupper(trim((string)($row['internal_id'] ?? '')));
        if (($code !== '' && $rowCode === $code) || ($internalId !== '' && $rowInternalId === $internalId)) {
            return $row;
        }
    }

    return null;
}

/** @return array<string, mixed> */
function forwarder_search_order_summary(array $row): array
{
    return [
        'id' => $row['id'] ?? null,
        'number' => $row['number'] ?? null,
        'internal_id' => $row['internal_id'] ?? null,
        'client_name_surname' => $row['client_name_surname'] ?? null,
        'seller' => $row['seller'] ?? null,
        'status' => $row['status'] ?? null,
        'category' => $row['category'] ?? null,
        'gross_weight' => $row['gross_weight'] ?? null,
        'amount' => $row['amount'] ?? null,
        'amount_currency' => $row['amount_currency'] ?? null,
        'created_at' => $row['created_at'] ?? null,
    ];
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_search_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_search_normalize_base_url(
    forwarder_search_arg($args, 'base-url', 'base_url')
);

forwarder_search_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_search_set_env('DEV_COLIBRI_LOGIN', forwarder_search_arg($args, 'login'));
forwarder_search_set_env('DEV_COLIBRI_PASSWORD', forwarder_search_arg($args, 'password'));
forwarder_search_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_search_set_env('FORWARDER_LOGIN', forwarder_search_arg($args, 'login'));
forwarder_search_set_env('FORWARDER_PASSWORD', forwarder_search_arg($args, 'password'));
forwarder_search_set_env('FORWARDER_SESSION_FILE', forwarder_search_arg($args, 'session-file', 'session_file'));
forwarder_search_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_search_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$query = forwarder_search_build_query($args);
$result = [];

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_search_forward: missing env config (FORWARDER_BASE_URL/FORWARDER_LOGIN/FORWARDER_PASSWORD)\n");
    exit(3);
}

if (!$config->isFlowEnabled()) {
    fwrite(STDERR, "run_search_forward: flow disabled by FORWARDER_FLOW_ENABLED\n");
    exit(4);
}

$correlationId = 'run-search-forward-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);

$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$path = '/collector/anonymous/show';
$response = $sessionClient->requestWithSession('GET', $path, $query, false);
$statusCode = (int)($response['status_code'] ?? 0);
$decoded = json_decode((string)($response['body'] ?? ''), true);
$json = is_array($response['json'] ?? null) ? $response['json'] : (is_array($decoded) ? $decoded : null);

$orders = is_array($json) && is_array($json['orders'] ?? null) ? $json['orders'] : [];
$rows = is_array($orders['data'] ?? null) ? $orders['data'] : [];
$exactMatch = forwarder_search_find_exact_match($rows, $query);

$httpOk = !empty($response['ok']) && $statusCode >= 200 && $statusCode < 400;
$businessSuccess = is_array($json) && (($json['case'] ?? '') === 'success');
$status = 'TEMP_ERROR';
if ($httpOk && $businessSuccess) {
    $status = count($rows) > 0 ? 'FOUND' : 'NOT_FOUND';
}

$result = [
    'status' => $status,
    'mode' => 'forwarder_search',
    'query' => $query,
    'request' => [
        'method' => 'GET',
        'path' => $path,
    ],
    'response_meta' => [
        'http_status' => $statusCode,
        'latency_ms' => (int)($response['latency_ms'] ?? 0),
        'error' => (string)($response['error'] ?? ''),
    ],
    'result' => [
        'total' => (int)($orders['total'] ?? 0),
        'current_page' => (int)($orders['current_page'] ?? 0),
        'last_page' => (int)($orders['last_page'] ?? 0),
        'count_on_page' => count($rows),
        'exact_match_found' => $exactMatch !== null,
        'exact_match' => $exactMatch !== null ? forwarder_search_order_summary($exactMatch) : null,
        'orders' => array_map(
            static fn ($row): array => is_array($row) ? forwarder_search_order_summary($row) : [],
            $rows
        ),
    ],
    'raw_case' => is_array($json) ? (string)($json['case'] ?? '') : '',
    'correlation_id' => $correlationId,
];

if (!$httpOk || !$businessSuccess) {
    $result['message'] = 'search request failed or business case != success';
    $result['raw_body'] = (string)($response['body'] ?? '');
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

$okStatuses = ['FOUND', 'NOT_FOUND'];
exit(in_array($status, $okStatuses, true) ? 0 : 1);
