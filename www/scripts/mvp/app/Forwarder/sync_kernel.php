<?php

declare(strict_types=1);

use App\Forwarder\Http\ForwarderSessionClient;

/**
 * Синхронизирует таблицу connector_*_flight_list_containers по данным /collector/get-containers для одного рейса.
 *
 * @param array{
 *   repo_root:string,
 *   session_client:ForwarderSessionClient,
 *   connector_id:int,
 *   flight_id:string,
 *   flight_table?:string,
 *   containers_table?:string,
 *   page_path?:string,
 *   csrf_token?:string
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
    $sessionClient = $params['session_client'] ?? null;

    if ($repoRoot === '' || $flightId === '' || $connectorId <= 0 || !($sessionClient instanceof ForwarderSessionClient)) {
        return [
            'status' => 'skipped',
            'message' => 'sync prerequisites are missing (repo_root/session_client/connector_id/flight_id)',
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
    $activeIds = [];
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
    }

    $deactivated = forwarder_sync_kernel_mark_missing_and_count(
        $db,
        $resolvedContainersTable,
        $connectorId,
        (string)($flightRow['external_id'] ?? ''),
        $activeIds
    );

    $snapshotRow = $flightRow;
    $snapshotRow['containers_url'] = '/collector/get-containers?flight_id=' . rawurlencode($flightId);
    $snapshotRow['containers_json'] = (string)json_encode($rows, JSON_UNESCAPED_UNICODE);
    $snapshotRow['containers_count'] = count($rows);
    $snapshotRow['containers_synced_at'] = gmdate('Y-m-d H:i:s');
    $snapshotRow['containers_sync_status'] = empty($rows) ? 'empty' : 'synced';
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
    $data = $payload['data'] ?? $payload;
    if (!is_array($data)) {
        return [];
    }

    $rows = [];
    foreach ($data as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
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
    $packagesCount = $packagesCountRaw === '' ? null : (int)$packagesCountRaw;

    $totalWeightRaw = (string)($row['total_weight'] ?? $row['weight'] ?? '');
    $totalWeightRaw = str_replace(',', '.', preg_replace('/[^0-9,\.\-]/', '', $totalWeightRaw) ?? '');
    $totalWeight = $totalWeightRaw === '' ? null : (float)$totalWeightRaw;

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
