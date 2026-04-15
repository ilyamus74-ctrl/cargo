<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/**
 * Forwarder flight list runner (DEV_COLIBRI-oriented baseline).
 *
 * Example:
 *   php run_flight_list.php \
 *     --base-url=https://dev-backend.colibri.az \
 *     --login=demo \
 *     --password=secret \
 *     --page-path=/collector/flights
 */

/** @return array<string, string> */
function forwarder_flight_list_read_cli_kv(array $argv): array
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

function forwarder_flight_list_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_flight_list_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_flight_list_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_flight_list_parse_bool(string $value, bool $default = true): bool
{
    $normalized = mb_strtolower(trim($value));
    if ($normalized === '') {
        return $default;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
        return false;
    }

    return $default;
}

function forwarder_flight_list_extract_rows(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return ['rows' => 0, 'headers' => []];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return ['rows' => 0, 'headers' => []];
    }

    $xpath = new DOMXPath($dom);
    $rows = $xpath->query("//table[contains(concat(' ', normalize-space(@class), ' '), ' references-table ')]//tbody//tr");
    $headers = $xpath->query("//table[contains(concat(' ', normalize-space(@class), ' '), ' references-table ')]//thead//th");

    $headerValues = [];
    if ($headers instanceof DOMNodeList) {
        foreach ($headers as $headerNode) {
            $value = trim((string)$headerNode->textContent);
            if ($value !== '') {
                $headerValues[] = $value;
            }
        }
    }

    return [
        'rows' => $rows instanceof DOMNodeList ? $rows->length : 0,
        'headers' => $headerValues,
    ];
}


function forwarder_flight_list_import_rows(
    string $html,
    string $repoRoot,
    int $connectorId,
    string $targetTable,
    string $writeMode,
    string $containersTable = '',
    bool $syncContainers = true,
    string $tableSelector = 'table.references-table',
    string $onlyFlightExternalId = ''
): array {
    if ($targetTable === '') {
        return [
            'status' => 'skipped',
            'message' => 'target_table не указан, импорт пропущен',
            'imported_rows' => 0,
            'rows_detected' => 0,
            'write_mode' => $writeMode,
            'target_table' => '',
        ];
    }

    if ($connectorId <= 0) {
        throw new RuntimeException('run_flight_list: для импорта укажите --connector-id > 0');
    }

    $connectDbPath = $repoRoot . '/configs/connectDB.php';
    $enginePath = $repoRoot . '/www/api/connectors/connector_engine.php';
    $subrunnerPath = $repoRoot . '/www/api/connectors/subrunners/connector_modules.php';
    foreach ([$connectDbPath, $enginePath, $subrunnerPath] as $requiredPath) {
        if (!is_file($requiredPath)) {
            throw new RuntimeException('run_flight_list: required file not found: ' . $requiredPath);
        }
        require_once $requiredPath;
    }

    if (!class_exists('mysqli')) {
        return [
            'status' => 'skipped',
            'message' => 'mysqli extension is not available, импорт пропущен',
            'imported_rows' => 0,
            'rows_detected' => 0,
            'rows_skipped' => 0,
            'write_mode' => $writeMode,
            'target_table' => $targetTable,
            'headers_detected' => [],
            'errors' => [],
        ];
    }

    // connectDB.php подключается внутри функции и может создать $dbcnx в локальном scope.
    // Пробрасываем его в $GLOBALS, чтобы subrunner использовал активное соединение.
    if (isset($dbcnx) && $dbcnx instanceof mysqli) {
        $GLOBALS['dbcnx'] = $dbcnx;
    }

    $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
    if (!($db instanceof mysqli)) {
        return [
            'status' => 'skipped',
            'message' => 'mysqli connection is not available, импорт пропущен',
            'imported_rows' => 0,
            'rows_detected' => 0,
            'rows_skipped' => 0,
            'write_mode' => $writeMode,
            'target_table' => $targetTable,
            'headers_detected' => [],
            'errors' => [],
        ];
    }

    $mode = strtolower(trim($writeMode));
    if (!in_array($mode, ['upsert', 'insert'], true)) {
        $mode = 'upsert';
    }

    $ctx = [
        'connector_id' => $connectorId,
        'connector' => [
            'id' => $connectorId,
            'name' => 'forwarder_flight_list',
            'base_url' => '',
            'ssl_ignore' => 0,
        ],
        'browser' => [
            'final_html' => $html,
        ],
    ];
    $options = [
        'table_name' => $targetTable,
        'containers_table_name' => $containersTable,
        'table_selector' => $tableSelector,
        'write_mode' => $mode,
        'sync_containers' => $syncContainers,
        'only_flight_external_id' => trim($onlyFlightExternalId),
        'timezone' => 'UTC',
    ];
    $subrunnerResult = connectors_subrunner_run_flight_list_colibri($ctx, $options);

    $subrunnerStatus = strtolower(trim((string)($subrunnerResult['status'] ?? 'ok')));
    $subrunnerErrors = isset($subrunnerResult['errors']) && is_array($subrunnerResult['errors']) ? $subrunnerResult['errors'] : [];
    $hasOnlyContainerSelectorErrors = false;
    if ($syncContainers && $subrunnerErrors !== []) {
        $hasOnlyContainerSelectorErrors = true;
        foreach ($subrunnerErrors as $entry) {
            $entryMessage = mb_strtolower(trim((string)($entry['message'] ?? '')));
            if ($entryMessage === '' || !str_contains($entryMessage, 'таблица не найдена по селектору')) {
                $hasOnlyContainerSelectorErrors = false;
                break;
            }
        }
    }
    if ($hasOnlyContainerSelectorErrors && in_array($subrunnerStatus, ['error', 'failed'], true)) {
        $subrunnerStatus = 'warning';
    }

    return [
        'status' => $subrunnerStatus,
        'message' => (string)($subrunnerResult['message'] ?? ''),
        'imported_rows' => (int)($subrunnerResult['metrics']['rows_written'] ?? 0),
        'rows_detected' => (int)($subrunnerResult['metrics']['rows_extracted'] ?? 0),
        'rows_skipped' => (int)($subrunnerResult['metrics']['rows_skipped'] ?? 0),
        'write_mode' => $mode,
        'target_table' => (string)($subrunnerResult['meta']['table_name'] ?? $targetTable),
        'containers_table_name' => (string)($subrunnerResult['meta']['containers_table_name'] ?? $containersTable),
        'headers_detected' => isset($subrunnerResult['meta']['detected_headers']) && is_array($subrunnerResult['meta']['detected_headers'])
            ? $subrunnerResult['meta']['detected_headers']
            : [],
        'errors' => $subrunnerErrors,
    ];
}
$argv = $_SERVER['argv'] ?? [];
$args = forwarder_flight_list_read_cli_kv($argv);

// Allow passing either domain root (recommended) or login URL.
// Example accepted inputs:
//   https://dev-backend.colibri.az
//   https://dev-backend.colibri.az/login
$normalizedBaseUrl = forwarder_flight_list_normalize_base_url(forwarder_flight_list_arg($args, 'base-url', 'base_url'));

forwarder_flight_list_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_flight_list_set_env('DEV_COLIBRI_LOGIN', forwarder_flight_list_arg($args, 'login'));
forwarder_flight_list_set_env('DEV_COLIBRI_PASSWORD', forwarder_flight_list_arg($args, 'password'));
forwarder_flight_list_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_flight_list_set_env('FORWARDER_LOGIN', forwarder_flight_list_arg($args, 'login'));
forwarder_flight_list_set_env('FORWARDER_PASSWORD', forwarder_flight_list_arg($args, 'password'));
forwarder_flight_list_set_env('FORWARDER_SESSION_FILE', forwarder_flight_list_arg($args, 'session-file', 'session_file'));
forwarder_flight_list_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_flight_list_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_flight_list_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/flights';
if ($pagePath === '' || $pagePath[0] !== '/') {
    $pagePath = '/collector/flights';
}

$repoRoot = dirname(__DIR__, 5);
$targetTable = forwarder_flight_list_arg($args, 'target-table', 'target_table');
$targetTable = $targetTable !== '' ? $targetTable : trim((string)getenv('FORWARDER_TARGET_TABLE'));

$containersTable = forwarder_flight_list_arg($args, 'containers-table', 'containers_table');
if ($containersTable === '' && $targetTable !== '' && str_ends_with($targetTable, '_containers')) {
    $containersTable = $targetTable;
    $targetTable = preg_replace('/_containers$/', '', $targetTable) ?? '';
}
$writeMode = forwarder_flight_list_arg($args, 'write-mode', 'write_mode');
$writeMode = $writeMode !== '' ? $writeMode : 'upsert';
$syncContainers = forwarder_flight_list_parse_bool(
    forwarder_flight_list_arg($args, 'sync-containers', 'sync_containers'),
    true
);
$tableSelector = forwarder_flight_list_arg($args, 'table-selector', 'table_selector');
$tableSelector = $tableSelector !== '' ? $tableSelector : 'table.references-table';
$connectorIdRaw = forwarder_flight_list_arg($args, 'connector-id', 'connector_id');
$connectorId = (int)($connectorIdRaw !== '' ? $connectorIdRaw : 0);
$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_flight_list: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-flight-list-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$response = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$statusCode = (int)($response['status_code'] ?? 0);
if (empty($response['ok']) || $statusCode < 200 || $statusCode >= 400) {
    $error = trim((string)($response['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_flight_list: request failed: status=' . $statusCode . ' error=' . $error . "\n");
    exit(4);
}

$body = (string)($response['body'] ?? '');
$parsed = forwarder_flight_list_extract_rows($body);
$rows = (int)($parsed['rows'] ?? 0);
$headers = isset($parsed['headers']) && is_array($parsed['headers']) ? $parsed['headers'] : [];
$import = forwarder_flight_list_import_rows(
    $body,
    $repoRoot,
    $connectorId,
    $targetTable,
    $writeMode,
    $containersTable,
    $syncContainers,
    $tableSelector
);

$result = [
    'status' => 'ok',
    'message' => 'Flight list page fetched via PHP session client',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'rows_detected' => $rows,
    'headers_detected' => $headers,
    'connector_id' => $connectorId,
    'target_table' => (string)($import['target_table'] ?? $targetTable),
    'containers_table' => (string)($import['containers_table_name'] ?? $containersTable),
    'sync_containers' => $syncContainers,
    'write_mode' => (string)($import['write_mode'] ?? $writeMode),
    'imported_rows' => (int)($import['imported_rows'] ?? 0),
    'rows_skipped' => (int)($import['rows_skipped'] ?? 0),
    'import_status' => (string)($import['status'] ?? 'skipped'),
    'import_message' => (string)($import['message'] ?? ''),
    'import_errors' => isset($import['errors']) && is_array($import['errors']) ? $import['errors'] : [],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);

