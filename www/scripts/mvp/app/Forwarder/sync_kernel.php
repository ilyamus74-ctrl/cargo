<?php

declare(strict_types=1);

use App\Forwarder\Http\ForwarderSessionClient;

/**
 * Синхронизирует таблицу connector_*_flight_list_containers по данным /collector/get-containers для одного рейса.
 *
 * @param array{
 *   repo_root:string,
 *   session_client:ForwarderSessionClient,
 *   connector_id?:int,
 *   flight_id:string,
 *   flight_table?:string,
 *   containers_table?:string,
 *   page_path?:string,
 *   csrf_token?:string,
 *   allow_empty_result_deactivate?:bool,
 *   deactivate_missing?:bool
 * } $params
 *
 * @return array<string,mixed>
 */
function forwarder_sync_flight_containers_kernel(array $params): array
{
    $repoRoot = trim((string)($params['repo_root'] ?? ''));
    $flightId = trim((string)($params['flight_id'] ?? ''));
    $connectorId = (int)($params['connector_id'] ?? 0);
    $flightTable = trim((string)($params['flight_table'] ?? 'connector_dev_colibri_operation_flight_list'));
    $containersTable = trim((string)($params['containers_table'] ?? ''));
    $pagePath = trim((string)($params['page_path'] ?? '/collector/containers'));
    $allowEmptyResultDeactivate = !empty($params['allow_empty_result_deactivate']);
    $deactivateMissing = !array_key_exists('deactivate_missing', $params) || !empty($params['deactivate_missing']);
    $sessionClient = $params['session_client'] ?? null;

    if ($repoRoot === '' || $flightId === '' || !($sessionClient instanceof ForwarderSessionClient)) {
        return [
            'status' => 'skipped',
            'message' => 'sync prerequisites are missing (repo_root/session_client/flight_id)',
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
        ];
    }

    $connectDbPath = $repoRoot . '/configs/connectDB.php';
    $enginePath = $repoRoot . '/www/api/connectors/connector_engine.php';
    $subrunnerPath = $repoRoot . '/www/api/connectors/subrunners/connector_modules.php';
    foreach ([$connectDbPath, $enginePath, $subrunnerPath] as $requiredPath) {
        if (!is_file($requiredPath)) {
            return [
                'status' => 'error',
                'message' => 'required file not found: ' . $requiredPath,
                'written' => 0,
                'fetched' => 0,
                'deactivated' => 0,
            ];
        }

        require_once $requiredPath;
    }

    if (!class_exists('mysqli')) {
        return [
            'status' => 'skipped',
            'message' => 'mysqli extension is not available',
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
        ];
    }

    if (isset($dbcnx) && $dbcnx instanceof mysqli) {
        $GLOBALS['dbcnx'] = $dbcnx;
    }

    $db = $GLOBALS['dbcnx'] ?? ($dbcnx ?? null);
    if (!($db instanceof mysqli)) {
        return [
            'status' => 'skipped',
            'message' => 'mysqli connection is not available',
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
        ];
    }

    $safeFlightTable = connectors_subrunner_sanitize_table_name($flightTable);
    $resolvedContainersTable = $containersTable !== ''
        ? connectors_subrunner_sanitize_table_name($containersTable)
        : connectors_subrunner_resolve_flight_containers_table_name($safeFlightTable, []);

    connectors_subrunner_ensure_flight_table($db, $safeFlightTable);
    connectors_subrunner_ensure_flight_containers_table($db, $resolvedContainersTable);

    if ($connectorId <= 0) {
        $connectorId = forwarder_sync_kernel_detect_connector_id($db, $safeFlightTable, $flightId);
    }
    if ($connectorId <= 0) {
        return [
            'status' => 'error',
            'message' => 'connector_id is not provided and could not be detected by flight_id=' . $flightId,
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
        ];
    }

    $flightRow = forwarder_sync_kernel_load_flight_row($db, $safeFlightTable, $connectorId, $flightId);
    if ($flightRow === null) {
        return [
            'status' => 'error',
            'message' => 'flight row not found in DB: connector_id=' . $connectorId . ', external_id=' . $flightId,
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
        ];
    }

    $csrfToken = trim((string)($params['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $pageResponse = $sessionClient->requestWithSession('GET', $pagePath !== '' ? $pagePath : '/collector/containers', [], false);
        $pageStatusCode = (int)($pageResponse['status_code'] ?? 0);
        if (empty($pageResponse['ok']) || $pageStatusCode < 200 || $pageStatusCode >= 400) {
            return [
                'status' => 'error',
                'message' => 'containers page request failed',
                'http_status' => $pageStatusCode,
                'error' => (string)($pageResponse['error'] ?? ''),
                'written' => 0,
                'fetched' => 0,
                'deactivated' => 0,
                'flight_table' => $safeFlightTable,
                'containers_table' => $resolvedContainersTable,
            ];
        }

        $csrfToken = forwarder_sync_kernel_extract_csrf_token((string)($pageResponse['body'] ?? ''));
    }

    if ($csrfToken === '') {
        return [
            'status' => 'error',
            'message' => 'csrf token not found',
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
        ];
    }

    $containersResponse = $sessionClient->requestWithSession('GET', '/collector/get-containers', [
        'flight_id' => $flightId,
        '_token' => $csrfToken,
    ], false);

    $containersStatusCode = (int)($containersResponse['status_code'] ?? 0);
    $containersBody = (string)($containersResponse['body'] ?? '');
    $containersJson = json_decode($containersBody, true);
    if (empty($containersResponse['ok']) || $containersStatusCode < 200 || $containersStatusCode >= 400 || !is_array($containersJson)) {
        return [
            'status' => 'error',
            'message' => 'get-containers request failed',
            'http_status' => $containersStatusCode,
            'error' => (string)($containersResponse['error'] ?? ''),
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
        ];
    }

    $rows = forwarder_sync_kernel_extract_containers_rows($containersJson);
    $activeBeforeSync = forwarder_sync_kernel_count_active_containers(
        $db,
        $resolvedContainersTable,
        $connectorId,
        (string)($flightRow['external_id'] ?? '')
    );
    if ($rows === [] && $activeBeforeSync > 0 && !$allowEmptyResultDeactivate) {
        return [
            'status' => 'error',
            'message' => 'empty get-containers payload; deactivation skipped to avoid data loss',
            'written' => 0,
            'fetched' => 0,
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
            'flight_row_id' => (int)$flightRow['id'],
            'flight_external_id' => (string)$flightRow['external_id'],
            'active_before_sync' => $activeBeforeSync,
        ];
    }
    $activeIds = [];
    $normalizedContainers = [];
    $written = 0;
    foreach ($rows as $index => $row) {
        $normalized = forwarder_sync_kernel_normalize_container_row($row, $flightRow, $index + 1);
        if ($normalized === null) {
            continue;
        }

        connectors_subrunner_upsert_container_row(
            $db,
            $resolvedContainersTable,
            $connectorId,
            (int)$flightRow['id'],
            $flightRow,
            $normalized
        );
        $written++;
        $activeIds[] = (string)$normalized['container_external_id'];
        $normalizedContainers[] = $normalized;
    }
    if ($activeIds === [] && $activeBeforeSync > 0 && !$allowEmptyResultDeactivate) {
        return [
            'status' => 'error',
            'message' => 'container rows could not be normalized; deactivation skipped to avoid data loss',
            'written' => $written,
            'fetched' => count($rows),
            'deactivated' => 0,
            'flight_table' => $safeFlightTable,
            'containers_table' => $resolvedContainersTable,
            'flight_row_id' => (int)$flightRow['id'],
            'flight_external_id' => (string)$flightRow['external_id'],
            'active_before_sync' => $activeBeforeSync,
        ];
    }

    $deactivated = 0;
    if ($deactivateMissing) {
        $deactivated = forwarder_sync_kernel_mark_missing_and_count(
            $db,
            $resolvedContainersTable,
            $connectorId,
            (string)($flightRow['external_id'] ?? ''),
            $activeIds
        );
    }

    $snapshotRow = $flightRow;
    $snapshotRow['containers_url'] = '/collector/get-containers?flight_id=' . rawurlencode($flightId);
    $snapshotRow['containers_json'] = (string)json_encode($normalizedContainers, JSON_UNESCAPED_UNICODE);
    $snapshotRow['containers_count'] = count($normalizedContainers);
    $snapshotRow['containers_synced_at'] = gmdate('Y-m-d H:i:s');
    $snapshotRow['containers_sync_status'] = empty($normalizedContainers) ? 'empty' : 'synced';
    $snapshotRow['containers_sync_error'] = null;
    connectors_subrunner_update_flight_container_snapshot($db, $safeFlightTable, (int)$flightRow['id'], $snapshotRow);

    return [
        'status' => 'ok',
        'message' => 'containers table synced from /collector/get-containers',
        'written' => $written,
        'fetched' => count($rows),
        'deactivated' => $deactivated,
        'flight_table' => $safeFlightTable,
        'containers_table' => $resolvedContainersTable,
        'flight_row_id' => (int)$flightRow['id'],
        'flight_external_id' => (string)$flightRow['external_id'],
    ];
}


function forwarder_sync_kernel_count_active_containers(
    mysqli $db,
    string $containersTable,
    int $connectorId,
    string $flightExternalId
): int {
    if ($flightExternalId === '') {
        return 0;
    }

    $safeTable = '`' . str_replace('`', '``', $containersTable) . '`';
    $sql = "SELECT COUNT(*) AS cnt FROM {$safeTable} WHERE connector_id = ? AND flight_external_id = ? AND is_active = 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (count active containers): ' . $db->error);
    }
    $stmt->bind_param('is', $connectorId, $flightExternalId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (count active containers): ' . $err);
    }

    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    return is_array($row) ? (int)($row['cnt'] ?? 0) : 0;
}

function forwarder_sync_kernel_load_flight_row(mysqli $db, string $flightTable, int $connectorId, string $flightId): ?array
{
    $safeTable = '`' . str_replace('`', '``', $flightTable) . '`';
    $sql = "SELECT * FROM {$safeTable} WHERE connector_id = ? AND external_id = ? ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (load flight row): ' . $db->error);
    }

    $stmt->bind_param('is', $connectorId, $flightId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (load flight row): ' . $err);
    }

    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->close();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}


if (!function_exists('forwarder_sync_kernel_detect_connector_id')) {
    function forwarder_sync_kernel_detect_connector_id(mysqli $db, string $flightTable, string $flightId): int
    {
        $safeTable = '`' . str_replace('`', '``', $flightTable) . '`';
        $sql = "SELECT connector_id FROM {$safeTable} WHERE external_id = ? ORDER BY is_active DESC, updated_at DESC, id DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (detect connector_id): ' . $db->error);
        }

        $stmt->bind_param('s', $flightId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('DB execute error (detect connector_id): ' . $err);
        }

        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->close();
        }
        $stmt->close();

        return is_array($row) ? (int)($row['connector_id'] ?? 0) : 0;
    }
}


function forwarder_sync_kernel_extract_csrf_token(string $html): string
{
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

/** @return array<int,array<string,mixed>> */
function forwarder_sync_kernel_extract_containers_rows(array $payload): array
{
    if (isset($payload['containers']) && is_array($payload['containers'])) {
        $rows = [];
        foreach ($payload['containers'] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    $data = $payload['data'] ?? $payload;
    if (is_string($data)) {
        return forwarder_sync_kernel_extract_containers_rows_from_html($data);
    }
    if (is_array($data) && isset($data['html']) && is_string($data['html'])) {
        return forwarder_sync_kernel_extract_containers_rows_from_html((string)$data['html']);
    }
    if (!is_array($data)) {
        if (isset($payload['html']) && is_string($payload['html'])) {
            return forwarder_sync_kernel_extract_containers_rows_from_html((string)$payload['html']);
        }
        return [];
    }

    $rows = [];
    foreach ($data as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }


    if (count($rows) === 1 && isset($rows[0]) && is_array($rows[0]) && array_is_list($rows[0])) {
        $flattened = [];
        foreach ($rows[0] as $nestedRow) {
            if (is_array($nestedRow)) {
                $flattened[] = $nestedRow;
            }
        }

        if ($flattened !== []) {
            return $flattened;
        }
    }
    return $rows;
}


/** @return array<int,array<string,mixed>> */
function forwarder_sync_kernel_extract_containers_rows_from_html(string $html): array
{
    $html = trim($html);
    if ($html === '') {
        return [];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML($html);
    libxml_clear_errors();
    if (!$loaded) {
        return [];
    }

    $xpath = new DOMXPath($dom);
    $rowNodes = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " references-table ")]//tbody//tr');
    if (!($rowNodes instanceof DOMNodeList) || $rowNodes->length === 0) {
        $rowNodes = $xpath->query('//tbody//tr');
    }
    if (!($rowNodes instanceof DOMNodeList)) {
        return [];
    }

    $rows = [];
    foreach ($rowNodes as $rowNode) {
        if (!($rowNode instanceof DOMElement)) {
            continue;
        }

        $cells = [];
        foreach ($xpath->query('.//td', $rowNode) as $tdNode) {
            if ($tdNode instanceof DOMElement) {
                $cells[] = trim((string)$tdNode->textContent);
            }
        }
        if ($cells === []) {
            continue;
        }

        $rowIdAttr = trim((string)$rowNode->getAttribute('id'));
        $containerId = '';
        if ($rowIdAttr !== '' && preg_match('/(\d+)/', $rowIdAttr, $m)) {
            $containerId = (string)$m[1];
        }
        if ($containerId === '' && preg_match('/(\d+)/', (string)($cells[0] ?? ''), $m)) {
            $containerId = (string)$m[1];
        }

        $rows[] = [
            'id' => $containerId,
            'name' => (string)($cells[0] ?? ''),
            'flight' => (string)($cells[1] ?? ''),
            'departure' => (string)($cells[2] ?? ''),
            'destination' => (string)($cells[3] ?? ''),
            'awb' => (string)($cells[4] ?? ''),
            'packages_count' => (string)($cells[5] ?? ''),
            'total_weight' => (string)($cells[6] ?? ''),
            'raw_row_html' => $dom->saveHTML($rowNode),
        ];
    }

    return $rows;
}


/** @return array<string,mixed>|null */
function forwarder_sync_kernel_normalize_container_row(array $row, array $flightRow, int $rowNo): ?array
{
    $idCandidates = [
        (string)($row['id'] ?? ''),
        (string)($row['container_id'] ?? ''),
        (string)($row['container'] ?? ''),
        (string)($row['external_id'] ?? ''),
    ];

    $containerExternalId = '';
    foreach ($idCandidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $containerExternalId = $candidate;
            break;
        }
    }

    if ($containerExternalId === '') {
        return null;
    }

    $packagesCountRaw = (string)($row['packages_count'] ?? $row['package_count'] ?? '');
    $packagesCountRaw = preg_replace('/[^0-9\-]/', '', $packagesCountRaw) ?? '';
    $packagesCount = $packagesCountRaw === '' ? 0 : (int)$packagesCountRaw;

    $totalWeightRaw = (string)($row['total_weight'] ?? $row['weight'] ?? '');
    $totalWeightRaw = str_replace(',', '.', preg_replace('/[^0-9,\.\-]/', '', $totalWeightRaw) ?? '');
    $totalWeight = $totalWeightRaw === '' ? 0.0 : (float)$totalWeightRaw;

    return [
        'container_external_id' => $containerExternalId,
        'name' => trim((string)($row['name'] ?? $row['number'] ?? $row['container'] ?? $containerExternalId)),
        'flight' => trim((string)($row['flight'] ?? $flightRow['flight_no'] ?? '')),
        'departure' => trim((string)($row['departure'] ?? $flightRow['departure'] ?? '')),
        'destination' => trim((string)($row['destination'] ?? $flightRow['destination'] ?? '')),
        'awb' => trim((string)($row['awb'] ?? $flightRow['awb'] ?? '')),
        'packages_count' => $packagesCount,
        'total_weight' => $totalWeight,
        'is_active' => 1,
        'raw_json' => json_encode([
            'row_no' => $rowNo,
            'source' => 'collector/get-containers',
            'row' => $row,
            'flight_external_id' => (string)($flightRow['external_id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE),
    ];
}

function forwarder_sync_kernel_mark_missing_and_count(
    mysqli $db,
    string $containersTable,
    int $connectorId,
    string $flightExternalId,
    array $activeIds
): int {
    if ($flightExternalId === '') {
        return 0;
    }

    $safeTable = '`' . str_replace('`', '``', $containersTable) . '`';
    $countSql = "SELECT COUNT(*) AS cnt FROM {$safeTable} WHERE connector_id = ? AND flight_external_id = ? AND is_active = 1";
    $countParams = [$connectorId, $flightExternalId];
    $countTypes = 'is';
    if ($activeIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($activeIds), '?'));
        $countSql .= " AND container_external_id NOT IN ({$placeholders})";
        $countTypes .= str_repeat('s', count($activeIds));
        foreach ($activeIds as $activeId) {
            $countParams[] = (string)$activeId;
        }
    }

    $countStmt = $db->prepare($countSql);
    if (!$countStmt) {
        throw new RuntimeException('DB prepare error (count containers to deactivate): ' . $db->error);
    }
    connectors_subrunner_bind_dynamic_params($countStmt, $countTypes, $countParams);
    if (!$countStmt->execute()) {
        $err = $countStmt->error;
        $countStmt->close();
        throw new RuntimeException('DB execute error (count containers to deactivate): ' . $err);
    }

    $countResult = $countStmt->get_result();
    $countRow = $countResult instanceof mysqli_result ? $countResult->fetch_assoc() : null;
    if ($countResult instanceof mysqli_result) {
        $countResult->close();
    }
    $countStmt->close();
    $toDeactivate = is_array($countRow) ? (int)($countRow['cnt'] ?? 0) : 0;

    connectors_subrunner_mark_missing_containers_inactive($db, $containersTable, $connectorId, $flightExternalId, $activeIds);

    return $toDeactivate;
}
