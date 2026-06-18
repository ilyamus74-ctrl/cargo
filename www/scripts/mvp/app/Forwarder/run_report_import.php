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
 * Fetches the forwarder all-packages report via the MVP Forwarder session client.
 *
 * Colibri default flow:
 *   GET  /collector/reports/all_packages  (session/csrf preflight)
 *   POST /collector/reports/all_packages  (from_date/to_date) -> xlsx/csv/html
 *
 * Output JSON contains normalized rows consumable by warehouse_forwarder_import_report_items().
 */

function forwarder_report_import_args(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        $arg = (string)$arg;
        if (str_starts_with($arg, '--')) {
            $eq = strpos($arg, '=');
            if ($eq === false) {
                $out[substr($arg, 2)] = '1';
            } else {
                $out[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
            }
        } elseif (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $out[$key] = $value;
        }
    }
    return $out;
}

function forwarder_report_import_arg(array $args, string ...$names): string
{
    foreach ($names as $name) {
        if (isset($args[$name]) && trim((string)$args[$name]) !== '') {
            return trim((string)$args[$name]);
        }
    }
    return '';
}

function forwarder_report_import_set_env(string $name, string $value): void
{
    if ($value !== '') {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function forwarder_report_import_load_connector(int $connectorId): array
{
    if ($connectorId <= 0) {
        throw new RuntimeException('Invalid connector-id');
    }

    $bootstrap = realpath(__DIR__ . '/../../../../bootstrap.php');
    if (!$bootstrap || !is_file($bootstrap)) {
        throw new RuntimeException('Application bootstrap.php not found');
    }
    require_once $bootstrap;

    if ((!isset($dbcnx) || !($dbcnx instanceof mysqli)) && isset($GLOBALS['dbcnx']) && $GLOBALS['dbcnx'] instanceof mysqli) {
        $dbcnx = $GLOBALS['dbcnx'];
    }
    if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) {
        throw new RuntimeException('DB connection $dbcnx is not available');
    }
    $GLOBALS['dbcnx'] = $dbcnx;
    $dbcnx->set_charset('utf8mb4');

    $stmt = $dbcnx->prepare('SELECT * FROM connectors WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare connector query: ' . $dbcnx->error);
    }
    $stmt->bind_param('i', $connectorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $connector = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$connector) {
        throw new RuntimeException('Connector not found: ' . $connectorId);
    }

    return $connector;
}

function forwarder_report_import_first_connector_value(array $connector, string ...$keys): string
{
    foreach ($keys as $key) {
        if (isset($connector[$key]) && trim((string)$connector[$key]) !== '') {
            return trim((string)$connector[$key]);
        }
    }
    return '';
}

function forwarder_report_import_is_service_message(string $value): bool
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return false;
    }
    return preg_match('/\b(?:packages?\s+not\s+found|no\s+packages?\s+found|not\s+found|no\s+data)\b/iu', $value) === 1;
}

function forwarder_report_import_internal_no_looks_real(string $value): bool
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') < 4 || forwarder_report_import_is_service_message($value)) {
        return false;
    }
    return preg_match('/^(?:CBR[A-Z0-9_-]*|H[A-Z0-9_-]*|\d+)$/iu', $value) === 1;
}

function forwarder_report_import_debug_write(string $debugDir, string $filename, string $contents): string
{
    if ($debugDir === '') {
        return '';
    }
    if (!is_dir($debugDir) && !mkdir($debugDir, 0775, true) && !is_dir($debugDir)) {
        throw new RuntimeException('Unable to create debug dir: ' . $debugDir);
    }
    $path = rtrim($debugDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($path, $contents);
    return $path;
}

function forwarder_report_import_raw_extension(string $body, string $contentType): string
{
    if ($body !== '' && str_starts_with($body, 'PK')) {
        return 'xlsx';
    }
    if (str_contains($contentType, 'csv')) {
        return 'csv';
    }
    if (str_contains($contentType, 'html') || preg_match('/<html\b|<table\b/iu', $body) === 1) {
        return 'html';
    }
    return 'raw';
}

function forwarder_report_import_json_exit(array $payload, int $code = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($code);
}

function forwarder_report_import_header_values(array $headers, string $headerName): array
{
    $values = [];
    $needle = strtolower($headerName) . ':';
    foreach ($headers as $line) {
        $line = trim((string)$line);
        if (str_starts_with(strtolower($line), $needle)) {
            $values[] = trim(substr($line, strlen($needle)));
        }
    }
    return $values;
}

function forwarder_report_import_content_type(array $response): string
{
    $headersRaw = (string)($response['headers_raw'] ?? '');
    $headers = preg_split('/\r\n|\n|\r/', $headersRaw) ?: [];
    return strtolower((string)(forwarder_report_import_header_values($headers, 'Content-Type')[0] ?? ''));
}

function forwarder_report_import_csrf_from_html(string $html): string
{
    if (preg_match('/<input\b[^>]*\bname\s*=\s*["\']_token["\'][^>]*\bvalue\s*=\s*["\']([^"\']+)["\']/iu', $html, $m)) {
        return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/<meta\b[^>]*\bname\s*=\s*["\']csrf-token["\'][^>]*\bcontent\s*=\s*["\']([^"\']+)["\']/iu', $html, $m)) {
        return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function forwarder_report_import_normalize_header(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(['№', '#'], ' no ', $value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '_', $value) ?? $value;
    return trim($value, '_');
}

function forwarder_report_import_pick_value(array $row, array $aliases): string
{
    foreach ($aliases as $alias) {
        $key = forwarder_report_import_normalize_header($alias);
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return '';
}

function forwarder_report_import_float(string $value): ?float
{
    $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float)$value : null;
}

function forwarder_report_import_normalize_row(array $row, int $rowNo): ?array
{
    $normalized = [
        'client_id' => forwarder_report_import_pick_value($row, ['client_id', 'client id', 'customer id', 'müştəri id', 'musteri id', 'код клиента']),
        'client_name' => forwarder_report_import_pick_value($row, ['client_name', 'client name', 'customer', 'receiver', 'fullname', 'full name', 'фио', 'получатель']),
        'forwarder_internal_no' => forwarder_report_import_pick_value($row, ['forwarder_internal_no', 'internal no', 'order no', 'package no', 'parcel no', 'id', 'no']),
        'tracking_no' => forwarder_report_import_pick_value($row, ['tracking_no', 'tracking', 'track', 'barcode', 'tracking number', 'трек', 'трек номер']),
        'declaration_status' => forwarder_report_import_pick_value($row, ['declaration_status', 'declaration status', 'status', 'статус']),
        'forwarder_position_code' => strtoupper(forwarder_report_import_pick_value($row, ['forwarder_position_code', 'position', 'position_code', 'cell', 'place', 'shelf', 'позиция', 'ячейка'])),
        'category' => forwarder_report_import_pick_value($row, ['category', 'категория']),
        'seller' => forwarder_report_import_pick_value($row, ['seller', 'shop', 'store', 'merchant', 'продавец']),
        'invoice_currency' => forwarder_report_import_pick_value($row, ['currency', 'invoice_currency', 'валюта']),
        'invoice_uploaded' => forwarder_report_import_pick_value($row, ['invoice_uploaded', 'invoice uploaded', 'invoice', 'инвойс']),
        'remote_created_at' => forwarder_report_import_pick_value($row, ['created_at', 'created', 'date', 'created date']),
        'remote_updated_at' => forwarder_report_import_pick_value($row, ['updated_at', 'updated', 'updated date']),
        'report_row_no' => $rowNo,
        'raw_report_row' => $row,
    ];
    $weight = forwarder_report_import_float(forwarder_report_import_pick_value($row, ['weight_kg', 'weight', 'kg', 'вес']));
    if ($weight !== null) {
        $normalized['weight_kg'] = $weight;
    }
    $invoice = forwarder_report_import_float(forwarder_report_import_pick_value($row, ['invoice_amount', 'amount', 'price', 'value', 'сумма']));
    if ($invoice !== null) {
        $normalized['invoice_amount'] = $invoice;
    }
    $rowText = trim(implode(' ', array_map(static fn($v) => trim((string)$v), $row)));
    if ($normalized['tracking_no'] === '' && forwarder_report_import_is_service_message($rowText)) {
        $GLOBALS['forwarder_report_import_filtered_service_rows'] = (int)($GLOBALS['forwarder_report_import_filtered_service_rows'] ?? 0) + 1;
        return null;
    }
    if ($normalized['tracking_no'] === '' && !forwarder_report_import_internal_no_looks_real($normalized['forwarder_internal_no'])) {
        return null;
    }
    return $normalized;
}

function forwarder_report_import_rows_from_matrix(array $matrix): array
{
    $headers = [];
    $rows = [];
    foreach ($matrix as $lineNo => $cells) {
        $cells = array_map(static fn($v) => trim((string)$v), (array)$cells);
        if (implode('', $cells) === '') {
            continue;
        }
        if (!$headers) {
            $headers = array_map('forwarder_report_import_normalize_header', $cells);
            continue;
        }
        $assoc = [];
        foreach ($headers as $idx => $header) {
            if ($header !== '') {
                $assoc[$header] = $cells[$idx] ?? '';
            }
        }
        $normalized = forwarder_report_import_normalize_row($assoc, (int)$lineNo + 1);
        if ($normalized !== null) {
            $rows[] = $normalized;
        }
    }
    return $rows;
}

function forwarder_report_import_parse_csv(string $body): array
{
    $matrix = [];
    $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $matrix[] = str_getcsv($line, str_contains($line, ';') ? ';' : ',');
    }
    return forwarder_report_import_rows_from_matrix($matrix);
}

function forwarder_report_import_parse_html(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $matrix = [];
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach (['th', 'td'] as $tag) {
            foreach ($tr->getElementsByTagName($tag) as $cell) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
            }
            if ($cells) {
                break;
            }
        }
        if ($cells) {
            $matrix[] = $cells;
        }
    }
    return forwarder_report_import_rows_from_matrix($matrix);
}

function forwarder_report_import_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string)($cell['t'] ?? '');
    $value = (string)($cell->v ?? '');
    if ($type === 's') {
        return (string)($sharedStrings[(int)$value] ?? '');
    }
    if ($type === 'inlineStr') {
        return trim((string)($cell->is->t ?? ''));
    }
    return trim($value);
}

function forwarder_report_import_parse_xlsx(string $body): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is required to parse xlsx reports');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'forwarder_report_');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temp xlsx file');
    }
    file_put_contents($tmp, $body);
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Unable to open xlsx report');
    }
    $sharedStrings = [];
    $sharedRaw = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($sharedRaw) && $sharedRaw !== '') {
        $xml = simplexml_load_string($sharedRaw);
        if ($xml instanceof SimpleXMLElement) {
            foreach ($xml->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string)$si->t;
                }
                foreach ($si->r as $run) {
                    $parts[] = (string)($run->t ?? '');
                }
                $sharedStrings[] = trim(implode('', $parts));
            }
        }
    }
    $sheetName = 'xl/worksheets/sheet1.xml';
    $sheetRaw = $zip->getFromName($sheetName);
    if (!is_string($sheetRaw) || $sheetRaw === '') {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetRaw = (string)$zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();
    @unlink($tmp);
    if (!is_string($sheetRaw) || $sheetRaw === '') {
        throw new RuntimeException('xlsx report does not contain worksheets');
    }
    $xml = simplexml_load_string($sheetRaw);
    if (!($xml instanceof SimpleXMLElement)) {
        throw new RuntimeException('Unable to parse xlsx worksheet XML');
    }
    $matrix = [];
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            $colIndex = count($cells);
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                $letters = $m[1];
                $colIndex = 0;
                for ($i = 0; $i < strlen($letters); $i++) {
                    $colIndex = $colIndex * 26 + (ord($letters[$i]) - 64);
                }
                $colIndex--;
            }
            $cells[$colIndex] = forwarder_report_import_xlsx_cell_value($cell, $sharedStrings);
        }
        if ($cells) {
            ksort($cells);
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $matrix[] = $line;
        }
    }
    return forwarder_report_import_rows_from_matrix($matrix);
}

function forwarder_report_import_parse_body(string $body, string $contentType): array
{
    if ($body !== '' && str_starts_with($body, 'PK')) {
        return forwarder_report_import_parse_xlsx($body);
    }
    if (str_contains($contentType, 'csv')) {
        return forwarder_report_import_parse_csv($body);
    }
    if (str_contains($contentType, 'spreadsheet') || str_contains($contentType, 'excel')) {
        if (str_starts_with($body, 'PK')) {
            return forwarder_report_import_parse_xlsx($body);
        }
        return forwarder_report_import_parse_html($body);
    }
    if (preg_match('/<table\b/iu', $body)) {
        return forwarder_report_import_parse_html($body);
    }
    return forwarder_report_import_parse_csv($body);
}

$args = forwarder_report_import_args($_SERVER['argv'] ?? []);
$connectorId = (int)(forwarder_report_import_arg($args, 'connector-id', 'connector_id') ?: 0);
$connector = [];
$connectorName = '';
$connectorCountries = '';
if ($connectorId > 0) {
    try {
        $connector = forwarder_report_import_load_connector($connectorId);
    } catch (Throwable $e) {
        forwarder_report_import_json_exit([
            'status' => 'CONFIG_ERROR',
            'message' => $e->getMessage(),
            'connector_id' => $connectorId,
            'rows' => [],
            'rows_total' => 0,
        ], 3);
    }
    $connectorName = (string)($connector['name'] ?? '');
    $connectorCountries = forwarder_report_import_first_connector_value($connector, 'countries', 'country_code', 'country');
    $baseUrl = forwarder_report_import_first_connector_value($connector, 'base_url');
    $login = forwarder_report_import_first_connector_value($connector, 'auth_username', 'login', 'username');
    $password = forwarder_report_import_first_connector_value($connector, 'auth_password', 'password');
} else {
    $baseUrl = forwarder_report_import_arg($args, 'base-url', 'base_url');
    $login = forwarder_report_import_arg($args, 'login', 'username');
    $password = forwarder_report_import_arg($args, 'password');
}
forwarder_report_import_set_env('DEV_COLIBRI_BASE_URL', $baseUrl);
forwarder_report_import_set_env('DEV_COLIBRI_LOGIN', $login);
forwarder_report_import_set_env('DEV_COLIBRI_PASSWORD', $password);
forwarder_report_import_set_env('FORWARDER_BASE_URL', $baseUrl);
forwarder_report_import_set_env('FORWARDER_LOGIN', $login);
forwarder_report_import_set_env('FORWARDER_PASSWORD', $password);
forwarder_report_import_set_env('FORWARDER_SESSION_FILE', forwarder_report_import_arg($args, 'session-file', 'session_file'));
forwarder_report_import_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_report_import_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$fromDate = forwarder_report_import_arg($args, 'from-date', 'from_date', 'date-from', 'date_from', 'from') ?: date('Y-m-d');
$toDate = forwarder_report_import_arg($args, 'to-date', 'to_date', 'date-to', 'date_to', 'to') ?: date('Y-m-d');
$pagePath = forwarder_report_import_arg($args, 'page-path', 'page_path') ?: '/collector/reports/all_packages';
$postPath = forwarder_report_import_arg($args, 'post-path', 'post_path') ?: $pagePath;
$debugDir = forwarder_report_import_arg($args, 'debug-dir', 'debug_dir');
$saveRaw = forwarder_report_import_arg($args, 'save-raw', 'save_raw') !== '';
$debugFiles = [];
$GLOBALS['forwarder_report_import_filtered_service_rows'] = 0;
$correlationId = 'run-report-import-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

try {
    $config = new ForwarderConfig();
    if (!$config->isConfigured()) {
        forwarder_report_import_json_exit([
            'status' => 'CONFIG_ERROR',
            'message' => 'Missing forwarder config (base-url/login/password)',
            'rows' => [],
            'rows_total' => 0,
        ], 3);
    }

    $logger = new ForwarderLogger($correlationId);
    $httpClient = new ForwarderHttpClient($config);
    $session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
    $loginService = new LoginService($config, $httpClient, $session, $logger);
    $sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

    $pageResponse = $sessionClient->requestWithSession('GET', $pagePath, [], false);
    $pageHtml = (string)($pageResponse['body'] ?? '');
    if ($saveRaw) {
        $debugFiles['get_page_html'] = forwarder_report_import_debug_write($debugDir, 'collector_all_packages_get.html', $pageHtml);
    }
    $token = forwarder_report_import_csrf_from_html($pageHtml) ?: $sessionClient->csrfToken();

    $payload = [
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ];
    if ($token !== '') {
        $payload['_token'] = $token;
    }

    $reportResponse = $sessionClient->requestWithSession('POST', $postPath, $payload, false);
    $statusCode = (int)($reportResponse['status_code'] ?? 0);
    $body = (string)($reportResponse['body'] ?? '');
    $contentType = forwarder_report_import_content_type($reportResponse);
    if ($saveRaw) {
        $debugFiles['post_response_raw'] = forwarder_report_import_debug_write($debugDir, 'collector_all_packages_response.' . forwarder_report_import_raw_extension($body, $contentType), $body);
    }
    if ($statusCode < 200 || $statusCode >= 300 || $body === '') {
        forwarder_report_import_json_exit([
            'status' => 'FETCH_ERROR',
            'message' => 'Forwarder report download failed',
            'rows' => [],
            'rows_total' => 0,
            'diagnostics' => [
                'http_status' => $statusCode,
                'content_type' => $contentType,
                'body_preview' => mb_substr(trim(preg_replace('/\s+/u', ' ', $body) ?? ''), 0, 500, 'UTF-8'),
            ],
        ], 2);
    }

    $rows = forwarder_report_import_parse_body($body, $contentType);
    if ($saveRaw) {
        $debugFiles['normalized_rows'] = forwarder_report_import_debug_write($debugDir, 'normalized_rows.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    }
    forwarder_report_import_json_exit([
        'status' => 'OK',
        'message' => 'Forwarder report imported from remote endpoint',
        'mode' => 'report_import',
        'correlation_id' => $correlationId,
        'connector_id' => $connectorId > 0 ? $connectorId : null,
        'connector_name' => $connectorName,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'rows_total' => count($rows),
        'rows' => $rows,
        'diagnostics' => [
            'connector_id' => $connectorId > 0 ? $connectorId : null,
            'connector_name' => $connectorName,
            'connector_countries' => $connectorCountries,
            'base_url' => $baseUrl,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'page_path' => $pagePath,
            'post_path' => $postPath,
            'http_status' => $statusCode,
            'content_type' => $contentType,
            'raw_size' => strlen($body),
            'rows_total' => count($rows),
            'filtered_service_rows' => (int)($GLOBALS['forwarder_report_import_filtered_service_rows'] ?? 0),
            'debug_files' => array_filter($debugFiles),
        ],
    ]);
} catch (Throwable $e) {
    forwarder_report_import_json_exit([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
        'rows' => [],
        'rows_total' => 0,
    ], 1);
}
