<?php

declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty
//$response = ['status' => 'error', 'message' => 'Unknown connector action'];

$routeActionRaw = $action ?? '';
$postActionRaw = $_POST['action'] ?? '';
$getActionRaw = $_GET['action'] ?? '';

// Важно: handler должен в первую очередь доверять action, уже отмаршрутизированному в core_api.php.
// Это исключает расхождение между роутером и switch при «грязном» POST/GET.
$routeActionRaw = isset($action) ? (string)$action : '';
$postActionRaw = isset($_POST['action']) ? (string)$_POST['action'] : '';
$getActionRaw = isset($_GET['action']) ? (string)$_GET['action'] : '';

// Важно: handler должен в первую очередь доверять action, уже отмаршрутизированному в core_api.php.
// Это исключает расхождение между роутером и switch при «грязном» POST/GET.
$normalizedRouteAction = connectors_resolve_action_alias(connectors_normalize_action($routeActionRaw));
$normalizedPostAction = connectors_resolve_action_alias(connectors_normalize_action($postActionRaw));
$normalizedGetAction = connectors_resolve_action_alias(connectors_normalize_action($getActionRaw));

// Для switch используем максимально «сырой», но уже отмаршрутизированный action (route-first),
// чтобы не ломать совпадение case из-за агрессивной нормализации.
$dispatchAction = $routeActionRaw !== '' ? trim($routeActionRaw) : trim(($postActionRaw !== '' ? $postActionRaw : $getActionRaw));
$dispatchAction = connectors_resolve_action_alias($dispatchAction);

$normalizedAction = $normalizedRouteAction !== '' ? $normalizedRouteAction : ($normalizedPostAction !== '' ? $normalizedPostAction : $normalizedGetAction);
$incomingAction = $routeActionRaw !== '' ? $routeActionRaw : ($postActionRaw !== '' ? $postActionRaw : $getActionRaw);

if ($normalizedRouteAction !== '' && $normalizedPostAction !== '' && $normalizedRouteAction !== $normalizedPostAction) {
    error_log('connector_actions action mismatch route_vs_post: route=' . $normalizedRouteAction . '; post=' . $normalizedPostAction . '; post_hex=' . bin2hex((string)$postActionRaw));
}

$response = ['status' => 'error', 'message' => 'Unknown connector action: ' . $normalizedAction];

require_once __DIR__ . '/connector_engine.php';

final class ConnectorStepLogException extends RuntimeException
{
    /** @var array<int,array<string,mixed>> */
    private array $stepLog;
    private string $artifactsDir;

    public function __construct(string $message, array $stepLog = [], int $code = 0, ?Throwable $previous = null, string $artifactsDir = '')
    {
        parent::__construct($message, $code, $previous);
        $this->stepLog = $stepLog;
        $this->artifactsDir = trim($artifactsDir);
    }

    /** @return array<int,array<string,mixed>> */
    public function getStepLog(): array
    {
        return $this->stepLog;
    }

    public function getArtifactsDir(): string
    {
        return $this->artifactsDir;
    }
}

function connectors_normalize_action($action): string
{
    $normalized = trim((string)$action);

    // byte-level cleanup: remove ASCII control chars even if input has broken UTF-8
    $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized) ?? $normalized;
    // remove common UTF-8 invisible chars by raw byte sequences
    $normalized = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xE2\x81\xA0", "\xEF\xBB\xBF"], '', $normalized);
    $normalized = preg_replace('/\s+/u', '', $normalized) ?? preg_replace('/\s+/', '', $normalized) ?? $normalized;
    $normalized = strtolower($normalized);

    return preg_replace('/[^a-z0-9_.-]/', '', $normalized) ?? $normalized;
}

function connectors_resolve_action_alias(string $action): string
{
    if (preg_match('/^test[_.-]*connector[_.-]*operations$/i', $action)) {
        return 'test_connector_operations';
    }

    static $aliases = [
        'testconnectoroperations' => 'test_connector_operations',
    ];

    return $aliases[$action] ?? $action;
}

function connectors_ensure_schema(mysqli $dbcnx): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS connectors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL,
            countries VARCHAR(255) NOT NULL DEFAULT '',
            base_url VARCHAR(255) NOT NULL DEFAULT '',
            auth_type VARCHAR(32) NOT NULL DEFAULT 'login',
            auth_username VARCHAR(128) NOT NULL DEFAULT '',
            auth_password VARCHAR(255) NOT NULL DEFAULT '',
            api_token VARCHAR(255) NOT NULL DEFAULT '',
            auth_token TEXT NULL,
            auth_token_expires_at DATETIME NULL,
            auth_cookies TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sync_at DATETIME NULL,
            last_success_at DATETIME NULL,
            last_error TEXT NULL,
            ssl_ignore TINYINT(1) NOT NULL DEFAULT 0,
            scenario_json TEXT NULL,
            last_manual_confirm_at DATETIME NULL,
            last_manual_confirm_by INT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($sql)) {
        error_log('connectors schema create error: ' . $dbcnx->error);
    }

    $columnsToEnsure = [
        'scenario_json' => "ALTER TABLE connectors ADD COLUMN scenario_json TEXT NULL AFTER last_error",
        'auth_token' => "ALTER TABLE connectors ADD COLUMN auth_token TEXT NULL AFTER api_token",
        'auth_token_expires_at' => "ALTER TABLE connectors ADD COLUMN auth_token_expires_at DATETIME NULL AFTER auth_token",
        'auth_cookies' => "ALTER TABLE connectors ADD COLUMN auth_cookies TEXT NULL AFTER auth_token_expires_at",
        'last_manual_confirm_at' => "ALTER TABLE connectors ADD COLUMN last_manual_confirm_at DATETIME NULL AFTER scenario_json",
        'last_manual_confirm_by' => "ALTER TABLE connectors ADD COLUMN last_manual_confirm_by INT NULL AFTER last_manual_confirm_at",
        'operations_json' => "ALTER TABLE connectors ADD COLUMN operations_json LONGTEXT NULL AFTER scenario_json",
        'is_test_connector' => "ALTER TABLE connectors ADD COLUMN is_test_connector TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
    ];

    foreach ($columnsToEnsure as $column => $alterSql) {
        $columnCheck = $dbcnx->query("SHOW COLUMNS FROM connectors LIKE '{$column}'");
        if ($columnCheck instanceof mysqli_result) {
            if ($columnCheck->num_rows === 0) {
                if (!$dbcnx->query($alterSql)) {
                    error_log('connectors schema alter error: ' . $dbcnx->error);
                }
            }
            $columnCheck->free();
        } elseif ($columnCheck === false) {
            error_log('connectors schema check error: ' . $dbcnx->error);
        }
    }



    $addonsSql = "
        CREATE TABLE IF NOT EXISTS connectors_addons (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            connector_id INT UNSIGNED NOT NULL,
            connector_name VARCHAR(128) NOT NULL DEFAULT '',
            addons_json LONGTEXT NULL,
            node_mapping_json LONGTEXT NULL,
            status_targets_json LONGTEXT NULL,
            report_out_statuses_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_connector_id (connector_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($addonsSql)) {
        error_log('connectors_addons schema create error: ' . $dbcnx->error);
    }

    $addonsColumnsToEnsure = [
        'status_targets_json' => "ALTER TABLE connectors_addons ADD COLUMN status_targets_json LONGTEXT NULL AFTER node_mapping_json",
        'report_out_statuses_json' => "ALTER TABLE connectors_addons ADD COLUMN report_out_statuses_json LONGTEXT NULL AFTER status_targets_json",
    ];

    foreach ($addonsColumnsToEnsure as $column => $alterSql) {
        $columnCheck = $dbcnx->query("SHOW COLUMNS FROM connectors_addons LIKE '{$column}'");
        if ($columnCheck instanceof mysqli_result) {
            if ($columnCheck->num_rows === 0) {
                if (!$dbcnx->query($alterSql)) {
                    error_log('connectors_addons schema alter error: ' . $dbcnx->error);
                }
            }
            $columnCheck->free();
        } elseif ($columnCheck === false) {
            error_log('connectors_addons schema check error: ' . $dbcnx->error);
        }
    }
}

function connectors_default_addons(): array
{
    return [
        'tariff_type' => '1',
        'category' => '',
        'extra_json' => '',
        'node_mapping_json' => '',
        'status_targets_json' => '',
        'report_out_statuses_json' => '',
    ];
}

function connectors_decode_addons(array $row): array
{
    $addons = connectors_default_addons();

    $rawAddons = trim((string)($row['addons_json'] ?? ''));
    if ($rawAddons !== '') {
        $decoded = json_decode($rawAddons, true);
        if (is_array($decoded)) {
            $tariffType = trim((string)($decoded['tariff_type'] ?? '1'));
            $addons['tariff_type'] = in_array($tariffType, ['1', '2', '3'], true) ? $tariffType : '1';
            $addons['category'] = trim((string)($decoded['category'] ?? ''));

            if (isset($decoded['extra']) && is_array($decoded['extra']) && !empty($decoded['extra'])) {
                $addons['extra_json'] = json_encode($decoded['extra'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
        }
    }

    $rawMapping = trim((string)($row['node_mapping_json'] ?? ''));
    if ($rawMapping !== '') {
        $decodedMapping = json_decode($rawMapping, true);
        if (is_array($decodedMapping) && !empty($decodedMapping)) {
            $addons['node_mapping_json'] = json_encode($decodedMapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }


    $rawStatusTargets = trim((string)($row['status_targets_json'] ?? ''));
    if ($rawStatusTargets !== '') {
        $decodedStatusTargets = json_decode($rawStatusTargets, true);
        if (is_array($decodedStatusTargets) && !empty($decodedStatusTargets)) {
            $addons['status_targets_json'] = json_encode($decodedStatusTargets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }

    $rawReportOutStatuses = trim((string)($row['report_out_statuses_json'] ?? ''));
    if ($rawReportOutStatuses !== '') {
        $decodedReportOutStatuses = json_decode($rawReportOutStatuses, true);
        if (is_array($decodedReportOutStatuses) && !empty($decodedReportOutStatuses)) {
            $addons['report_out_statuses_json'] = json_encode($decodedReportOutStatuses, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }

    return $addons;
}

function connectors_fetch_addons(mysqli $dbcnx, int $connectorId): array
{
    $addons = connectors_default_addons();
    $stmt = $dbcnx->prepare('SELECT addons_json, node_mapping_json, status_targets_json, report_out_statuses_json FROM connectors_addons WHERE connector_id = ? LIMIT 1');
    if (!$stmt) {
        error_log('connectors addons fetch prepare error: ' . $dbcnx->error);
        return $addons;
    }

    $stmt->bind_param('i', $connectorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $addons;
    }

    return connectors_decode_addons($row);
}

function connectors_build_addons_payload_from_post(): array
{
    $tariffType = trim((string)($_POST['addon_tariff_type'] ?? '1'));
    if (!in_array($tariffType, ['1', '2', '3'], true)) {
        $tariffType = '1';
    }

    $category = trim((string)($_POST['addon_category'] ?? ''));
    $extraJsonRaw = trim((string)($_POST['addon_extra_json'] ?? ''));
    $nodeMappingJsonRaw = trim((string)($_POST['addon_node_mapping_json'] ?? ''));
    $statusTargetsJsonRaw = trim((string)($_POST['addon_status_targets_json'] ?? ''));
    $reportOutStatusesJsonRaw = trim((string)($_POST['addon_report_out_statuses_json'] ?? ''));

    $extra = [];
    if ($extraJsonRaw !== '') {
        $decodedExtra = json_decode($extraJsonRaw, true);
        if (!is_array($decodedExtra)) {
            throw new InvalidArgumentException('Некорректный JSON в "Дополнения (extra)"');
        }
        $extra = $decodedExtra;
    }

    $nodeMapping = [];
    if ($nodeMappingJsonRaw !== '') {
        $decodedMapping = json_decode($nodeMappingJsonRaw, true);
        if (!is_array($decodedMapping)) {
            throw new InvalidArgumentException('Некорректный JSON в "Node mapping"');
        }
        $nodeMapping = $decodedMapping;
    }

    $statusTargets = [];
    if ($statusTargetsJsonRaw !== '') {
        $decodedStatusTargets = json_decode($statusTargetsJsonRaw, true);
        if (!is_array($decodedStatusTargets)) {
            throw new InvalidArgumentException('Некорректный JSON в "Карта статусов -> таблица"');
        }
        foreach ($decodedStatusTargets as $status => $targetTable) {
            $statusKey = trim((string)$status);
            $target = trim((string)$targetTable);
            if ($statusKey === '' || $target === '') {
                continue;
            }
            $statusTargets[$statusKey] = $target;
        }
    }

    $reportOutStatuses = [];
    if ($reportOutStatusesJsonRaw !== '') {
        $decodedReportOutStatuses = json_decode($reportOutStatusesJsonRaw, true);
        if (!is_array($decodedReportOutStatuses)) {
            throw new InvalidArgumentException('Некорректный JSON в "Карта статусов репорта -> warehouse_item_out.status"');
        }

        $allowedOutStatuses = ['error', 'for_sync', 'half_sync', 'confirmed_sync', 'to_send', 'sended', 'success'];
        foreach ($decodedReportOutStatuses as $reportStatus => $outStatus) {
            $reportStatusKey = trim((string)$reportStatus);
            $normalizedOutStatus = strtolower(trim((string)$outStatus));
            if ($reportStatusKey === '' || $normalizedOutStatus === '') {
                continue;
            }
            if (!in_array($normalizedOutStatus, $allowedOutStatuses, true)) {
                throw new InvalidArgumentException('Недопустимый warehouse_item_out.status в карте статусов репорта: ' . $normalizedOutStatus);
            }
            $reportOutStatuses[$reportStatusKey] = $normalizedOutStatus;
        }
    }

    return [
        'addons' => [
            'tariff_type' => $tariffType,
            'category' => $category,
            'extra' => $extra,
        ],
        'node_mapping' => $nodeMapping,
        'status_targets' => $statusTargets,
        'report_out_statuses' => $reportOutStatuses,
    ];
}
function connectors_build_status(array $connector): array
{
    $isActive = (int)($connector['is_active'] ?? 0) === 1;
    $lastError = trim((string)($connector['last_error'] ?? ''));

    if (!$isActive) {
        $connector['status_label'] = 'Отключен';
        $connector['status_class'] = 'secondary';
        return $connector;
    }

    if ($lastError !== '') {
        $connector['status_label'] = 'Ошибка';
        $connector['status_class'] = 'danger';
        return $connector;
    }

    if (!empty($connector['last_success_at'])) {
        $connector['status_label'] = 'Работает';
        $connector['status_class'] = 'success';
        return $connector;
    }

    $connector['status_label'] = 'Ожидание';
    $connector['status_class'] = 'warning';
    return $connector;
}

function connectors_fetch_one(mysqli $dbcnx, int $connectorId): ?array
{
    $sql = "SELECT
                id,
                name,
                countries,
                base_url,
                auth_type,
                auth_username,
                auth_password,
                api_token,
                auth_token,
                auth_token_expires_at,
                auth_cookies,
                is_active,
                last_sync_at,
                last_success_at,
                last_error,
                ssl_ignore,
                scenario_json,
                operations_json,
                last_manual_confirm_at,
                last_manual_confirm_by,
                notes,
                is_test_connector,
                created_at,
                updated_at
            FROM connectors
            WHERE id = ?
            LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        error_log('connectors fetch prepare error: ' . $dbcnx->error);
        return null;
    }
    $stmt->bind_param('i', $connectorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }
    return connectors_try_migrate_operations_json($row);
}


function connectors_default_operations(array $connector): array
{
    $defaultTarget = '';
    $name = strtolower(trim((string)($connector['name'] ?? '')));
    $countries = strtolower(trim((string)($connector['countries'] ?? '')));

    if ($name !== '' && $countries !== '') {
        $country = trim(explode(',', $countries)[0]);
        $country = preg_replace('/[^a-z0-9_]+/', '_', $country ?? '');
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $defaultTarget = trim($name . '_' . $country, '_');
    }

    return [
        'report' => [
            'schema_version' => 2,
            'operation_id' => 'report',
            'display_name' => 'Операция #1 (report)',
            'module' => 'connectors',
            'action' => 'sync_connector_report',
            'kind' => 'browser_steps',
            'run_after' => [],
            'run_with' => [],
            'run_finally' => [],
            'entrypoint' => 1,
            'on_dependency_fail' => 'stop',
            'enabled' => 0,
            'page_url' => '',
            'file_extension' => 'xlsx',
            'download_mode' => 'browser',
            'log_steps' => 0,
            'steps_json' => '',
            'curl_config_json' => '',
            'target_table' => $defaultTarget,
            'field_mapping_json' => '',
        ],
        'submission' => [
            'schema_version' => 2,
            'operation_id' => 'submission',
            'display_name' => 'Операция #2 (submission)',
            'module' => 'connectors',
            'action' => 'submit_connector_shipment',
            'kind' => 'browser_steps',
            'run_after' => [],
            'run_with' => [],
            'run_finally' => [],
            'entrypoint' => 0,
            'on_dependency_fail' => 'stop',
            'enabled' => 0,
            'page_url' => '',
            'log_steps' => 0,
            'steps_json' => '',
            'request_config_json' => '',
            'success_selector' => '',
            'success_text' => '',
            'error_selector' => '',
        ],
        'track_and_label_info' => [
            'schema_version' => 2,
            'operation_id' => 'track_and_label_info',
            'display_name' => 'Операция #3 (track_and_label_info)',
            'module' => 'connectors',
            'action' => 'track_and_label_info',
            'kind' => 'noop',
            'run_after' => [],
            'run_with' => [],
            'run_finally' => [],
            'entrypoint' => 0,
            'on_dependency_fail' => 'stop',
            'enabled' => 0,
        ],
    ];
}


function connectors_normalize_dependency_links($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $result = [];
    foreach ($value as $item) {
        $id = trim((string)$item);
        if ($id === '') {
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $id)) {
            continue;
        }
        $result[$id] = true;
    }

    return array_keys($result);
}

function connectors_normalize_dependency_policy($value): string
{
    $value = strtolower(trim((string)$value));
    if (!in_array($value, ['stop', 'skip', 'continue'], true)) {
        return 'stop';
    }

    return $value;
}


function connectors_operation_module_registry(): array
{
    return ['warehouse', 'connectors', 'devices', 'tools', 'users', 'system', 'generic'];
}

function connectors_operation_kind_registry(): array
{
    return ['api_call', 'browser_steps', 'script', 'noop'];
}

function connectors_extract_module_from_handler_path(string $handlerPath): string
{
    if (preg_match('#^api/([^/]+)/#', trim($handlerPath), $matches)) {
        return strtolower(trim((string)$matches[1]));
    }

    return 'generic';
}

function connectors_core_api_action_module_registry(): array
{
    static $registry = null;
    if (is_array($registry)) {
        return $registry;
    }

    $registry = [];
    $coreApiPath = __DIR__ . '/../../core_api.php';
    if (!is_file($coreApiPath)) {
        return $registry;
    }

    $contents = (string)file_get_contents($coreApiPath);
    if ($contents === '') {
        return $registry;
    }

    if (preg_match_all("/['\"]([a-zA-Z0-9_.-]+)['\"]\s*=>\s*['\"](api\/[a-zA-Z0-9_\/-]+\.php)['\"]/", $contents, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $action = trim((string)($match[1] ?? ''));
            $handlerPath = trim((string)($match[2] ?? ''));
            if ($action === '' || $handlerPath === '') {
                continue;
            }

            $registry[$action] = connectors_extract_module_from_handler_path($handlerPath);
        }
    }

    return $registry;
}

function connectors_validate_operation_action_module(string $operationId, string $module, string $kind, string $action): void
{
    if ($module === 'generic' || $kind !== 'api_call' || $action === '') {
        return;
    }

    $registry = connectors_core_api_action_module_registry();
    if (!isset($registry[$action])) {
        throw new InvalidArgumentException('Операция "' . $operationId . '": action "' . $action . '" не найден в core_api router');
    }

    $routerModule = (string)$registry[$action];
    if ($routerModule !== $module) {
        throw new InvalidArgumentException('Операция "' . $operationId . '": module="' . $module . '" не совпадает с модулем action "' . $action . '" из router ("' . $routerModule . '")');
    }
}

function connectors_is_valid_operation_module(string $module): bool
{
    return in_array($module, connectors_operation_module_registry(), true);
}

function connectors_is_valid_operation_kind(string $kind): bool
{
    return in_array($kind, connectors_operation_kind_registry(), true);
}

function connectors_normalize_operation_module($value): string
{
    $module = strtolower(trim((string)$value));
    if ($module === '') {
        return 'generic';
    }

    return $module;
}

function connectors_normalize_operation_kind($value, string $module = 'generic'): string
{
    $kind = strtolower(trim((string)$value));
    if ($kind === '' && $module === 'generic') {
        return 'browser_steps';
    }

    return $kind;
}

function connectors_is_v3_operations_payload(array $payload): bool
{
    return (int)($payload['schema_version'] ?? 0) === 3
        && isset($payload['operations'])
        && is_array($payload['operations']);
}

function connectors_v3_payload_to_runtime_operations(array $payload): array
{
    $result = [];
    $operations = $payload['operations'] ?? [];
    if (!is_array($operations)) {
        return $result;
    }

    foreach ($operations as $operation) {
        if (!is_array($operation)) {
            continue;
        }

        $operationId = trim((string)($operation['operation_id'] ?? ''));
        if ($operationId === '') {
            continue;
        }

        $module = connectors_normalize_operation_module($operation['module'] ?? 'generic');
        $kind = connectors_normalize_operation_kind($operation['kind'] ?? '', $module);
        $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];

        $result[$operationId] = [
            'schema_version' => 3,
            'operation_id' => $operationId,
            'display_name' => trim((string)($operation['display_name'] ?? $operationId)),
            'module' => $module,
            'action' => trim((string)($operation['action'] ?? '')),
            'kind' => $kind,
            'run_after' => connectors_normalize_dependency_links($operation['run_after'] ?? []),
            'run_with' => connectors_normalize_dependency_links($operation['run_with'] ?? []),
            'run_finally' => connectors_normalize_dependency_links($operation['run_finally'] ?? []),
            'entrypoint' => !empty($operation['entrypoint']) ? 1 : 0,
            'on_dependency_fail' => connectors_normalize_dependency_policy($operation['on_dependency_fail'] ?? 'stop'),
            'enabled' => !empty($operation['enabled']) ? 1 : 0,
            'config' => $config,
        ];
    }

    return $result;
}


function connectors_build_v3_operation_payload(array $operation): array
{
    $operationId = trim((string)($operation['operation_id'] ?? ''));
    $module = connectors_normalize_operation_module($operation['module'] ?? 'generic');
    $kind = connectors_normalize_operation_kind($operation['kind'] ?? '', $module);
    $displayName = trim((string)($operation['display_name'] ?? $operationId));

    if ($displayName === '') {
        $displayName = $operationId;
    }

    $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];

    return [
        'operation_id' => $operationId,
        'display_name' => $displayName,
        'module' => $module,
        'action' => trim((string)($operation['action'] ?? '')),
        'kind' => $kind,
        'enabled' => !empty($operation['enabled']) ? 1 : 0,
        'entrypoint' => !empty($operation['entrypoint']) ? 1 : 0,
        'on_dependency_fail' => connectors_normalize_dependency_policy($operation['on_dependency_fail'] ?? 'stop'),
        'run_after' => connectors_normalize_dependency_links($operation['run_after'] ?? []),
        'run_with' => connectors_normalize_dependency_links($operation['run_with'] ?? []),
        'run_finally' => connectors_normalize_dependency_links($operation['run_finally'] ?? []),
        'config' => $config,
    ];
}

function connectors_legacy_operations_to_v3_payload(array $payload): array
{
    $operations = [];

    foreach ($payload as $operationKey => $operation) {
        if (!is_array($operation)) {
            continue;
        }

        $operationId = trim((string)($operation['operation_id'] ?? $operationKey));
        if ($operationId === '') {
            continue;
        }

        $module = connectors_normalize_operation_module($operation['module'] ?? 'connectors');
        $kind = connectors_normalize_operation_kind($operation['kind'] ?? '', $module);
        $displayName = trim((string)($operation['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Операция ' . $operationId;
        }

        $config = [];
        foreach ([
            'page_url', 'file_extension', 'download_mode', 'log_steps', 'steps', 'curl_config',
            'target_table', 'field_mapping', 'request_config', 'success_selector', 'success_text', 'error_selector',
        ] as $configKey) {
            if (array_key_exists($configKey, $operation)) {
                $config[$configKey] = $operation[$configKey];
            }
        }

        $operations[] = connectors_build_v3_operation_payload([
            'operation_id' => $operationId,
            'display_name' => $displayName,
            'module' => $module,
            'action' => trim((string)($operation['action'] ?? '')),
            'kind' => $kind,
            'enabled' => !empty($operation['enabled']) ? 1 : 0,
            'entrypoint' => !empty($operation['entrypoint']) ? 1 : 0,
            'on_dependency_fail' => $operation['on_dependency_fail'] ?? 'stop',
            'run_after' => $operation['run_after'] ?? [],
            'run_with' => $operation['run_with'] ?? [],
            'run_finally' => $operation['run_finally'] ?? [],
            'config' => $config,
        ]);
    }

    return [
        'schema_version' => 3,
        'operations' => $operations,
    ];
}

function connectors_validate_v3_operations_payload(array $operationsPayload): void
{
    if ((int)($operationsPayload['schema_version'] ?? 0) !== 3) {
        throw new InvalidArgumentException('Для нового формата ожидается schema_version = 3');
    }

    if (!isset($operationsPayload['operations']) || !is_array($operationsPayload['operations']) || $operationsPayload['operations'] === []) {
        throw new InvalidArgumentException('Операции v3 должны содержать непустой массив operations');
    }

    $runtimeOperations = connectors_v3_payload_to_runtime_operations($operationsPayload);
    if ($runtimeOperations === []) {
        throw new InvalidArgumentException('Операции v3 не содержат валидных operation_id');
    }


    $seenOperationIds = [];
    foreach ($operationsPayload['operations'] as $index => $operation) {
        if (!is_array($operation)) {
            continue;
        }
        $operationId = trim((string)($operation['operation_id'] ?? ''));
        if ($operationId === '') {
            continue;
        }
        if (isset($seenOperationIds[$operationId])) {
            throw new InvalidArgumentException('Дублирующийся operation_id в operations_v3_json: "' . $operationId . '" (операции #' . $seenOperationIds[$operationId] . ' и #' . ($index + 1) . ')');
        }
        $seenOperationIds[$operationId] = $index + 1;
    }

    foreach ($operationsPayload['operations'] as $index => $operation) {
        if (!is_array($operation)) {
            throw new InvalidArgumentException('Операция #' . ($index + 1) . ': ожидается JSON-объект');
        }

        $operationId = trim((string)($operation['operation_id'] ?? ''));
        if ($operationId === '') {
            throw new InvalidArgumentException('Операция #' . ($index + 1) . ': operation_id обязателен');
        }


        $displayName = trim((string)($operation['display_name'] ?? ''));
        if ($displayName === '') {
            throw new InvalidArgumentException('Операция "' . $operationId . '": display_name обязателен');
        }

        foreach (['run_after', 'run_with', 'run_finally'] as $dependencyField) {
            if (!array_key_exists($dependencyField, $operation) || !is_array($operation[$dependencyField])) {
                throw new InvalidArgumentException('Операция "' . $operationId . '": поле ' . $dependencyField . ' должно быть JSON-массивом');
            }
        }

        if (!array_key_exists('config', $operation) || !is_array($operation['config'])) {
            throw new InvalidArgumentException('Операция "' . $operationId . '": config должен быть JSON-объектом');
        }

        $module = connectors_normalize_operation_module($operation['module'] ?? 'generic');
        $kind = connectors_normalize_operation_kind($operation['kind'] ?? '', $module);
        $action = trim((string)($operation['action'] ?? ''));


        if (!connectors_is_valid_operation_module($module)) {
            throw new InvalidArgumentException('Операция "' . $operationId . '": module должен входить в справочник [' . implode(', ', connectors_operation_module_registry()) . ']');
        }

        if (!connectors_is_valid_operation_kind($kind)) {
            throw new InvalidArgumentException('Операция "' . $operationId . '": kind должен входить в справочник [' . implode(', ', connectors_operation_kind_registry()) . ']');
        }

        if ($module === 'generic' && $action === '') {
            // ok for generic/browser steps
        } elseif ($kind === 'api_call' && $action === '') {
            throw new InvalidArgumentException('Операция "' . $operationId . '": для kind=api_call поле action обязательно');
        }
        connectors_validate_operation_action_module($operationId, $module, $kind, $action);
    }


    connectors_validate_operations_runtime($runtimeOperations);

    foreach ($runtimeOperations as $operation) {
        if (!empty($operation['entrypoint']) && !empty($operation['enabled'])) {
            connectors_build_execution_plan($runtimeOperations, (string)($operation['operation_id'] ?? ''));
        }
    }
}

function connectors_dependency_graph_rollout_mode(): string
{
    $mode = strtolower(trim((string)(getenv('CONNECTORS_DEPENDENCY_GRAPH_ROLLOUT') ?: 'test_only')));
    if (!in_array($mode, ['off', 'test_only', 'all'], true)) {
        return 'test_only';
    }

    return $mode;
}

function connectors_is_test_connector(array $connector): bool
{
    return (int)($connector['is_test_connector'] ?? 0) === 1;
}


function connectors_build_graph_error(string $runId, int $connectorId, string $entrypoint, string $errorCode, array $details = []): array
{
    return [
        'run_id' => $runId,
        'connector_id' => $connectorId,
        'entrypoint' => $entrypoint,
        'error_code' => $errorCode,
        'details' => $details,
    ];
}

function connectors_resolve_graph_error_code(string $message): string
{
    if (mb_stripos($message, 'Дублирующийся operation_id') !== false) {
        return 'duplicate_operation_id';
    }
    if (mb_stripos($message, 'не найдена') !== false) {
        return 'missing_dependency';
    }
    if (mb_stripos($message, 'ссылка на саму себя') !== false) {
        return 'self_dependency';
    }
    if (mb_stripos($message, 'неактивна') !== false) {
        return 'inactive_dependency';
    }
    if (mb_stripos($message, 'циклическая зависимость') !== false || mb_stripos($message, 'цикл в run_with/run_after') !== false) {
        return 'dependency_cycle';
    }
    if (mb_stripos($message, 'Entrypoint операция не найдена') !== false || mb_stripos($message, 'Не найден entrypoint') !== false) {
        return 'entrypoint_not_found';
    }

    return 'invalid_graph';
}

function connectors_is_dependency_graph_enabled(array $connector): bool
{
    $rolloutMode = connectors_dependency_graph_rollout_mode();
    if ($rolloutMode === 'off') {
        return false;
    }

    if ($rolloutMode === 'all') {
        return true;
    }

    return connectors_is_test_connector($connector);
}

function connectors_decode_dependency_links_json(string $raw, string $fieldLabel): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Некорректный JSON в "' . $fieldLabel . '"');
    }

    return connectors_normalize_dependency_links($decoded);
}


function connectors_validate_single_operation_schema(string $operationKey, array $operation): void
{
    $requiredFields = [
        'schema_version',
        'operation_id',
        'run_after',
        'run_with',
        'run_finally',
        'entrypoint',
        'on_dependency_fail',
        'enabled',
    ];

    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $operation)) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": отсутствует обязательное поле "' . $field . '"');
        }
    }

    $schemaVersion = (int)$operation['schema_version'];
    if (!in_array($schemaVersion, [2, 3], true)) {
        throw new InvalidArgumentException('Операция "' . $operationKey . '": поддерживается schema_version = 2|3');
    }

    $operationId = trim((string)$operation['operation_id']);
    if ($operationId === '' || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $operationId)) {
        throw new InvalidArgumentException('Операция "' . $operationKey . '": некорректный operation_id');
    }

    foreach (['run_after', 'run_with', 'run_finally'] as $dependencyField) {
        if (!is_array($operation[$dependencyField])) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": поле "' . $dependencyField . '" должно быть массивом');
        }
        foreach ($operation[$dependencyField] as $link) {
            if (!is_string($link) || trim($link) === '' || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $link)) {
                throw new InvalidArgumentException('Операция "' . $operationKey . '": поле "' . $dependencyField . '" содержит некорректную ссылку');
            }
        }
    }

    $onDependencyFail = strtolower(trim((string)$operation['on_dependency_fail']));
    if (!in_array($onDependencyFail, ['stop', 'skip', 'continue'], true)) {
        throw new InvalidArgumentException('Операция "' . $operationKey . '": поле "on_dependency_fail" должно быть stop|skip|continue');
    }

    if ($schemaVersion === 3) {
        $displayName = trim((string)($operation['display_name'] ?? ''));
        if ($displayName === '') {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": для schema_version=3 поле "display_name" обязательно');
        }

        $module = connectors_normalize_operation_module($operation['module'] ?? '');
        if (!connectors_is_valid_operation_module($module)) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": module должен входить в справочник [' . implode(', ', connectors_operation_module_registry()) . ']');
        }

        $kind = connectors_normalize_operation_kind($operation['kind'] ?? '', $module);
        if (!connectors_is_valid_operation_kind($kind)) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": kind должен входить в справочник [' . implode(', ', connectors_operation_kind_registry()) . ']');
        }

        if (!array_key_exists('config', $operation) || !is_array($operation['config'])) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": для schema_version=3 поле "config" должно быть JSON-объектом');
        }

        $action = trim((string)($operation['action'] ?? ''));
        if ($module !== 'generic' && $kind === 'api_call' && $action === '') {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": для module != generic и kind=api_call поле "action" обязательно');
        }
        connectors_validate_operation_action_module($operationKey, $module, $kind, $action);
    }
}

function connectors_validate_operations_runtime(array $operations): void
{
    $operationIndex = [];
    $dependencies = [];

    foreach ($operations as $operationKey => $operation) {
        if (!is_array($operation)) {
            throw new InvalidArgumentException('Операция "' . $operationKey . '": неверный формат');
        }

        connectors_validate_single_operation_schema((string)$operationKey, $operation);

        $operationId = trim((string)$operation['operation_id']);
        if (isset($operationIndex[$operationId])) {
            throw new InvalidArgumentException('Дублирующийся operation_id: "' . $operationId . '"');
        }

        $operationIndex[$operationId] = [
            'key' => (string)$operationKey,
            'enabled' => !empty($operation['enabled']),
        ];

        $dependencies[$operationId] = [];
        foreach (['run_after', 'run_with', 'run_finally'] as $field) {
            foreach ($operation[$field] as $linkedOperationId) {
                $dependencies[$operationId][] = trim((string)$linkedOperationId);
            }
        }
    }

    foreach ($operations as $operationKey => $operation) {
        $operationId = trim((string)$operation['operation_id']);
        foreach (['run_after', 'run_with', 'run_finally'] as $field) {
            foreach ($operation[$field] as $linkedOperationIdRaw) {
                $linkedOperationId = trim((string)$linkedOperationIdRaw);

                if (!isset($operationIndex[$linkedOperationId])) {
                    throw new InvalidArgumentException('Операция "' . $operationId . '": ссылка "' . $linkedOperationId . '" в "' . $field . '" не найдена');
                }

                if ($linkedOperationId === $operationId) {
                    throw new InvalidArgumentException('Операция "' . $operationId . '": ссылка на саму себя в "' . $field . '" запрещена');
                }

                if (!$operationIndex[$linkedOperationId]['enabled']) {
                    throw new InvalidArgumentException('Операция "' . $operationId . '": зависимость "' . $linkedOperationId . '" неактивна');
                }
            }
        }
    }

    $adjacency = [];
    foreach (array_keys($dependencies) as $operationId) {
        $adjacency[$operationId] = [];
    }
    foreach ($dependencies as $operationId => $linkedOperationIds) {
        foreach ($linkedOperationIds as $linkedOperationId) {
            if ($linkedOperationId === '' || !isset($adjacency[$linkedOperationId])) {
                continue;
            }
            $adjacency[$linkedOperationId][] = $operationId;
        }
    }

    $stableOrder = [];
    $index = 0;
    foreach ($operations as $operation) {
        $operationId = trim((string)($operation['operation_id'] ?? ''));
        if ($operationId === '') {
            continue;
        }
        $stableOrder[$operationId] = $index++;
    }
    try {
        connectors_topological_sort_operations(array_keys($dependencies), $adjacency, $stableOrder);
    } catch (InvalidArgumentException $e) {
        throw new InvalidArgumentException('Обнаружен цикл зависимостей между операциями (run_after/run_with/run_finally): ' . $e->getMessage());
    }
}

function connectors_topological_sort_operations(array $nodes, array $adjacency, array $stableOrder): array
{
    $nodeSet = [];
    foreach ($nodes as $nodeId) {
        $nodeSet[(string)$nodeId] = true;
    }

    $indegree = [];
    foreach (array_keys($nodeSet) as $nodeId) {
        $indegree[$nodeId] = 0;
    }

    foreach ($adjacency as $from => $toList) {
        $from = (string)$from;
        if (!isset($nodeSet[$from]) || !is_array($toList)) {
            continue;
        }

        foreach ($toList as $to) {
            $to = (string)$to;
            if (!isset($nodeSet[$to])) {
                continue;
            }
            $indegree[$to] = (int)($indegree[$to] ?? 0) + 1;
        }
    }

    $queue = [];
    foreach ($indegree as $nodeId => $degree) {
        if ((int)$degree === 0) {
            $queue[] = $nodeId;
        }
    }

    usort($queue, static function (string $a, string $b) use ($stableOrder): int {
        $aPos = (int)($stableOrder[$a] ?? PHP_INT_MAX);
        $bPos = (int)($stableOrder[$b] ?? PHP_INT_MAX);
        if ($aPos === $bPos) {
            return strcmp($a, $b);
        }
        return $aPos <=> $bPos;
    });

    $result = [];
    while (!empty($queue)) {
        $current = array_shift($queue);
        $result[] = $current;

        $targets = isset($adjacency[$current]) && is_array($adjacency[$current]) ? $adjacency[$current] : [];
        foreach ($targets as $target) {
            $target = (string)$target;
            if (!isset($nodeSet[$target])) {
                continue;
            }

            $indegree[$target]--;
            if ($indegree[$target] === 0) {
                $queue[] = $target;
            }
        }

        usort($queue, static function (string $a, string $b) use ($stableOrder): int {
            $aPos = (int)($stableOrder[$a] ?? PHP_INT_MAX);
            $bPos = (int)($stableOrder[$b] ?? PHP_INT_MAX);
            if ($aPos === $bPos) {
                return strcmp($a, $b);
            }
            return $aPos <=> $bPos;
        });
    }

    if (count($result) !== count($nodeSet)) {
        throw new InvalidArgumentException('Не удалось построить топологический порядок (обнаружен цикл в подграфе)');
    }

    return $result;
}

function connectors_build_execution_plan(array $operations, ?string $entrypointOperationId = null): array
{
    connectors_validate_operations_runtime($operations);

    $operationsById = [];
    $stableOrder = [];
    $index = 0;

    foreach ($operations as $operationKey => $operation) {
        $operationId = trim((string)($operation['operation_id'] ?? ''));
        if ($operationId === '' || empty($operation['enabled'])) {
            continue;
        }
        $operationsById[$operationId] = [
            'key' => (string)$operationKey,
            'config' => $operation,
        ];
        $stableOrder[$operationId] = $index++;
    }

    if (empty($operationsById)) {
        throw new InvalidArgumentException('Нет активных операций для построения плана');
    }

    if ($entrypointOperationId !== null) {
        $entrypointOperationId = trim($entrypointOperationId);
    }

    if ($entrypointOperationId === null || $entrypointOperationId === '') {
        foreach ($operationsById as $operationId => $entry) {
            if (!empty($entry['config']['entrypoint'])) {
                $entrypointOperationId = $operationId;
                break;
            }
        }
    }

    if ($entrypointOperationId === null || $entrypointOperationId === '') {
        throw new InvalidArgumentException('Не найден entrypoint среди активных операций');
    }

    if (!isset($operationsById[$entrypointOperationId])) {
        throw new InvalidArgumentException('Entrypoint операция не найдена среди активных: "' . $entrypointOperationId . '"');
    }

    $main = $operationsById[$entrypointOperationId]['config'];
    $mainOperationId = trim((string)$main['operation_id']);

    $duringSet = [];
    $duringQueue = [];
    foreach ((array)($main['run_with'] ?? []) as $duringId) {
        $duringId = trim((string)$duringId);
        if ($duringId !== '') {
            $duringQueue[] = $duringId;
        }
    }

    while (!empty($duringQueue)) {
        $current = array_shift($duringQueue);
        if ($current === $mainOperationId || isset($duringSet[$current])) {
            continue;
        }
        $duringSet[$current] = true;

        $cfg = $operationsById[$current]['config'] ?? null;
        if (!is_array($cfg)) {
            throw new InvalidArgumentException('During-операция не найдена: "' . $current . '"');
        }

        foreach ((array)($cfg['run_with'] ?? []) as $nestedDuringId) {
            $nestedDuringId = trim((string)$nestedDuringId);
            if ($nestedDuringId !== '' && !isset($duringSet[$nestedDuringId])) {
                $duringQueue[] = $nestedDuringId;
            }
        }
    }

    $beforeSet = [];
    $beforeQueue = [$mainOperationId, ...array_keys($duringSet)];
    while (!empty($beforeQueue)) {
        $current = array_shift($beforeQueue);
        $cfg = $operationsById[$current]['config'] ?? null;
        if (!is_array($cfg)) {
            continue;
        }
        foreach ((array)($cfg['run_after'] ?? []) as $dependencyId) {
            $dependencyId = trim((string)$dependencyId);
            if ($dependencyId === '' || $dependencyId === $mainOperationId || isset($duringSet[$dependencyId])) {
                continue;
            }
            if (!isset($beforeSet[$dependencyId])) {
                $beforeSet[$dependencyId] = true;
                $beforeQueue[] = $dependencyId;
            }
        }
    }

    $finallySet = [];
    $finallyQueue = (array)($main['run_finally'] ?? []);
    while (!empty($finallyQueue)) {
        $current = trim((string)array_shift($finallyQueue));
        if ($current === '' || $current === $mainOperationId || isset($duringSet[$current]) || isset($beforeSet[$current])) {
            continue;
        }
        if (isset($finallySet[$current])) {
            continue;
        }

        $finallySet[$current] = true;
        $cfg = $operationsById[$current]['config'] ?? null;
        if (is_array($cfg)) {
            foreach ((array)($cfg['run_finally'] ?? []) as $nestedFinallyId) {
                $nestedFinallyId = trim((string)$nestedFinallyId);
                if ($nestedFinallyId !== '' && !isset($finallySet[$nestedFinallyId])) {
                    $finallyQueue[] = $nestedFinallyId;
                }
            }
        }
    }

    $adjacency = [];
    foreach ($operationsById as $operationId => $entry) {
        $adjacency[$operationId] = [];
    }
    foreach ($operationsById as $operationId => $entry) {
        $cfg = $entry['config'];
        foreach ((array)($cfg['run_after'] ?? []) as $dependencyId) {
            $dependencyId = trim((string)$dependencyId);
            if ($dependencyId !== '' && isset($adjacency[$dependencyId])) {
                $adjacency[$dependencyId][] = $operationId;
            }
        }
    }

    $beforeOrder = connectors_topological_sort_operations(array_keys($beforeSet), $adjacency, $stableOrder);
    $finallyOrder = connectors_topological_sort_operations(array_keys($finallySet), $adjacency, $stableOrder);

    $duringNodes = array_keys($duringSet);
    $duringAdjacency = [];
    foreach ($duringNodes as $nodeId) {
        $duringAdjacency[$nodeId] = [];
    }
    foreach ($duringNodes as $nodeId) {
        $cfg = $operationsById[$nodeId]['config'] ?? [];
        foreach ((array)($cfg['run_after'] ?? []) as $dependencyId) {
            $dependencyId = trim((string)$dependencyId);
            if (isset($duringSet[$dependencyId])) {
                $duringAdjacency[$dependencyId][] = $nodeId;
            }
        }
    }

    $duringGroups = [];
    if (!empty($duringNodes)) {
        $indegree = [];
        foreach ($duringNodes as $nodeId) {
            $indegree[$nodeId] = 0;
        }
        foreach ($duringAdjacency as $from => $toList) {
            foreach ($toList as $toId) {
                $indegree[$toId]++;
            }
        }

        $processed = 0;
        while ($processed < count($duringNodes)) {
            $group = [];
            foreach ($indegree as $nodeId => $degree) {
                if ($degree === 0) {
                    $group[] = $nodeId;
                }
            }

            if (empty($group)) {
                throw new InvalidArgumentException('Не удалось запланировать during-группы: цикл в run_with/run_after');
            }

            usort($group, static function (string $a, string $b) use ($stableOrder): int {
                $aPos = (int)($stableOrder[$a] ?? PHP_INT_MAX);
                $bPos = (int)($stableOrder[$b] ?? PHP_INT_MAX);
                if ($aPos === $bPos) {
                    return strcmp($a, $b);
                }
                return $aPos <=> $bPos;
            });

            $duringGroups[] = $group;
            foreach ($group as $nodeId) {
                $processed++;
                $indegree[$nodeId] = -1;
                foreach ((array)($duringAdjacency[$nodeId] ?? []) as $toId) {
                    $indegree[$toId]--;
                }
            }
        }
    }

    return [
        'entrypoint_operation_id' => $entrypointOperationId,
        'before' => $beforeOrder,
        'main' => $mainOperationId,
        'during' => $duringGroups,
        'during_groups' => $duringGroups,
        'finally' => $finallyOrder,
        'after' => $finallyOrder,
    ];
}

function connectors_build_legacy_execution_plan(string $entrypointOperationId): array
{
    return [
        'entrypoint_operation_id' => $entrypointOperationId,
        'before' => [],
        'main' => $entrypointOperationId,
        'during' => [],
        'finally' => [],
        'legacy_mode' => true,
        'reason' => 'dependency_graph_rollout_disabled',
    ];
}

function connectors_validate_operations_payload(array $operationsPayload): void
{
    if (connectors_is_v3_operations_payload($operationsPayload)) {
        connectors_validate_v3_operations_payload($operationsPayload);
        return;
    }

    if (!isset($operationsPayload['report']) || !isset($operationsPayload['submission'])) {
        throw new InvalidArgumentException('Операции должны содержать как минимум report и submission');
    }

    connectors_validate_operations_runtime($operationsPayload);

    foreach ($operationsPayload as $operation) {
        if (!is_array($operation) || empty($operation['entrypoint']) || empty($operation['enabled'])) {
            continue;
        }
        connectors_build_execution_plan($operationsPayload, (string)($operation['operation_id'] ?? ''));
    }
}

function connectors_decode_operations(array $connector): array
{
    $operations = connectors_default_operations($connector);
    $raw = (string)($connector['operations_json'] ?? '');
    if ($raw === '') {
        return $operations;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $operations;
    }



    if (connectors_is_v3_operations_payload($decoded)) {
        return connectors_build_legacy_compat_operations_view($decoded, $connector);
    }

    if (isset($decoded['report']) && is_array($decoded['report'])) {
        $report = $decoded['report'];
        $operations['report']['schema_version'] = (int)($report['schema_version'] ?? 2) > 0 ? (int)$report['schema_version'] : 2;
        $operations['report']['operation_id'] = trim((string)($report['operation_id'] ?? 'report')) ?: 'report';
        $operations['report']['run_after'] = connectors_normalize_dependency_links($report['run_after'] ?? []);
        $operations['report']['run_with'] = connectors_normalize_dependency_links($report['run_with'] ?? []);
        $operations['report']['run_finally'] = connectors_normalize_dependency_links($report['run_finally'] ?? []);
        $operations['report']['entrypoint'] = !empty($report['entrypoint']) ? 1 : 0;
        $operations['report']['on_dependency_fail'] = connectors_normalize_dependency_policy($report['on_dependency_fail'] ?? 'stop');
        $operations['report']['enabled'] = !empty($report['enabled']) ? 1 : 0;
        $operations['report']['display_name'] = trim((string)($report['display_name'] ?? $operations['report']['display_name']));
        $operations['report']['module'] = connectors_normalize_operation_module($report['module'] ?? $operations['report']['module']);
        $operations['report']['action'] = trim((string)($report['action'] ?? $operations['report']['action']));
        $operations['report']['kind'] = connectors_normalize_operation_kind($report['kind'] ?? $operations['report']['kind'], (string)$operations['report']['module']);
        $operations['report']['page_url'] = trim((string)($report['page_url'] ?? ''));
        $operations['report']['file_extension'] = trim((string)($report['file_extension'] ?? 'xlsx')) ?: 'xlsx';
        $operations['report']['download_mode'] = in_array(($report['download_mode'] ?? 'browser'), ['browser', 'curl'], true)
            ? (string)$report['download_mode']
            : 'browser';
        $operations['report']['log_steps'] = !empty($report['log_steps']) ? 1 : 0;

        if (isset($report['steps'])) {
            $operations['report']['steps_json'] = json_encode($report['steps'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
        if (isset($report['curl_config'])) {
            $operations['report']['curl_config_json'] = json_encode($report['curl_config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        $operations['report']['target_table'] = trim((string)($report['target_table'] ?? $operations['report']['target_table']));

        if (isset($report['field_mapping'])) {
            $operations['report']['field_mapping_json'] = json_encode($report['field_mapping'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }

    if (isset($decoded['submission']) && is_array($decoded['submission'])) {
        $submission = $decoded['submission'];
        $operations['submission']['schema_version'] = (int)($submission['schema_version'] ?? 2) > 0 ? (int)$submission['schema_version'] : 2;
        $operations['submission']['operation_id'] = trim((string)($submission['operation_id'] ?? 'submission')) ?: 'submission';
        $operations['submission']['run_after'] = connectors_normalize_dependency_links($submission['run_after'] ?? []);
        $operations['submission']['run_with'] = connectors_normalize_dependency_links($submission['run_with'] ?? []);
        $operations['submission']['run_finally'] = connectors_normalize_dependency_links($submission['run_finally'] ?? []);
        $operations['submission']['entrypoint'] = !empty($submission['entrypoint']) ? 1 : 0;
        $operations['submission']['on_dependency_fail'] = connectors_normalize_dependency_policy($submission['on_dependency_fail'] ?? 'stop');
        $operations['submission']['enabled'] = !empty($submission['enabled']) ? 1 : 0;
        $operations['submission']['display_name'] = trim((string)($submission['display_name'] ?? $operations['submission']['display_name']));
        $operations['submission']['module'] = connectors_normalize_operation_module($submission['module'] ?? $operations['submission']['module']);
        $operations['submission']['action'] = trim((string)($submission['action'] ?? $operations['submission']['action']));
        $operations['submission']['kind'] = connectors_normalize_operation_kind($submission['kind'] ?? $operations['submission']['kind'], (string)$operations['submission']['module']);
        $operations['submission']['page_url'] = trim((string)($submission['page_url'] ?? ''));
        $operations['submission']['log_steps'] = !empty($submission['log_steps']) ? 1 : 0;
        $operations['submission']['success_selector'] = trim((string)($submission['success_selector'] ?? ''));
        $operations['submission']['success_text'] = trim((string)($submission['success_text'] ?? ''));
        $operations['submission']['error_selector'] = trim((string)($submission['error_selector'] ?? ''));

        if (isset($submission['steps'])) {
            $operations['submission']['steps_json'] = json_encode($submission['steps'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        if (isset($submission['request_config'])) {
            $operations['submission']['request_config_json'] = json_encode($submission['request_config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }

    if (isset($decoded['track_and_label_info']) && is_array($decoded['track_and_label_info'])) {
        $trackAndLabel = $decoded['track_and_label_info'];
        $operations['track_and_label_info']['schema_version'] = (int)($trackAndLabel['schema_version'] ?? 2) > 0 ? (int)$trackAndLabel['schema_version'] : 2;
        $operations['track_and_label_info']['operation_id'] = trim((string)($trackAndLabel['operation_id'] ?? 'track_and_label_info')) ?: 'track_and_label_info';
        $operations['track_and_label_info']['run_after'] = connectors_normalize_dependency_links($trackAndLabel['run_after'] ?? []);
        $operations['track_and_label_info']['run_with'] = connectors_normalize_dependency_links($trackAndLabel['run_with'] ?? []);
        $operations['track_and_label_info']['run_finally'] = connectors_normalize_dependency_links($trackAndLabel['run_finally'] ?? []);
        $operations['track_and_label_info']['entrypoint'] = !empty($trackAndLabel['entrypoint']) ? 1 : 0;
        $operations['track_and_label_info']['on_dependency_fail'] = connectors_normalize_dependency_policy($trackAndLabel['on_dependency_fail'] ?? 'stop');
        $operations['track_and_label_info']['enabled'] = !empty($trackAndLabel['enabled']) ? 1 : 0;
        $operations['track_and_label_info']['display_name'] = trim((string)($trackAndLabel['display_name'] ?? $operations['track_and_label_info']['display_name']));
        $operations['track_and_label_info']['module'] = connectors_normalize_operation_module($trackAndLabel['module'] ?? $operations['track_and_label_info']['module']);
        $operations['track_and_label_info']['action'] = trim((string)($trackAndLabel['action'] ?? $operations['track_and_label_info']['action']));
        $operations['track_and_label_info']['kind'] = connectors_normalize_operation_kind($trackAndLabel['kind'] ?? $operations['track_and_label_info']['kind'], (string)$operations['track_and_label_info']['module']);
    }
    return $operations;
}


function connectors_decode_runtime_json_fields(array $operation): array
{
    $jsonToArrayMap = [
        'steps_json' => 'steps',
        'curl_config_json' => 'curl_config',
        'field_mapping_json' => 'field_mapping',
        'request_config_json' => 'request_config',
    ];

    foreach ($jsonToArrayMap as $jsonKey => $arrayKey) {
        if (isset($operation[$arrayKey])) {
            continue;
        }

        $rawJson = trim((string)($operation[$jsonKey] ?? ''));
        if ($rawJson === '') {
            continue;
        }

        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            $operation[$arrayKey] = $decoded;
        }
    }

    return $operation;
}

function connectors_decode_operations_payload(array $connector): array
{
    $raw = trim((string)($connector['operations_json'] ?? ''));
    if ($raw === '') {
        return connectors_migrate_operations_payload(connectors_default_operations($connector));
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return connectors_migrate_operations_payload(connectors_default_operations($connector));
    }

    return connectors_migrate_operations_payload($decoded);
}

function connectors_resolve_report_operation_id(array $runtimeOperations): ?string
{
    if (isset($runtimeOperations['report']) && is_array($runtimeOperations['report'])) {
        return 'report';
    }

    foreach ($runtimeOperations as $operationId => $operation) {
        if (!is_array($operation) || empty($operation['enabled']) || empty($operation['entrypoint'])) {
            continue;
        }
        return trim((string)$operationId) ?: null;
    }

    foreach ($runtimeOperations as $operationId => $operation) {
        if (!is_array($operation) || empty($operation['enabled'])) {
            continue;
        }
        return trim((string)$operationId) ?: null;
    }

    return null;
}

function connectors_resolve_legacy_test_entrypoint(array $operationsPayload, string $testOperation): string
{
    $testOperation = strtolower(trim($testOperation));

    if (connectors_is_v3_operations_payload($operationsPayload)) {
        $runtimeOperations = connectors_v3_payload_to_runtime_operations($operationsPayload);
        $preferred = $testOperation === 'submission' ? 'submission' : 'report';
        if (isset($runtimeOperations[$preferred])) {
            return (string)($runtimeOperations[$preferred]['operation_id'] ?? $preferred);
        }

        return $testOperation === 'submission' ? 'submission' : 'report';
    }

    return $testOperation === 'submission'
        ? (string)($operationsPayload['submission']['operation_id'] ?? 'submission')
        : (string)($operationsPayload['report']['operation_id'] ?? 'report');
}

function connectors_migrate_operations_payload(array $payload): array
{

    if (connectors_is_v3_operations_payload($payload)) {
        $normalized = [];
        foreach ((array)($payload['operations'] ?? []) as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $normalized[] = connectors_build_v3_operation_payload($operation);
        }

        return [
            'schema_version' => 3,
            'operations' => $normalized,
        ];
    }

    $operationDefaults = [
        'report' => [
            'operation_id' => 'report',
            'entrypoint' => 1,
            'schema_version' => 2,
        ],
        'submission' => [
            'operation_id' => 'submission',
            'entrypoint' => 0,
            'schema_version' => 2,
        ],
        'track_and_label_info' => [
            'operation_id' => 'track_and_label_info',
            'entrypoint' => 0,
            'schema_version' => 2,
        ],
    ];

    $mainOperationKey = 'report';
    foreach (['report', 'submission', 'track_and_label_info'] as $operationKey) {
        if (!empty($payload[$operationKey]['enabled'])) {
            $mainOperationKey = $operationKey;
            break;
        }
    }

    $hasExplicitEntrypoint = false;
    foreach ($operationDefaults as $operationKey => $defaults) {
        if (!isset($payload[$operationKey]) || !is_array($payload[$operationKey])) {
            continue;
        }
        if (array_key_exists('entrypoint', $payload[$operationKey])) {
            $hasExplicitEntrypoint = $hasExplicitEntrypoint || !empty($payload[$operationKey]['entrypoint']);
        }
    }

    foreach ($operationDefaults as $operationKey => $defaults) {
        if (!isset($payload[$operationKey]) || !is_array($payload[$operationKey])) {
            continue;
        }

        $operationId = trim((string)($payload[$operationKey]['operation_id'] ?? ''));
        if ($operationId === '') {
            $payload[$operationKey]['operation_id'] = $defaults['operation_id'];
        }

        if (!isset($payload[$operationKey]['run_after']) || !is_array($payload[$operationKey]['run_after'])) {
            $payload[$operationKey]['run_after'] = [];
        }

        $currentSchemaVersion = (int)($payload[$operationKey]['schema_version'] ?? 0);
        if ($currentSchemaVersion !== (int)$defaults['schema_version']) {
            $payload[$operationKey]['schema_version'] = (int)$defaults['schema_version'];
        }

        if (!array_key_exists('entrypoint', $payload[$operationKey])) {
            $payload[$operationKey]['entrypoint'] = (!$hasExplicitEntrypoint && $operationKey === $mainOperationKey)
                ? 1
                : $defaults['entrypoint'];
        }
    }

    return connectors_legacy_operations_to_v3_payload($payload);
}


function connectors_build_legacy_compat_operations_view(array $operationsPayload, array $connector = []): array
{
    if (!connectors_is_v3_operations_payload($operationsPayload)) {
        return $operationsPayload;
    }

    $operations = connectors_default_operations($connector);
    $runtimeOperations = connectors_v3_payload_to_runtime_operations($operationsPayload);

    foreach (['report', 'submission', 'track_and_label_info'] as $operationKey) {
        if (!isset($runtimeOperations[$operationKey])) {
            continue;
        }

        $op = $runtimeOperations[$operationKey];
        $cfg = isset($op['config']) && is_array($op['config']) ? $op['config'] : [];
        $operations[$operationKey]['schema_version'] = 3;
        $operations[$operationKey]['operation_id'] = $op['operation_id'];
        $operations[$operationKey]['display_name'] = trim((string)($op['display_name'] ?? $operations[$operationKey]['display_name'] ?? $operationKey));
        $operations[$operationKey]['module'] = connectors_normalize_operation_module($op['module'] ?? 'generic');
        $operations[$operationKey]['action'] = trim((string)($op['action'] ?? ''));
        $operations[$operationKey]['kind'] = connectors_normalize_operation_kind($op['kind'] ?? '', (string)($operations[$operationKey]['module'] ?? 'generic'));
        $operations[$operationKey]['run_after'] = connectors_normalize_dependency_links($op['run_after'] ?? []);
        $operations[$operationKey]['run_with'] = connectors_normalize_dependency_links($op['run_with'] ?? []);
        $operations[$operationKey]['run_finally'] = connectors_normalize_dependency_links($op['run_finally'] ?? []);
        $operations[$operationKey]['entrypoint'] = !empty($op['entrypoint']) ? 1 : 0;
        $operations[$operationKey]['on_dependency_fail'] = connectors_normalize_dependency_policy($op['on_dependency_fail'] ?? 'stop');
        $operations[$operationKey]['enabled'] = !empty($op['enabled']) ? 1 : 0;

        if ($operationKey === 'report') {
            $operations[$operationKey]['page_url'] = trim((string)($cfg['page_url'] ?? ''));
            $operations[$operationKey]['file_extension'] = trim((string)($cfg['file_extension'] ?? 'xlsx')) ?: 'xlsx';
            $operations[$operationKey]['download_mode'] = in_array(($cfg['download_mode'] ?? 'browser'), ['browser', 'curl'], true)
                ? (string)$cfg['download_mode']
                : 'browser';
            $operations[$operationKey]['log_steps'] = !empty($cfg['log_steps']) ? 1 : 0;
            if (isset($cfg['steps']) && is_array($cfg['steps'])) {
                $operations[$operationKey]['steps_json'] = json_encode($cfg['steps'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
            if (isset($cfg['curl_config']) && is_array($cfg['curl_config'])) {
                $operations[$operationKey]['curl_config_json'] = json_encode($cfg['curl_config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
            $operations[$operationKey]['target_table'] = trim((string)($cfg['target_table'] ?? $operations[$operationKey]['target_table']));
            if (isset($cfg['field_mapping']) && is_array($cfg['field_mapping'])) {
                $operations[$operationKey]['field_mapping_json'] = json_encode($cfg['field_mapping'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
        }

        if ($operationKey === 'submission') {
            $operations[$operationKey]['page_url'] = trim((string)($cfg['page_url'] ?? ''));
            $operations[$operationKey]['log_steps'] = !empty($cfg['log_steps']) ? 1 : 0;
            if (isset($cfg['steps']) && is_array($cfg['steps'])) {
                $operations[$operationKey]['steps_json'] = json_encode($cfg['steps'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
            if (isset($cfg['request_config']) && is_array($cfg['request_config'])) {
                $operations[$operationKey]['request_config_json'] = json_encode($cfg['request_config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
            }
            $operations[$operationKey]['success_selector'] = trim((string)($cfg['success_selector'] ?? ''));
            $operations[$operationKey]['success_text'] = trim((string)($cfg['success_text'] ?? ''));
            $operations[$operationKey]['error_selector'] = trim((string)($cfg['error_selector'] ?? ''));
        }
    }

    return $operations;
}

function connectors_try_migrate_operations_json(array $connector): array
{
    $raw = trim((string)($connector['operations_json'] ?? ''));
    if ($raw === '') {
        return $connector;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $connector;
    }

    $migrated = connectors_migrate_operations_payload($decoded);
    if ($migrated === $decoded) {
        return $connector;
    }

    $encoded = json_encode($migrated, JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return $connector;
    }

    $connector['operations_json'] = $encoded;
    return $connector;
}



function connectors_validate_iso_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD');
    }

    return $value;
}

function connectors_normalize_report_table_name(string $table): string
{
    $table = strtolower(trim($table));
    $table = preg_replace('/[^a-z0-9_]+/', '_', $table) ?? '';
    $table = trim($table, '_');

    if ($table === '') {
        throw new InvalidArgumentException('Не указана целевая таблица отчета');
    }

    if (strpos($table, 'connector_report_') !== 0) {
        $table = 'connector_report_' . $table;
    }

    return $table;
}

function connectors_ensure_report_table(mysqli $dbcnx, string $tableName): void
{
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = "CREATE TABLE IF NOT EXISTS {$safeTable} (
"
        . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
        . " connector_id INT UNSIGNED NOT NULL,
"
        . " period_from DATE NULL,
"
        . " period_to DATE NULL,
"
        . " payload_json LONGTEXT NULL,
"
        . " source_file VARCHAR(255) NOT NULL DEFAULT '',
"
        . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
        . " PRIMARY KEY (id),
"
        . " KEY idx_connector_period (connector_id, period_from, period_to)
"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$dbcnx->query($sql)) {
        throw new RuntimeException('DB error (create report table): ' . $dbcnx->error);
    }
}


function connectors_apply_vars($value, array $vars)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = connectors_apply_vars($v, $vars);
        }
        return $result;
    }

    if (!is_string($value)) {
        return $value;
    }

    return preg_replace_callback('/\$\{([a-zA-Z0-9_]+)\}/', static function ($m) use ($vars) {
        $key = $m[1] ?? '';
        return array_key_exists($key, $vars) ? (string)$vars[$key] : '';
    }, $value) ?? $value;
}


function connectors_extract_browser_login_steps(array $connector): array
{
    $scenarioRaw = trim((string)($connector['scenario_json'] ?? ''));
    if ($scenarioRaw === '') {
        return [];
    }

    $scenario = json_decode($scenarioRaw, true);
    if (!is_array($scenario)) {
        return [];
    }

    if (isset($scenario['browser_login_steps']) && is_array($scenario['browser_login_steps'])) {
        return $scenario['browser_login_steps'];
    }

    if (isset($scenario['login']['browser_steps']) && is_array($scenario['login']['browser_steps'])) {
        return $scenario['login']['browser_steps'];
    }

    return [];
}


function connectors_parse_set_cookie_header(array $headers): string
{
    $cookies = [];
    foreach ($headers as $headerLine) {
        if (stripos((string)$headerLine, 'Set-Cookie:') !== 0) {
            continue;
        }
        $raw = trim(substr((string)$headerLine, strlen('Set-Cookie:')));
        if ($raw === '') {
            continue;
        }
        $firstPart = trim(explode(';', $raw, 2)[0] ?? '');
        if ($firstPart === '' || strpos($firstPart, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $firstPart, 2));
        if ($k === '') {
            continue;
        }
        $cookies[$k] = $v;
    }

    $pairs = [];
    foreach ($cookies as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return implode('; ', $pairs);
}

function connectors_curl_request(array $cfg, array $vars, bool $sslIgnore): array
{
    $url = trim((string)connectors_apply_vars((string)($cfg['url'] ?? ''), $vars));
    if ($url === '') {
        throw new InvalidArgumentException('В cURL-запросе отсутствует url');
    }

    $method = strtoupper(trim((string)($cfg['method'] ?? 'GET')));
    if ($method === '') {
        $method = 'GET';
    }

    $headers = [];
    if (isset($cfg['headers']) && is_array($cfg['headers'])) {
        foreach ($cfg['headers'] as $k => $v) {
            $headers[] = $k . ': ' . (string)connectors_apply_vars((string)$v, $vars);
        }
    }

    $bodyData = [];
    $rawBody = [];
    if (isset($cfg['body']) && is_array($cfg['body'])) {
        $rawBody = $cfg['body'];
    } elseif (isset($cfg['fields']) && is_array($cfg['fields'])) {
        $rawBody = $cfg['fields'];
    }
    foreach ($rawBody as $k => $v) {
        $bodyData[$k] = connectors_apply_vars($v, $vars);
    }

    $responseHeaders = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, $line) use (&$responseHeaders) {
        $responseHeaders[] = trim((string)$line);
        return strlen($line);
    });
    if ($sslIgnore) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($bodyData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyData));
        }
    }

    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Ошибка cURL: ' . $curlErr);
    }

    return [
        'http_code' => $httpCode,
        'body' => (string)$body,
        'headers' => $responseHeaders,
        'cookies' => connectors_parse_set_cookie_header($responseHeaders),
    ];
}



function connectors_clear_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            connectors_clear_directory($path);
            @rmdir($path);
            continue;
        }

        @unlink($path);
    }
}

function connectors_is_node_runtime_available(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (!function_exists('exec') || !is_callable('exec')) {
        $cached = false;
        return $cached;
    }

    $disabledFunctions = (string)ini_get('disable_functions');
    if ($disabledFunctions !== '') {
        $disabledList = array_map('trim', explode(',', $disabledFunctions));
        if (in_array('exec', $disabledList, true)) {
            $cached = false;
            return $cached;
        }
    }

    $output = [];
    $exitCode = 1;
    @exec('node --version 2>/dev/null', $output, $exitCode);
    $cached = ($exitCode === 0);
    return $cached;
}

function connectors_generate_run_id(int $connectorId): string
{
    $random = bin2hex(random_bytes(4));
    return 'run-' . date('YmdHis') . '-c' . max(0, $connectorId) . '-' . $random;
}


function connectors_ensure_run_trace_tables(mysqli $dbcnx): void
{
    $runsSql = "
        CREATE TABLE IF NOT EXISTS connector_operation_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            connector_id INT UNSIGNED NOT NULL,
            run_id VARCHAR(96) NOT NULL,
            test_operation VARCHAR(64) NOT NULL DEFAULT 'report',
            status VARCHAR(32) NOT NULL DEFAULT 'error',
            message TEXT NULL,
            target_table VARCHAR(128) NOT NULL DEFAULT '',
            started_at DATETIME NOT NULL,
            finished_at DATETIME NOT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT NOT NULL DEFAULT 0,
            trace_log_json LONGTEXT NULL,
            step_log_json LONGTEXT NULL,
            execution_plan_json LONGTEXT NULL,
            chain_status_json LONGTEXT NULL,
            artifacts_dir TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_run_id (run_id),
            KEY idx_connector_created (connector_id, created_at),
            KEY idx_connector_status_created (connector_id, status, created_at),
            KEY idx_test_operation_created (test_operation, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($runsSql)) {
        error_log('connector_operation_runs schema create error: ' . $dbcnx->error);
    }

    $eventsSql = "
        CREATE TABLE IF NOT EXISTS connector_operation_run_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id VARCHAR(96) NOT NULL,
            connector_id INT UNSIGNED NOT NULL,
            event_index INT UNSIGNED NOT NULL DEFAULT 0,
            event_source VARCHAR(16) NOT NULL DEFAULT 'trace',
            operation_id VARCHAR(96) NOT NULL DEFAULT '',
            stage VARCHAR(96) NOT NULL DEFAULT '',
            step_name VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT '',
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            meta_json LONGTEXT NULL,
            event_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_run_event_index (run_id, event_index),
            KEY idx_connector_event_time (connector_id, event_time),
            KEY idx_operation_event_time (operation_id, event_time),
            KEY idx_status_event_time (status, event_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($eventsSql)) {
        error_log('connector_operation_run_events schema create error: ' . $dbcnx->error);
    }
}

function connectors_persist_run_trace(mysqli $dbcnx, array $payload): bool
{
    connectors_ensure_run_trace_tables($dbcnx);

    $connectorId = (int)($payload['connector_id'] ?? 0);
    $runId = trim((string)($payload['run_id'] ?? ''));
    if ($connectorId <= 0 || $runId === '') {
        return false;
    }

    $testOperation = trim((string)($payload['test_operation'] ?? 'report')) ?: 'report';
    $status = trim((string)($payload['status'] ?? 'error')) ?: 'error';
    $message = trim((string)($payload['message'] ?? ''));
    $targetTable = trim((string)($payload['target_table'] ?? ''));
    $createdBy = (int)($payload['created_by'] ?? 0);
    $startedAt = trim((string)($payload['started_at'] ?? date('Y-m-d H:i:s')));
    $finishedAt = trim((string)($payload['finished_at'] ?? date('Y-m-d H:i:s')));
    $durationMs = max(0, (int)($payload['duration_ms'] ?? 0));
    $artifactsDir = trim((string)($payload['artifacts_dir'] ?? ''));

    $traceLog = isset($payload['trace_log']) && is_array($payload['trace_log']) ? $payload['trace_log'] : [];
    $stepLog = isset($payload['step_log']) && is_array($payload['step_log']) ? $payload['step_log'] : [];
    $executionPlan = isset($payload['execution_plan']) && is_array($payload['execution_plan']) ? $payload['execution_plan'] : [];
    $chainStatus = isset($payload['chain_status']) && is_array($payload['chain_status']) ? $payload['chain_status'] : [];

    $traceLogJson = json_encode($traceLog, JSON_UNESCAPED_UNICODE);
    $stepLogJson = json_encode($stepLog, JSON_UNESCAPED_UNICODE);
    $executionPlanJson = json_encode($executionPlan, JSON_UNESCAPED_UNICODE);
    $chainStatusJson = json_encode($chainStatus, JSON_UNESCAPED_UNICODE);

    if ($traceLogJson === false || $stepLogJson === false || $executionPlanJson === false || $chainStatusJson === false) {
        return false;
    }

    $dbcnx->begin_transaction();
    try {
        $runSql = 'INSERT INTO connector_operation_runs (connector_id, run_id, test_operation, status, message, target_table, started_at, finished_at, duration_ms, created_by, trace_log_json, step_log_json, execution_plan_json, chain_status_json, artifacts_dir) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE connector_id = VALUES(connector_id), test_operation = VALUES(test_operation), status = VALUES(status), message = VALUES(message), target_table = VALUES(target_table), started_at = VALUES(started_at), finished_at = VALUES(finished_at), duration_ms = VALUES(duration_ms), created_by = VALUES(created_by), trace_log_json = VALUES(trace_log_json), step_log_json = VALUES(step_log_json), execution_plan_json = VALUES(execution_plan_json), chain_status_json = VALUES(chain_status_json), artifacts_dir = VALUES(artifacts_dir)';
        $runStmt = $dbcnx->prepare($runSql);
        if (!$runStmt) {
            throw new RuntimeException('prepare connector_operation_runs failed');
        }

        $runStmt->bind_param(
            'isssssssissssss',
            $connectorId,
            $runId,
            $testOperation,
            $status,
            $message,
            $targetTable,
            $startedAt,
            $finishedAt,
            $durationMs,
            $createdBy,
            $traceLogJson,
            $stepLogJson,
            $executionPlanJson,
            $chainStatusJson,
            $artifactsDir
        );
        if (!$runStmt->execute()) {
            $error = $runStmt->error;
            $runStmt->close();
            throw new RuntimeException('execute connector_operation_runs failed: ' . $error);
        }
        $runStmt->close();

        $deleteStmt = $dbcnx->prepare('DELETE FROM connector_operation_run_events WHERE run_id = ?');
        if (!$deleteStmt) {
            throw new RuntimeException('prepare connector_operation_run_events delete failed');
        }
        $deleteStmt->bind_param('s', $runId);
        $deleteStmt->execute();
        $deleteStmt->close();

        $eventSql = 'INSERT INTO connector_operation_run_events (run_id, connector_id, event_index, event_source, operation_id, stage, step_name, status, duration_ms, message, meta_json, event_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $eventStmt = $dbcnx->prepare($eventSql);
        if (!$eventStmt) {
            throw new RuntimeException('prepare connector_operation_run_events insert failed');
        }

        $eventIndex = 0;
        $appendEvent = static function (array $event, string $source) use (&$eventIndex, $eventStmt, $runId, $connectorId): void {
            $eventTime = trim((string)($event['time'] ?? ''));
            if ($eventTime === '') {
                $eventTime = date('Y-m-d H:i:s');
            } else {
                $timestamp = strtotime($eventTime);
                $eventTime = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
            }

            $operationId = trim((string)($event['operation_id'] ?? ''));
            $stage = trim((string)($event['stage'] ?? ($event['step'] ?? '')));
            $stepName = trim((string)($event['step'] ?? ''));
            $status = trim((string)($event['status'] ?? ''));
            $durationMs = max(0, (int)($event['duration_ms'] ?? (($event['meta']['duration_ms'] ?? 0))));
            $message = trim((string)($event['message'] ?? ''));
            $meta = isset($event['meta']) && is_array($event['meta']) ? $event['meta'] : [];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
            if ($metaJson === false) {
                $metaJson = '{}';
            }

            $index = $eventIndex;
            $eventStmt->bind_param('siisssssisss', $runId, $connectorId, $index, $source, $operationId, $stage, $stepName, $status, $durationMs, $message, $metaJson, $eventTime);
            if (!$eventStmt->execute()) {
                throw new RuntimeException('execute connector_operation_run_events failed: ' . $eventStmt->error);
            }
            $eventIndex++;
        };

        foreach ($traceLog as $traceEvent) {
            if (is_array($traceEvent)) {
                $appendEvent($traceEvent, 'trace');
            }
        }

        foreach ($stepLog as $stepEvent) {
            if (is_array($stepEvent)) {
                $appendEvent($stepEvent, 'step');
            }
        }

        $eventStmt->close();
        $dbcnx->commit();
        return true;
    } catch (Throwable $e) {
        $dbcnx->rollback();
        error_log('connectors_persist_run_trace error: ' . $e->getMessage());
        return false;
    }
}


function connectors_append_trace_event(array &$traceLog, string $runId, string $operationId, string $stage, string $status, string $message, array $meta = []): void
{
    $traceLog[] = [
        'time' => date('c'),
        'run_id' => $runId,
        'event_type' => 'trace',
        'operation_id' => $operationId,
        'stage' => $stage,
        'status' => $status,
        'message' => $message,
        'meta' => $meta,
    ];
}


function connectors_append_operation_executed_event(
    array &$traceLog,
    string $runId,
    string $operationId,
    string $stage,
    string $status,
    string $message,
    int $durationMs = 0,
    ?string $startedAt = null,
    ?string $finishedAt = null,
    array $meta = []
): void {
    $resolvedStartedAt = $startedAt ?: date('c');
    $resolvedFinishedAt = $finishedAt ?: date('c');
    $traceLog[] = [
        'time' => $resolvedFinishedAt,
        'run_id' => $runId,
        'event_type' => 'operation_executed',
        'operation_id' => $operationId,
        'stage' => $stage,
        'status' => $status,
        'started_at' => $resolvedStartedAt,
        'finished_at' => $resolvedFinishedAt,
        'duration_ms' => max(0, $durationMs),
        'message' => $message,
        'meta' => $meta,
    ];
}

function connectors_build_chain_status_map(array $executionPlan, string $currentOperationId, bool $isSuccess, array $traceLog = []): array
{
    $stageByOperation = [];
    $operationIds = [];

    foreach ((array)($executionPlan['before'] ?? []) as $operationId) {
        $normalized = trim((string)$operationId);
        if ($normalized === '') {
            continue;
        }
        $operationIds[] = $normalized;
        $stageByOperation[$normalized] = 'before';
    }


    $mainOperationId = trim((string)($executionPlan['main'] ?? ''));
    if ($mainOperationId !== '') {
        $operationIds[] = $mainOperationId;
        $stageByOperation[$mainOperationId] = 'main';
    }

    foreach ((array)($executionPlan['during'] ?? []) as $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $operationId) {

            $normalized = trim((string)$operationId);
            if ($normalized === '') {
                continue;
            }
            $operationIds[] = $normalized;
            $stageByOperation[$normalized] = 'during';
        }
    }

    foreach ((array)($executionPlan['finally'] ?? []) as $operationId) {
        $normalized = trim((string)$operationId);
        if ($normalized === '') {
            continue;
        }
        $operationIds[] = $normalized;
        $stageByOperation[$normalized] = 'finally';
    }

    $operationIds = array_values(array_filter(array_unique($operationIds), static fn(string $v): bool => $v !== ''));
    if (empty($operationIds)) {
        return [];
    }

    $statusByOperation = [];
    foreach ($operationIds as $operationId) {
        $statusByOperation[$operationId] = 'pending';
    }

    $timeline = [];
    $currentEvent = null;
    foreach ($traceLog as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventType = trim((string)($event['event_type'] ?? ''));
        if ($eventType !== 'operation_executed') {
            continue;
        }

        $eventOperationId = trim((string)($event['operation_id'] ?? ''));
        if ($eventOperationId === '' || !isset($statusByOperation[$eventOperationId])) {
            continue;
        }

        $eventStatus = strtolower(trim((string)($event['status'] ?? 'pending')));
        if (!in_array($eventStatus, ['success', 'failed', 'pending'], true)) {
            $eventStatus = 'pending';
        }
        $statusByOperation[$eventOperationId] = $eventStatus;

        $timelineEvent = [
            'event_type' => 'operation_executed',
            'operation_id' => $eventOperationId,
            'stage' => trim((string)($event['stage'] ?? ($stageByOperation[$eventOperationId] ?? 'main'))),
            'status' => $eventStatus,
            'started_at' => (string)($event['started_at'] ?? ''),
            'finished_at' => (string)($event['finished_at'] ?? ($event['time'] ?? '')),
            'duration_ms' => max(0, (int)($event['duration_ms'] ?? 0)),
            'message' => (string)($event['message'] ?? ''),
        ];

        $timeline[] = $timelineEvent;
        $currentEvent = $timelineEvent;
    }

    $statusMap = [];
    foreach ($operationIds as $operationId) {
        $state = $statusByOperation[$operationId] ?? 'pending';

        $statusMap[] = [
            'operation_id' => $operationId,
            'stage' => $stageByOperation[$operationId] ?? 'main',
            'status' => $state,
        ];
    }

    $stages = [
        'before' => ['executed' => 0, 'success' => 0, 'failed' => 0],
        'during' => ['executed' => 0, 'success' => 0, 'failed' => 0],
        'main' => ['executed' => 0, 'success' => 0, 'failed' => 0],
        'finally' => ['executed' => 0, 'success' => 0, 'failed' => 0],
    ];

    foreach ($timeline as $event) {
        $stage = trim((string)($event['stage'] ?? ''));
        if (!isset($stages[$stage])) {
            continue;
        }
        $stages[$stage]['executed']++;
        if ($event['status'] === 'success') {
            $stages[$stage]['success']++;
        }
        if ($event['status'] === 'failed') {
            $stages[$stage]['failed']++;
        }
    }

    return [
        'operations' => $statusMap,
        'stages' => $stages,
        'current_event' => $currentEvent,
        'timeline' => $timeline,
    ];
}


function connectors_download_report_file(array $connector, array $reportCfg, ?string $periodFrom, ?string $periodTo): array
{
    $ext = strtolower(trim((string)($reportCfg['file_extension'] ?? 'xlsx')));
    if ($ext === '') {
        $ext = 'xlsx';
    }

    $today = date('Y-m-d');
    $defaultDateFrom = date('Y-m-d', strtotime('-2 years', strtotime($today)));
    $resolvedDateFrom = $periodFrom ?? $defaultDateFrom;
    $resolvedDateTo = $periodTo ?? $today;

    $vars = [
        'date_from' => $resolvedDateFrom,
        'date_to' => $resolvedDateTo,
        'test_period_from' => $resolvedDateFrom,
        'test_period_to' => $resolvedDateTo,
        'today' => $today,
        'today_minus_2y' => $defaultDateFrom,
        'base_url' => trim((string)($connector['base_url'] ?? '')),
        'login' => trim((string)($connector['auth_username'] ?? '')),
        'password' => trim((string)($connector['auth_password'] ?? '')),
    ];


    $logStepsEnabled = !empty($reportCfg['log_steps']);
    $stepLog = [];
    $appendStepLog = static function (string $step, string $message, array $meta = []) use (&$stepLog, $logStepsEnabled): void {
        if (!$logStepsEnabled) {
            return;
        }

        $stepLog[] = [
            'time' => date('c'),
            'step' => $step,
            'message' => $message,
            'meta' => $meta,
        ];
    };

    if (($reportCfg['download_mode'] ?? 'browser') === 'browser') {
        $reportSteps = isset($reportCfg['steps']) && is_array($reportCfg['steps']) ? $reportCfg['steps'] : [];
        $loginSteps = connectors_extract_browser_login_steps($connector);
        $steps = array_merge($loginSteps, $reportSteps);
        if (empty($steps)) {
            throw new InvalidArgumentException('Для режима browser заполните report_steps_json или browser_login_steps в scenario_json');
        }

        $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
        if (!$scriptPath) {
            throw new RuntimeException('Не найден browser script для теста скачивания');
        }


        $tempDir = __DIR__ . '/../../scripts/_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        connectors_clear_directory($tempDir);

        $payload = [
            'steps' => $steps,
            'vars' => $vars,
            'file_extension' => $ext,
            'ssl_ignore' => !empty($connector['ssl_ignore']),
            'cookies' => (string)($connector['auth_cookies'] ?? ''),
            'auth_token' => (string)($connector['auth_token'] ?? ''),
            'temp_dir' => realpath($tempDir) ?: $tempDir,
        ];

        $cmd = 'node ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string)json_encode($payload, JSON_UNESCAPED_UNICODE));
        $output = shell_exec($cmd . ' 2>&1');
        $decoded = json_decode(trim((string)$output), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Не удалось выполнить browser-тест скачивания: ' . trim((string)$output));
        }
        if (empty($decoded['ok'])) {
           $browserStepLog = isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [];
           $browserArtifactsDir = trim((string)($decoded['artifacts_dir'] ?? ''));
           if (!empty($browserStepLog)) {
              throw new ConnectorStepLogException((string)($decoded['message'] ?? 'Browser test failed'), $browserStepLog, 0, null, $browserArtifactsDir);
              }
            throw new RuntimeException((string)($decoded['message'] ?? 'Browser test failed'));
        }

        $filePath = (string)($decoded['file_path'] ?? '');
        if ($filePath === '' || !is_file($filePath)) {
            throw new RuntimeException('Browser-тест не вернул путь к скачанному файлу');
        }

        return [
            'file_path' => $filePath,
            'file_size' => (int)filesize($filePath),
            'file_extension' => $ext,
            'download_mode' => 'browser',
            'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
            'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
        ];
    }

    $curlCfg = isset($reportCfg['curl_config']) && is_array($reportCfg['curl_config']) ? $reportCfg['curl_config'] : [];
    $url = trim((string)($curlCfg['url'] ?? ''));
    if ($url === '') {
        throw new InvalidArgumentException('Для режима cURL нужен JSON-объект в report_curl_config_json с полем url (например {"url":"https://.../export","method":"POST"}).');
    }

    $url = (string)connectors_apply_vars($url, $vars);
    $method = strtoupper(trim((string)($curlCfg['method'] ?? 'GET')));
    if ($method === '') {
        $method = 'GET';
    }

    $headers = [];
    $hasAuthorizationHeader = false;
    $hasCookieHeader = false;
    if (isset($curlCfg['headers']) && is_array($curlCfg['headers'])) {
        foreach ($curlCfg['headers'] as $k => $v) {
            $headerName = trim((string)$k);
            if (strcasecmp($headerName, 'Authorization') === 0) {
                $hasAuthorizationHeader = true;
            }
            if (strcasecmp($headerName, 'Cookie') === 0) {
                $hasCookieHeader = true;
            }
            $headers[] = $headerName . ': ' . (string)connectors_apply_vars((string)$v, $vars);
        }
    }

    $bodyData = [];
    if (isset($curlCfg['body']) && is_array($curlCfg['body'])) {
        foreach ($curlCfg['body'] as $k => $v) {
            $bodyData[$k] = connectors_apply_vars($v, $vars);
        }
    }


    if (!$hasAuthorizationHeader && !empty($connector['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . trim((string)$connector['auth_token']);
    }

    $cookieParts = [];
    if (!empty($connector['auth_cookies'])) {
        $cookieParts[] = trim((string)$connector['auth_cookies']);
    }

    if (isset($curlCfg['login']) && is_array($curlCfg['login'])) {
        $appendStepLog('login', 'Выполняем login-запрос через cURL', [
            'url' => (string)($curlCfg['login']['url'] ?? ''),
            'method' => strtoupper((string)($curlCfg['login']['method'] ?? 'GET')),
        ]);
        $loginResponse = connectors_curl_request($curlCfg['login'], $vars, !empty($connector['ssl_ignore']));
        $loginHttp = (int)($loginResponse['http_code'] ?? 0);
        $appendStepLog('login', 'Login-запрос выполнен', ['http_code' => $loginHttp]);
        if ($loginHttp >= 400) {
            throw new ConnectorStepLogException('Ошибка логина через cURL: HTTP ' . $loginHttp, $stepLog);
        }

        $successCfg = isset($curlCfg['login']['success']) && is_array($curlCfg['login']['success'])
            ? $curlCfg['login']['success']
            : [];
        $successSelector = trim((string)($successCfg['selector'] ?? ''));
        if ($successSelector !== '') {
            $loginBody = (string)($loginResponse['body'] ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('*', $successSelector)), static fn($part) => $part !== ''));
            $found = true;
            foreach ($parts as $part) {
                if (stripos($loginBody, $part) === false) {
                    $found = false;
                    break;
                }
            }

            $appendStepLog('login', 'Проверка login.success.selector', [
                'selector' => $successSelector,
                'matched' => $found,
            ]);

            if (!$found) {
                throw new ConnectorStepLogException('Логин через cURL не прошёл проверку success.selector', $stepLog);
            }
        }
        $loginCookies = trim((string)($loginResponse['cookies'] ?? ''));
        if ($loginCookies !== '') {
            $cookieParts[] = $loginCookies;
        }
    }

    if (!$hasCookieHeader && !empty($cookieParts)) {
        $headers[] = 'Cookie: ' . implode('; ', $cookieParts);
    }

    $appendStepLog('download_prepare', 'Подготовлен запрос на скачивание файла', [
        'url' => $url,
        'method' => $method,
        'has_cookie_header' => $hasCookieHeader || !empty($cookieParts),
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'connector-report-');
    if ($tmpFile === false) {
        throw new RuntimeException('Не удалось создать временный файл');
    }
    $filePath = $tmpFile . '.' . $ext;
    @rename($tmpFile, $filePath);

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Не удалось открыть временный файл для записи');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_FILE, $fh);
    if (!empty($connector['ssl_ignore'])) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($bodyData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyData));
        }
    }

    $ok = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if (!$ok || $httpCode >= 400) {
        @unlink($filePath);
        $appendStepLog('download', 'Скачивание завершилось ошибкой', ['http_code' => $httpCode]);
        throw new ConnectorStepLogException('Ошибка скачивания через cURL: HTTP ' . $httpCode . ' ' . $curlErr, $stepLog);
    }

    $size = (int)filesize($filePath);
    if ($size <= 0) {
        @unlink($filePath);
        $appendStepLog('download', 'Скачивание завершилось пустым файлом', ['http_code' => $httpCode]);
        throw new ConnectorStepLogException('Скачанный файл пустой', $stepLog);
    }

    $appendStepLog('download', 'Файл успешно скачан', [
        'http_code' => $httpCode,
        'file_size' => $size,
        'file_path' => $filePath,
    ]);

    return [
        'file_path' => $filePath,
        'file_size' => $size,
        'file_extension' => $ext,
        'download_mode' => 'curl',
        'step_log' => $logStepsEnabled ? $stepLog : [],
    ];
}

function connectors_import_csv_into_report_table(mysqli $dbcnx, string $tableName, string $filePath, int $connectorId, ?string $periodFrom, ?string $periodTo, array $fieldMapping): int
{
    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Не удалось открыть CSV для импорта');
    }

    $header = fgetcsv($fh);
    if (!is_array($header) || empty($header)) {
        fclose($fh);
        throw new RuntimeException('CSV не содержит заголовок');
    }

    $headerMap = [];
    foreach ($header as $idx => $name) {
        $headerMap[trim((string)$name)] = (int)$idx;
    }

    if (empty($fieldMapping)) {
        fclose($fh);
        throw new InvalidArgumentException('Для CSV-импорта заполните "Маппинг полей"');
    }

    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = 'INSERT INTO ' . $safeTable . ' (connector_id, period_from, period_to, payload_json, source_file) VALUES (?, ?, ?, ?, ?)';
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        fclose($fh);
        throw new RuntimeException('DB error (prepare import): ' . $dbcnx->error);
    }

    $count = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $payload = [];
        foreach ($fieldMapping as $targetField => $csvColumnName) {
            $csvColumnName = trim((string)$csvColumnName);
            if ($csvColumnName === '' || !array_key_exists($csvColumnName, $headerMap)) {
                $payload[$targetField] = null;
                continue;
            }
            $payload[$targetField] = $row[$headerMap[$csvColumnName]] ?? null;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            continue;
        }

        $sourceFile = basename($filePath);
        $stmt->bind_param('issss', $connectorId, $periodFrom, $periodTo, $payloadJson, $sourceFile);
        if ($stmt->execute()) {
            $count++;
        }
    }

    $stmt->close();
    fclose($fh);
    return $count;
}


function connectors_read_xlsx_rows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Расширение ZipArchive недоступно для XLSX-импорта');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Не удалось открыть XLSX для импорта');
    }

    try {
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && $sharedXml !== '') {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx !== false && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string)$run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('XLSX не содержит sheet1.xml');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false || !isset($sheet->sheetData->row)) {
            throw new RuntimeException('XLSX не содержит данных в первом листе');
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            $nextCol = 0;

            foreach ($row->c as $cell) {
                $ref = (string)($cell['r'] ?? '');
                if ($ref !== '' && preg_match('/^([A-Z]+)\d+$/i', $ref, $m)) {
                    $letters = strtoupper($m[1]);
                    $idx = 0;
                    for ($i = 0; $i < strlen($letters); $i++) {
                        $idx = $idx * 26 + (ord($letters[$i]) - 64);
                    }
                    $cellCol = max(0, $idx - 1);
                    while ($nextCol < $cellCol) {
                        $rowData[] = '';
                        $nextCol++;
                    }
                }

                $type = (string)($cell['t'] ?? '');
                $value = '';
                if ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string)$cell->is->t : '';
                } elseif ($type === 's') {
                    $sharedIndex = (int)($cell->v ?? -1);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }

                $rowData[] = $value;
                $nextCol++;
            }

            $rows[] = $rowData;
        }

        if (empty($rows)) {
            throw new RuntimeException('XLSX не содержит строк');
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function connectors_import_xlsx_into_report_table(mysqli $dbcnx, string $tableName, string $filePath, int $connectorId, ?string $periodFrom, ?string $periodTo, array $fieldMapping): int
{
    $rows = connectors_read_xlsx_rows($filePath);
    $header = array_shift($rows);
    if (!is_array($header) || empty($header)) {
        throw new RuntimeException('XLSX не содержит заголовок');
    }

    $headerMap = [];
    foreach ($header as $idx => $name) {
        $headerMap[trim((string)$name)] = (int)$idx;
    }

    if (empty($fieldMapping)) {
        throw new InvalidArgumentException('Для XLSX-импорта заполните "Маппинг полей"');
    }

    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = 'INSERT INTO ' . $safeTable . ' (connector_id, period_from, period_to, payload_json, source_file) VALUES (?, ?, ?, ?, ?)';
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB error (prepare import): ' . $dbcnx->error);
    }

    $count = 0;
    foreach ($rows as $row) {
        $payload = [];
        foreach ($fieldMapping as $targetField => $xlsxColumnName) {
            $xlsxColumnName = trim((string)$xlsxColumnName);
            if ($xlsxColumnName === '' || !array_key_exists($xlsxColumnName, $headerMap)) {
                $payload[$targetField] = null;
                continue;
            }
            $payload[$targetField] = $row[$headerMap[$xlsxColumnName]] ?? null;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            continue;
        }

        $sourceFile = basename($filePath);
        $stmt->bind_param('issss', $connectorId, $periodFrom, $periodTo, $payloadJson, $sourceFile);
        if ($stmt->execute()) {
            $count++;
        }
    }

    $stmt->close();
    return $count;
}






function connectors_build_submission_test_vars(array $connector): array
{
    $vars = [
        'base_url' => trim((string)($connector['base_url'] ?? '')),
        'login' => trim((string)($connector['auth_username'] ?? '')),
        'password' => trim((string)($connector['auth_password'] ?? '')),
    ];

    $scenarioRaw = trim((string)($connector['scenario_json'] ?? ''));
    if ($scenarioRaw !== '') {
        $decoded = json_decode($scenarioRaw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                if (!is_string($k) || $k === '') {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $vars[$k] = (string)($v ?? '');
                }
            }
        }
    }

    return $vars;
}

function connectors_run_submission_test(array $connector, array $submissionCfg): array
{
    $steps = isset($submissionCfg['steps']) && is_array($submissionCfg['steps']) ? $submissionCfg['steps'] : [];
    if (empty($steps)) {
        throw new InvalidArgumentException('Для теста операции #2 заполните "Шаги формы операции #2".');
    }

    $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
    if (!$scriptPath) {
        throw new RuntimeException('Не найден browser script для теста операции #2');
    }

    $tempDir = __DIR__ . '/../../scripts/_tmp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0775, true);
    }
    connectors_clear_directory($tempDir);

    $vars = connectors_build_submission_test_vars($connector);
    $payload = [
        'steps' => $steps,
        'vars' => $vars,
        'ssl_ignore' => !empty($connector['ssl_ignore']),
        'cookies' => (string)($connector['auth_cookies'] ?? ''),
        'auth_token' => (string)($connector['auth_token'] ?? ''),
        'temp_dir' => realpath($tempDir) ?: $tempDir,
        'expect_download' => false,
        'error_selector' => trim((string)($submissionCfg['error_selector'] ?? '')),
        'error_wait_ms' => 1800,
    ];

    $cmd = 'node ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string)json_encode($payload, JSON_UNESCAPED_UNICODE));
    $output = shell_exec($cmd . ' 2>&1');
    $decoded = json_decode(trim((string)$output), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Не удалось выполнить browser-тест операции #2: ' . trim((string)$output));
    }
    if (empty($decoded['ok'])) {
        $browserStepLog = isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [];
        $browserArtifactsDir = trim((string)($decoded['artifacts_dir'] ?? ''));
        if (!empty($browserStepLog)) {
            throw new ConnectorStepLogException((string)($decoded['message'] ?? 'Browser test failed'), $browserStepLog, 0, null, $browserArtifactsDir);
        }
        throw new RuntimeException((string)($decoded['message'] ?? 'Browser test failed'));
    }

    $resolvedSuccessSelector = trim((string)connectors_apply_vars((string)($submissionCfg['success_selector'] ?? ''), $vars));
    $resolvedSuccessText = trim((string)connectors_apply_vars((string)($submissionCfg['success_text'] ?? ''), $vars));
    $resolvedErrorSelector = trim((string)connectors_apply_vars((string)($submissionCfg['error_selector'] ?? ''), $vars));
    $tracking = trim((string)($vars['tracking_number'] ?? ''));

    return [
        'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
        'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
        'resolved_success_selector' => $resolvedSuccessSelector,
        'resolved_success_text' => $resolvedSuccessText,
        'resolved_error_selector' => $resolvedErrorSelector,
        'captured_error_text' => trim((string)($decoded['captured_error_text'] ?? '')),
        'tracking_number' => $tracking,
        'message' => trim((string)($decoded['message'] ?? '')),
        'node_payload' => $payload,
    ];
}



function connectors_core_api_action_handler_registry(): array
{
    static $registry = null;
    if (is_array($registry)) {
        return $registry;
    }

    $registry = [];
    $coreApiPath = __DIR__ . '/../../core_api.php';
    if (!is_file($coreApiPath)) {
        return $registry;
    }

    $contents = (string)file_get_contents($coreApiPath);
    if ($contents === '') {
        return $registry;
    }

    if (preg_match_all('/[\'"]([a-zA-Z0-9_.-]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $action = trim((string)($match[1] ?? ''));
            $handler = trim((string)($match[2] ?? ''));
            if ($action === '' || $handler === '' || strpos($handler, 'api/') !== 0) {
                continue;
            }
            $registry[$action] = $handler;
        }
    }

    return $registry;
}

function connectors_execute_api_call_operation(array $operation, int $connectorId): array
{
    $action = trim((string)($operation['action'] ?? ''));
    if ($action === '') {
        throw new InvalidArgumentException('Для kind=api_call поле action обязательно');
    }

    $routes = connectors_core_api_action_handler_registry();
    $handlerPath = trim((string)($routes[$action] ?? ''));
    if ($handlerPath === '') {
        throw new InvalidArgumentException('Не найден handler для action "' . $action . '"');
    }

    $fullHandlerPath = __DIR__ . '/../../' . ltrim($handlerPath, '/');
    if (!is_file($fullHandlerPath)) {
        throw new RuntimeException('Файл handler не найден: ' . $handlerPath);
    }

    if (realpath($fullHandlerPath) === realpath(__FILE__)) {
        throw new RuntimeException('Рекурсивный вызов action "' . $action . '" запрещен в manual test dispatcher');
    }

    $postBackup = $_POST;
    $getBackup = $_GET;
    $responseBackupSet = array_key_exists('response', $GLOBALS);
    $responseBackup = $responseBackupSet ? $GLOBALS['response'] : null;

    $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];
    $_POST = [
        'action' => $action,
        'connector_id' => $connectorId,
        'operation_id' => (string)($operation['operation_id'] ?? ''),
        'operation_config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
    ];
    $_GET = [];
    $response = null;

    try {
        require $fullHandlerPath;
    } finally {
        $_POST = $postBackup;
        $_GET = $getBackup;
    }

    if (!is_array($response)) {
        if ($responseBackupSet) {
            $GLOBALS['response'] = $responseBackup;
        } else {
            unset($GLOBALS['response']);
        }
        throw new RuntimeException('API handler не вернул корректный response для action "' . $action . '"');
    }

    if ($responseBackupSet) {
        $GLOBALS['response'] = $responseBackup;
    } else {
        unset($GLOBALS['response']);
    }

    if (($response['status'] ?? '') !== 'ok') {
        throw new RuntimeException((string)($response['message'] ?? ('api_call failed: ' . $action)));
    }

    return [
        'message' => trim((string)($response['message'] ?? ('api_call выполнен: ' . $action))),
        'payload' => $response,
    ];
}



function connectors_execute_browser_steps_operation(array $connector, array $operation, ?string $periodFrom, ?string $periodTo): array
{
    $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];
    $steps = isset($config['steps']) && is_array($config['steps']) ? $config['steps'] : [];
    $prependLoginSteps = !array_key_exists('prepend_login_steps', $config) || !empty($config['prepend_login_steps']);
    if ($prependLoginSteps) {
        $steps = array_merge(connectors_extract_browser_login_steps($connector), $steps);
    }

    if (empty($steps)) {
        throw new InvalidArgumentException('Для kind=browser_steps нужно заполнить operation.config.steps (или browser_login_steps в scenario_json).');
    }

    $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
    if (!$scriptPath) {
        throw new RuntimeException('Не найден browser script для kind=browser_steps');
    }

    $tempDir = __DIR__ . '/../../scripts/_tmp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0775, true);
    }
    connectors_clear_directory($tempDir);

    $today = date('Y-m-d');
    $defaultDateFrom = date('Y-m-d', strtotime('-2 years', strtotime($today)));
    $resolvedDateFrom = $periodFrom ?? $defaultDateFrom;
    $resolvedDateTo = $periodTo ?? $today;
    $vars = connectors_build_submission_test_vars($connector);
    $vars['date_from'] = $resolvedDateFrom;
    $vars['date_to'] = $resolvedDateTo;
    $vars['test_period_from'] = $resolvedDateFrom;
    $vars['test_period_to'] = $resolvedDateTo;
    $vars['today'] = $today;
    $vars['today_minus_2y'] = $defaultDateFrom;

    $expectDownload = !empty($config['expect_download']);
    $payload = [
        'steps' => $steps,
        'vars' => $vars,
        'file_extension' => strtolower(trim((string)($config['file_extension'] ?? 'xlsx'))) ?: 'xlsx',
        'ssl_ignore' => !empty($connector['ssl_ignore']),
        'cookies' => (string)($connector['auth_cookies'] ?? ''),
        'auth_token' => (string)($connector['auth_token'] ?? ''),
        'temp_dir' => realpath($tempDir) ?: $tempDir,
        'expect_download' => $expectDownload,
        'error_selector' => trim((string)($config['error_selector'] ?? '')),
        'error_wait_ms' => max(0, (int)($config['error_wait_ms'] ?? 1800)),
    ];

    $cmd = 'node ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string)json_encode($payload, JSON_UNESCAPED_UNICODE));
    $output = shell_exec($cmd . ' 2>&1');
    $decoded = json_decode(trim((string)$output), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Не удалось выполнить kind=browser_steps: ' . trim((string)$output));
    }
    if (empty($decoded['ok'])) {
        $browserStepLog = isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [];
        $browserArtifactsDir = trim((string)($decoded['artifacts_dir'] ?? ''));
        if (!empty($browserStepLog)) {
            throw new ConnectorStepLogException((string)($decoded['message'] ?? 'Browser steps failed'), $browserStepLog, 0, null, $browserArtifactsDir);
        }
        throw new RuntimeException((string)($decoded['message'] ?? 'Browser steps failed'));
    }

    $result = [
        'message' => trim((string)($decoded['message'] ?? 'Операция browser_steps выполнена')),
        'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
        'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
    ];

    if ($expectDownload) {
        $filePath = (string)($decoded['file_path'] ?? '');
        if ($filePath === '' || !is_file($filePath)) {
            throw new RuntimeException('kind=browser_steps (expect_download=1) не вернул путь к скачанному файлу');
        }
        $result['download'] = [
            'file_path' => $filePath,
            'file_size' => (int)filesize($filePath),
            'file_extension' => strtolower(trim((string)($payload['file_extension'] ?? 'xlsx'))) ?: 'xlsx',
            'download_mode' => 'browser',
            'step_log' => $result['step_log'],
            'artifacts_dir' => $result['artifacts_dir'],
        ];
    }

    return $result;
}

function connectors_execute_script_operation(array $operation): array
{
    $config = isset($operation['config']) && is_array($operation['config']) ? $operation['config'] : [];
    $scriptPathRaw = trim((string)($config['script_path'] ?? ''));
    if ($scriptPathRaw === '') {
        throw new InvalidArgumentException('Для kind=script укажите operation.config.script_path');
    }

    $rootPath = realpath(__DIR__ . '/../../');
    $scriptPath = realpath($scriptPathRaw);
    if ($scriptPath === false && $rootPath !== false) {
        $scriptPath = realpath($rootPath . '/' . ltrim($scriptPathRaw, '/'));
    }
    if ($scriptPath === false || !is_file($scriptPath)) {
        throw new RuntimeException('kind=script: файл не найден: ' . $scriptPathRaw);
    }

    if ($rootPath !== false && strpos($scriptPath, $rootPath) !== 0) {
        throw new RuntimeException('kind=script: путь должен быть внутри репозитория');
    }

    $interpreter = strtolower(trim((string)($config['interpreter'] ?? '')));
    if ($interpreter === '') {
        $ext = strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION));
        $interpreter = $ext === 'js' ? 'node' : ($ext === 'php' ? 'php' : 'bash');
    }
    if (!in_array($interpreter, ['bash', 'sh', 'node', 'php', 'python3'], true)) {
        throw new InvalidArgumentException('kind=script: недопустимый interpreter (bash|sh|node|php|python3)');
    }

    $args = [];
    if (isset($config['args']) && is_array($config['args'])) {
        foreach ($config['args'] as $arg) {
            $args[] = (string)$arg;
        }
    }

    $timeoutSec = max(1, (int)($config['timeout_sec'] ?? 60));
    $parts = ['timeout', (string)$timeoutSec, $interpreter, escapeshellarg($scriptPath)];
    foreach ($args as $arg) {
        $parts[] = escapeshellarg($arg);
    }
    $cmd = implode(' ', $parts) . ' 2>&1';

    $lines = [];
    $exitCode = 0;
    @exec($cmd, $lines, $exitCode);
    $output = trim((string)implode("\n", $lines));
    if ($exitCode !== 0) {
        throw new RuntimeException('kind=script завершился с ошибкой (exit_code=' . $exitCode . '): ' . $output);
    }

    return [
        'message' => 'Операция script выполнена',
        'script' => [
            'script_path' => $scriptPath,
            'interpreter' => $interpreter,
            'output' => $output,
            'exit_code' => $exitCode,
        ],
    ];
}

function connectors_execute_operation_by_kind_for_manual_test(array $connector, array $operation, int $connectorId, ?string $periodFrom, ?string $periodTo): array
{
    $kind = trim((string)($operation['kind'] ?? ''));
    $kind = $kind !== '' ? $kind : 'browser_steps';

    if ($kind === 'noop') {
        return [
            'message' => 'Операция noop пропущена (технический узел графа).',
            'trace_meta' => ['kind' => 'noop'],
        ];
    }

    if ($kind === 'script') {
        $scriptResult = connectors_execute_script_operation($operation);
        return [
            'message' => (string)($scriptResult['message'] ?? 'Операция script выполнена'),
            'script' => isset($scriptResult['script']) && is_array($scriptResult['script']) ? $scriptResult['script'] : null,
            'trace_meta' => ['kind' => 'script'],
        ];
    }

    if ($kind === 'api_call') {
        $apiCallResult = connectors_execute_api_call_operation($operation, $connectorId);
        return [
            'message' => $apiCallResult['message'],
            'api_response' => $apiCallResult['payload'],
            'trace_meta' => ['kind' => 'api_call', 'action' => (string)($operation['action'] ?? '')],
        ];
    }

    $browserResult = connectors_execute_browser_steps_operation($connector, $operation, $periodFrom, $periodTo);
    return [
        'message' => (string)($browserResult['message'] ?? 'Операция browser_steps выполнена'),
        'download' => isset($browserResult['download']) && is_array($browserResult['download']) ? $browserResult['download'] : null,
        'step_log' => isset($browserResult['step_log']) && is_array($browserResult['step_log']) ? $browserResult['step_log'] : [],
        'artifacts_dir' => (string)($browserResult['artifacts_dir'] ?? ''),
        'trace_meta' => ['kind' => 'browser_steps'],
    ];
}


function connectors_execute_manual_test_execution_plan(
    array $connector,
    int $connectorId,
    array $executionPlan,
    array $runtimeOperations,
    ?string $periodFrom,
    ?string $periodTo,
    string $runId,
    array &$traceLog
): array {
    $executed = [];
    $stageCollections = [
        'before' => (array)($executionPlan['before'] ?? []),
        'main' => [trim((string)($executionPlan['main'] ?? ''))],
    ];

    foreach ($stageCollections as $stage => $operationIds) {
        foreach ($operationIds as $operationIdRaw) {
            $operationId = trim((string)$operationIdRaw);
            if ($operationId === '' || isset($executed[$operationId])) {
                continue;
            }
            if (!isset($runtimeOperations[$operationId]) || !is_array($runtimeOperations[$operationId])) {
                throw new InvalidArgumentException('Операция из execution plan не найдена: ' . $operationId);
            }

            $startedAtTs = microtime(true);
            $startedAt = date('c');
            $result = connectors_execute_operation_by_kind_for_manual_test($connector, $runtimeOperations[$operationId], $connectorId, $periodFrom, $periodTo);
            $finishedAtTs = microtime(true);
            $finishedAt = date('c');

            connectors_append_operation_executed_event(
                $traceLog,
                $runId,
                $operationId,
                $stage,
                'success',
                (string)($result['message'] ?? 'Операция выполнена'),
                (int)round(($finishedAtTs - $startedAtTs) * 1000),
                $startedAt,
                $finishedAt,
                isset($result['trace_meta']) && is_array($result['trace_meta']) ? $result['trace_meta'] : []
            );

            $executed[$operationId] = [
                'operation_id' => $operationId,
                'stage' => $stage,
                'result' => $result,
            ];
        }
    }

    $duringGroups = (array)($executionPlan['during_groups'] ?? ($executionPlan['during'] ?? []));
    foreach ($duringGroups as $groupIndex => $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $operationIdRaw) {
            $operationId = trim((string)$operationIdRaw);
            if ($operationId === '' || isset($executed[$operationId])) {
                continue;
            }
            if (!isset($runtimeOperations[$operationId]) || !is_array($runtimeOperations[$operationId])) {
                throw new InvalidArgumentException('During-операция из execution plan не найдена: ' . $operationId);
            }

            $startedAtTs = microtime(true);
            $startedAt = date('c');
            $result = connectors_execute_operation_by_kind_for_manual_test($connector, $runtimeOperations[$operationId], $connectorId, $periodFrom, $periodTo);
            $finishedAtTs = microtime(true);
            $finishedAt = date('c');

            $stage = 'during';
            connectors_append_operation_executed_event(
                $traceLog,
                $runId,
                $operationId,
                $stage,
                'success',
                (string)($result['message'] ?? ('Операция выполнена (during группа #' . ($groupIndex + 1) . ')')),
                (int)round(($finishedAtTs - $startedAtTs) * 1000),
                $startedAt,
                $finishedAt,
                isset($result['trace_meta']) && is_array($result['trace_meta']) ? $result['trace_meta'] : []
            );

            $executed[$operationId] = [
                'operation_id' => $operationId,
                'stage' => $stage,
                'result' => $result,
                'during_group' => $groupIndex + 1,
            ];
        }
    }

    foreach ((array)($executionPlan['after'] ?? ($executionPlan['finally'] ?? [])) as $operationIdRaw) {
        $operationId = trim((string)$operationIdRaw);
        if ($operationId === '' || isset($executed[$operationId])) {
            continue;
        }
        if (!isset($runtimeOperations[$operationId]) || !is_array($runtimeOperations[$operationId])) {
            throw new InvalidArgumentException('After-операция из execution plan не найдена: ' . $operationId);
        }

        $startedAtTs = microtime(true);
        $startedAt = date('c');
        $result = connectors_execute_operation_by_kind_for_manual_test($connector, $runtimeOperations[$operationId], $connectorId, $periodFrom, $periodTo);
        $finishedAtTs = microtime(true);
        $finishedAt = date('c');

        connectors_append_operation_executed_event(
            $traceLog,
            $runId,
            $operationId,
            'after',
            'success',
            (string)($result['message'] ?? 'Операция выполнена'),
            (int)round(($finishedAtTs - $startedAtTs) * 1000),
            $startedAt,
            $finishedAt,
            isset($result['trace_meta']) && is_array($result['trace_meta']) ? $result['trace_meta'] : []
        );

        $executed[$operationId] = [
            'operation_id' => $operationId,
            'stage' => 'after',
            'result' => $result,
        ];
    }

    return array_values($executed);
}


function connectors_has_operations_payload_in_post(): bool
{
    $fields = ['operations_v3_json'];

    foreach (['report', 'submission', 'track_and_label_info'] as $operationKey) {
        foreach (['enabled', 'operation_id', 'steps_json', 'run_after_json', 'run_with_json', 'run_finally_json'] as $suffix) {
            $fields[] = $operationKey . '_' . $suffix;
        }
    }
    foreach ($fields as $field) {
        if (array_key_exists($field, $_POST)) {
            return true;
        }
    }

    return false;
}


function connectors_build_operations_payload_from_post(): array
{

    $operationsV3Json = trim((string)($_POST['operations_v3_json'] ?? ''));
    if ($operationsV3Json !== '') {
        $decodedV3 = json_decode($operationsV3Json, true);
        if (!is_array($decodedV3)) {
            throw new InvalidArgumentException('Некорректный JSON в "operations_v3_json"');
        }

        if (!connectors_is_v3_operations_payload($decodedV3)) {
            throw new InvalidArgumentException('Поле "operations_v3_json" должно содержать payload формата v3: {"schema_version":3,"operations":[...]}');
        }

        return $decodedV3;
    }


    $legacyOperationDefs = [
        'report' => [
            'default_operation_id' => 'report',
            'label' => 'Report',
            'config_builder' => static function (): array {
                $targetTable = strtolower(trim((string)($_POST['report_target_table'] ?? '')));
                $targetTable = preg_replace('/[^a-z0-9_]+/', '_', $targetTable ?? '');
                if ($targetTable !== '') {
                    $targetTable = trim($targetTable, '_');
                }

                $steps = [];
                $stepsJson = trim((string)($_POST['report_steps_json'] ?? ''));
                if ($stepsJson !== '') {
                    $decoded = json_decode($stepsJson, true);
                    if (!is_array($decoded)) {
                        throw new InvalidArgumentException('Некорректный JSON в "Шаги формы/кнопок"');
                    }
                    $steps = $decoded;
                }

                $curlConfig = [];
                $curlConfigJson = trim((string)($_POST['report_curl_config_json'] ?? ''));
                if ($curlConfigJson !== '') {
                    $decoded = json_decode($curlConfigJson, true);
                    if (!is_array($decoded)) {
                        throw new InvalidArgumentException('Некорректный JSON в "PHP cURL конфиг"');
                    }
                    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
                    if ($isList) {
                        throw new InvalidArgumentException('"PHP cURL конфиг" должен быть JSON-объектом вида {"url":"...","method":"GET|POST",...}, а не массивом browser-шагов. Шаги нужно указывать в "Шаги формы/кнопок".');
                    }
                    $curlConfig = $decoded;
                }

                $fieldMapping = [];
                $fieldMappingJson = trim((string)($_POST['report_field_mapping_json'] ?? ''));
                if ($fieldMappingJson !== '') {
                    $decoded = json_decode($fieldMappingJson, true);
                    if (!is_array($decoded)) {
                        throw new InvalidArgumentException('Некорректный JSON в "Маппинг полей"');
                    }
                    $fieldMapping = $decoded;
                }
                $downloadMode = trim((string)($_POST['report_download_mode'] ?? 'browser'));
                if (!in_array($downloadMode, ['browser', 'curl'], true)) {
                    $downloadMode = 'browser';
                }

                $fileExtension = strtolower(trim((string)($_POST['report_file_extension'] ?? 'xlsx')));
                if ($fileExtension === '') {
                    $fileExtension = 'xlsx';
                }

                return [
                    'page_url' => trim((string)($_POST['report_page_url'] ?? '')),
                    'file_extension' => $fileExtension,
                    'download_mode' => $downloadMode,
                    'log_steps' => !empty($_POST['report_log_steps']) ? 1 : 0,
                    'steps' => $steps,
                    'curl_config' => $curlConfig,
                    'target_table' => $targetTable,
                    'field_mapping' => $fieldMapping,
                ];
            },
        ],
        'submission' => [

            'default_operation_id' => 'submission',
            'label' => 'Submission',
            'config_builder' => static function (): array {
                $steps = [];
                $stepsJson = trim((string)($_POST['submission_steps_json'] ?? ''));
                if ($stepsJson !== '') {
                    $decoded = json_decode($stepsJson, true);
                    if (!is_array($decoded)) {
                        throw new InvalidArgumentException('Некорректный JSON в "Шаги формы операции #2"');
                    }
                    $steps = $decoded;
                }

                $requestConfig = [];
                $requestConfigJson = trim((string)($_POST['submission_request_config_json'] ?? ''));
                if ($requestConfigJson !== '') {
                    $decoded = json_decode($requestConfigJson, true);
                    if (!is_array($decoded)) {
                        throw new InvalidArgumentException('Некорректный JSON в "AJAX / Request конфиг"');
                    }
                    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
                    if ($isList) {
                        throw new InvalidArgumentException('"AJAX / Request конфиг" должен быть JSON-объектом, а не массивом.');
                    }
                    $requestConfig = $decoded;
                }

                return [
                    'page_url' => trim((string)($_POST['submission_page_url'] ?? '')),
                    'log_steps' => !empty($_POST['submission_log_steps']) ? 1 : 0,
                    'steps' => $steps,
                    'request_config' => $requestConfig,
                    'success_selector' => trim((string)($_POST['submission_success_selector'] ?? '')),
                    'success_text' => trim((string)($_POST['submission_success_text'] ?? '')),
                    'error_selector' => trim((string)($_POST['submission_error_selector'] ?? '')),
                ];
            },
        ],

        'track_and_label_info' => [
            'default_operation_id' => 'track_and_label_info',
            'label' => 'TrackAndLabelInfo',
            'config_builder' => static function (): array {
                return [];
            },
        ],
    ];

    $operations = [];
    foreach ($legacyOperationDefs as $operationKey => $def) {
        $operationId = trim((string)($_POST[$operationKey . '_operation_id'] ?? $def['default_operation_id']));
        if ($operationId === '') {
            $operationId = $def['default_operation_id'];
        }

        $operations[$operationKey] = array_merge([
            'schema_version' => 2,
            'operation_id' => $operationId,
            'run_after' => connectors_decode_dependency_links_json((string)($_POST[$operationKey . '_run_after_json'] ?? ''), $def['label'] . ' run_after'),
            'run_with' => connectors_decode_dependency_links_json((string)($_POST[$operationKey . '_run_with_json'] ?? ''), $def['label'] . ' run_with'),
            'run_finally' => connectors_decode_dependency_links_json((string)($_POST[$operationKey . '_run_finally_json'] ?? ''), $def['label'] . ' run_finally'),
            'entrypoint' => !empty($_POST[$operationKey . '_entrypoint']) ? 1 : 0,
            'on_dependency_fail' => connectors_normalize_dependency_policy($_POST[$operationKey . '_on_dependency_fail'] ?? 'stop'),
            'enabled' => !empty($_POST[$operationKey . '_enabled']) ? 1 : 0,
        ], $def['config_builder']());
    }

    return $operations;
}


connectors_ensure_schema($dbcnx);

switch ($dispatchAction) {
    case 'view_connectors':
        $connectors = [];
        $sql = "SELECT
                    id,
                    name,
                    countries,
                    base_url,
                    auth_type,
                    auth_username,
                    auth_token,
                    is_active,
                    last_sync_at,
                    last_success_at,
                    last_error,
                    ssl_ignore,
                    scenario_json,
                    last_manual_confirm_at,
                    created_at,
                    updated_at
                FROM connectors
                ORDER BY id DESC";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $connectors[] = connectors_build_status($row);
            }
            $res->free();
        }

        $smarty->assign('connectors', $connectors);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_connectors.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_new_connector':
        $connector = [
            'id' => 0,
            'name' => '',
            'countries' => '',
            'base_url' => '',
            'auth_type' => 'login',
            'auth_username' => '',
            'auth_password' => '',
            'api_token' => '',
            'auth_token' => '',
            'auth_token_expires_at' => null,
            'auth_cookies' => '',
            'is_active' => 1,
            'last_sync_at' => null,
            'last_success_at' => null,
            'last_error' => '',
            'ssl_ignore' => 0,
            'scenario_json' => '',
            'operations_json' => '',
            'last_manual_confirm_at' => null,
            'last_manual_confirm_by' => null,
            'manual_instruction' => '',
            'notes' => '',
            'created_at' => null,
            'updated_at' => null,
        ];

        $smarty->assign('connector', $connector);

        ob_start();
        $smarty->display('cells_NA_API_connector_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_edit_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        $connector['manual_instruction'] = '';
        if (!empty($connector['scenario_json'])) {
            $scenario = json_decode((string)$connector['scenario_json'], true);
            if (is_array($scenario) && isset($scenario['manual_confirm']['instruction'])) {
                $connector['manual_instruction'] = (string)$scenario['manual_confirm']['instruction'];
            }
        }

        $smarty->assign('connector', $connector);

        ob_start();
        $smarty->display('cells_NA_API_connector_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;


    case 'test_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $userId = (int)($user['id'] ?? 0);
        $result = connector_engine_run_by_id($dbcnx, $connectorId, $userId);
        audit_log(
            $userId,
            $result['ok'] ? 'CONNECTOR_TEST_OK' : 'CONNECTOR_TEST_FAIL',
            'connector',
            $connectorId,
            $result['message'] ?? 'Проверка коннектора'
        );
        $response = [
            'status' => 'ok',
            'ok' => $result['ok'],
            'message' => $result['message'],
            'connector_id' => $connectorId,
        ];
        break;

    case 'manual_confirm_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение токена');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены',
            'connector_id' => $connectorId,
        ];
        break;


    case 'manual_confirm_puppeteer':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        $baseUrl = trim((string)($connector['base_url'] ?? ''));
        $login = trim((string)($connector['auth_username'] ?? ''));
        $password = trim((string)($connector['auth_password'] ?? ''));
        $scenarioJson = (string)($connector['scenario_json'] ?? '');
        $scenario = json_decode($scenarioJson, true);

        $loginUrl = '';
        if (is_array($scenario) && isset($scenario['login']['url'])) {
            $loginUrl = trim((string)$scenario['login']['url']);
        }
        if ($loginUrl === '' && $baseUrl !== '') {
            $loginUrl = $baseUrl;
        }

        if ($loginUrl === '') {
            $response = [
                'status' => 'error',
                'message' => 'Укажите base_url или login.url в сценарии',
            ];
            break;
        }

        $scriptDir = realpath(__DIR__ . '/../../scripts');
        $scriptPath = $scriptDir ? $scriptDir . '/manual_confirm_puppeteer.js' : '';
        if (!$scriptPath || !is_file($scriptPath)) {
            $response = [
                'status' => 'error',
                'message' => 'Скрипт Puppeteer не найден',
            ];
            break;
        }

        $command = sprintf(
            'node %s %s %s %s',
            escapeshellarg($scriptPath),
            escapeshellarg($loginUrl),
            escapeshellarg($login),
            escapeshellarg($password)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $rawOutput = trim(implode("\n", $output));
        $payload = $rawOutput !== '' ? json_decode($rawOutput, true) : null;

        if ($exitCode !== 0 || !is_array($payload)) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось получить токен/куки через Puppeteer',
                'details' => $rawOutput,
            ];
            break;
        }

        $authToken = trim((string)($payload['auth_token'] ?? ''));
        $authCookies = trim((string)($payload['auth_cookies'] ?? ''));
        $authTokenExpiresAt = trim((string)($payload['auth_token_expires_at'] ?? ''));
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение (Puppeteer)');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены через браузер',
            'connector_id' => $connectorId,
        ];
        break;

    case 'manual_confirm_extension':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        if ($authToken === '' && $authCookies === '') {
            $response = [
                'status' => 'error',
                'message' => 'Нужен токен или cookies',
            ];
            break;
        }

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение (Extension)');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены через расширение',
            'connector_id' => $connectorId,
        ];
        break;


    case 'form_connector_operations':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        $operations = connectors_decode_operations($connector);
        $operationsV3Payload = connectors_decode_operations_payload($connector);
        $nodeRuntimeAvailable = connectors_is_node_runtime_available();
        if (!$nodeRuntimeAvailable && (($operations['report']['download_mode'] ?? 'browser') === 'curl')) {
            $operations['report']['download_mode'] = 'browser';
        }
        $smarty->assign('connector', $connector);
        $addons = connectors_fetch_addons($dbcnx, $connectorId);

        $smarty->assign('operations', $operations);
        $smarty->assign('operations_v3_json', json_encode($operationsV3Payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $smarty->assign('addons', $addons);
        $openTab = trim((string)($_POST['open_tab'] ?? ''));
        $smarty->assign('open_tab', $openTab);
        $smarty->assign('node_runtime_available', $nodeRuntimeAvailable);

        if (method_exists($smarty, 'clearCompiledTemplate')) {
            $smarty->clearCompiledTemplate('cells_NA_API_connector_operations_modal.html');
        }

        ob_start();
        $smarty->display('cells_NA_API_connector_operations_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html' => $html,
        ];
        break;

    case 'save_connector_operations':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        try {
            $operationsPayload = connectors_build_operations_payload_from_post();
            connectors_validate_operations_payload($operationsPayload);
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            break;
        }

        $operationsJson = json_encode($operationsPayload, JSON_UNESCAPED_UNICODE);
        if ($operationsJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать операции',
            ];
            break;
        }

        $stmt = $dbcnx->prepare('UPDATE connectors SET operations_json = ? WHERE id = ?');
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save operations)',
            ];
            break;
        }
        $stmt->bind_param('si', $operationsJson, $connectorId);
        if (!$stmt->execute()) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save operations): ' . $stmt->error,
            ];
            $stmt->close();
            break;
        }
        $stmt->close();

        $userId = (int)($user['id'] ?? 0);
        audit_log($userId, 'CONNECTOR_OPERATIONS_UPDATE', 'connector', $connectorId, 'Операции коннектора обновлены');

        $response = [
            'status' => 'ok',
            'message' => 'Операции сохранены',
            'connector_id' => $connectorId,
            'open_tab' => trim((string)($_POST['open_tab'] ?? '')),
        ];
        break;



    case 'save_connector_addons':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        try {
            $addonsPayload = connectors_build_addons_payload_from_post();
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            break;
        }

        $addonsJson = json_encode($addonsPayload['addons'], JSON_UNESCAPED_UNICODE);
        if ($addonsJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать ДопИнфо',
            ];
            break;
        }

        $nodeMappingJson = json_encode($addonsPayload['node_mapping'], JSON_UNESCAPED_UNICODE);
        if ($nodeMappingJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать Node mapping',
            ];
            break;
        }

        $statusTargetsJson = json_encode($addonsPayload['status_targets'], JSON_UNESCAPED_UNICODE);
        if ($statusTargetsJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать карту статусов',
            ];
            break;
        }

        $reportOutStatusesJson = json_encode($addonsPayload['report_out_statuses'], JSON_UNESCAPED_UNICODE);
        if ($reportOutStatusesJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать карту статусов репорта -> warehouse_item_out.status',
            ];
            break;
        }

        $stmt = $dbcnx->prepare('INSERT INTO connectors_addons (connector_id, connector_name, addons_json, node_mapping_json, status_targets_json, report_out_statuses_json) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE connector_name = VALUES(connector_name), addons_json = VALUES(addons_json), node_mapping_json = VALUES(node_mapping_json), status_targets_json = VALUES(status_targets_json), report_out_statuses_json = VALUES(report_out_statuses_json)');
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save addons)',
            ];
            break;
        }

        $connectorName = trim((string)($connector['name'] ?? ''));
        $stmt->bind_param('isssss', $connectorId, $connectorName, $addonsJson, $nodeMappingJson, $statusTargetsJson, $reportOutStatusesJson);
        if (!$stmt->execute()) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save addons): ' . $stmt->error,
            ];
            $stmt->close();
            break;
        }
        $stmt->close();

        $userId = (int)($user['id'] ?? 0);
        audit_log($userId, 'CONNECTOR_ADDONS_UPDATE', 'connector', $connectorId, 'ДопИнфо коннектора обновлено');

        $response = [
            'status' => 'ok',
            'message' => 'ДопИнфо сохранено',
            'connector_id' => $connectorId,
        ];
        break;



    case 'test_connector_operations':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        $runId = connectors_generate_run_id($connectorId);
        $traceLog = [];
        $executionPlan = [];
        $graphErrors = [];
        $response = [];
        $targetTable = '';
        $periodFrom = null;
        $periodTo = null;
        $reportCfg = [];
        $submissionCfg = [];
        $manualKindFlowHandled = false;
        $runStartedAtTs = microtime(true);
        $runStartedAt = date('Y-m-d H:i:s');
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
                'run_id' => $runId,
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
                'run_id' => $runId,
            ];
            break;
        }

        $testOperation = trim((string)($_POST['test_operation'] ?? 'report'));
        connectors_append_trace_event($traceLog, $runId, $testOperation ?: 'report', 'start', 'start', 'Запуск теста операции');

        try {
            if (connectors_has_operations_payload_in_post()) {
                $operationsPayload = connectors_build_operations_payload_from_post();
            } else {
                $operationsPayload = connectors_decode_operations_payload($connector);
            }
            connectors_validate_operations_payload($operationsPayload);
            $entrypoint = connectors_resolve_legacy_test_entrypoint($operationsPayload, $testOperation);
            if (connectors_is_dependency_graph_enabled($connector)) {
                try {
                    $executionPlan = connectors_build_execution_plan($operationsPayload, $entrypoint);
                } catch (InvalidArgumentException $graphException) {
                    $graphErrors[] = connectors_build_graph_error(
                        $runId,
                        $connectorId,
                        $entrypoint,
                        connectors_resolve_graph_error_code($graphException->getMessage()),
                        [
                            'message' => $graphException->getMessage(),
                            'source' => 'manual_test',
                        ]
                    );
                    throw $graphException;
                }
            } else {
                $executionPlan = connectors_build_legacy_execution_plan($entrypoint);
            }


            $runtimeOperations = connectors_is_v3_operations_payload($operationsPayload)
                ? connectors_v3_payload_to_runtime_operations($operationsPayload)
                : connectors_decode_operations_for_runtime($connector);

            if (isset($runtimeOperations[$entrypoint]) && is_array($runtimeOperations[$entrypoint])) {
                $periodFrom = connectors_validate_iso_date($_POST['test_period_from'] ?? null);
                $periodTo = connectors_validate_iso_date($_POST['test_period_to'] ?? null);
                if ($periodFrom !== null && $periodTo !== null && $periodFrom > $periodTo) {
                    throw new InvalidArgumentException('Дата начала периода больше даты окончания');
                }



                $executedOperations = connectors_execute_manual_test_execution_plan(
                    $connector,
                    $connectorId,
                    $executionPlan,
                    $runtimeOperations,
                    $periodFrom,
                    $periodTo,
                    $runId,
                    $traceLog
                );
                $manualKindFlowHandled = true;


                $mainOperationId = trim((string)($executionPlan['main'] ?? $entrypoint));
                if ($mainOperationId === '') {
                    $mainOperationId = $entrypoint;
                }

                $mainResult = null;
                $allStepLog = [];
                $lastArtifactsDir = '';
                $lastDownload = null;
                $lastApiResponse = null;
                $lastScriptResult = null;

                foreach ($executedOperations as $executedOperation) {
                    if (!is_array($executedOperation)) {
                        continue;
                    }
                    $result = isset($executedOperation['result']) && is_array($executedOperation['result']) ? $executedOperation['result'] : [];
                    $opId = trim((string)($executedOperation['operation_id'] ?? ''));
                    if ($opId === $mainOperationId) {
                        $mainResult = $result;
                    }
                    if (isset($result['step_log']) && is_array($result['step_log'])) {
                        $allStepLog = array_merge($allStepLog, $result['step_log']);
                    }
                    $artifactDir = trim((string)($result['artifacts_dir'] ?? ''));
                    if ($artifactDir !== '') {
                        $lastArtifactsDir = $artifactDir;
                    }
                    if (isset($result['download']) && is_array($result['download'])) {
                        $lastDownload = $result['download'];
                    }
                    if (isset($result['api_response']) && is_array($result['api_response'])) {
                        $lastApiResponse = $result['api_response'];
                    }
                    if (isset($result['script']) && is_array($result['script'])) {
                        $lastScriptResult = $result['script'];
                    }
                }

                if (!is_array($mainResult)) {
                    $mainResult = isset($executedOperations[0]['result']) && is_array($executedOperations[0]['result']) ? $executedOperations[0]['result'] : [];
                }
                $response = [
                    'status' => 'ok',
                    'test_operation' => $testOperation,
                    'message' => (string)($mainResult['message'] ?? 'Операции выполнены'),
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'download' => $lastDownload,
                    'api_response' => $lastApiResponse,
                    'script' => $lastScriptResult,
                    'submission_tracking' => (string)($mainResult['submission_tracking'] ?? ''),
                    'step_log' => $allStepLog,
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'executed_operations' => $executedOperations,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $mainOperationId, true, $traceLog),
                    'artifacts_dir' => $lastArtifactsDir,
                    'graph_errors' => $graphErrors,
                ];
            }

            if (!$manualKindFlowHandled) {
                $legacyCompatPayload = connectors_build_legacy_compat_operations_view($operationsPayload, $connector);
                $reportCfg = (array)($legacyCompatPayload['report'] ?? []);
                $submissionCfg = (array)($legacyCompatPayload['submission'] ?? []);
                if ($testOperation === 'submission') {
                $submissionResult = connectors_run_submission_test($connector, $submissionCfg);
                $tracking = (string)($submissionResult['tracking_number'] ?? '');
                $suffix = $tracking !== '' ? (' Трек: ' . $tracking . '.') : '';

                $operationId = trim((string)($submissionCfg['operation_id'] ?? 'submission'));
                connectors_append_operation_executed_event($traceLog, $runId, $operationId, 'main', 'success', 'Операция #2 выполнена успешно', 0, null, null, [
                    'tracking_number' => $tracking,
                ]);
                $response = [
                    'status' => 'ok',
                    'test_operation' => 'submission',
                    'message' => 'Тест операции #2 пройден. Проверка Last changes выполнена.' . $suffix,
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'open_tab' => 'op2-pane',
                    'submission_tracking' => $tracking,
                    'resolved_success_selector' => (string)($submissionResult['resolved_success_selector'] ?? ''),
                    'resolved_success_text' => (string)($submissionResult['resolved_success_text'] ?? ''),
                    'resolved_error_selector' => (string)($submissionResult['resolved_error_selector'] ?? ''),
                    'captured_error_text' => (string)($submissionResult['captured_error_text'] ?? ''),
                    'step_log' => isset($submissionResult['step_log']) && is_array($submissionResult['step_log']) ? $submissionResult['step_log'] : [],
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $operationId, true, $traceLog),
                    'artifacts_dir' => (string)($submissionResult['artifacts_dir'] ?? ''),
                    'node_payload' => isset($submissionResult['node_payload']) && is_array($submissionResult['node_payload']) ? $submissionResult['node_payload'] : null,
                    'graph_errors' => $graphErrors,
                ];
                }

                if ($testOperation !== 'submission') {
                    $periodFrom = connectors_validate_iso_date($_POST['test_period_from'] ?? null);
                $periodTo = connectors_validate_iso_date($_POST['test_period_to'] ?? null);
                if ($periodFrom !== null && $periodTo !== null && $periodFrom > $periodTo) {
                    throw new InvalidArgumentException('Дата начала периода больше даты окончания');
                }

                $targetTable = connectors_normalize_report_table_name((string)($reportCfg['target_table'] ?? ''));
                connectors_ensure_report_table($dbcnx, $targetTable);
                }
            }
        } catch (InvalidArgumentException $e) {

            if (empty($graphErrors) && connectors_is_dependency_graph_enabled($connector)) {
                $graphErrors[] = connectors_build_graph_error(
                    $runId,
                    $connectorId,
                    isset($entrypoint) ? (string)$entrypoint : '',
                    connectors_resolve_graph_error_code($e->getMessage()),
                    [
                        'message' => $e->getMessage(),
                        'source' => 'manual_test',
                    ]
                );
            }
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'run_id' => $runId,
                'trace_log' => $traceLog,
                'graph_errors' => $graphErrors,
            ];
        } catch (RuntimeException $e) {

            connectors_append_trace_event($traceLog, $runId, $testOperation ?: 'report', 'validate', 'failed', 'Ошибка подготовки операции', [
                'error' => $e->getMessage(),
            ]);
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'test_operation' => $testOperation,
                'run_id' => $runId,
                'trace_log' => $traceLog,
            ];
        } catch (ConnectorStepLogException $e) {
            connectors_append_trace_event($traceLog, $runId, $testOperation ?: 'report', 'validate', 'failed', 'Ошибка выполнения шага', [
                'error' => $e->getMessage(),
            ]);
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'test_operation' => $testOperation,
                'run_id' => $runId,
                'step_log' => $e->getStepLog(),
                'trace_log' => $traceLog,
                'artifacts_dir' => $e->getArtifactsDir(),
            ];
        }

        if (($response['status'] ?? '') === 'error' || $manualKindFlowHandled) {
            // Ошибка подготовки: запуск скачивания/импорта пропускаем.
        } else {
            $periodMessage = '';
            if ($periodFrom !== null || $periodTo !== null) {
                $periodMessage = ' Период: ' . ($periodFrom ?? '...') . ' — ' . ($periodTo ?? '...') . '.';
            }
            try {
                $downloadInfo = connectors_download_report_file($connector, $reportCfg, $periodFrom, $periodTo);
                $reportOperationId = trim((string)($reportCfg['operation_id'] ?? 'report'));
                connectors_append_operation_executed_event($traceLog, $runId, $reportOperationId, 'main', 'success', 'Файл успешно скачан', 0, null, null, [
                    'file_size' => (int)($downloadInfo['file_size'] ?? 0),
                ]);
                $importedRows = 0;
                $importMessage = ' Парсинг не выполнен: поддержан авто-импорт CSV/XLSX.';
                $fieldMapping = isset($reportCfg['field_mapping']) && is_array($reportCfg['field_mapping']) ? $reportCfg['field_mapping'] : [];
                $fileExt = strtolower(trim((string)($downloadInfo['file_extension'] ?? '')));
                if ($fileExt === 'csv') {
                    $importedRows = connectors_import_csv_into_report_table(
                        $dbcnx,
                        $targetTable,
                        (string)$downloadInfo['file_path'],
                        $connectorId,
                        $periodFrom,
                        $periodTo,
                        $fieldMapping
                    );
                    $importMessage = ' Импортировано строк: ' . $importedRows . '.';
                } elseif ($fileExt === 'xlsx') {
                    $importedRows = connectors_import_xlsx_into_report_table(
                        $dbcnx,
                        $targetTable,
                        (string)$downloadInfo['file_path'],
                        $connectorId,
                        $periodFrom,
                        $periodTo,
                        $fieldMapping
                    );
                    $importMessage = ' Импортировано строк: ' . $importedRows . '.';
                }

                $response = [
                    'status' => 'ok',
                    'message' => 'Тест операции пройден. Файл скачан (' . (int)($downloadInfo['file_size'] ?? 0) . ' байт). Таблица `' . $targetTable . '` готова.' . $periodMessage . $importMessage,
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'target_table' => $targetTable,
                    'period_from' => $periodFrom,
                    'period_to' => $periodTo,
                    'download' => $downloadInfo,
                    'step_log' => isset($downloadInfo['step_log']) && is_array($downloadInfo['step_log']) ? $downloadInfo['step_log'] : [],
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $reportOperationId, true, $traceLog),
                    'imported_rows' => $importedRows,
                ];
            } catch (InvalidArgumentException $e) {
                $reportOperationId = trim((string)($reportCfg['operation_id'] ?? 'report'));
                connectors_append_operation_executed_event($traceLog, $runId, $reportOperationId, 'main', 'failed', 'Ошибка валидации операции #1', 0, null, null, [
                    'error' => $e->getMessage(),
                ]);
                $response = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'target_table' => $targetTable,
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $reportOperationId, false, $traceLog),
                ];
            } catch (ConnectorStepLogException $e) {
                $reportOperationId = trim((string)($reportCfg['operation_id'] ?? 'report'));
                connectors_append_operation_executed_event($traceLog, $runId, $reportOperationId, 'main', 'failed', 'Ошибка шага операции #1', 0, null, null, [
                    'error' => $e->getMessage(),
                ]);
                $response = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'target_table' => $targetTable,
                    'step_log' => $e->getStepLog(),
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $reportOperationId, false, $traceLog),
                    'artifacts_dir' => $e->getArtifactsDir(),
                ];
            } catch (RuntimeException $e) {
                $reportOperationId = trim((string)($reportCfg['operation_id'] ?? 'report'));
                connectors_append_operation_executed_event($traceLog, $runId, $reportOperationId, 'main', 'failed', 'Ошибка выполнения операции #1', 0, null, null, [
                    'error' => $e->getMessage(),
                ]);
                $response = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'target_table' => $targetTable,
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $reportOperationId, false, $traceLog),
                ];
            } catch (Throwable $e) {
                $reportOperationId = trim((string)($reportCfg['operation_id'] ?? 'report'));
                connectors_append_operation_executed_event($traceLog, $runId, $reportOperationId, 'main', 'failed', 'Фатальная ошибка операции #1', 0, null, null, [
                    'error' => $e->getMessage(),
                ]);
                error_log('test_connector_operations fatal: ' . $e->getMessage());
                $response = [
                    'status' => 'error',
                    'message' => 'Ошибка во время теста операции: ' . $e->getMessage(),
                    'connector_id' => $connectorId,
                    'run_id' => $runId,
                    'target_table' => $targetTable,
                    'trace_log' => $traceLog,
                    'execution_plan' => $executionPlan,
                    'chain_status' => connectors_build_chain_status_map($executionPlan, $reportOperationId, false, $traceLog),
                ];
            }
            }

        $runFinishedAt = date('Y-m-d H:i:s');
        $runDurationMs = max(0, (int)round((microtime(true) - $runStartedAtTs) * 1000));
        connectors_persist_run_trace($dbcnx, [
            'connector_id' => $connectorId,
            'run_id' => $runId,
            'test_operation' => (string)($response['test_operation'] ?? $testOperation),
            'status' => (string)($response['status'] ?? 'error'),
            'message' => (string)($response['message'] ?? ''),
            'target_table' => (string)($response['target_table'] ?? $targetTable),
            'created_by' => (int)($user['id'] ?? 0),
            'started_at' => $runStartedAt,
            'finished_at' => $runFinishedAt,
            'duration_ms' => $runDurationMs,
            'trace_log' => isset($response['trace_log']) && is_array($response['trace_log']) ? $response['trace_log'] : $traceLog,
            'step_log' => isset($response['step_log']) && is_array($response['step_log']) ? $response['step_log'] : [],
            'execution_plan' => isset($response['execution_plan']) && is_array($response['execution_plan']) ? $response['execution_plan'] : $executionPlan,
            'chain_status' => isset($response['chain_status']) && is_array($response['chain_status']) ? $response['chain_status'] : [],
            'artifacts_dir' => (string)($response['artifacts_dir'] ?? ''),
            'graph_errors' => isset($response['graph_errors']) && is_array($response['graph_errors']) ? $response['graph_errors'] : $graphErrors,
        ]);
        if (!isset($response['graph_errors']) || !is_array($response['graph_errors'])) {
            $response['graph_errors'] = $graphErrors;
        }
        break;


    case 'save_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        $deleteFlag = !empty($_POST['delete']);

        if ($deleteFlag && $connectorId > 0) {
            $stmt = $dbcnx->prepare("DELETE FROM connectors WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $connectorId);
                $stmt->execute();
                $stmt->close();
            }

            $userId = (int)($user['id'] ?? 0);
            audit_log($userId, 'CONNECTOR_DELETE', 'connector', $connectorId, 'Коннектор удалён');

            $response = [
                'status' => 'ok',
                'message' => 'Коннектор удалён',
                'deleted' => true,
            ];
            break;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $response = [
                'status' => 'error',
                'message' => 'Название обязательно',
            ];
            break;
        }

        $countries = trim($_POST['countries'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $authType = trim($_POST['auth_type'] ?? 'login');
        $authUsername = trim($_POST['auth_username'] ?? '');
        $authPassword = trim($_POST['auth_password'] ?? '');
        $apiToken = trim($_POST['api_token'] ?? '');
        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
        $scenarioJson = trim($_POST['scenario_json'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $isTestConnector = !empty($_POST['is_test_connector']) ? 1 : 0;
        $sslIgnore = !empty($_POST['ssl_ignore']) ? 1 : 0;

        $isNew = $connectorId <= 0;
        if ($connectorId > 0) {
            $existing = connectors_fetch_one($dbcnx, $connectorId);
            if (!$existing) {
                $response = [
                    'status' => 'error',
                    'message' => 'Коннектор не найден',
                ];
                break;
            }

            if ($authPassword === '') {
                $authPassword = $existing['auth_password'];
            }
            if ($apiToken === '') {
                $apiToken = $existing['api_token'];
            }
            $operationsJson = (string)($existing['operations_json'] ?? '');
            $sql = "UPDATE connectors
                       SET name = ?,
                           countries = ?,
                           base_url = ?,
                           auth_type = ?,
                           auth_username = ?,
                           auth_password = ?,
                           api_token = ?,
                           auth_token = ?,
                           auth_cookies = ?,
                           auth_token_expires_at = ?,
                           is_active = ?,
                           is_test_connector = ?,
                           ssl_ignore = ?,
                           scenario_json = ?,
                           operations_json = ?,
                           notes = ?
                     WHERE id = ?";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (update connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'ssssssssssiiisssi',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $authToken,
                $authCookies,
                $authTokenExpiresAt,
                $isActive,
                $isTestConnector,
                $sslIgnore,
                $scenarioJson,
                $operationsJson,
                $notes,
                $connectorId
            );
            if (!$stmt->execute()) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (update connector): ' . $stmt->error,
                ];
                $stmt->close();
                break;
            }
            $stmt->close();
        } else {
            $sql = "INSERT INTO connectors
                        (name, countries, base_url, auth_type, auth_username, auth_password, api_token, auth_token, auth_cookies, auth_token_expires_at, is_active, is_test_connector, ssl_ignore, scenario_json, operations_json, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'ssssssssssiiisss',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $authToken,
                $authCookies,
                $authTokenExpiresAt,
                $isActive,
                $isTestConnector,
                $sslIgnore,
                $scenarioJson,
                '',
                $notes
            );
            if (!$stmt->execute()) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector): ' . $stmt->error,
                ];
                $stmt->close();
                break;
            }
            $connectorId = (int)$stmt->insert_id;
            $stmt->close();
        }


        $userId = (int)($user['id'] ?? 0);
        if ($isNew) {
            audit_log($userId, 'CONNECTOR_CREATE', 'connector', $connectorId, 'Коннектор создан');
        } else {
            audit_log($userId, 'CONNECTOR_UPDATE', 'connector', $connectorId, 'Коннектор обновлён');
        }

        $response = [
            'status' => 'ok',
            'message' => 'Коннектор сохранён',
            'connector_id' => $connectorId,
        ];
        break;

    default:        error_log('connector_actions unknown action: normalized=' . $normalizedAction
            . '; route=' . $normalizedRouteAction
            . '; post=' . $normalizedPostAction
            . '; get=' . $normalizedGetAction
            . '; raw=' . json_encode($incomingAction, JSON_UNESCAPED_UNICODE)
            . '; raw_hex=' . bin2hex((string)$incomingAction));
        $response = [
            'status' => 'error',
            'message' => 'Unknown connector action: ' . $normalizedAction,
        ];
        break;
}
