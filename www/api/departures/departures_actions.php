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

        $packagesCountRaw = $container['packages_count'] ?? null;
        $totalWeightRaw = $container['total_weight'] ?? null;
        $hasZeroPackages = is_numeric($packagesCountRaw) && (float)$packagesCountRaw == 0.0;
        $hasZeroWeight = is_numeric($totalWeightRaw) && (float)$totalWeightRaw == 0.0;

        $containers[] = [
            'container_external_id' => trim((string)($container['container_external_id'] ?? '')),
            'name' => trim((string)($container['name'] ?? '')),
            'flight' => trim((string)($container['flight'] ?? '')),
            'departure' => trim((string)($container['departure'] ?? '')),
            'destination' => trim((string)($container['destination'] ?? '')),
            'awb' => trim((string)($container['awb'] ?? '')),
            'packages_count' => departures_format_value($packagesCountRaw, 0),
            'total_weight' => departures_format_value($totalWeightRaw),
            'is_empty_placeholder' => $hasZeroPackages && $hasZeroWeight,
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

                $containers = departures_decode_containers($flight['containers_json'] ?? '');
                $containersTotal = isset($flight['containers_count']) && $flight['containers_count'] !== null
                    ? (int)$flight['containers_count']
                    : count($containers);
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
    $stmt = $dbcnx->prepare('SELECT id, name, countries, operations_json, is_active FROM connectors WHERE id = ? LIMIT 1');
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
}
