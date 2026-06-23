<?php

declare(strict_types=1);

use App\Forwarder\Config\ConnectorConfigRepository;
use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\CamexSessionClient;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/connector_config_loader.php';
require_once dirname(__DIR__, 4) . '/api/warehouse/warehouse_forwarder_sync_helpers.php';

const CAMEX_AZ_FLIGHT_PACKAGES_SOURCE = 'camex_az_flight_packages';
const CAMEX_AZ_FORWARDER = 'CAMEX';
const CAMEX_AZ_COUNTRY = 'AZ';
const CAMEX_AZ_BOT_ID = 9999;

function camex_az_sync_flight_packages_usage(): string
{
    return <<<'TXT'
Usage:
  php run_sync_flight_packages.php --connector-id=3 [options]

Options:
  --connector-id=ID                  Load active connector by connectors.id.
  --connector-name=NAME              Load active connector by connectors.name.
  --connector-key=NAME               Load active connector by connectors.name.
  --limit-flights=N                  Number of latest flights to process (default: 5).
  --flight-no="DE 13.06.2026"        Process one exact flight number instead of latest N.
  --target-table=TABLE               Flight list table (default: connector_camex_az_operation_flight_list).
  --containers-table=TABLE           Containers table (default: connector_camex_az_operation_flight_list_containers).
  --debug-dir=DIR                    Optional directory for debug HTML snapshots.
  --dry-run=0|1                      Parse without DB/local writes (default: 0).
  --sync-local-states=0|1            Update local stock/out statuses (default: 1).
  --refresh-flight-list=0|1          Run CAMEX_AZ run_flight_list.php before reading latest flights (default: 0).
  --help                             Show this help.
TXT;
}

/** @param array<string, mixed> $payload */
function camex_az_sync_flight_packages_json(array $payload, int $exitCode = 0): void
{
    fwrite(STDOUT, json_encode(camex_az_sync_flight_packages_mask($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($exitCode);
}

/** @param array<string, mixed> $value */
function camex_az_sync_flight_packages_mask(array $value): array
{
    $secretKeys = ['auth-password', 'http-auth-password', 'password', 'auth_password', 'http_auth_password', 'cookies', 'auth_cookies', 'set-cookie', 'authorization', 'cookie', 'web_password'];
    foreach ($value as $key => $item) {
        $normalized = strtolower(str_replace('_', '-', (string)$key));
        if (in_array($normalized, $secretKeys, true)) {
            $value[$key] = '***';
            continue;
        }
        if (is_array($item)) {
            $value[$key] = camex_az_sync_flight_packages_mask($item);
        }
    }

    return $value;
}

function camex_az_sync_flight_packages_bool(array $args, string $key, bool $default = false): bool
{
    $alt = str_replace('-', '_', $key);
    if (!array_key_exists($key, $args) && !array_key_exists($alt, $args)) {
        return $default;
    }
    $value = (string)($args[$key] ?? $args[$alt] ?? '');
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function camex_az_sync_flight_packages_clean_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

/** @return array{prefix:string,code:string} */
function camex_az_sync_flight_packages_normalize_place(string $raw): array
{
    $raw = camex_az_sync_flight_packages_clean_text($raw);
    if ($raw === '') {
        return ['prefix' => '', 'code' => 'UNKNOWN'];
    }

    $parts = preg_split('/\s+/u', $raw) ?: [];
    $prefix = '';
    $code = '';
    if (count($parts) >= 2) {
        $prefix = strtolower(trim((string)$parts[0]));
        $code = trim(implode(' ', array_slice($parts, 1)));
    } else {
        $code = trim((string)($parts[0] ?? ''));
    }

    $code = strtoupper((string)preg_replace('/\s+/u', '', $code));
    if (preg_match('/^BOX0*(\d+)$/i', $code, $m) === 1) {
        $num = (int)$m[1];
        if ($num < 100) {
            $code = 'BOX' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        } else {
            $code = 'BOX' . (string)$num;
        }
    }

    return ['prefix' => $prefix, 'code' => $code !== '' ? $code : 'UNKNOWN'];
}

/** @return list<array<string, mixed>> */
function camex_az_sync_flight_packages_parse_html(string $html): array
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
    $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " sofT ")]');
    if (!$tables instanceof DOMNodeList || $tables->length === 0) {
        return [];
    }

    $packages = [];
    foreach ($tables as $table) {
        if (!$table instanceof DOMElement) {
            continue;
        }
        $headerMap = [];
        $rows = $xpath->query('.//tr', $table);
        if (!$rows instanceof DOMNodeList) {
            continue;
        }
        foreach ($rows as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            $cells = $xpath->query('./th|./td', $row);
            if (!$cells instanceof DOMNodeList || $cells->length === 0) {
                continue;
            }

            $cellTexts = [];
            foreach ($cells as $idx => $cell) {
                $cellTexts[$idx] = camex_az_sync_flight_packages_clean_text($cell->textContent ?? '');
            }

            $isHeader = false;
            foreach ($cellTexts as $idx => $text) {
                $key = strtolower($text);
                if (in_array($key, ['name', 'last name', 'tracking', 'invoice price', 'purchased from', 'weight', 'amount for weight', 'order place'], true)) {
                    $headerMap[$key] = (int)$idx;
                    $isHeader = true;
                }
            }
            if ($isHeader || $headerMap === []) {
                continue;
            }

            $get = static function (string $header) use ($cells, $headerMap): string {
                $idx = $headerMap[strtolower($header)] ?? null;
                if ($idx === null || !$cells->item($idx)) {
                    return '';
                }
                return camex_az_sync_flight_packages_clean_text($cells->item($idx)->textContent ?? '');
            };

            $nameCellIdx = $headerMap['name'] ?? 0;
            $nameCell = $cells->item((int)$nameCellIdx);
            $firstName = '';
            $checked = false;
            $pdfUrl = '';
            $qrUrl = '';
            $orderId = '';
            if ($nameCell instanceof DOMElement) {
                $imgs = $xpath->query('.//img', $nameCell);
                if ($imgs instanceof DOMNodeList) {
                    foreach ($imgs as $img) {
                        if ($img instanceof DOMElement && stripos($img->getAttribute('src'), 'images/checked.png') !== false) {
                            $checked = true;
                            break;
                        }
                    }
                }
                $anchors = $xpath->query('.//a', $nameCell);
                if ($anchors instanceof DOMNodeList) {
                    foreach ($anchors as $anchor) {
                        if (!$anchor instanceof DOMElement) {
                            continue;
                        }
                        $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        if (stripos($href, 'pdf_camex.php') !== false) {
                            $pdfUrl = $href;
                        }
                        if (stripos($href, 'camex_az_qr_label.php') !== false) {
                            $qrUrl = $href;
                        }
                        if ($orderId === '') {
                            $query = (string)(parse_url($href, PHP_URL_QUERY) ?: '');
                            parse_str($query, $parsed);
                            if (isset($parsed['ord'])) {
                                $orderId = (string)$parsed['ord'];
                            }
                        }
                    }
                }
                $clone = $nameCell->cloneNode(true);
                if ($clone instanceof DOMElement) {
                    while (($link = $clone->getElementsByTagName('a')->item(0)) !== null) {
                        $link->parentNode?->removeChild($link);
                    }
                    while (($img = $clone->getElementsByTagName('img')->item(0)) !== null) {
                        $img->parentNode?->removeChild($img);
                    }
                    $firstName = camex_az_sync_flight_packages_clean_text($clone->textContent ?? '');
                    $firstName = trim($firstName, "\xc2\xa0 \t\n\r\0\x0B");
                }
            }

            $tracking = $get('Tracking');
            $orderPlaceRaw = $get('Order place');
            if ($tracking === '' && $orderPlaceRaw === '') {
                continue;
            }
            $place = camex_az_sync_flight_packages_normalize_place($orderPlaceRaw);
            $packages[] = [
                'order_id' => $orderId,
                'tracking' => $tracking,
                'first_name' => $firstName,
                'last_name' => $get('Last Name'),
                'invoice_price' => $get('Invoice Price'),
                'shop' => $get('Purchased From'),
                'weight_kg' => $get('Weight'),
                'amount_for_weight' => $get('Amount for Weight'),
                'order_place_raw' => $orderPlaceRaw,
                'place_prefix' => $place['prefix'],
                'place_code' => $place['code'],
                'checked' => $checked,
                'pdf_url' => $pdfUrl,
                'qr_url' => $qrUrl,
            ];
        }
    }

    return $packages;
}

/** @param list<array<string, mixed>> $packages @return array<string, array{packages:list<array<string,mixed>>,total_weight:float}> */
function camex_az_sync_flight_packages_group(array $packages): array
{
    $groups = [];
    foreach ($packages as $package) {
        $code = (string)($package['place_code'] ?? 'UNKNOWN');
        if (!isset($groups[$code])) {
            $groups[$code] = ['packages' => [], 'total_weight' => 0.0];
        }
        $groups[$code]['packages'][] = $package;
        $groups[$code]['total_weight'] += (float)str_replace(',', '.', (string)($package['weight_kg'] ?? '0'));
    }
    ksort($groups);
    return $groups;
}

function camex_az_sync_flight_packages_safe_table(string $tableName): string
{
    if (function_exists('connectors_subrunner_sanitize_table_name')) {
        return connectors_subrunner_sanitize_table_name($tableName);
    }
    $tableName = strtolower(trim($tableName));
    $tableName = preg_replace('/[^a-z0-9_]+/', '_', $tableName) ?? $tableName;
    $tableName = trim($tableName, '_');
    if ($tableName === '') {
        throw new InvalidArgumentException('Table name is empty after normalization.');
    }
    return $tableName;
}

function camex_az_sync_flight_packages_qtable(string $tableName): string
{
    return '`' . str_replace('`', '``', $tableName) . '`';
}

function camex_az_sync_flight_packages_table_exists(mysqli $db, string $tableName): bool
{
    $stmt = $db->prepare('SHOW TABLES LIKE ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

/** @return array<string, bool> */
function camex_az_sync_flight_packages_columns(mysqli $db, string $tableName): array
{
    $result = $db->query('SHOW COLUMNS FROM ' . camex_az_sync_flight_packages_qtable($tableName));
    $columns = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = true;
        }
        $result->close();
    }
    return $columns;
}

function camex_az_sync_flight_packages_column_exists(mysqli $db, string $tableName, string $column): bool
{
    $columns = camex_az_sync_flight_packages_columns($db, $tableName);
    return isset($columns[$column]);
}

function camex_az_sync_flight_packages_index_exists(mysqli $db, string $tableName, string $indexName): bool
{
    $sql = 'SHOW INDEX FROM ' . camex_az_sync_flight_packages_qtable($tableName) . " WHERE Key_name = '" . $db->real_escape_string($indexName) . "'";
    $result = $db->query($sql);
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->close();
    }
    return $exists;
}

function camex_az_sync_flight_packages_prepare_schema(mysqli $db, string $flightTable, string $containersTable): void
{
    connectors_subrunner_ensure_flight_table($db, $flightTable);
    connectors_subrunner_ensure_flight_containers_table($db, $containersTable);
    $safe = camex_az_sync_flight_packages_qtable($containersTable);

    if (camex_az_sync_flight_packages_index_exists($db, $containersTable, 'uniq_connector_container')) {
        if (!$db->query("ALTER TABLE {$safe} DROP INDEX uniq_connector_container")) {
            throw new RuntimeException('Could not drop obsolete uniq_connector_container: ' . $db->error);
        }
    }
    if (!camex_az_sync_flight_packages_index_exists($db, $containersTable, 'uq_camex_flight_container')) {
        if (!$db->query("ALTER TABLE {$safe} ADD UNIQUE KEY uq_camex_flight_container (connector_id, flight_no, container_external_id)")) {
            throw new RuntimeException('Could not create uq_camex_flight_container: ' . $db->error);
        }
    }
}

/** @return list<array<string, mixed>> */
function camex_az_sync_flight_packages_load_flights(mysqli $db, string $tableName, int $connectorId, int $limit, string $flightNo): array
{
    $safe = camex_az_sync_flight_packages_qtable($tableName);
    $columns = camex_az_sync_flight_packages_columns($db, $tableName);
    $activeClause = isset($columns['is_active']) ? ' AND is_active = 1' : '';
    if ($flightNo !== '') {
        $stmt = $db->prepare("SELECT * FROM {$safe} WHERE connector_id = ? AND flight_no = ? {$activeClause} ORDER BY id DESC LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (load flight by flight_no): ' . $db->error);
        }
        $stmt->bind_param('is', $connectorId, $flightNo);
    } else {
        $order = isset($columns['departure_at']) ? 'ORDER BY (departure_at IS NULL) ASC, departure_at DESC, id DESC' : 'ORDER BY id DESC';
        $stmt = $db->prepare("SELECT * FROM {$safe} WHERE connector_id = ? AND TRIM(COALESCE(flight_no, '')) <> '' {$activeClause} {$order} LIMIT ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (load latest flights): ' . $db->error);
        }
        $stmt->bind_param('ii', $connectorId, $limit);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (load flights): ' . $err);
    }
    $res = $stmt->get_result();
    $rows = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
    $stmt->close();

    if ($flightNo === '' || $rows !== [] || !isset($columns['external_id'])) {
        return $rows;
    }

    $stmt = $db->prepare("SELECT * FROM {$safe} WHERE connector_id = ? AND external_id = ? {$activeClause} ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (load flight by external_id): ' . $db->error);
    }
    $stmt->bind_param('is', $connectorId, $flightNo);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    $stmt->close();

    return is_array($row) ? [$row] : [];
}

/** @param array<string,mixed> $flight @param array<string,array{packages:list<array<string,mixed>>,total_weight:float}> $groups */
function camex_az_sync_flight_packages_write_containers(mysqli $db, string $tableName, int $connectorId, array $flight, array $groups, string $sourceUrl, bool $dryRun): int
{
    if ($dryRun) {
        return count($groups);
    }

    $safe = camex_az_sync_flight_packages_qtable($tableName);
    $flightNo = (string)($flight['flight_no'] ?? '');
    $seenCodes = array_keys($groups);
    if ($seenCodes !== []) {
        $placeholders = implode(',', array_fill(0, count($seenCodes), '?'));
        $types = 'is' . str_repeat('s', count($seenCodes));
        $params = array_merge([$connectorId, $flightNo], $seenCodes);
        $stmt = $db->prepare("UPDATE {$safe} SET is_active = 0, updated_at = UTC_TIMESTAMP() WHERE connector_id = ? AND flight_no = ? AND container_external_id NOT IN ({$placeholders})");
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $db->prepare("UPDATE {$safe} SET is_active = 0, updated_at = UTC_TIMESTAMP() WHERE connector_id = ? AND flight_no = ?");
        if ($stmt) {
            $stmt->bind_param('is', $connectorId, $flightNo);
            $stmt->execute();
            $stmt->close();
        }
    }

    $sql = "
        INSERT INTO {$safe}
            (connector_id, flight_record_id, flight_external_id, flight_no, container_external_id, name, flight, departure, destination, awb, packages_count, total_weight, forwarder_packages_json, forwarder_packages_synced_at, warehouse_packages_count, warehouse_total_weight, compare_status, compared_at, compare_error, is_active, raw_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), NULL, NULL, 'pending', NULL, NULL, 1, ?)
        ON DUPLICATE KEY UPDATE
            flight_record_id = VALUES(flight_record_id),
            flight_external_id = VALUES(flight_external_id),
            flight_no = VALUES(flight_no),
            name = VALUES(name),
            flight = VALUES(flight),
            departure = VALUES(departure),
            destination = VALUES(destination),
            awb = VALUES(awb),
            packages_count = VALUES(packages_count),
            total_weight = VALUES(total_weight),
            forwarder_packages_json = VALUES(forwarder_packages_json),
            forwarder_packages_synced_at = UTC_TIMESTAMP(),
            compare_status = 'pending',
            compared_at = NULL,
            compare_error = NULL,
            is_active = 1,
            raw_json = VALUES(raw_json)
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (upsert CAMEX containers): ' . $db->error);
    }

    $written = 0;
    foreach ($groups as $placeCode => $group) {
        $packagesJson = json_encode($group['packages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rawJson = json_encode([
            'flight_no' => $flightNo,
            'place_code' => $placeCode,
            'source_url' => $sourceUrl,
            'extracted_at' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $flightRecordId = (int)($flight['id'] ?? 0);
        $flightExternalId = (string)($flight['external_id'] ?? $flightNo);
        if ($flightExternalId === '') {
            $flightExternalId = $flightNo;
        }
        $departure = (string)($flight['departure'] ?? '');
        if ($departure === '') {
            $departure = (string)($flight['departure_at'] ?? '');
        }
        $destination = (string)($flight['destination'] ?? '');
        if ($destination === '') {
            $destination = CAMEX_AZ_COUNTRY;
        }
        $awb = (string)($flight['awb'] ?? '');
        $packagesCount = count($group['packages']);
        $totalWeight = number_format((float)$group['total_weight'], 3, '.', '');
        $stmt->bind_param(
            'iissssssssidss',
            $connectorId,
            $flightRecordId,
            $flightExternalId,
            $flightNo,
            $placeCode,
            $placeCode,
            $flightNo,
            $departure,
            $destination,
            $awb,
            $packagesCount,
            $totalWeight,
            $packagesJson,
            $rawJson
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('DB execute error (upsert CAMEX containers): ' . $err);
        }
        $written++;
    }
    $stmt->close();

    return $written;
}

/** @return array<string,mixed>|null */
function camex_az_sync_flight_packages_find_track(mysqli $db, string $tableName, string $tracking): ?array
{
    if (!camex_az_sync_flight_packages_table_exists($db, $tableName)) {
        return null;
    }
    $safe = camex_az_sync_flight_packages_qtable($tableName);
    $columns = camex_az_sync_flight_packages_columns($db, $tableName);
    $where = [];
    $types = '';
    $params = [];
    foreach (['tracking_no', 'tuid'] as $column) {
        if (isset($columns[$column])) {
            $where[] = "{$column} = ?";
            $types .= 's';
            $params[] = $tracking;
        }
    }
    if ($where === []) {
        return null;
    }
    $stmt = $db->prepare("SELECT * FROM {$safe} WHERE " . implode(' OR ', $where) . ' ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    $stmt->close();
    return is_array($row) ? $row : null;
}

/** @param array<string,mixed> $payload */
function camex_az_sync_flight_packages_audit(mysqli $db, string $tracking, string $status, string $message, array $payload, bool $dryRun): void
{
    if ($dryRun || !camex_az_sync_flight_packages_table_exists($db, 'warehouse_sync_audit')) {
        return;
    }
    $columns = camex_az_sync_flight_packages_columns($db, 'warehouse_sync_audit');
    $values = [
        'source' => CAMEX_AZ_FLIGHT_PACKAGES_SOURCE,
        'tracking_no' => $tracking,
        'forwarder' => CAMEX_AZ_FORWARDER,
        'country_code' => CAMEX_AZ_COUNTRY,
        'status' => $status,
        'message' => $message,
        'response_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'raw_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ];
    $insert = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) {
            $insert[$column] = $value;
        }
    }
    if ($insert === []) {
        return;
    }
    $safe = camex_az_sync_flight_packages_qtable('warehouse_sync_audit');
    $cols = array_keys($insert);
    $sql = "INSERT INTO {$safe} (`" . implode('`,`', array_map(static fn($c) => str_replace('`', '``', $c), $cols)) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $types = str_repeat('s', count($insert));
    $params = array_values($insert);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
}

/** @param array<string,mixed> $values */
function camex_az_sync_flight_packages_update_dynamic(mysqli $db, string $tableName, int $id, array $values): bool
{
    $columns = camex_az_sync_flight_packages_columns($db, $tableName);
    $sets = [];
    $params = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) {
            $sets[] = '`' . str_replace('`', '``', $column) . '` = ?';
            $params[] = $value;
        }
    }
    if ($sets === []) {
        return false;
    }
    $safe = camex_az_sync_flight_packages_qtable($tableName);
    $sql = "UPDATE {$safe} SET " . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $params[] = $id;
    $types = str_repeat('s', count($params) - 1) . 'i';
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** @return array<string,array<string,mixed>> */
function camex_az_sync_flight_packages_describe_columns(mysqli $db, string $tableName): array
{
    $result = $db->query('SHOW COLUMNS FROM ' . camex_az_sync_flight_packages_qtable($tableName));
    $columns = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = $row;
        }
        $result->close();
    }
    return $columns;
}

function camex_az_sync_flight_packages_is_valid_place(string $placeCode): bool
{
    return preg_match('/^BOX[0-9]+$/', strtoupper(trim($placeCode))) === 1;
}

function camex_az_sync_flight_packages_camex_batch_uid(int $connectorId): int
{
    return 99990000 + $connectorId;
}

/** @param array<string,mixed> $package */
function camex_az_sync_flight_packages_receiver_name(array $package): string
{
    $name = trim(trim((string)($package['first_name'] ?? '')) . ' ' . trim((string)($package['last_name'] ?? '')));
    return $name !== '' ? $name : CAMEX_AZ_FORWARDER;
}

/** @param array<string,mixed> $package */
function camex_az_sync_flight_packages_payload(array $package, int $connectorId, string $placeCode): string
{
    return json_encode([
        'source' => CAMEX_AZ_FLIGHT_PACKAGES_SOURCE,
        'connector_id' => $connectorId,
        'place_code' => $placeCode,
        'package' => $package,
        'synced_at' => gmdate('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

/** @param array<string,mixed> $values @param array<string,array<string,mixed>> $meta @return array<string,mixed> */
function camex_az_sync_flight_packages_with_required_defaults(array $values, array $meta, int $connectorId): array
{
    foreach ($meta as $column => $info) {
        if (array_key_exists($column, $values) || $column === 'id') {
            continue;
        }
        $null = strtoupper((string)($info['Null'] ?? ''));
        $default = $info['Default'] ?? null;
        $extra = strtolower((string)($info['Extra'] ?? ''));
        if ($null === 'NO' && $default === null && strpos($extra, 'auto_increment') === false) {
            if (in_array($column, ['batch_uid'], true)) {
                $values[$column] = camex_az_sync_flight_packages_camex_batch_uid($connectorId);
            } elseif (in_array($column, ['uid_created', 'user_id'], true)) {
                $values[$column] = CAMEX_AZ_BOT_ID;
            } elseif (preg_match('/(_at|date)$/', $column) === 1) {
                $values[$column] = gmdate('Y-m-d H:i:s');
            } elseif (strpos((string)($info['Type'] ?? ''), 'int') !== false || strpos((string)($info['Type'] ?? ''), 'decimal') !== false || strpos((string)($info['Type'] ?? ''), 'float') !== false || strpos((string)($info['Type'] ?? ''), 'double') !== false) {
                $values[$column] = 0;
            } else {
                $values[$column] = '';
            }
        }
    }
    return $values;
}

/** @param array<string,mixed> $package @return array{action:string,stock_id:?int,stock:?array,message:string} */
function camex_az_sync_flight_packages_ensure_stock(mysqli $db, int $connectorId, array $package, string $placeCode, bool $dryRun): array
{
    $tracking = trim((string)($package['tracking'] ?? ''));
    if ($tracking === '') {
        return ['action' => 'error', 'stock_id' => null, 'stock' => null, 'message' => 'missing tracking'];
    }
    $stock = camex_az_sync_flight_packages_find_track($db, 'warehouse_item_stock', $tracking);
    $columns = camex_az_sync_flight_packages_columns($db, 'warehouse_item_stock');
    $now = gmdate('Y-m-d H:i:s');
    $cellId = null;
    if (function_exists('warehouse_forwarder_resolve_local_cell')) {
        try {
            $cellId = warehouse_forwarder_resolve_local_cell($db, $connectorId, $placeCode, CAMEX_AZ_COUNTRY);
        } catch (Throwable $e) {
            $cellId = null;
        }
    }
    if ($stock === null) {
        $values = [
            'batch_uid' => camex_az_sync_flight_packages_camex_batch_uid($connectorId),
            'uid_created' => CAMEX_AZ_BOT_ID,
            'user_id' => CAMEX_AZ_BOT_ID,
            'committed' => 1,
            'tuid' => $tracking,
            'tracking_no' => $tracking,
            'receiver_name' => camex_az_sync_flight_packages_receiver_name($package),
            'receiver_company' => CAMEX_AZ_FORWARDER,
            'receiver_country_code' => CAMEX_AZ_COUNTRY,
            'weight_kg' => (string)($package['weight_kg'] ?? '0'),
            'source_origin' => CAMEX_AZ_FLIGHT_PACKAGES_SOURCE,
            'connector_id' => $connectorId,
            'forwarder_position_code' => $placeCode,
            'forwarder_synced_at' => $now,
            'addons_json' => camex_az_sync_flight_packages_payload($package, $connectorId, $placeCode),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($cellId !== null) {
            $values['cell_id'] = $cellId;
        }
        $meta = camex_az_sync_flight_packages_describe_columns($db, 'warehouse_item_stock');
        $values = camex_az_sync_flight_packages_with_required_defaults($values, $meta, $connectorId);
        $insert = [];
        foreach ($values as $column => $value) {
            if (isset($columns[$column]) && $value !== null) {
                $insert[$column] = $value;
            }
        }
        if ($dryRun) {
            return ['action' => 'would_create', 'stock_id' => null, 'stock' => $insert, 'message' => 'would create stock from CAMEX flight package'];
        }
        $safe = camex_az_sync_flight_packages_qtable('warehouse_item_stock');
        $cols = array_keys($insert);
        $sql = "INSERT INTO {$safe} (`" . implode('`,`', array_map(static fn($c) => str_replace('`', '``', $c), $cols)) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (ensure_stock): ' . $db->error);
        }
        $params = array_map(static fn($v) => (string)$v, array_values($insert));
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('DB execute error (ensure_stock): ' . $err);
        }
        $id = (int)$db->insert_id;
        $stmt->close();
        $stock = camex_az_sync_flight_packages_find_track($db, 'warehouse_item_stock', $tracking);
        return ['action' => 'created', 'stock_id' => $id, 'stock' => $stock, 'message' => 'created stock from CAMEX flight package'];
    }

    $updates = [
        'forwarder_position_code' => $placeCode,
        'forwarder_synced_at' => $now,
        'addons_json' => camex_az_sync_flight_packages_payload($package, $connectorId, $placeCode),
        'updated_at' => $now,
    ];
    if (empty($stock['connector_id'])) {
        $updates['connector_id'] = $connectorId;
    }
    if (empty($stock['receiver_name'])) {
        $updates['receiver_name'] = camex_az_sync_flight_packages_receiver_name($package);
    }
    if (empty($stock['receiver_company'])) {
        $updates['receiver_company'] = CAMEX_AZ_FORWARDER;
    }
    if (empty($stock['receiver_country_code'])) {
        $updates['receiver_country_code'] = CAMEX_AZ_COUNTRY;
    }
    if (empty($stock['weight_kg']) && isset($package['weight_kg'])) {
        $updates['weight_kg'] = (string)$package['weight_kg'];
    }
    if ($cellId !== null && empty($stock['cell_id'])) {
        $updates['cell_id'] = $cellId;
    }
    $changed = false;
    foreach ($updates as $column => $value) {
        if (isset($columns[$column]) && (string)($stock[$column] ?? '') !== (string)$value) {
            $changed = true;
            break;
        }
    }
    if (!$changed) {
        return ['action' => 'exists', 'stock_id' => (int)$stock['id'], 'stock' => $stock, 'message' => 'stock already up to date'];
    }
    if ($dryRun) {
        return ['action' => 'would_update', 'stock_id' => (int)$stock['id'], 'stock' => $stock, 'message' => 'would update stock CAMEX fields'];
    }
    if (!camex_az_sync_flight_packages_update_dynamic($db, 'warehouse_item_stock', (int)$stock['id'], $updates)) {
        throw new RuntimeException('DB update error (ensure_stock): ' . $db->error);
    }
    $stock = camex_az_sync_flight_packages_find_track($db, 'warehouse_item_stock', $tracking);
    return ['action' => 'updated', 'stock_id' => (int)($stock['id'] ?? 0), 'stock' => $stock, 'message' => 'updated stock CAMEX fields'];
}

/** @param array<string,mixed>|null $stockRow */
function camex_az_sync_flight_packages_insert_out(mysqli $db, int $connectorId, string $tracking, ?array $stockRow, string $status, string $flightNo, string $placeCode): ?int
{
    if (!camex_az_sync_flight_packages_table_exists($db, 'warehouse_item_out')) {
        return null;
    }
    $columns = camex_az_sync_flight_packages_columns($db, 'warehouse_item_out');
    $now = gmdate('Y-m-d H:i:s');
    $batchUid = (int)($stockRow['batch_uid'] ?? 0);
    if ($batchUid <= 0) {
        $batchUid = camex_az_sync_flight_packages_camex_batch_uid($connectorId);
    }
    $message = $status === 'to_send'
        ? 'camex_place_code=BOX100 camex_state=declared_ready_to_ship'
        : 'camex_place_code=' . $placeCode . ' camex_state=forwarder_already_shipped';
    $values = [
        'batch_uid' => $batchUid,
        'stock_item_id' => $stockRow['id'] ?? null,
        'tracking_no' => $tracking,
        'tuid' => $tracking,
        'status' => $status,
        'status_message' => $message,
        'status_updated_at' => $now,
        'forwarder' => CAMEX_AZ_FORWARDER,
        'receiver_company' => CAMEX_AZ_FORWARDER,
        'country' => CAMEX_AZ_COUNTRY,
        'country_code' => CAMEX_AZ_COUNTRY,
        'receiver_country_code' => CAMEX_AZ_COUNTRY,
        'receiver_name' => $stockRow['receiver_name'] ?? CAMEX_AZ_FORWARDER,
        'receiver_address' => $stockRow['receiver_address'] ?? null,
        'weight_kg' => $stockRow['weight_kg'] ?? null,
        'uid_created' => $stockRow['uid_created'] ?? CAMEX_AZ_BOT_ID,
        'user_id' => $stockRow['user_id'] ?? CAMEX_AZ_BOT_ID,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($status === 'sended') {
        $values['shipped_flight_no'] = $flightNo;
        $values['flight_no'] = $flightNo;
        $values['shipped_container_name'] = $placeCode;
        $values['shipped_at'] = $now;
    }
    $meta = camex_az_sync_flight_packages_describe_columns($db, 'warehouse_item_out');
    $values = camex_az_sync_flight_packages_with_required_defaults($values, $meta, $connectorId);
    $insert = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column]) && $value !== null) {
            $insert[$column] = $value;
        }
    }
    $safe = camex_az_sync_flight_packages_qtable('warehouse_item_out');
    $cols = array_keys($insert);
    $sql = "INSERT INTO {$safe} (`" . implode('`,`', array_map(static fn($c) => str_replace('`', '``', $c), $cols)) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (create_out): ' . $db->error);
    }
    $params = array_map(static fn($v) => (string)$v, array_values($insert));
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (create_out): ' . $err);
    }
    $id = (int)$db->insert_id;
    $stmt->close();
    return $id;
}

/** @param array<string,mixed> $package @return array<string,int> */
function camex_az_sync_flight_packages_sync_local(mysqli $db, int $connectorId, array $package, string $flightNo, bool $dryRun): array
{
    $stats = ['matched_local' => 0, 'left_in_stock' => 0, 'moved_to_out' => 0, 'marked_sended' => 0, 'skipped_unknown' => 0, 'local_sync_attempted' => 0, 'stock_created' => 0, 'stock_updated' => 0, 'stock_existing' => 0, 'out_created_to_send' => 0, 'out_existing_to_send' => 0, 'out_skipped_advanced_state' => 0, 'out_created_sended' => 0, 'out_promoted_to_sended' => 0, 'out_existing_sended' => 0, 'skipped_invalid_place' => 0, 'skipped_missing_tracking' => 0, 'would_create_stock' => 0, 'would_create_to_send' => 0, 'would_create_sended' => 0, 'would_promote_sended' => 0, 'errors' => 0];
    $tracking = trim((string)($package['tracking'] ?? ''));
    $placeCode = strtoupper(trim((string)($package['place_code'] ?? 'UNKNOWN')));
    $auditPayload = static fn(?array $stock, ?array $out, ?string $prev, ?string $result): array => [
        'connector_id' => $connectorId,
        'tracking' => $tracking,
        'flight_no' => $flightNo,
        'place_code' => $placeCode,
        'stock_item_id' => $stock['id'] ?? null,
        'out_item_id' => $out['id'] ?? null,
        'previous_out_status' => $prev,
        'resulting_out_status' => $result,
        'package' => $package,
    ];

    if ($tracking === '') {
        $stats['skipped_unknown']++;
        $stats['skipped_missing_tracking']++;
        return $stats;
    }
    $stats['local_sync_attempted']++;

    if (!camex_az_sync_flight_packages_is_valid_place($placeCode)) {
        $stats['skipped_invalid_place']++;
        camex_az_sync_flight_packages_audit($db, $tracking, 'skipped_invalid_place', 'manual review: invalid CAMEX place code', $auditPayload(null, null, null, null), $dryRun);
        return $stats;
    }

    try {
        $ensure = camex_az_sync_flight_packages_ensure_stock($db, $connectorId, $package, $placeCode, $dryRun);
        if (in_array($ensure['action'], ['created', 'would_create'], true)) {
            $stats['stock_created']++;
            if ($ensure['action'] === 'would_create') {
                $stats['would_create_stock']++;
            }
        } elseif (in_array($ensure['action'], ['updated', 'would_update'], true)) {
            $stats['stock_updated']++;
        } elseif ($ensure['action'] === 'exists') {
            $stats['stock_existing']++;
        }
        $stock = $ensure['stock'];
        if (!$dryRun && $stock === null) {
            $stock = camex_az_sync_flight_packages_find_track($db, 'warehouse_item_stock', $tracking);
        }
        $out = camex_az_sync_flight_packages_find_track($db, 'warehouse_item_out', $tracking);
        if ($stock !== null || $out !== null) {
            $stats['matched_local']++;
        }
        if (!$dryRun && in_array($ensure['action'], ['created', 'updated'], true)) {
            camex_az_sync_flight_packages_audit($db, $tracking, 'stock_' . $ensure['action'] . '_from_camex', $ensure['message'], $auditPayload($stock, $out, $out['status'] ?? null, $out['status'] ?? null), $dryRun);
        }

        if ($out !== null && $stock !== null && empty($out['stock_item_id']) && !$dryRun) {
            camex_az_sync_flight_packages_update_dynamic($db, 'warehouse_item_out', (int)$out['id'], ['stock_item_id' => (int)$stock['id'], 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        $previousStatus = $out !== null ? strtolower(trim((string)($out['status'] ?? ''))) : null;

        if ($placeCode === 'BOX001') {
            $stats['left_in_stock']++;
            return $stats;
        }

        if ($placeCode === 'BOX100') {
            if ($out === null) {
                if ($dryRun) {
                    $stats['out_created_to_send']++;
                    $stats['would_create_to_send']++;
                    $stats['moved_to_out']++;
                    return $stats;
                }
                $id = camex_az_sync_flight_packages_insert_out($db, $connectorId, $tracking, $stock, 'to_send', $flightNo, $placeCode);
                $stats['out_created_to_send']++;
                $stats['moved_to_out']++;
                camex_az_sync_flight_packages_audit($db, $tracking, 'out_created_to_send_from_camex', 'created to_send out from CAMEX BOX100', $auditPayload($stock, ['id' => $id], null, 'to_send'), $dryRun);
                return $stats;
            }
            if ($previousStatus === '') {
                if (!$dryRun) {
                    camex_az_sync_flight_packages_update_dynamic($db, 'warehouse_item_out', (int)$out['id'], ['status' => 'to_send', 'status_message' => 'camex_place_code=BOX100 camex_state=declared_ready_to_ship', 'status_updated_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
                }
                $stats['out_created_to_send']++;
                $stats['moved_to_out']++;
                return $stats;
            }
            if ($previousStatus === 'to_send') {
                $stats['out_existing_to_send']++;
                camex_az_sync_flight_packages_audit($db, $tracking, 'existing_to_send', 'existing to_send out kept', $auditPayload($stock, $out, $previousStatus, $previousStatus), $dryRun);
            } else {
                $stats['out_skipped_advanced_state']++;
                camex_az_sync_flight_packages_audit($db, $tracking, 'skipped_existing_advanced_state', 'existing out status kept to avoid downgrade', $auditPayload($stock, $out, $previousStatus, $previousStatus), $dryRun);
            }
            return $stats;
        }

        if ($out === null) {
            if ($dryRun) {
                $stats['out_created_sended']++;
                $stats['would_create_sended']++;
                $stats['marked_sended']++;
                return $stats;
            }
            $id = camex_az_sync_flight_packages_insert_out($db, $connectorId, $tracking, $stock, 'sended', $flightNo, $placeCode);
            $stats['out_created_sended']++;
            $stats['marked_sended']++;
            camex_az_sync_flight_packages_audit($db, $tracking, 'out_created_sended_from_camex', 'created sended out from CAMEX transport BOX', $auditPayload($stock, ['id' => $id], null, 'sended'), $dryRun);
            return $stats;
        }
        if ($previousStatus === 'sended') {
            $stats['out_existing_sended']++;
            camex_az_sync_flight_packages_audit($db, $tracking, 'existing_sended', 'existing sended out kept without update', $auditPayload($stock, $out, $previousStatus, $previousStatus), $dryRun);
            return $stats;
        }
        if ($dryRun) {
            $stats['out_promoted_to_sended']++;
            $stats['would_promote_sended']++;
            $stats['marked_sended']++;
            return $stats;
        }
        $updates = [
            'status' => 'sended',
            'status_message' => 'camex_place_code=' . $placeCode . ' camex_state=forwarder_already_shipped',
            'status_updated_at' => gmdate('Y-m-d H:i:s'),
            'shipped_flight_no' => $flightNo,
            'flight_no' => $flightNo,
            'shipped_container_name' => $placeCode,
            'forwarder' => CAMEX_AZ_FORWARDER,
            'country' => CAMEX_AZ_COUNTRY,
            'country_code' => CAMEX_AZ_COUNTRY,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if (empty($out['shipped_at'])) {
            $updates['shipped_at'] = gmdate('Y-m-d H:i:s');
        }
        if (!camex_az_sync_flight_packages_update_dynamic($db, 'warehouse_item_out', (int)$out['id'], $updates)) {
            throw new RuntimeException('DB update error (update_out): ' . $db->error);
        }
        $stats['out_promoted_to_sended']++;
        $stats['marked_sended']++;
        camex_az_sync_flight_packages_audit($db, $tracking, 'out_promoted_sended_from_camex', 'promoted out to sended from CAMEX transport BOX', $auditPayload($stock, $out, $previousStatus, 'sended'), $dryRun);
    } catch (Throwable $e) {
        $stats['errors']++;
        camex_az_sync_flight_packages_audit($db, $tracking, 'error', $e->getMessage(), $auditPayload(null, null, null, null) + ['stage' => 'sync_local', 'sql_error' => $e->getMessage()], $dryRun);
    }

    return $stats;
}

$argv = $_SERVER['argv'] ?? [];
$args = ConnectorConfigRepository::cliArgs($argv);
if (isset($args['help']) || isset($args['h'])) {
    fwrite(STDOUT, camex_az_sync_flight_packages_usage() . PHP_EOL);
    exit(0);
}

$repoRoot = dirname(__DIR__, 5);
$connectorId = (int)($args['connector-id'] ?? $args['connector_id'] ?? 0);
$limitFlights = max(1, (int)($args['limit-flights'] ?? $args['limit_flights'] ?? 5));
$flightNoArg = trim((string)($args['flight-no'] ?? $args['flight_no'] ?? ''));
$targetTableArg = trim((string)($args['target-table'] ?? $args['target_table'] ?? 'connector_camex_az_operation_flight_list'));
$containersTableArg = trim((string)($args['containers-table'] ?? $args['containers_table'] ?? 'connector_camex_az_operation_flight_list_containers'));
$debugDir = trim((string)($args['debug-dir'] ?? $args['debug_dir'] ?? ''));
$dryRun = camex_az_sync_flight_packages_bool($args, 'dry-run', false);
$syncLocalStates = camex_az_sync_flight_packages_bool($args, 'sync-local-states', true);
$refreshFlightList = camex_az_sync_flight_packages_bool($args, 'refresh-flight-list', false);

try {
    require_once $repoRoot . '/configs/connectDB.php';
    require_once $repoRoot . '/www/api/connectors/connector_engine.php';
    require_once $repoRoot . '/www/api/connectors/subrunners/connector_modules.php';

    $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
    if (!($db instanceof mysqli)) {
        throw new RuntimeException('mysqli connection is not available');
    }
    $GLOBALS['dbcnx'] = $db;

    $connectorRow = ConnectorConfigRepository::loadRow($args, $repoRoot);
    $overrides = ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
    $connectorId = (int)($connectorRow['id'] ?? $connectorId);
    if ($connectorId <= 0) {
        throw new RuntimeException('Missing required --connector-id/--connector-name/--connector-key.');
    }

    $targetTable = camex_az_sync_flight_packages_safe_table($targetTableArg);
    $containersTable = camex_az_sync_flight_packages_safe_table($containersTableArg);
    if (!$dryRun) {
        camex_az_sync_flight_packages_prepare_schema($db, $targetTable, $containersTable);
    }

    if ($refreshFlightList) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/run_flight_list.php')
            . ' --connector-id=' . escapeshellarg((string)$connectorId)
            . ' --target-table=' . escapeshellarg($targetTable)
            . ' --dry-run=0';
        if ($debugDir !== '') {
            $cmd .= ' --debug-dir=' . escapeshellarg($debugDir);
        }
        $refreshOutput = [];
        $refreshCode = 0;
        exec($cmd . ' 2>&1', $refreshOutput, $refreshCode);
        if ($refreshCode !== 0) {
            throw new RuntimeException('refresh-flight-list failed: ' . implode("\n", $refreshOutput));
        }
    }

    $config = new ForwarderConfig($overrides);
    if ($config->baseUrl() === '' || $config->webLogin() === '' || $config->webPassword() === '') {
        throw new RuntimeException('Missing CAMEX_AZ connector base_url/auth_username/auth_password.');
    }
    if ($config->httpAuthEnabled() && ($config->httpAuthLogin() === '' || $config->httpAuthPassword() === '')) {
        throw new RuntimeException('Missing HTTP auth username/password for enabled HTTP auth.');
    }

    $correlationId = 'run-sync-flight-packages-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $logger = new ForwarderLogger($correlationId);
    $sessionClient = new CamexSessionClient($config, new ForwarderHttpClient($config), new SessionManager(), $logger);

    $flights = camex_az_sync_flight_packages_load_flights($db, $targetTable, $connectorId, $limitFlights, $flightNoArg);
    $summary = [
        'status' => 'ok',
        'connector' => 'CAMEX_AZ',
        'connector_id' => $connectorId,
        'mode' => $dryRun ? 'dry_run' : 'sync',
        'flights_requested' => $flightNoArg !== '' ? 1 : $limitFlights,
        'flights_processed' => 0,
        'containers_table' => $containersTable,
        'packages_seen' => 0,
        'container_rows_written' => 0,
        'matched_local' => 0,
        'left_in_stock' => 0,
        'moved_to_out' => 0,
        'marked_sended' => 0,
        'skipped_unknown' => 0,
        'local_sync_attempted' => 0,
        'stock_created' => 0,
        'stock_updated' => 0,
        'stock_existing' => 0,
        'out_created_to_send' => 0,
        'out_existing_to_send' => 0,
        'out_skipped_advanced_state' => 0,
        'out_created_sended' => 0,
        'out_promoted_to_sended' => 0,
        'out_existing_sended' => 0,
        'skipped_invalid_place' => 0,
        'skipped_missing_tracking' => 0,
        'would_create_stock' => 0,
        'would_create_to_send' => 0,
        'would_create_sended' => 0,
        'would_promote_sended' => 0,
        'errors' => 0,
        'flights' => [],
        'details' => [],
    ];

    foreach ($flights as $idx => $flight) {
        $flightNo = trim((string)($flight['flight_no'] ?? $flight['external_id'] ?? ''));
        if ($flightNo === '') {
            continue;
        }
        $path = '/cadmin/usa/index.php?do=flight&action=list&ID=' . rawurlencode($flightNo);
        $response = $sessionClient->requestWithSession('GET', $path, [], false);
        $httpStatus = (int)($response['status_code'] ?? 0);
        $body = (string)($response['body'] ?? '');
        $debugHtml = '';
        if ($debugDir !== '') {
            if (!is_dir($debugDir)) {
                @mkdir($debugDir, 0775, true);
            }
            $debugHtml = rtrim($debugDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sprintf('flight_%02d_%s.html', $idx + 1, preg_replace('/[^A-Za-z0-9_.-]+/', '_', $flightNo));
            @file_put_contents($debugHtml, $body);
        }

        if ($httpStatus !== 200 || empty($response['ok'])) {
            $summary['errors']++;
            $summary['flights'][] = ['flight_no' => $flightNo, 'http_status' => $httpStatus, 'packages_seen' => 0, 'groups' => [], 'error' => (string)($response['error'] ?? 'request failed'), 'debug_html' => $debugHtml];
            continue;
        }

        $packages = camex_az_sync_flight_packages_parse_html($body);
        $groups = camex_az_sync_flight_packages_group($packages);
        $written = camex_az_sync_flight_packages_write_containers($db, $containersTable, $connectorId, array_merge($flight, ['flight_no' => $flightNo]), $groups, $path, $dryRun);

        $flightGroups = [];
        foreach ($groups as $placeCode => $group) {
            $flightGroups[] = [
                'place_code' => $placeCode,
                'packages_count' => count($group['packages']),
                'total_weight' => number_format((float)$group['total_weight'], 3, '.', ''),
            ];
        }

        if ($syncLocalStates) {
            foreach ($packages as $package) {
                $local = camex_az_sync_flight_packages_sync_local($db, $connectorId, $package, $flightNo, $dryRun);
                foreach ($local as $key => $value) {
                    $summary[$key] += $value;
                }
            }
        } else {
            $summary['skipped_unknown'] += count($packages);
        }

        $summary['flights_processed']++;
        $summary['packages_seen'] += count($packages);
        $summary['container_rows_written'] += $written;
        $summary['flights'][] = [
            'flight_no' => $flightNo,
            'http_status' => $httpStatus,
            'packages_seen' => count($packages),
            'groups' => $flightGroups,
            'debug_html' => $debugHtml,
        ];
    }

    camex_az_sync_flight_packages_json($summary, 0);
} catch (Throwable $e) {
    camex_az_sync_flight_packages_json([
        'status' => 'error',
        'connector' => 'CAMEX_AZ',
        'connector_id' => $connectorId,
        'mode' => $dryRun ? 'dry_run' : 'sync',
        'stage' => 'run_sync_flight_packages',
        'message' => $e->getMessage(),
        'errors' => 1,
    ], 1);
}