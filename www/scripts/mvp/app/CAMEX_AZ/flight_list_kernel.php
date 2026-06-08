<?php

declare(strict_types=1);

use App\Forwarder\Http\ForwarderSessionClient;

/** @return list<array<string, mixed>> */
function camex_az_flight_list_parse_rows(string $html, string $pagePath = '/cadmin/usa/index.php?do=flight', int $limit = 0): array
{
    if (trim($html) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return [];
    }

    $xpath = new DOMXPath($dom);
    $lis = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " column_res ")]/li');
    if (!$lis instanceof DOMNodeList || $lis->length === 0) {
        return [];
    }

    $rows = [];
    foreach ($lis as $li) {
        if (!$li instanceof DOMElement) {
            continue;
        }

        $normalized = camex_az_flight_list_parse_li($li, $xpath, $pagePath);
        if ($normalized === null) {
            continue;
        }

        $rows[] = $normalized;
        if ($limit > 0 && count($rows) >= $limit) {
            break;
        }
    }

    return $rows;
}

/** @return array<string, mixed>|null */
function camex_az_flight_list_parse_li(DOMElement $li, DOMXPath $xpath, string $pagePath): ?array
{
    $firstFlightAnchor = null;
    $anchors = $xpath->query('.//a', $li);
    if (!$anchors instanceof DOMNodeList) {
        return null;
    }

    foreach ($anchors as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }
        $spans = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " result_column ")]', $anchor);
        if ($spans instanceof DOMNodeList && $spans->length >= 2) {
            $firstFlightAnchor = $anchor;
            break;
        }
    }

    if (!$firstFlightAnchor instanceof DOMElement) {
        return null;
    }

    $spans = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " result_column ")]', $firstFlightAnchor);
    if (!$spans instanceof DOMNodeList || $spans->length < 2) {
        return null;
    }

    $camexFlightId = camex_az_flight_list_clean_text($spans->item(0)?->textContent ?? '');
    $flightNo = camex_az_flight_list_clean_text($spans->item(1)?->textContent ?? '');
    if ($camexFlightId === '' || $flightNo === '' || !preg_match('/^\d+$/', $camexFlightId)) {
        return null;
    }

    $hrefs = [
        'flight_href' => html_entity_decode($firstFlightAnchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'ord_by_type_href' => '',
        'ord_by_group_href' => '',
        'boxes_href' => '',
        'edit_awb_href' => '',
    ];
    $editAwbText = '';
    foreach ($anchors as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }
        $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = camex_az_flight_list_clean_text($anchor->textContent ?? '');
        if (stripos($href, 'repo_type=usa_type_det') !== false || strcasecmp($text, 'OrdByType') === 0) {
            $hrefs['ord_by_type_href'] = $href;
        } elseif (stripos($href, 'repo_type=usa_type_group') !== false || strcasecmp($text, 'OrdByGroup') === 0) {
            $hrefs['ord_by_group_href'] = $href;
        } elseif (stripos($href, 'do=boxes') !== false || strcasecmp($text, 'Boxes') === 0) {
            $hrefs['boxes_href'] = $href;
        } elseif (stripos($href, 'do=edit_flight') !== false || stripos($text, 'Edit AWB') === 0) {
            $hrefs['edit_awb_href'] = $href;
            $editAwbText = $text;
        }
    }

    $dateParts = camex_az_flight_list_extract_departure_raw($flightNo);
    $departureRaw = $dateParts['raw'];
    $departureAt = camex_az_flight_list_parse_departure_at($departureRaw);
    $carrier = camex_az_flight_list_extract_carrier($flightNo);
    $awb = camex_az_flight_list_extract_awb($editAwbText);
    $boxesHref = (string)$hrefs['boxes_href'];
    $containersUrl = $boxesHref !== '' ? camex_az_flight_list_normalize_href_to_path($boxesHref, $pagePath) : '';

    $rawPayload = [
        'camex_flight_id' => $camexFlightId,
        'flight_href' => $hrefs['flight_href'],
        'ord_by_type_href' => $hrefs['ord_by_type_href'],
        'ord_by_group_href' => $hrefs['ord_by_group_href'],
        'boxes_href' => $hrefs['boxes_href'],
        'edit_awb_href' => $hrefs['edit_awb_href'],
        'edit_awb_text' => $editAwbText,
        'li_text' => camex_az_flight_list_clean_text($li->textContent ?? ''),
    ];

    return [
        'flight_no' => $flightNo,
        'departure_at' => $departureAt,
        'departure_raw' => $departureRaw,
        'route' => '',
        'status' => '',
        'external_id' => $flightNo,
        'name' => $flightNo,
        'flight_time' => '',
        'carrier' => $carrier,
        'flight_number' => '',
        'awb' => $awb,
        'departure' => '',
        'destination' => '',
        'packages_count' => null,
        'total_weight' => null,
        'closed_at' => null,
        'source_row_id' => $camexFlightId,
        'containers_url' => $containersUrl,
        'containers_json' => null,
        'containers_count' => null,
        'containers_synced_at' => null,
        'containers_sync_status' => 'pending',
        'containers_sync_error' => null,
        'raw_json' => json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function camex_az_flight_list_clean_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

/** @return array{raw: string} */
function camex_az_flight_list_extract_departure_raw(string $flightNo): array
{
    if (preg_match('/(\d{1,2})\s*\.\s*(\d{1,2})\s*[\.\/]\s*(\d{4})/u', $flightNo, $match) === 1) {
        return ['raw' => sprintf('%02d.%02d.%04d', (int)$match[1], (int)$match[2], (int)$match[3])];
    }

    return ['raw' => ''];
}

function camex_az_flight_list_parse_departure_at(string $departureRaw): ?string
{
    if ($departureRaw === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('!d.m.Y', $departureRaw, new DateTimeZone('UTC'));
    if (!$dt instanceof DateTimeImmutable) {
        return null;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if (is_array($errors) && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) {
        return null;
    }

    return $dt->format('Y-m-d 00:00:00');
}

function camex_az_flight_list_extract_carrier(string $flightNo): string
{
    $carrier = preg_replace('/\s*\d.*$/u', '', trim($flightNo));
    return camex_az_flight_list_clean_text((string)$carrier);
}

function camex_az_flight_list_extract_awb(string $editAwbText): string
{
    $text = camex_az_flight_list_clean_text($editAwbText);
    if ($text === '' || stripos($text, 'Edit AWB') !== 0) {
        return '';
    }

    $awb = trim((string)preg_replace('/^Edit\s+AWB\s*:\s*/iu', '', $text));
    if (strcasecmp($awb, 'NULL') === 0) {
        return '';
    }

    return $awb;
}

function camex_az_flight_list_normalize_href_to_path(string $href, string $pagePath): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
        return '';
    }

    $href = explode('#', $href, 2)[0];
    if (preg_match('#^https?://#i', $href) === 1) {
        $path = (string)(parse_url($href, PHP_URL_PATH) ?: '/');
        $query = parse_url($href, PHP_URL_QUERY);
        return $path . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    if ($href[0] === '/') {
        return $href;
    }

    $pathOnly = explode('?', $pagePath, 2)[0];
    $dir = rtrim(str_replace('\\', '/', dirname($pathOnly)), '/');
    if ($dir === '') {
        $dir = '/';
    }

    if ($href[0] === '?') {
        return $pathOnly . $href;
    }

    return rtrim($dir, '/') . '/' . $href;
}

/**
 * @param array{repo_root:string,session_client:ForwarderSessionClient,connector_id:int,target_table:string,page_path:string,debug_dir?:string,dry_run?:bool,limit?:int} $params
 * @return array<string, mixed>
 */
function camex_az_sync_flight_list_kernel(array $params): array
{
    $repoRoot = trim((string)($params['repo_root'] ?? ''));
    $sessionClient = $params['session_client'] ?? null;
    $connectorId = (int)($params['connector_id'] ?? 0);
    $targetTable = trim((string)($params['target_table'] ?? 'connector_camex_az_operation_flight_list'));
    $pagePath = camex_az_flight_list_normalize_page_path((string)($params['page_path'] ?? '/cadmin/usa/index.php?do=flight'));
    $debugDir = trim((string)($params['debug_dir'] ?? ''));
    $dryRun = !empty($params['dry_run']);
    $limit = max(0, (int)($params['limit'] ?? 0));

    if ($repoRoot === '' || !($sessionClient instanceof ForwarderSessionClient) || $connectorId <= 0) {
        return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'sync prerequisites are missing (repo_root/session_client/connector_id)'];
    }

    $response = $sessionClient->requestWithSession('GET', $pagePath, [], false);
    $httpStatus = (int)($response['status_code'] ?? 0);
    $body = (string)($response['body'] ?? '');
    $debugHtml = camex_az_flight_list_save_debug_html($debugDir, '01_flight_list.html', $body);

    if ($httpStatus !== 200 || empty($response['ok'])) {
        return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list request failed.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
    }
    if (camex_az_flight_list_looks_like_login_page($body)) {
        return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list response looks like login page.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
    }
    if (stripos($body, 'column_res') === false && stripos($body, 'do=flight') === false) {
        return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list markers were not found in response.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
    }

    $rows = camex_az_flight_list_parse_rows($body, $pagePath, $limit);
    if ($rows === []) {
        return ['status' => 'error', 'stage' => 'flight_list_parse', 'message' => 'No flight rows extracted from flight list page.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
    }

    $safeTable = $targetTable;
    $written = 0;
    if (!$dryRun) {
        $load = camex_az_flight_list_load_db_helpers($repoRoot);
        if (($load['status'] ?? 'ok') !== 'ok') {
            return array_merge($load, ['stage' => 'db_write', 'http_status' => $httpStatus, 'debug_html' => $debugHtml]);
        }
        /** @var mysqli $db */
        $db = $load['db'];
        $safeTable = connectors_subrunner_sanitize_table_name($targetTable);
        connectors_subrunner_ensure_flight_table($db, $safeTable);
        foreach ($rows as $row) {
            connectors_subrunner_upsert_flight_row($db, $safeTable, $connectorId, $row);
            $written++;
        }
    }

    return [
        'status' => 'ok',
        'connector' => 'CAMEX_AZ',
        'connector_id' => $connectorId,
        'target_table' => $safeTable,
        'page_path' => $pagePath,
        'http_status' => $httpStatus,
        'rows_extracted' => count($rows),
        'rows_written' => $written,
        'rows_skipped' => 0,
        'dry_run' => $dryRun,
        'debug_html' => $debugHtml,
    ];
}

function camex_az_flight_list_normalize_page_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/cadmin/usa/index.php?do=flight';
    }

    return $path[0] === '/' ? $path : '/' . $path;
}

function camex_az_flight_list_save_debug_html(string $debugDir, string $fileName, string $html): string
{
    if ($debugDir === '') {
        return '';
    }
    if (!is_dir($debugDir)) {
        @mkdir($debugDir, 0775, true);
    }
    if (!is_dir($debugDir)) {
        return '';
    }

    $path = rtrim($debugDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    @file_put_contents($path, $html);
    return $path;
}

function camex_az_flight_list_looks_like_login_page(string $html): bool
{
    if ($html === '') {
        return false;
    }

    $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
    return $hasPassword && (stripos($html, 'login.php') !== false || stripos($html, 'auth=do') !== false);
}

/** @return array<string, mixed> */
function camex_az_flight_list_load_db_helpers(string $repoRoot): array
{
    foreach ([
        rtrim($repoRoot, '/') . '/configs/connectDB.php',
        rtrim($repoRoot, '/') . '/www/api/connectors/connector_engine.php',
        rtrim($repoRoot, '/') . '/www/api/connectors/subrunners/connector_modules.php',
    ] as $requiredPath) {
        if (!is_file($requiredPath)) {
            return ['status' => 'error', 'message' => 'required file not found: ' . $requiredPath];
        }
        require_once $requiredPath;
    }

    $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
    if (!($db instanceof mysqli)) {
        return ['status' => 'error', 'message' => 'mysqli connection is not available'];
    }

    return ['status' => 'ok', 'db' => $db];
}
