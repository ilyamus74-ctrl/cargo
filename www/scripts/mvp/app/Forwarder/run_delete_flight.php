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
function forwarder_delete_flight_read_cli_kv(array $argv): array
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

function forwarder_delete_flight_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_delete_flight_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_delete_flight_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_delete_flight_path_from_url(string $raw, string $defaultPath): string
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

/** @return array{action:string,method:string,payload_defaults:array<string,string>,search_field:string} */
function forwarder_delete_flight_extract_search_form(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return [
            'action' => '/collector/flights',
            'method' => 'GET',
            'payload_defaults' => [],
            'search_field' => 'search_values',
        ];
    }

    $xpath = new DOMXPath($dom);
    $inputNode = $xpath->query('//input[@id="search_values" or @name="search_values"]')->item(0);
    if (!($inputNode instanceof DOMElement)) {
        return [
            'action' => '/collector/flights',
            'method' => 'GET',
            'payload_defaults' => [],
            'search_field' => 'search_values',
        ];
    }

    $searchField = trim((string)$inputNode->getAttribute('column_name'));
    if ($searchField === '') {
        $searchField = trim((string)$inputNode->getAttribute('name'));
    }
    if ($searchField === '') {
        $searchField = 'search_values';
    }

    $formNode = $inputNode;
    while ($formNode !== null && !($formNode instanceof DOMElement && strtolower($formNode->tagName) === 'form')) {
        $formNode = $formNode->parentNode;
    }

    if (!($formNode instanceof DOMElement)) {
        return [
            'action' => '/collector/flights',
            'method' => 'GET',
            'payload_defaults' => [
                'search' => '1',
            ],
            'search_field' => $searchField,
        ];
    }

    $action = trim((string)$formNode->getAttribute('action'));
    $method = strtoupper(trim((string)$formNode->getAttribute('method')));
    if ($method === '') {
        $method = 'GET';
    }

    $payloadDefaults = [];
    foreach ($xpath->query('.//input[@name]', $formNode) as $input) {
        if (!($input instanceof DOMElement)) {
            continue;
        }

        $name = trim((string)$input->getAttribute('name'));
        if ($name === '') {
            continue;
        }

        $type = strtolower(trim((string)$input->getAttribute('type')));
        if (in_array($type, ['submit', 'button', 'file'], true)) {
            continue;
        }

        if (array_key_exists($name, $payloadDefaults)) {
            continue;
        }

        $payloadDefaults[$name] = (string)$input->getAttribute('value');
    }

    return [
        'action' => $action,
        'method' => $method,
        'payload_defaults' => $payloadDefaults,
        'search_field' => $searchField,
    ];
}


function forwarder_delete_flight_extract_csrf_token(DOMXPath $xpath): string
{
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

function forwarder_delete_flight_extract_row_id(DOMElement $rowNode): string
{
    $rowIdAttr = trim((string)$rowNode->getAttribute('id'));
    if ($rowIdAttr !== '' && preg_match('/(\d+)/', $rowIdAttr, $m)) {
        return (string)$m[1];
    }

    $onclick = trim((string)$rowNode->getAttribute('onclick'));
    if ($onclick !== '' && preg_match('/select_row\((\d+)\)/', $onclick, $m)) {
        return (string)$m[1];
    }

    $firstCell = $rowNode->getElementsByTagName('td')->item(0);
    if ($firstCell instanceof DOMElement) {
        $firstCellText = trim((string)$firstCell->textContent);
        if ($firstCellText !== '' && preg_match('/^\d+$/', $firstCellText)) {
            return $firstCellText;
        }
    }

    return '';
}

/** @return array{ok:bool,delete_path:string,delete_method:string,delete_payload:array<string,string>,error:string} */
function forwarder_delete_flight_extract_delete_path(string $html, string $targetFlightId, string $flightSearchValue, string $pagePath): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return ['ok' => false, 'delete_path' => '', 'delete_method' => 'GET', 'delete_payload' => [], 'error' => 'invalid_html'];
    }

    $xpath = new DOMXPath($dom);
    $rowNodes = $xpath->query('//table[contains(@class,"references-table")]//tbody//tr');
    if (!($rowNodes instanceof DOMNodeList) || $rowNodes->length === 0) {
        return ['ok' => false, 'delete_path' => '', 'delete_method' => 'GET', 'delete_payload' => [], 'error' => 'rows_not_found'];
    }

    $normalizedTargetId = mb_strtolower(trim($targetFlightId));
    $normalizedSearch = mb_strtolower(trim($flightSearchValue));
    $csrfToken = forwarder_delete_flight_extract_csrf_token($xpath);

    foreach ($rowNodes as $rowNode) {
        if (!($rowNode instanceof DOMElement)) {
            continue;
        }
        $rowId = forwarder_delete_flight_extract_row_id($rowNode);
        $rowText = mb_strtolower(trim((string)$rowNode->textContent));
        $targetIdMatched = ($normalizedTargetId !== '' && (
            str_contains($rowText, $normalizedTargetId)
            || ($rowId !== '' && mb_strtolower($rowId) === $normalizedTargetId)
        ));
        $searchMatched = ($normalizedSearch !== '' && str_contains($rowText, $normalizedSearch));

        // В ряде интерфейсов ID рейса не отображается в строке таблицы.
        // Поэтому, если ID не найден в тексте строки, допускаем fallback по номеру рейса.
        if ($normalizedTargetId !== '' || $normalizedSearch !== '') {
            if (!$targetIdMatched && !$searchMatched) {
                continue;
            }
        } elseif ($rowText === '') {
            continue;
        }

        $deleteLink = $xpath->query('.//a[contains(@class,"action-btn")][contains(@onclick,"/collector/flights/delete") or contains(@href,"/collector/flights/delete")]', $rowNode)->item(0);

        if ($deleteLink instanceof DOMElement) {
            $onclick = trim((string)$deleteLink->getAttribute('onclick'));
            $href = trim((string)$deleteLink->getAttribute('href'));
            $candidate = '';

            if ($onclick !== '' && preg_match('#(/collector/flights/delete[^"\'\s]*)#', $onclick, $m)) {
                $candidate = (string)$m[1];
            }
            if ($candidate === '' && $href !== '' && str_contains($href, '/collector/flights/delete')) {
                $candidate = $href;
            }

            if ($candidate !== '') {
                return [
                    'ok' => true,
                    'delete_path' => forwarder_delete_flight_path_from_url($candidate, $pagePath),
                    'delete_method' => 'GET',
                    'delete_payload' => [],
                    'error' => '',
                ];
            }
        }

        // Новый UI может не рендерить delete link в строке списка.
        // Тогда используем id строки таблицы и CSRF, как в реальном DELETE-запросе интерфейса.
        if ($rowId !== '') {
            $payload = [
                'id' => $rowId,
            ];
            if ($csrfToken !== '') {
                $payload['_token'] = $csrfToken;
            }

            return [
                'ok' => true,
                'delete_path' => '/collector/flights/delete',
                'delete_method' => 'DELETE',
                'delete_payload' => $payload,
                'error' => '',
            ];
        }
    }
    return ['ok' => false, 'delete_path' => '', 'delete_method' => 'GET', 'delete_payload' => [], 'error' => 'delete_link_not_found'];
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_delete_flight_read_cli_kv($argv);

$normalizedBaseUrl = forwarder_delete_flight_normalize_base_url(forwarder_delete_flight_arg($args, 'base-url', 'base_url'));

forwarder_delete_flight_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_delete_flight_set_env('DEV_COLIBRI_LOGIN', forwarder_delete_flight_arg($args, 'login'));
forwarder_delete_flight_set_env('DEV_COLIBRI_PASSWORD', forwarder_delete_flight_arg($args, 'password'));
forwarder_delete_flight_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_delete_flight_set_env('FORWARDER_LOGIN', forwarder_delete_flight_arg($args, 'login'));
forwarder_delete_flight_set_env('FORWARDER_PASSWORD', forwarder_delete_flight_arg($args, 'password'));
forwarder_delete_flight_set_env('FORWARDER_SESSION_FILE', forwarder_delete_flight_arg($args, 'session-file', 'session_file'));
forwarder_delete_flight_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_delete_flight_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_delete_flight_arg($args, 'page-path', 'page_path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/flights';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/collector/flights';
}

$flightSearchValue = forwarder_delete_flight_arg($args, 'flight-search', 'flight_search_value', 'flight', 'flight_no');
$targetFlightId = forwarder_delete_flight_arg($args, 'target-flight-id', 'target_flight_id', 'flight_id', 'external_id');

if ($flightSearchValue === '' && $targetFlightId === '') {
    fwrite(STDERR, "run_delete_flight: missing args --flight-search and/or --target-flight-id\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_delete_flight: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-delete-flight-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
$pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
    $error = trim((string)($pageResponse['error'] ?? 'request_failed'));
    fwrite(STDERR, 'run_delete_flight: page request failed: status=' . $pageStatusCode . ' error=' . $error . "\n");
    exit(4);
}

$searchBody = (string)($pageResponse['body'] ?? '');
$searchForm = forwarder_delete_flight_extract_search_form($searchBody);
$searchActionPath = forwarder_delete_flight_path_from_url((string)($searchForm['action'] ?? ''), $pagePath);
$searchMethod = strtoupper(trim((string)($searchForm['method'] ?? 'GET')));
$searchField = trim((string)($searchForm['search_field'] ?? 'search_values'));
if ($searchField === '') {
    $searchField = 'search_values';
}
$searchPayload = isset($searchForm['payload_defaults']) && is_array($searchForm['payload_defaults']) ? $searchForm['payload_defaults'] : [];

$searchPayload[$searchField] = $flightSearchValue !== '' ? $flightSearchValue : $targetFlightId;
if (!array_key_exists('search', $searchPayload)) {
    $searchPayload['search'] = '1';
}
$searchResponse = $sessionClient->requestWithSession($searchMethod === 'POST' ? 'POST' : 'GET', $searchActionPath, $searchPayload, false);
$searchStatusCode = (int)($searchResponse['status_code'] ?? 0);
if (empty($searchResponse['ok']) || $searchStatusCode < 200 || $searchStatusCode >= 400) {
    $error = trim((string)($searchResponse['error'] ?? 'search_request_failed'));
    fwrite(STDERR, 'run_delete_flight: search request failed: status=' . $searchStatusCode . ' error=' . $error . "\n");
    exit(5);
}

$searchResultHtml = (string)($searchResponse['body'] ?? '');
$deleteTarget = forwarder_delete_flight_extract_delete_path($searchResultHtml, $targetFlightId, $flightSearchValue, $pagePath);
if (empty($deleteTarget['ok'])) {
    $error = trim((string)($deleteTarget['error'] ?? 'delete_target_not_found'));
    fwrite(STDERR, 'run_delete_flight: delete target parse failed: ' . $error . "\n");
    exit(6);
}

$deletePath = (string)($deleteTarget['delete_path'] ?? '');
$deleteMethod = strtoupper(trim((string)($deleteTarget['delete_method'] ?? 'GET')));
$deletePayload = isset($deleteTarget['delete_payload']) && is_array($deleteTarget['delete_payload']) ? $deleteTarget['delete_payload'] : [];
$deleteHttpMethod = in_array($deleteMethod, ['POST', 'DELETE'], true) ? $deleteMethod : 'GET';
$deleteResponse = $sessionClient->requestWithSession($deleteHttpMethod, $deletePath, $deletePayload, false);
$deleteStatusCode = (int)($deleteResponse['status_code'] ?? 0);
$deleteOk = !empty($deleteResponse['ok']) && $deleteStatusCode >= 200 && $deleteStatusCode < 400;

$result = [
    'status' => $deleteOk ? 'ok' : 'error',
    'message' => $deleteOk ? 'Flight delete submitted via PHP session client' : 'Flight delete submit failed',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'search_path' => $searchActionPath,
    'search_method' => $searchMethod === 'POST' ? 'POST' : 'GET',
    'search_field' => $searchField,
    'search_value' => $searchPayload[$searchField] ?? '',
    'search_response_status' => $searchStatusCode,
    'search_response_error' => (string)($searchResponse['error'] ?? ''),
    'search_response_body' => $searchResultHtml,
    'target_flight_id' => $targetFlightId,
    'delete_path' => $deletePath,
    'delete_method' => $deleteHttpMethod,
    'http_status' => $deleteStatusCode,
    'error' => (string)($deleteResponse['error'] ?? ''),
    'delete_response_body' => (string)($deleteResponse['body'] ?? ''),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($deleteOk ? 0 : 1);
