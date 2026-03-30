<?php

declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown departures action'];

require_once __DIR__ . '/../connectors/subrunners/connector_modules.php';

function departures_resolve_action_alias(string $action): string
{
    if (preg_match('/^departures$/i', $action)) {
        return 'view_departures';
    }

    if (preg_match('/^flight[_.-]*list$/i', $action)) {
        return 'view_departures';
    }

    static $aliases = [
        'departures' => 'view_departures',
        'flight_list' => 'view_departures',
        'flightlist' => 'view_departures',
    ];

    return $aliases[$action] ?? $action;
}

function departures_extract_runtime_operations(array $connector): array
{
    $raw = trim((string)($connector['operations_json'] ?? ''));
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    if (isset($decoded['operations']) && is_array($decoded['operations'])) {
        $operations = [];
        foreach ($decoded['operations'] as $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $operationId = trim((string)($operation['operation_id'] ?? ''));
            if ($operationId === '') {
                continue;
            }

            $operations[$operationId] = [
                'operation_id' => $operationId,
                'config' => isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [],
            ];
        }

        return $operations;
    }

    $operations = [];
    foreach ($decoded as $operationKey => $operation) {
        if (!is_array($operation)) {
            continue;
        }

        $operationId = trim((string)($operation['operation_id'] ?? $operationKey));
        if ($operationId === '') {
            continue;
        }

        $config = [];
        foreach ([
            'page_url', 'file_extension', 'download_mode', 'log_steps', 'steps', 'curl_config',
            'target_table', 'field_mapping', 'request_config', 'success_selector', 'success_text', 'error_selector',
            'subrunner',
        ] as $configKey) {
            if (array_key_exists($configKey, $operation)) {
                $config[$configKey] = $operation[$configKey];
            }
        }

        $operations[$operationId] = [
            'operation_id' => $operationId,
            'config' => $config,
        ];
    }

    return $operations;
}

function departures_table_exists(mysqli $dbcnx, string $tableName): bool
{
    $normalizedTable = connectors_subrunner_sanitize_table_name($tableName);
    $escapedTable = $dbcnx->real_escape_string($normalizedTable);
    $res = $dbcnx->query("SHOW TABLES LIKE '{$escapedTable}'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }

    return false;
}

function departures_resolve_table_names(array $connector): array
{
    $tableNames = [];
    $runtimeOperations = departures_extract_runtime_operations($connector);

    foreach ($runtimeOperations as $operation) {
        if (!is_array($operation)) {
            continue;
        }

        $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];
        $subrunner = isset($config['subrunner']) && is_array($config['subrunner']) ? $config['subrunner'] : [];
        $subrunnerName = trim((string)($subrunner['name'] ?? ''));
        if ($subrunnerName === '' || stripos($subrunnerName, 'flight_list') !== 0) {
            continue;
        }

        $options = isset($subrunner['options']) && is_array($subrunner['options']) ? $subrunner['options'] : [];

        try {
            $tableNames[] = connectors_subrunner_resolve_flight_table_name($connector, $options);
        } catch (Throwable $e) {
            error_log('departures resolve table error: ' . $e->getMessage());
        }
    }

    if ($tableNames === []) {
        try {
            $tableNames[] = connectors_subrunner_resolve_flight_table_name($connector, []);
        } catch (Throwable $e) {
            error_log('departures resolve default table error: ' . $e->getMessage());
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $tableNames))));
}

function departures_status_badge_class(string $status): string
{
    $normalizedStatus = strtoupper(trim($status));

    return match ($normalizedStatus) {
        'OPEN', 'ACTIVE', 'READY' => 'success',
        'CLOSED', 'DONE', 'COMPLETED' => 'secondary',
        'ERROR', 'FAILED' => 'danger',
        default => 'light text-dark',
    };
}

function departures_format_value($value, int $decimals = 3): string
{
    if ($value === null) {
        return '—';
    }

    if (is_numeric($value)) {
        $normalized = number_format((float)$value, $decimals, '.', '');
        $normalized = rtrim(rtrim($normalized, '0'), '.');
        return $normalized !== '' ? $normalized : '0';
    }

    $stringValue = trim((string)$value);
    return $stringValue !== '' ? $stringValue : '—';
}

function departures_format_datetime(?string $value): string
{
    $normalized = trim((string)$value);
    if ($normalized === '' || $normalized === '0000-00-00 00:00:00') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }

    return date('Y-m-d H:i', $timestamp);
}

function departures_decode_containers($rawContainers): array
{
    $decoded = json_decode((string)$rawContainers, true);
    if (!is_array($decoded)) {
        return [];
    }

    $containers = [];
    foreach ($decoded as $container) {
        if (!is_array($container)) {
            continue;
        }
        $containerExternalId = trim((string)($container['container_external_id'] ?? ''));
        $packagesCountRaw = $container['packages_count'] ?? null;
        $totalWeightRaw = $container['total_weight'] ?? null;

        $hasZeroPackages = departures_value_is_zero($packagesCountRaw);
        $hasZeroWeight = departures_value_is_zero($totalWeightRaw);
        $hasPackages = is_numeric($packagesCountRaw) && (float)$packagesCountRaw > 0.0;
        $hasWeight = is_numeric($totalWeightRaw) && (float)$totalWeightRaw > 0.0;

        $containers[] = [
            'container_external_id' => $containerExternalId,
            'name' => trim((string)($container['name'] ?? '')),
            'flight' => trim((string)($container['flight'] ?? '')),
            'departure' => trim((string)($container['departure'] ?? '')),
            'destination' => trim((string)($container['destination'] ?? '')),
            'awb' => trim((string)($container['awb'] ?? '')),
            'packages_count' => departures_format_value($packagesCountRaw, 0),
            'total_weight' => departures_format_value($totalWeightRaw),
            'is_empty_placeholder' => $hasZeroPackages && $hasZeroWeight,
            'can_delete_placeholder' => $containerExternalId !== '' && $hasZeroPackages,
            'can_close_flight' => $containerExternalId !== '' && $hasPackages && $hasWeight,
        ];
    }

    return $containers;
}

function departures_value_is_zero($value): bool
{
    if ($value === null) {
        return false;
    }

    if (is_int($value) || is_float($value)) {
        return (float)$value == 0.0;
    }

    $normalized = trim((string)$value);
    if ($normalized === '' || $normalized === '—' || $normalized === '-') {
        return false;
    }

    $normalized = str_replace(',', '.', $normalized);
    if (!is_numeric($normalized)) {
        return false;
    }

    return (float)$normalized == 0.0;
}

function departures_get_table_columns(mysqli $dbcnx, string $tableName): array
{
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    if (!departures_table_exists($dbcnx, $tableName)) {
        $cache[$tableName] = [];
        return [];
    }

    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $columns = [];
    if ($res = $dbcnx->query("SHOW COLUMNS FROM {$safeTable}")) {
        while ($row = $res->fetch_assoc()) {
            $field = trim((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        $res->free();
    }

    $cache[$tableName] = $columns;
    return $columns;
}


function departures_load_live_warehouse_metrics(mysqli $dbcnx, array $containerNames): array
{
    $normalizedContainerNames = [];
    foreach ($containerNames as $name) {
        $value = departures_normalize_container_key((string)$name);
        if ($value !== '') {
            $normalizedContainerNames[$value] = true;
        }
    }

    if ($normalizedContainerNames === []) {
        return [];
    }

    if (!departures_table_exists($dbcnx, 'warehouse_item_out') || !departures_table_exists($dbcnx, 'warehouse_item_stock')) {
        return [];
    }

    $sql = "SELECT
                TRIM(wo.shipped_container_name) AS container_name,
                COUNT(*) AS warehouse_packages_count,
                SUM(COALESCE(wi.weight_kg, 0)) AS warehouse_total_weight
            FROM warehouse_item_out wo
            INNER JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
            WHERE LOWER(TRIM(wo.status)) = 'sended'
              AND TRIM(wo.shipped_container_name) <> ''
            GROUP BY TRIM(wo.shipped_container_name)";

    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $metrics = [];
    $res = $stmt->get_result();
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $containerName = trim((string)($row['container_name'] ?? ''));
            if ($containerName === '') {
                continue;
            }
            $normalizedKey = departures_normalize_container_key($containerName);
            if ($normalizedKey === '' || !isset($normalizedContainerNames[$normalizedKey])) {
                continue;
            }
            if (!isset($metrics[$normalizedKey])) {
                $metrics[$normalizedKey] = [
                    'warehouse_packages_count' => 0,
                    'warehouse_total_weight' => 0.0,
                ];
            }
            $metrics[$normalizedKey]['warehouse_packages_count'] += isset($row['warehouse_packages_count']) ? (int)$row['warehouse_packages_count'] : 0;
            $metrics[$normalizedKey]['warehouse_total_weight'] += isset($row['warehouse_total_weight']) ? (float)$row['warehouse_total_weight'] : 0.0;
        }
        $res->free();
    }

    $stmt->close();

    return $metrics;
}

function departures_load_live_warehouse_packages(mysqli $dbcnx, array $containerNames): array
{
    $normalizedContainerNames = [];
    foreach ($containerNames as $name) {
        $value = departures_normalize_container_key((string)$name);
        if ($value !== '') {
            $normalizedContainerNames[$value] = true;
        }
    }

    if ($normalizedContainerNames === []) {
        return [];
    }

    if (!departures_table_exists($dbcnx, 'warehouse_item_out') || !departures_table_exists($dbcnx, 'warehouse_item_stock')) {
        return [];
    }

    $sql = "SELECT
                TRIM(wo.shipped_container_name) AS container_name,
                TRIM(COALESCE(wo.tracking_no, '')) AS tracking_no,
                COALESCE(wi.weight_kg, 0) AS weight_kg
            FROM warehouse_item_out wo
            INNER JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
            WHERE LOWER(TRIM(wo.status)) = 'sended'
              AND TRIM(wo.shipped_container_name) <> ''
            ORDER BY wo.updated_at DESC, wo.id DESC";

    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $packages = [];
    $res = $stmt->get_result();
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $containerName = trim((string)($row['container_name'] ?? ''));
            $normalizedKey = departures_normalize_container_key($containerName);
            if ($normalizedKey === '' || !isset($normalizedContainerNames[$normalizedKey])) {
                continue;
            }
            if (!isset($packages[$normalizedKey])) {
                $packages[$normalizedKey] = [];
            }
            $packages[$normalizedKey][] = [
                'tracking' => trim((string)($row['tracking_no'] ?? '')),
                'weight' => departures_format_value($row['weight_kg'] ?? null),
            ];
        }
        $res->free();
    }
    $stmt->close();

    return $packages;
}

function departures_extract_forwarder_packages($rawJson): array
{
    if (!is_string($rawJson) || trim($rawJson) === '') {
        return [];
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $sourcePackages = isset($decoded['packages']) && is_array($decoded['packages'])
        ? $decoded['packages']
        : [];

    $packages = [];
    foreach ($sourcePackages as $row) {
        if (!is_array($row)) {
            continue;
        }

        $tracking = '';
        foreach (['Tracking', 'tracking', 'tracking_no', 'tn', 'Number', 'number', 'Code', 'code'] as $trackingKey) {
            $candidate = trim((string)($row[$trackingKey] ?? ''));
            if ($candidate !== '') {
                $tracking = $candidate;
                break;
            }
        }

        $weight = '';
        foreach (['Weight', 'weight', 'weight_kg', 'total_weight'] as $weightKey) {
            if (!array_key_exists($weightKey, $row)) {
                continue;
            }
            $rawWeight = str_replace(',', '.', trim((string)$row[$weightKey]));
            $rawWeight = preg_replace('/[^0-9.\-]/', '', $rawWeight);
            if ($rawWeight !== '' && is_numeric($rawWeight)) {
                $weight = departures_format_value((float)$rawWeight);
                break;
            }
        }

        $packages[] = [
            'tracking' => $tracking,
            'weight' => $weight,
        ];
    }

    return $packages;
}

function departures_normalize_container_key(string $value): string
{
    $normalized = strtoupper(trim($value));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/^CONTAINER\s*/', '', $normalized);
    $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

    return trim((string)$normalized);
}


function departures_load_containers_from_table(mysqli $dbcnx, string $flightTableName, int $connectorId, int $flightRecordId, string $flightExternalId): array
{
    if ($connectorId <= 0 || ($flightRecordId <= 0 && $flightExternalId === '')) {
        return [];
    }

    $containersTableName = connectors_subrunner_resolve_flight_containers_table_name($flightTableName, []);
    if (!departures_table_exists($dbcnx, $containersTableName)) {
        return [];
    }

    $safeContainersTable = '`' . str_replace('`', '``', $containersTableName) . '`';

    $availableColumns = array_map('strtolower', departures_get_table_columns($dbcnx, $containersTableName));
    $selectColumns = [
        'container_external_id',
        'name',
        'flight',
        'departure',
        'destination',
        'awb',
        'packages_count',
        'total_weight',
    ];
    foreach (['warehouse_packages_count', 'warehouse_total_weight', 'compare_status', 'compared_at', 'compare_error', 'forwarder_packages_synced_at', 'forwarder_packages_json'] as $optionalColumn) {
        if (in_array(strtolower($optionalColumn), $availableColumns, true)) {
            $selectColumns[] = $optionalColumn;
        }
    }
    $selectSql = implode(', ', $selectColumns);
    if ($flightRecordId > 0) {
        $sql = "SELECT {$selectSql}
                  FROM {$safeContainersTable}
                 WHERE connector_id = ? AND flight_record_id = ? AND is_active = 1
                 ORDER BY updated_at DESC, id DESC";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            error_log('departures containers prepare error: ' . $dbcnx->error);
            return [];
        }
        $stmt->bind_param('ii', $connectorId, $flightRecordId);
    } else {
        $sql = "SELECT {$selectSql}
                  FROM {$safeContainersTable}
                 WHERE connector_id = ? AND flight_external_id = ? AND is_active = 1
                 ORDER BY updated_at DESC, id DESC";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            error_log('departures containers prepare error: ' . $dbcnx->error);
            return [];
        }
        $stmt->bind_param('is', $connectorId, $flightExternalId);
    }

    if (!$stmt->execute()) {
        error_log('departures containers execute error: ' . $stmt->error);
        $stmt->close();
        return [];
    }

    $rawRows = [];
    $containerLookupKeys = [];

    $res = $stmt->get_result();
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }
            $rawRows[] = $row;
            $containerName = departures_normalize_container_key((string)($row['name'] ?? ''));
            $containerExternalId = departures_normalize_container_key((string)($row['container_external_id'] ?? ''));
            if ($containerName !== '') {
                $containerLookupKeys[$containerName] = true;
            }
            if ($containerExternalId !== '') {
                $containerLookupKeys[$containerExternalId] = true;
            }
        }
        $res->free();
    }

    $stmt->close();

    $warehouseMetrics = departures_load_live_warehouse_metrics(
        $dbcnx,
        array_keys($containerLookupKeys)
    );

    $warehousePackages = departures_load_live_warehouse_packages(
        $dbcnx,
        array_keys($containerLookupKeys)
    );

    $containers = [];
    foreach ($rawRows as $row) {
        $packagesCountRaw = $row['packages_count'] ?? null;
        $totalWeightRaw = $row['total_weight'] ?? null;
        $containerExternalId = trim((string)($row['container_external_id'] ?? ''));
        $containerName = trim((string)($row['name'] ?? ''));
        $hasZeroPackages = departures_value_is_zero($packagesCountRaw);
        $hasZeroWeight = departures_value_is_zero($totalWeightRaw);
        $hasPackages = is_numeric($packagesCountRaw) && (float)$packagesCountRaw > 0.0;
        $hasWeight = is_numeric($totalWeightRaw) && (float)$totalWeightRaw > 0.0;

        $liveMetrics = null;
        $normalizedName = departures_normalize_container_key($containerName);
        $normalizedExternalId = departures_normalize_container_key($containerExternalId);
        if ($normalizedName !== '' && isset($warehouseMetrics[$normalizedName])) {
            $liveMetrics = $warehouseMetrics[$normalizedName];
        } elseif ($normalizedExternalId !== '' && isset($warehouseMetrics[$normalizedExternalId])) {
            $liveMetrics = $warehouseMetrics[$normalizedExternalId];
        }

        $warehousePackagesRaw = $liveMetrics['warehouse_packages_count'] ?? null;
        $warehouseWeightRaw = $liveMetrics['warehouse_total_weight'] ?? null;
        $compareStatus = trim((string)($row['compare_status'] ?? 'pending'));
        $calculatedCompare = departures_recalculate_compare_status(
            is_numeric($warehousePackagesRaw) ? (int)$warehousePackagesRaw : null,
            is_numeric($warehouseWeightRaw) ? (float)$warehouseWeightRaw : null,
            is_numeric($packagesCountRaw) ? (int)$packagesCountRaw : null,
            is_numeric($totalWeightRaw) ? (float)$totalWeightRaw : null
        );
        if ($compareStatus === '' || $compareStatus === 'pending' || $compareStatus === 'matched' || $compareStatus === 'mismatch') {
            $compareStatus = (string)$calculatedCompare['status'];
        }

        $warehousePackagesDetail = [];
        if ($normalizedName !== '' && isset($warehousePackages[$normalizedName])) {
            $warehousePackagesDetail = $warehousePackages[$normalizedName];
        } elseif ($normalizedExternalId !== '' && isset($warehousePackages[$normalizedExternalId])) {
            $warehousePackagesDetail = $warehousePackages[$normalizedExternalId];
        }
        $forwarderPackagesDetail = departures_extract_forwarder_packages((string)($row['forwarder_packages_json'] ?? ''));

        $containers[] = [
            'container_external_id' => $containerExternalId,
            'name' => $containerName,
            'flight' => trim((string)($row['flight'] ?? '')),
            'departure' => trim((string)($row['departure'] ?? '')),
            'destination' => trim((string)($row['destination'] ?? '')),
            'awb' => trim((string)($row['awb'] ?? '')),
            'packages_count' => departures_format_value($packagesCountRaw, 0),
            'total_weight' => departures_format_value($totalWeightRaw),
            'warehouse_packages_count' => departures_format_value($warehousePackagesRaw),
            'warehouse_total_weight' => departures_format_value($warehouseWeightRaw),
            'compare_status' => $compareStatus,
            'compared_at' => departures_format_datetime($row['compared_at'] ?? null),
            'forwarder_packages_synced_at' => departures_format_datetime($row['forwarder_packages_synced_at'] ?? null),
            'compare_error' => trim((string)($calculatedCompare['error'] ?? ($row['compare_error'] ?? ''))),
            'is_empty_placeholder' => $hasZeroPackages && $hasZeroWeight,
            'can_delete_placeholder' => $containerExternalId !== '' && $hasZeroPackages,
            'can_close_flight' => $containerExternalId !== '' && $hasPackages && $hasWeight,
            'compare_modal_payload_json' => (string)json_encode([
                'container' => $containerName !== '' ? $containerName : $containerExternalId,
                'warehouse' => $warehousePackagesDetail,
                'forwarder' => $forwarderPackagesDetail,
                'compare_status' => $compareStatus,
                'compare_error' => trim((string)($calculatedCompare['error'] ?? ($row['compare_error'] ?? ''))),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
    return $containers;
}
function departures_fetch_rows(mysqli $dbcnx, array $connector, string $statusFilter = 'ALL'): array
{
    $connectorId = (int)($connector['id'] ?? 0);
    if ($connectorId <= 0) {
        return [];
    }

    $rows = [];
    $normalizedStatusFilter = strtoupper(trim($statusFilter));

    foreach (departures_resolve_table_names($connector) as $tableName) {
        if (!departures_table_exists($dbcnx, $tableName)) {
            continue;
        }

        $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
        $sql = "SELECT
                    id,
                    connector_id,
                    flight_no,
                    route,
                    status,
                    external_id,
                    name,
                    flight_time,
                    flight_number,
                    awb,
                    departure,
                    destination,
                    packages_count,
                    total_weight,
                    closed_at,
                    containers_json,
                    containers_count,
                    containers_synced_at,
                    containers_sync_status,
                    containers_sync_error,
                    updated_at
                FROM {$safeTable}
                WHERE connector_id = ?
                ORDER BY COALESCE(updated_at, closed_at) DESC, id DESC
                LIMIT 250";

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            error_log('departures prepare error: ' . $dbcnx->error . '; table=' . $tableName);
            continue;
        }

        $stmt->bind_param('i', $connectorId);
        if (!$stmt->execute()) {
            error_log('departures execute error: ' . $stmt->error . '; table=' . $tableName);
            $stmt->close();
            continue;
        }

        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($flight = $res->fetch_assoc()) {
                $normalizedStatus = strtoupper(trim((string)($flight['status'] ?? '')));
                if ($normalizedStatusFilter !== '' && $normalizedStatusFilter !== 'ALL' && $normalizedStatus !== $normalizedStatusFilter) {
                    continue;
                }

                $containersFromSnapshot = departures_decode_containers($flight['containers_json'] ?? '');
                $containersFromTable = departures_load_containers_from_table(
                    $dbcnx,
                    $tableName,
                    $connectorId,
                    (int)($flight['id'] ?? 0),
                    trim((string)($flight['external_id'] ?? ''))
                );

                // Приоритет за фактическими данными из *_flight_list_containers,
                // чтобы UI отражал изменения, сделанные в таблице контейнеров.
                $containers = $containersFromTable !== [] ? $containersFromTable : $containersFromSnapshot;
                $containersTotal = isset($flight['containers_count']) && $flight['containers_count'] !== null
                    ? (int)$flight['containers_count']
                    : count($containers);

                if ($containersTotal <= 0 && $containers !== []) {
                    $containersTotal = count($containers);
                }
                $canCloseFlight = false;
                foreach ($containers as $container) {
                    if (!empty($container['can_close_flight'])) {
                        $canCloseFlight = true;
                        break;
                    }
                }
                $updatedAtRaw = trim((string)($flight['updated_at'] ?? ''));
                $closedAtRaw = trim((string)($flight['closed_at'] ?? ''));

                $rows[] = [
                    'row_uid' => 'departure_' . $tableName . '_' . (int)($flight['id'] ?? 0),
                    'connector_id' => (int)($connector['id'] ?? 0),
                    'flight_record_id' => (int)($flight['id'] ?? 0),
                    'flight_id' => trim((string)($flight['external_id'] ?? '')),
                    'flight_no' => trim((string)($flight['flight_no'] ?? '')),
                    'name' => trim((string)($flight['name'] ?? '')),
                    'flight_number' => trim((string)($flight['flight_number'] ?? '')),
                    'awb' => trim((string)($flight['awb'] ?? '')),
                    'forwarder_name' => trim((string)($connector['name'] ?? '')),
                    'forwarder_countries' => trim((string)($connector['countries'] ?? '')),
                    'departure' => trim((string)($flight['departure'] ?? '')),
                    'destination' => trim((string)($flight['destination'] ?? '')),
                    'route' => trim((string)($flight['route'] ?? '')),
                    'status' => trim((string)($flight['status'] ?? '')),
                    'status_badge_class' => departures_status_badge_class((string)($flight['status'] ?? '')),
                    'containers_total' => $containersTotal,
                    'can_close_flight' => $canCloseFlight,
                    'packages_count' => departures_format_value($flight['packages_count'] ?? null, 0),
                    'total_weight' => departures_format_value($flight['total_weight'] ?? null),
                    'containers_sync_status' => trim((string)($flight['containers_sync_status'] ?? 'pending')),
                    'containers_synced_at' => departures_format_datetime($flight['containers_synced_at'] ?? null),
                    'containers_sync_error' => trim((string)($flight['containers_sync_error'] ?? '')),
                    'updated_at' => departures_format_datetime($updatedAtRaw),
                    'flight_time' => trim((string)($flight['flight_time'] ?? '')),
                    'closed_at' => departures_format_datetime($closedAtRaw),
                    'containers' => $containers,
                    '_sort_ts' => strtotime($updatedAtRaw !== '' ? $updatedAtRaw : $closedAtRaw) ?: 0,
                ];
            }
            $res->free();
        }

        $stmt->close();
    }

    usort($rows, static function (array $left, array $right): int {
        return ($right['_sort_ts'] ?? 0) <=> ($left['_sort_ts'] ?? 0);
    });

    foreach ($rows as &$row) {
        unset($row['_sort_ts']);
    }
    unset($row);

    return $rows;
}

function departures_fetch_connector(mysqli $dbcnx, int $connectorId): ?array
{
    $stmt = $dbcnx->prepare('SELECT id, name, countries, operations_json, is_active, base_url, auth_username, auth_password FROM connectors WHERE id = ? LIMIT 1');
    if (!$stmt) {
        error_log('departures connector fetch prepare error: ' . $dbcnx->error);
        return null;
    }

    $stmt->bind_param('i', $connectorId);
    if (!$stmt->execute()) {
        error_log('departures connector fetch execute error: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $res = $stmt->get_result();
    $connector = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();

    return is_array($connector) ? $connector : null;
}


function departures_recalculate_compare_status(
    ?int $warehousePackages,
    ?float $warehouseWeight,
    ?int $forwarderPackages,
    ?float $forwarderWeight
): array {
    if ($warehousePackages === null || $warehouseWeight === null) {
        return ['status' => 'pending', 'error' => 'warehouse metrics are not set'];
    }
    if ($forwarderPackages === null || $forwarderWeight === null) {
        return ['status' => 'pending', 'error' => 'forwarder metrics are not set'];
    }
    if ($warehousePackages === $forwarderPackages && abs($warehouseWeight - $forwarderWeight) < 0.0005) {
        return ['status' => 'matched', 'error' => null];
    }

    return [
        'status' => 'mismatch',
        'error' => 'warehouse=' . $warehousePackages . '/' . $warehouseWeight . '; forwarder=' . $forwarderPackages . '/' . $forwarderWeight,
    ];
}

function departures_update_container_compare_from_db(
    mysqli $dbcnx,
    array $connector,
    int $flightRecordId,
    string $containerExternalId
): array {
    if ($flightRecordId <= 0 || $containerExternalId === '') {
        throw new InvalidArgumentException('Не переданы flight_record_id/container_external_id');
    }

    $connectorId = (int)($connector['id'] ?? 0);
    $updated = 0;
    $tables = [];
    foreach (departures_resolve_table_names($connector) as $flightTableName) {
        $containersTableName = connectors_subrunner_resolve_flight_containers_table_name($flightTableName, []);
        if (!departures_table_exists($dbcnx, $containersTableName)) {
            continue;
        }

        $tables[] = $containersTableName;
        $safeTable = '`' . str_replace('`', '``', $containersTableName) . '`';
        $selectStmt = $dbcnx->prepare("SELECT id, name, container_external_id, packages_count, total_weight FROM {$safeTable} WHERE connector_id = ? AND flight_record_id = ? AND container_external_id = ? LIMIT 1");
        if (!$selectStmt) {
            continue;
        }
        $selectStmt->bind_param('iis', $connectorId, $flightRecordId, $containerExternalId);
        if (!$selectStmt->execute()) {
            $selectStmt->close();
            continue;
        }
        $row = $selectStmt->get_result()?->fetch_assoc() ?: null;
        $selectStmt->close();
        if (!is_array($row)) {
            continue;
        }

        $warehouseMetrics = departures_load_live_warehouse_metrics(
            $dbcnx,
            [
                (string)($row['name'] ?? ''),
                (string)($row['container_external_id'] ?? ''),
                $containerExternalId,
            ]
        );
        $normalizedName = departures_normalize_container_key((string)($row['name'] ?? ''));
        $normalizedExternalId = departures_normalize_container_key((string)($row['container_external_id'] ?? ''));
        $metrics = null;
        if ($normalizedName !== '' && isset($warehouseMetrics[$normalizedName])) {
            $metrics = $warehouseMetrics[$normalizedName];
        } elseif ($normalizedExternalId !== '' && isset($warehouseMetrics[$normalizedExternalId])) {
            $metrics = $warehouseMetrics[$normalizedExternalId];
        }
        $warehousePackages = isset($metrics['warehouse_packages_count']) ? (int)$metrics['warehouse_packages_count'] : null;
        $warehouseWeight = isset($metrics['warehouse_total_weight']) ? (float)$metrics['warehouse_total_weight'] : null;

        $forwarderPackages = isset($row['packages_count']) ? (int)$row['packages_count'] : null;
        $forwarderWeight = isset($row['total_weight']) ? (float)$row['total_weight'] : null;
        $compare = departures_recalculate_compare_status($warehousePackages, $warehouseWeight, $forwarderPackages, $forwarderWeight);

        $compareStatus = (string)$compare['status'];
        $compareError = $compare['error'];
        $updateStmt = $dbcnx->prepare("UPDATE {$safeTable} SET compare_status = ?, compare_error = ?, compared_at = UTC_TIMESTAMP() WHERE id = ? LIMIT 1");
        if (!$updateStmt) {
            continue;
        }
        $containerRowId = (int)$row['id'];
        $updateStmt->bind_param('ssi', $compareStatus, $compareError, $containerRowId);
        if ($updateStmt->execute()) {
            $updated += (int)$updateStmt->affected_rows;
        }
        $updateStmt->close();
    }

    return [
        'updated' => $updated,
        'tables_checked' => $tables,
    ];
}

function departures_sync_flight_containers_from_forwarder(array $connector, string $flightId): array
{
    $connectorId = (int)($connector['id'] ?? 0);
    $baseUrl = trim((string)($connector['base_url'] ?? ''));
    $login = trim((string)($connector['auth_username'] ?? ''));
    $password = trim((string)($connector['auth_password'] ?? ''));
    if ($connectorId <= 0 || $flightId === '' || $baseUrl === '' || $login === '' || $password === '') {
        throw new InvalidArgumentException('Недостаточно данных для sync с форвардом (connector/base_url/login/password/flight_id).');
    }

    $scriptPath = dirname(__DIR__, 2) . '/scripts/mvp/app/Forwarder/run_sync_flight_containers.php';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Не найден скрипт sync: ' . $scriptPath);
    }

    $cmdParts = [
        'php',
        $scriptPath,
        '--base-url=' . $baseUrl,
        '--login=' . $login,
        '--password=' . $password,
        '--connector-id=' . $connectorId,
        '--flight-id=' . $flightId,
    ];
    $cmd = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
    $lines = [];
    $exitCode = 0;
    @exec($cmd, $lines, $exitCode);
    $output = trim((string)implode("\n", $lines));
    $parsed = json_decode($output, true);
    if (!is_array($parsed)) {
        $parsed = ['raw_output' => $output, 'status' => $exitCode === 0 ? 'ok' : 'error'];
    }

    if ($exitCode !== 0 || strtolower((string)($parsed['status'] ?? '')) !== 'ok') {
        $message = trim((string)($parsed['message'] ?? ''));
        throw new RuntimeException($message !== '' ? $message : ('sync ошибка: exit=' . $exitCode . '; output=' . $output));
    }

    return $parsed;
}


function departures_sync_forwarder_container_packages(array $connector, string $containerExternalId): array
{
    $connectorId = (int)($connector['id'] ?? 0);
    $baseUrl = trim((string)($connector['base_url'] ?? ''));
    $login = trim((string)($connector['auth_username'] ?? ''));
    $password = trim((string)($connector['auth_password'] ?? ''));
    if ($connectorId <= 0 || $containerExternalId === '' || $baseUrl === '' || $login === '' || $password === '') {
        throw new InvalidArgumentException('Недостаточно данных для sync snapshot форварда (connector/base_url/login/password/container_external_id).');
    }

    $scriptPath = dirname(__DIR__, 2) . '/scripts/mvp/app/Forwarder/run_list_container.php';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Не найден скрипт run_list_container: ' . $scriptPath);
    }

    $cmdParts = [
        'php',
        $scriptPath,
        '--base-url=' . $baseUrl,
        '--login=' . $login,
        '--password=' . $password,
        '--position=' . $containerExternalId,
        '--all-pages=1',
    ];
    $cmd = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
    $lines = [];
    $exitCode = 0;
    @exec($cmd, $lines, $exitCode);
    $output = trim((string)implode("\n", $lines));
    $parsed = json_decode($output, true);
    if (!is_array($parsed)) {
        $parsed = ['raw_output' => $output, 'status' => $exitCode === 0 ? 'ok' : 'error'];
    }

    $status = strtolower(trim((string)($parsed['status'] ?? '')));
    if ($exitCode !== 0 || ($status !== 'ok' && $status !== 'warning')) {
        $message = trim((string)($parsed['message'] ?? ''));
        throw new RuntimeException($message !== '' ? $message : ('run_list_container ошибка: exit=' . $exitCode . '; output=' . $output));
    }

    return $parsed;
}

function departures_update_container_forwarder_snapshot(
    mysqli $dbcnx,
    array $connector,
    int $flightRecordId,
    string $containerExternalId,
    array $snapshot
): array {
    if ($flightRecordId <= 0 || $containerExternalId === '') {
        throw new InvalidArgumentException('Не переданы flight_record_id/container_external_id');
    }

    $connectorId = (int)($connector['id'] ?? 0);
    $updated = 0;
    $tables = [];
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $metrics = departures_extract_forwarder_metrics_from_snapshot($snapshot);
    $forwarderPackagesCount = $metrics['packages_count'];
    $forwarderTotalWeight = $metrics['total_weight'];

    foreach (departures_resolve_table_names($connector) as $flightTableName) {
        $containersTableName = connectors_subrunner_resolve_flight_containers_table_name($flightTableName, []);
        if (!departures_table_exists($dbcnx, $containersTableName)) {
            continue;
        }

        $tables[] = $containersTableName;
        $safeTable = '`' . str_replace('`', '``', $containersTableName) . '`';
        $stmt = $dbcnx->prepare("
            UPDATE {$safeTable}
               SET forwarder_packages_json = ?,
                   forwarder_packages_synced_at = UTC_TIMESTAMP(),
                   packages_count = ?,
                   total_weight = ?
             WHERE connector_id = ?
               AND flight_record_id = ?
               AND container_external_id = ?
             LIMIT 1
        ");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('sidiis', $snapshotJson, $forwarderPackagesCount, $forwarderTotalWeight, $connectorId, $flightRecordId, $containerExternalId);
        if ($stmt->execute()) {
            $updated += (int)$stmt->affected_rows;
        }
        $stmt->close();
    }

    return [
        'updated' => $updated,
        'tables_checked' => $tables,
    ];
}

function departures_extract_forwarder_metrics_from_snapshot(array $snapshot): array
{
    $packages = isset($snapshot['packages']) && is_array($snapshot['packages'])
        ? $snapshot['packages']
        : [];

    $packagesCount = 0;
    $totalWeight = 0.0;
    foreach ($packages as $row) {
        if (!is_array($row)) {
            continue;
        }

        $itemCount = 1;
        foreach (['Count', 'count', 'qty', 'quantity'] as $countKey) {
            if (!array_key_exists($countKey, $row)) {
                continue;
            }
            $raw = str_replace(',', '.', trim((string)$row[$countKey]));
            if ($raw !== '' && is_numeric($raw)) {
                $itemCount = max(1, (int)round((float)$raw));
                break;
            }
        }
        $packagesCount += $itemCount;

        foreach (['Weight', 'weight', 'total_weight', 'weight_kg'] as $weightKey) {
            if (!array_key_exists($weightKey, $row)) {
                continue;
            }
            $raw = str_replace(',', '.', trim((string)$row[$weightKey]));
            $raw = preg_replace('/[^0-9.\\-]/', '', $raw);
            if ($raw !== '' && is_numeric($raw)) {
                $totalWeight += (float)$raw;
                break;
            }
        }
    }

    return [
        'packages_count' => $packagesCount,
        'total_weight' => round($totalWeight, 3),
    ];
}


function departures_delete_local_flight(mysqli $dbcnx, array $connector, int $flightRecordId, string $flightId = '', string $flightNo = ''): array
{
    $connectorId = (int)($connector['id'] ?? 0);
    if ($connectorId <= 0) {
        throw new InvalidArgumentException('Некорректный connector_id');
    }

    if ($flightRecordId <= 0 && $flightId === '' && $flightNo === '') {
        throw new InvalidArgumentException('Не передан идентификатор рейса для локального удаления');
    }

    $flightRowsDeleted = 0;
    $containerRowsDeleted = 0;
    $tablesChecked = [];

    foreach (departures_resolve_table_names($connector) as $tableName) {
        if (!departures_table_exists($dbcnx, $tableName)) {
            continue;
        }

        $tablesChecked[] = $tableName;
        $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
        $containersTableName = connectors_subrunner_resolve_flight_containers_table_name($tableName, []);
        $safeContainersTable = '`' . str_replace('`', '``', $containersTableName) . '`';
        $containersTableExists = departures_table_exists($dbcnx, $containersTableName);

        $matchedIds = [];
        $matchedExternalIds = [];
        $matchedFlightNos = [];

        if ($flightRecordId > 0) {
            $stmt = $dbcnx->prepare("SELECT id, external_id, flight_no FROM {$safeTable} WHERE connector_id = ? AND id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $connectorId, $flightRecordId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
                    if ($result instanceof mysqli_result) {
                        $result->free();
                    }
                    if (is_array($row)) {
                        $matchedIds[] = (int)($row['id'] ?? 0);
                        $matchedExternalIds[] = trim((string)($row['external_id'] ?? ''));
                        $matchedFlightNos[] = trim((string)($row['flight_no'] ?? ''));
                    }
                }
                $stmt->close();
            }
        }

        if ($matchedIds === [] && $flightId !== '') {
            $stmt = $dbcnx->prepare("SELECT id, external_id, flight_no FROM {$safeTable} WHERE connector_id = ? AND external_id = ?");
            if ($stmt) {
                $stmt->bind_param('is', $connectorId, $flightId);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result instanceof mysqli_result) {
                        while ($row = $result->fetch_assoc()) {
                            $matchedIds[] = (int)($row['id'] ?? 0);
                            $matchedExternalIds[] = trim((string)($row['external_id'] ?? ''));
                            $matchedFlightNos[] = trim((string)($row['flight_no'] ?? ''));
                        }
                        $result->free();
                    }
                }
                $stmt->close();
            }
        }

        if ($matchedIds === [] && $flightNo !== '') {
            $stmt = $dbcnx->prepare("SELECT id, external_id, flight_no FROM {$safeTable} WHERE connector_id = ? AND flight_no = ?");
            if ($stmt) {
                $stmt->bind_param('is', $connectorId, $flightNo);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result instanceof mysqli_result) {
                        while ($row = $result->fetch_assoc()) {
                            $matchedIds[] = (int)($row['id'] ?? 0);
                            $matchedExternalIds[] = trim((string)($row['external_id'] ?? ''));
                            $matchedFlightNos[] = trim((string)($row['flight_no'] ?? ''));
                        }
                        $result->free();
                    }
                }
                $stmt->close();
            }
        }

        $matchedIds = array_values(array_unique(array_filter(array_map('intval', $matchedIds))));
        $matchedExternalIds = array_values(array_unique(array_filter(array_map('strval', $matchedExternalIds))));
        $matchedFlightNos = array_values(array_unique(array_filter(array_map('strval', $matchedFlightNos))));

        if ($matchedIds === [] && $matchedExternalIds === [] && $matchedFlightNos === []) {
            continue;
        }

        if ($matchedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($matchedIds), '?'));
            $types = 'i' . str_repeat('i', count($matchedIds));
            $params = array_merge([$connectorId], $matchedIds);
            $stmt = $dbcnx->prepare("DELETE FROM {$safeTable} WHERE connector_id = ? AND id IN ({$placeholders})");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $flightRowsDeleted += (int)$stmt->affected_rows;
                }
                $stmt->close();
            }
        }

        if (!$containersTableExists) {
            continue;
        }

        if ($matchedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($matchedIds), '?'));
            $types = 'i' . str_repeat('i', count($matchedIds));
            $params = array_merge([$connectorId], $matchedIds);
            $stmt = $dbcnx->prepare("DELETE FROM {$safeContainersTable} WHERE connector_id = ? AND flight_record_id IN ({$placeholders})");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $containerRowsDeleted += (int)$stmt->affected_rows;
                }
                $stmt->close();
            }
        }

        if ($matchedExternalIds !== []) {
            $placeholders = implode(',', array_fill(0, count($matchedExternalIds), '?'));
            $types = 'i' . str_repeat('s', count($matchedExternalIds));
            $params = array_merge([$connectorId], $matchedExternalIds);
            $stmt = $dbcnx->prepare("DELETE FROM {$safeContainersTable} WHERE connector_id = ? AND flight_external_id IN ({$placeholders})");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $containerRowsDeleted += (int)$stmt->affected_rows;
                }
                $stmt->close();
            }
        } elseif ($matchedFlightNos !== []) {
            $placeholders = implode(',', array_fill(0, count($matchedFlightNos), '?'));
            $types = 'i' . str_repeat('s', count($matchedFlightNos));
            $params = array_merge([$connectorId], $matchedFlightNos);
            $stmt = $dbcnx->prepare("DELETE FROM {$safeContainersTable} WHERE connector_id = ? AND flight_no IN ({$placeholders})");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $containerRowsDeleted += (int)$stmt->affected_rows;
                }
                $stmt->close();
            }
        }
    }

    return [
        'flight_rows_deleted' => $flightRowsDeleted,
        'container_rows_deleted' => $containerRowsDeleted,
        'tables_checked' => $tablesChecked,
    ];
}

$normalizedAction = departures_resolve_action_alias(trim((string)($action ?? '')));

switch ($normalizedAction) {
    case 'view_departures':
        $departureForwarders = [];
        $sql = 'SELECT id, name, countries, is_active FROM connectors ORDER BY name ASC, id ASC';
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $departureForwarders[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => trim((string)($row['name'] ?? '')),
                    'countries' => trim((string)($row['countries'] ?? '')),
                    'is_active' => (int)($row['is_active'] ?? 0),
                ];
            }
            $res->free();
        }

        $smarty->assign('departure_forwarders', $departureForwarders);

        ob_start();
        $smarty->display('cells_NA_API_departures.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html' => $html,
        ];
        break;

    case 'departures_flights':
        $forwarderId = (int)($_POST['forwarder_id'] ?? 0);
        $statusFilter = trim((string)($_POST['flight_status'] ?? 'ALL'));

        $connectors = [];
        if ($forwarderId > 0) {
            $connector = departures_fetch_connector($dbcnx, $forwarderId);
            if (is_array($connector)) {
                $connectors[] = $connector;
            }
        } else {
            $sql = 'SELECT id, name, countries, operations_json, is_active FROM connectors ORDER BY name ASC, id ASC';
            if ($res = $dbcnx->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $connectors[] = $row;
                }
                $res->free();
            }
        }

        $departureRows = [];
        foreach ($connectors as $connector) {
            if (!is_array($connector)) {
                continue;
            }

            $departureRows = array_merge(
                $departureRows,
                departures_fetch_rows($dbcnx, $connector, $statusFilter)
            );
        }

        usort($departureRows, static function (array $left, array $right): int {
            $leftUpdated = strtotime((string)($left['updated_at'] ?? '')) ?: 0;
            $rightUpdated = strtotime((string)($right['updated_at'] ?? '')) ?: 0;
            return $rightUpdated <=> $leftUpdated;
        });

        $smarty->assign('departure_rows', $departureRows);

        ob_start();
        $smarty->display('cells_NA_API_departures_rows.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html' => $html,
            'total' => count($departureRows),
        ];
        break;

    case 'departures_delete_local_flight':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        $flightRecordId = (int)($_POST['flight_record_id'] ?? 0);
        $flightId = trim((string)($_POST['flight_id'] ?? ''));
        $flightNo = trim((string)($_POST['flight_no'] ?? ''));

        $connector = departures_fetch_connector($dbcnx, $connectorId);
        if (!is_array($connector)) {
            $response = [
                'status' => 'error',
                'message' => 'Форвард не найден для локального удаления рейса',
            ];
            break;
        }

        try {
            $stats = departures_delete_local_flight($dbcnx, $connector, $flightRecordId, $flightId, $flightNo);
            $response = [
                'status' => 'ok',
                'message' => 'Локальная запись рейса удалена.',
                'stats' => $stats,
            ];
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
        break;

    case 'departures_container_action':
        $operation = trim((string)($_POST['operation'] ?? ''));
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        $flightId = trim((string)($_POST['flight_id'] ?? ''));
        $flightRecordId = (int)($_POST['flight_record_id'] ?? 0);
        $containerExternalId = trim((string)($_POST['container_external_id'] ?? ''));

        $connector = departures_fetch_connector($dbcnx, $connectorId);
        if (!is_array($connector)) {
            $response = [
                'status' => 'error',
                'message' => 'Форвард не найден для операции контейнера',
            ];
            break;
        }

        try {
            if ($operation === 'sync_forwarder') {
                $syncResult = departures_sync_flight_containers_from_forwarder($connector, $flightId);
                $compareRefresh = null;
                $forwarderSnapshot = null;
                if ($flightRecordId > 0 && $containerExternalId !== '') {
                    $snapshotResult = departures_sync_forwarder_container_packages($connector, $containerExternalId);
                    $forwarderSnapshot = departures_update_container_forwarder_snapshot(
                        $dbcnx,
                        $connector,
                        $flightRecordId,
                        $containerExternalId,
                        $snapshotResult
                    );
                    $compareRefresh = departures_update_container_compare_from_db(
                        $dbcnx,
                        $connector,
                        $flightRecordId,
                        $containerExternalId
                    );
                }
                $response = [
                    'status' => 'ok',
                    'message' => 'Данные по контейнерам запрошены у форварда, snapshot и локальная сверка обновлены.',
                    'result' => $syncResult,
                    'forwarder_snapshot' => $forwarderSnapshot,
                    'compare_refresh' => $compareRefresh,
                ];
                break;
            }

            if ($operation === 'sync_local') {
                $syncResult = departures_update_container_compare_from_db(
                    $dbcnx,
                    $connector,
                    $flightRecordId,
                    $containerExternalId
                );
                $response = [
                    'status' => 'ok',
                    'message' => 'Сверка контейнера с локальными полями выполнена.',
                    'result' => $syncResult,
                ];
                break;
            }

            $response = [
                'status' => 'error',
                'message' => 'Неизвестная операция контейнера',
            ];
        } catch (Throwable $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
        break;
}
