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
function forwarder_list_container_packages_cli_kv(array $argv): array
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

function forwarder_list_container_packages_arg(array $args, string $primaryKey, string ...$aliases): string
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

function forwarder_list_container_packages_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_list_container_packages_normalize_base_url(string $rawBaseUrl): string
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

function forwarder_list_container_packages_bool(string $value): bool
{
    return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true);
}

/** @return array{ok:bool,http_ok:bool,http_status:int,error:string,json:mixed,raw_body:string,case:string,change:mixed,content:string} */
function forwarder_list_container_packages_position_check(
    ForwarderSessionClient $sessionClient,
    string $checkPath,
    string $position
): array {
    $response = $sessionClient->requestWithSession('POST', $checkPath, ['position' => $position], true);
    $statusCode = (int)($response['status_code'] ?? 0);
    $json = is_array($response['json'] ?? null)
        ? $response['json']
        : json_decode((string)($response['body'] ?? ''), true);
    $case = is_array($json) ? mb_strtolower(trim((string)($json['case'] ?? ''))) : '';
    $businessOk = is_array($json) && in_array($case, ['success', 'warning'], true);
    $httpOk = !empty($response['ok']) && $statusCode >= 200 && $statusCode < 400;

    return [
        'ok' => $httpOk && $businessOk,
        'http_ok' => $httpOk,
        'http_status' => $statusCode,
        'error' => (string)($response['error'] ?? ''),
        'json' => $json,
        'raw_body' => (string)($response['body'] ?? ''),
        'case' => is_array($json) ? (string)($json['case'] ?? '') : '',
        'change' => is_array($json) ? ($json['change'] ?? null) : null,
        'content' => is_array($json) ? (string)($json['content'] ?? '') : '',
    ];
}

/** @return array{headers:array<int,string>,rows:array<int,array<string,string>>,is_empty:bool} */
function forwarder_list_container_packages_extract_table(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return ['headers' => [], 'rows' => [], 'is_empty' => true];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return ['headers' => [], 'rows' => [], 'is_empty' => true];
    }

    $xpath = new DOMXPath($dom);
    $table = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " references-table ")]')->item(0);
    if (!($table instanceof DOMElement)) {
        return ['headers' => [], 'rows' => [], 'is_empty' => true];
    }

    $headers = [];
    foreach ($xpath->query('.//thead//th', $table) as $thNode) {
        if (!($thNode instanceof DOMElement)) {
            continue;
        }

        $header = trim((string)$thNode->textContent);
        $headers[] = $header;
    }

    $rows = [];
    $isEmpty = false;
    foreach ($xpath->query('.//tbody//tr', $table) as $rowNode) {
        if (!($rowNode instanceof DOMElement)) {
            continue;
        }

        $colspanNode = $xpath->query('./td[@colspan]', $rowNode)->item(0);
        if ($colspanNode instanceof DOMElement) {
            $text = trim((string)$colspanNode->textContent);
            if ($text !== '' && mb_stripos($text, 'no packages') !== false) {
                $isEmpty = true;
                continue;
            }
        }

        $cells = [];
        foreach ($xpath->query('./td', $rowNode) as $tdNode) {
            if ($tdNode instanceof DOMElement) {
                $cells[] = trim((string)$tdNode->textContent);
            }
        }

        if ($cells === []) {
            continue;
        }

        $assoc = [];
        foreach ($headers as $idx => $header) {
            $key = $header !== '' ? $header : ('col_' . ($idx + 1));
            $assoc[$key] = (string)($cells[$idx] ?? '');
        }
        if ($assoc === []) {
            foreach ($cells as $idx => $value) {
                $assoc['col_' . ($idx + 1)] = $value;
            }
        }

        $rows[] = $assoc;
    }

    if ($rows === [] && $isEmpty === false) {
        $isEmpty = true;
    }

    return [
        'headers' => $headers,
        'rows' => $rows,
        'is_empty' => $isEmpty,
    ];
}

/** @param array<int,array<string,string>> $rows */
function forwarder_list_container_packages_has_select_placeholder(array $rows): bool
{
    if ($rows === []) {
        return false;
    }

    $firstRow = $rows[0] ?? [];
    if (!is_array($firstRow) || $firstRow === []) {
        return false;
    }

    $firstValue = '';
    foreach ($firstRow as $value) {
        $firstValue = trim((string)$value);
        if ($firstValue !== '') {
            break;
        }
    }

    if ($firstValue === '') {
        return false;
    }

    return mb_stripos($firstValue, 'please select flight or container') !== false;
}

/** @return array{current:int,last:int,available_pages:array<int,int>} */
function forwarder_list_container_packages_extract_pagination(string $html): array
{
    $result = ['current' => 1, 'last' => 1, 'available_pages' => [1]];
    $html = trim($html);
    if ($html === '') {
        return $result;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return $result;
    }

    $xpath = new DOMXPath($dom);
    $pageSet = [1 => true];
    foreach ($xpath->query('//a[@href]') as $aNode) {
        if (!($aNode instanceof DOMElement)) {
            continue;
        }

        $href = (string)$aNode->getAttribute('href');
        if ($href === '' || !preg_match('/(?:^|[?&])page=(\d+)/', $href, $m)) {
            continue;
        }

        $page = (int)$m[1];
        if ($page > 0) {
            $pageSet[$page] = true;
        }
    }

    $currentNode = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " active ")]')->item(0);
    if ($currentNode instanceof DOMElement) {
        $currentText = trim((string)$currentNode->textContent);
        if (ctype_digit($currentText) && (int)$currentText > 0) {
            $result['current'] = (int)$currentText;
            $pageSet[(int)$currentText] = true;
        }
    }

    $pages = array_keys($pageSet);
    sort($pages, SORT_NUMERIC);

    $result['available_pages'] = $pages;
    $result['last'] = (int)max($pages);

    return $result;
}

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_list_container_packages_cli_kv($argv);

$normalizedBaseUrl = forwarder_list_container_packages_normalize_base_url(
    forwarder_list_container_packages_arg($args, 'base-url', 'base_url')
);

forwarder_list_container_packages_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_list_container_packages_set_env('DEV_COLIBRI_LOGIN', forwarder_list_container_packages_arg($args, 'login'));
forwarder_list_container_packages_set_env('DEV_COLIBRI_PASSWORD', forwarder_list_container_packages_arg($args, 'password'));
forwarder_list_container_packages_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_list_container_packages_set_env('FORWARDER_LOGIN', forwarder_list_container_packages_arg($args, 'login'));
forwarder_list_container_packages_set_env('FORWARDER_PASSWORD', forwarder_list_container_packages_arg($args, 'password'));
forwarder_list_container_packages_set_env('FORWARDER_SESSION_FILE', forwarder_list_container_packages_arg($args, 'session-file', 'session_file'));
forwarder_list_container_packages_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_list_container_packages_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$pagePath = forwarder_list_container_packages_arg($args, 'page-path', 'page_path', 'path');
$pagePath = $pagePath !== '' ? $pagePath : '/collector/packages';
if (!str_starts_with($pagePath, '/')) {
    $pagePath = '/' . ltrim($pagePath, '/');
}

$pageStart = (int)forwarder_list_container_packages_arg($args, 'page', 'page-start', 'page_start');
if ($pageStart <= 0) {
    $pageStart = 1;
}

$allPages = forwarder_list_container_packages_bool(
    forwarder_list_container_packages_arg($args, 'all-pages', 'all_pages')
);
$maxPages = (int)forwarder_list_container_packages_arg($args, 'max-pages', 'max_pages');
if ($maxPages <= 0) {
    $maxPages = 20;
}

$position = forwarder_list_container_packages_arg($args, 'position', 'container', 'container_id');
$checkPath = forwarder_list_container_packages_arg($args, 'check-path', 'check_path');
$checkPath = $checkPath !== '' ? $checkPath : '/collect/check-position';

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_list_container: missing config (base-url/login/password)\n");
    exit(3);
}

$correlationId = 'run-list-container-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$positionCheck = null;
if ($position !== '') {
    $positionCheck = forwarder_list_container_packages_position_check($sessionClient, $checkPath, $position);
    if (empty($positionCheck['ok'])) {
        $result = [
            'status' => 'error',
            'message' => 'Container position activation failed before packages fetch',
            'correlation_id' => $correlationId,
            'base_url' => $config->baseUrl(),
            'page_path' => $pagePath,
            'position' => $position,
            'check_path' => $checkPath,
            'position_check' => $positionCheck,
            'count' => 0,
            'headers' => [],
            'packages' => [],
        ];
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(9);
    }
}

$pagesVisited = [];
$visited = [];
$rows = [];
$headers = [];
$lastPagination = ['current' => $pageStart, 'last' => $pageStart, 'available_pages' => [$pageStart]];
$errorMessage = '';
$errorStatusCode = 0;

$pending = [$pageStart];
while ($pending !== [] && count($pagesVisited) < $maxPages) {
    $page = (int)array_shift($pending);
    if ($page <= 0 || isset($visited[$page])) {
        continue;
    }
    $visited[$page] = true;

    $payload = $page > 1 ? ['page' => (string)$page] : [];
    if ($position !== '') {
        $payload['position'] = $position;
    }
    $response = $sessionClient->requestWithSession('GET', $pagePath, $payload, false);
    $statusCode = (int)($response['status_code'] ?? 0);
    if (empty($response['ok']) || $statusCode < 200 || $statusCode >= 400) {
        $errorMessage = (string)($response['error'] ?? 'request_failed');
        $errorStatusCode = $statusCode;
        break;
    }

    $body = (string)($response['body'] ?? '');
    $table = forwarder_list_container_packages_extract_table($body);
    $usedContainerFilter = false;
    if (
        $position !== ''
        && forwarder_list_container_packages_has_select_placeholder($table['rows'])
    ) {
        $payloadWithContainer = $payload;
        $payloadWithContainer['container'] = $position;
        $payloadWithContainer['search'] = '1';
        $retryResponse = $sessionClient->requestWithSession('GET', $pagePath, $payloadWithContainer, false);
        $retryStatusCode = (int)($retryResponse['status_code'] ?? 0);
        if (!empty($retryResponse['ok']) && $retryStatusCode >= 200 && $retryStatusCode < 400) {
            $response = $retryResponse;
            $statusCode = $retryStatusCode;
            $body = (string)($retryResponse['body'] ?? '');
            $table = forwarder_list_container_packages_extract_table($body);
            $usedContainerFilter = true;
        }
    }
    $pagination = forwarder_list_container_packages_extract_pagination($body);
    $lastPagination = $pagination;

    if ($headers === [] && $table['headers'] !== []) {
        $headers = $table['headers'];
    }

    foreach ($table['rows'] as $row) {
        $rows[] = $row;
    }

    $pagesVisited[] = [
        'page' => $page,
        'http_status' => $statusCode,
        'rows' => count($table['rows']),
        'is_empty' => $table['is_empty'],
        'placeholder_detected' => forwarder_list_container_packages_has_select_placeholder($table['rows']),
        'used_container_filter_retry' => $usedContainerFilter,
    ];

    if (!$allPages) {
        break;
    }

    foreach ($pagination['available_pages'] as $candidatePage) {
        $candidatePage = (int)$candidatePage;
        if ($candidatePage > $pageStart && !isset($visited[$candidatePage])) {
            $pending[] = $candidatePage;
        }
    }

    sort($pending, SORT_NUMERIC);
}

$ok = $errorMessage === '';
$placeholderOnly = $ok
    && $rows !== []
    && forwarder_list_container_packages_has_select_placeholder([$rows[0]])
    && count($rows) === 1;
$result = [
    'status' => $ok ? ($placeholderOnly ? 'warning' : 'ok') : 'error',
    'message' => $ok
        ? ($placeholderOnly
            ? 'Container page returned placeholder row; selection likely not applied'
            : 'Container packages list fetched via PHP session client')
        : 'Failed to load container packages page',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'page_path' => $pagePath,
    'position' => $position,
    'check_path' => $checkPath,
    'position_check' => $positionCheck,
    'start_page' => $pageStart,
    'all_pages' => $allPages,
    'max_pages' => $maxPages,
    'pagination' => $lastPagination,
    'visited_pages' => $pagesVisited,
    'count' => count($rows),
    'headers' => $headers,
    'packages' => $rows,
    'error' => $errorMessage,
    'http_status' => $errorStatusCode,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($ok ? ($placeholderOnly ? 10 : 0) : 8);
