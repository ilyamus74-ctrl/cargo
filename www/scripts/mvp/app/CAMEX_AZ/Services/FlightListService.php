<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\Http\CamexSessionClient;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use mysqli;

final class FlightListService
{
    public function __construct(
        private string $repoRoot,
        private CamexSessionClient $sessionClient
    ) {
        $this->repoRoot = rtrim($this->repoRoot, '/');
    }

    /**
     * @param array{connector_id:int,target_table:string,page_path:string,debug_dir?:string,dry_run?:bool,limit?:int} $params
     * @return array<string, mixed>
     */
    public function sync(array $params): array
    {
        $connectorId = (int)($params['connector_id'] ?? 0);
        $targetTable = trim((string)($params['target_table'] ?? 'connector_camex_az_operation_flight_list'));
        $pagePath = self::normalizePagePath((string)($params['page_path'] ?? '/cadmin/usa/index.php?do=flight'));
        $debugDir = trim((string)($params['debug_dir'] ?? ''));
        $dryRun = !empty($params['dry_run']);
        $limit = max(0, (int)($params['limit'] ?? 0));

        if ($this->repoRoot === '' || $connectorId <= 0) {
            return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'sync prerequisites are missing (repo_root/connector_id)'];
        }

        $response = $this->sessionClient->requestWithSession('GET', $pagePath, [], false);
        $httpStatus = (int)($response['status_code'] ?? 0);
        $body = (string)($response['body'] ?? '');
        $debugHtml = self::saveDebugHtml($debugDir, '01_flight_list.html', $body);

        if ($httpStatus !== 200 || empty($response['ok'])) {
            return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list request failed.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
        }
        if (self::looksLikeLoginPage($body)) {
            return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list response looks like login page.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
        }
        if (stripos($body, 'column_res') === false && stripos($body, 'do=flight') === false) {
            return ['status' => 'error', 'stage' => 'flight_list_fetch', 'message' => 'Flight list markers were not found in response.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
        }

        $rows = self::parseRows($body, $pagePath, $limit);
        if ($rows === []) {
            return ['status' => 'error', 'stage' => 'flight_list_parse', 'message' => 'No flight rows extracted from flight list page.', 'http_status' => $httpStatus, 'debug_html' => $debugHtml];
        }

        $safeTable = $targetTable;
        $written = 0;
        if (!$dryRun) {
            $load = $this->loadDbHelpers();
            if (($load['status'] ?? 'ok') !== 'ok') {
                return array_merge($load, ['stage' => 'db_write', 'http_status' => $httpStatus, 'debug_html' => $debugHtml]);
            }
            /** @var mysqli $db */
            $db = $load['db'];
            $safeTable = \connectors_subrunner_sanitize_table_name($targetTable);
            \connectors_subrunner_ensure_flight_table($db, $safeTable);
            foreach ($rows as $row) {
                \connectors_subrunner_upsert_flight_row($db, $safeTable, $connectorId, $row);
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

    /** @return list<array<string, mixed>> */
    public static function parseRows(string $html, string $pagePath = '/cadmin/usa/index.php?do=flight', int $limit = 0): array
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
            $normalized = self::parseLi($li, $xpath, $pagePath);
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
    private static function parseLi(DOMElement $li, DOMXPath $xpath, string $pagePath): ?array
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

        $camexFlightId = self::cleanText($spans->item(0)?->textContent ?? '');
        $flightNo = self::cleanText($spans->item(1)?->textContent ?? '');
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
            $text = self::cleanText($anchor->textContent ?? '');
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

        $departureRaw = self::extractDepartureRaw($flightNo);
        $rawPayload = [
            'camex_flight_id' => $camexFlightId,
            'flight_href' => $hrefs['flight_href'],
            'ord_by_type_href' => $hrefs['ord_by_type_href'],
            'ord_by_group_href' => $hrefs['ord_by_group_href'],
            'boxes_href' => $hrefs['boxes_href'],
            'edit_awb_href' => $hrefs['edit_awb_href'],
            'edit_awb_text' => $editAwbText,
            'li_text' => self::cleanText($li->textContent ?? ''),
        ];

        return [
            'flight_no' => $flightNo,
            'departure_at' => self::parseDepartureAt($departureRaw),
            'departure_raw' => $departureRaw,
            'route' => '',
            'status' => '',
            'external_id' => $flightNo,
            'name' => $flightNo,
            'flight_time' => '',
            'carrier' => self::extractCarrier($flightNo),
            'flight_number' => '',
            'awb' => self::extractAwb($editAwbText),
            'departure' => '',
            'destination' => '',
            'packages_count' => null,
            'total_weight' => null,
            'closed_at' => null,
            'source_row_id' => $camexFlightId,
            'containers_url' => $hrefs['boxes_href'] !== '' ? self::normalizeHrefToPath((string)$hrefs['boxes_href'], $pagePath) : '',
            'containers_json' => null,
            'containers_count' => null,
            'containers_synced_at' => null,
            'containers_sync_status' => 'pending',
            'containers_sync_error' => null,
            'raw_json' => json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    public static function normalizePagePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/cadmin/usa/index.php?do=flight';
        }

        return $path[0] === '/' ? $path : '/' . $path;
    }

    public static function looksLikeLoginPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
        return $hasPassword && (stripos($html, 'login.php') !== false || stripos($html, 'auth=do') !== false);
    }

    public static function saveDebugHtml(string $debugDir, string $fileName, string $html): string
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

    private static function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private static function extractDepartureRaw(string $flightNo): string
    {
        if (preg_match('/(\d{1,2})\s*\.\s*(\d{1,2})\s*[\.\/]\s*(\d{4})/u', $flightNo, $match) === 1) {
            return sprintf('%02d.%02d.%04d', (int)$match[1], (int)$match[2], (int)$match[3]);
        }

        return '';
    }

    private static function parseDepartureAt(string $departureRaw): ?string
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

    private static function extractCarrier(string $flightNo): string
    {
        $carrier = preg_replace('/\s*\d.*$/u', '', trim($flightNo));
        return self::cleanText((string)$carrier);
    }

    private static function extractAwb(string $editAwbText): string
    {
        $text = self::cleanText($editAwbText);
        if ($text === '' || stripos($text, 'Edit AWB') !== 0) {
            return '';
        }
        $awb = trim((string)preg_replace('/^Edit\s+AWB\s*:\s*/iu', '', $text));
        if (strcasecmp($awb, 'NULL') === 0) {
            return '';
        }

        return $awb;
    }

    private static function normalizeHrefToPath(string $href, string $pagePath): string
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

    /** @return array<string, mixed> */
    private function loadDbHelpers(): array
    {
        foreach ([
            $this->repoRoot . '/configs/connectDB.php',
            $this->repoRoot . '/www/api/connectors/connector_engine.php',
            $this->repoRoot . '/www/api/connectors/subrunners/connector_modules.php',
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
}
