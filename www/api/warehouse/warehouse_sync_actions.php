<?php
declare(strict_types=1);

$response = ['status' => 'error', 'message' => 'Unknown warehouse sync action'];

if (!function_exists('warehouse_sync_normalize_key')) {
    function warehouse_sync_normalize_key(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    }
}

if (!function_exists('warehouse_sync_table_exists')) {
    function warehouse_sync_table_exists(mysqli $dbcnx, string $tableName): bool
    {
        $safe = $dbcnx->real_escape_string($tableName);
        $sql = "SHOW TABLES LIKE '{$safe}'";
        $res = $dbcnx->query($sql);
        if (!($res instanceof mysqli_result)) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('warehouse_sync_report_identifiers')) {
    function warehouse_sync_report_identifiers(mysqli $dbcnx, string $tableName): array
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        if (!warehouse_sync_table_exists($dbcnx, $tableName)) {
            $cache[$tableName] = [];
            return [];
        }

        $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
        $columns = [];
        if ($res = $dbcnx->query("SHOW COLUMNS FROM {$safeTable}")) {
            while ($row = $res->fetch_assoc()) {
                $field = strtolower(trim((string)($row['Field'] ?? '')));
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
            $res->free();
        }

        $candidateColumns = [
            'tuid',
            'tracking_no',
            'tracking_number',
            'tracking',
            'track_no',
            'track_number',
            'parcel_uid',
            'uid_created',
            'uid',
            'barcode',
            'ttn',
        ];

        $availableColumns = [];
        foreach ($candidateColumns as $column) {
            if (in_array($column, $columns, true)) {
                $availableColumns[] = $column;
            }
        }

        if (empty($availableColumns)) {
            $cache[$tableName] = [];
            return [];
        }

        $identifiers = [];
        foreach ($availableColumns as $column) {
            $safeColumn = '`' . str_replace('`', '``', $column) . '`';
            $sql = "SELECT DISTINCT {$safeColumn} AS value FROM {$safeTable} WHERE {$safeColumn} IS NOT NULL AND TRIM({$safeColumn}) <> ''";
            if (!($res = $dbcnx->query($sql))) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $val = strtoupper(trim((string)($row['value'] ?? '')));
                if ($val !== '') {
                    $identifiers[$val] = true;
                }
            }
            $res->free();
        }

        $cache[$tableName] = $identifiers;
        return $identifiers;
    }
}


if (!function_exists('warehouse_sync_table_columns')) {
    function warehouse_sync_table_columns(mysqli $dbcnx, string $tableName): array
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        if (!warehouse_sync_table_exists($dbcnx, $tableName)) {
            $cache[$tableName] = [];
            return [];
        }

        $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
        $columns = [];
        if ($res = $dbcnx->query("SHOW COLUMNS FROM {$safeTable}")) {
            while ($row = $res->fetch_assoc()) {
                $field = strtolower(trim((string)($row['Field'] ?? '')));
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
            $res->free();
        }

        $cache[$tableName] = $columns;
        return $columns;
    }
}

if (!function_exists('warehouse_sync_find_column')) {
    function warehouse_sync_find_column(array $availableColumns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $availableColumns, true)) {
                return $candidate;
            }
        }
        return '';
    }
}



if (!function_exists('warehouse_sync_apply_vars')) {
    function warehouse_sync_apply_vars($value, array $vars)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = warehouse_sync_apply_vars($v, $vars);
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
}

if (!function_exists('warehouse_sync_ensure_audit_table')) {
    function warehouse_sync_ensure_audit_table(mysqli $dbcnx): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS warehouse_sync_audit (
"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
            . " item_id BIGINT UNSIGNED NOT NULL,
"
            . " tracking_no VARCHAR(255) NOT NULL DEFAULT '',
"
            . " forwarder VARCHAR(120) NOT NULL DEFAULT '',
"
            . " country_code VARCHAR(16) NOT NULL DEFAULT '',
"
            . " status VARCHAR(20) NOT NULL DEFAULT 'error',
"
            . " message TEXT NULL,
"
            . " response_json LONGTEXT NULL,
"
            . " created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
"
            . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
            . " PRIMARY KEY (id),
"
            . " KEY idx_item_created (item_id, created_at),
"
            . " KEY idx_status_created (status, created_at)
"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $dbcnx->query($sql);
    }
}

if (!function_exists('warehouse_sync_audit_log')) {
    function warehouse_sync_audit_log(mysqli $dbcnx, array $entry): void
    {
        warehouse_sync_ensure_audit_table($dbcnx);

        $sql = 'INSERT INTO warehouse_sync_audit (item_id, tracking_no, forwarder, country_code, status, message, response_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            return;
        }

        $itemId = (int)($entry['item_id'] ?? 0);
        $tracking = trim((string)($entry['tracking_no'] ?? ''));
        $forwarder = trim((string)($entry['forwarder'] ?? ''));
        $country = trim((string)($entry['country_code'] ?? ''));
        $status = trim((string)($entry['status'] ?? 'error'));
        $message = trim((string)($entry['message'] ?? ''));
        $responseJson = trim((string)($entry['response_json'] ?? ''));
        $createdBy = (int)($entry['created_by'] ?? 0);

        $stmt->bind_param('issssssi', $itemId, $tracking, $forwarder, $country, $status, $message, $responseJson, $createdBy);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('warehouse_sync_fetch_item')) {
    function warehouse_sync_fetch_item(mysqli $dbcnx, int $itemId): ?array
    {
        $sql = "SELECT id, tuid, tracking_no, receiver_name, receiver_country_code, receiver_company, uid_created, weight_kg, addons_json
"
            . "FROM warehouse_item_stock WHERE id = ? LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('warehouse_sync_fetch_connector')) {
    function warehouse_sync_fetch_connector(mysqli $dbcnx, string $forwarder, string $country): ?array
    {
        $forwarder = strtoupper(trim($forwarder));
        $forwarderCandidates = [$forwarder];
        if (strpos($forwarder, 'DEV_') === 0) {
            $forwarderCandidates[] = substr($forwarder, 4);
        }

        $sql = "SELECT id, name, countries, auth_username, auth_password, auth_token, auth_cookies, base_url, ssl_ignore, scenario_json, operations_json, is_active
"
            . "FROM connectors WHERE UPPER(TRIM(name)) = ? AND is_active = 1 ORDER BY id DESC";
        $rows = [];

        foreach ($forwarderCandidates as $candidate) {
            $candidate = strtoupper(trim((string)$candidate));
            if ($candidate === '') {
                continue;
            }

            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            $stmt->close();

            if (!empty($rows)) {
                break;
            }
        }
        if (empty($rows)) return null;

        $country = strtoupper(trim($country));
        if ($country !== '') {
            foreach ($rows as $row) {
                $countriesRaw = strtoupper(trim((string)($row['countries'] ?? '')));
                if ($countriesRaw === '') continue;
                $parts = array_map('trim', explode(',', $countriesRaw));
                if (in_array($country, $parts, true)) {
                    return $row;
                }
            }
        }

        return $rows[0];
    }
}

if (!function_exists('warehouse_sync_submission_steps')) {
    function warehouse_sync_submission_steps(array $connector): array
    {
        $raw = trim((string)($connector['operations_json'] ?? ''));
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $submission = isset($decoded['submission']) && is_array($decoded['submission']) ? $decoded['submission'] : [];
        return isset($submission['steps']) && is_array($submission['steps']) ? $submission['steps'] : [];
    }
}

if (!function_exists('warehouse_sync_build_vars')) {
    function warehouse_sync_build_vars(array $connector, array $item): array
    {
        $vars = [
            'base_url' => trim((string)($connector['base_url'] ?? '')),
            'login' => trim((string)($connector['auth_username'] ?? '')),
            'password' => trim((string)($connector['auth_password'] ?? '')),
            'tracking_number' => trim((string)($item['tracking_no'] ?? '')),
            'suite' => trim((string)($item['tuid'] ?? '')),
            'client_name_surname' => trim((string)($item['receiver_name'] ?? '')),
            'gross_weight' => trim((string)($item['weight_kg'] ?? '')),
        ];

        if ($vars['suite'] === '') {
            $vars['suite'] = trim((string)($item['uid_created'] ?? ''));
        }

        $scenarioRaw = trim((string)($connector['scenario_json'] ?? ''));
        if ($scenarioRaw !== '') {
            $scenario = json_decode($scenarioRaw, true);
            if (is_array($scenario)) {
                foreach ($scenario as $k => $v) {
                    if (is_string($k) && $k !== '' && (is_scalar($v) || $v === null)) {
                        $vars[$k] = (string)($v ?? '');
                    }
                }
            }
        }

        $addonsRaw = trim((string)($item['addons_json'] ?? ''));
        if ($addonsRaw !== '') {
            $addons = json_decode($addonsRaw, true);
            if (is_array($addons)) {
                if (!empty($addons['tariff_type'])) {
                    $vars['tariff_type'] = (string)$addons['tariff_type'];
                }
                if (!empty($addons['category'])) {
                    $vars['category'] = (string)$addons['category'];
                }
                if (!empty($addons['sub_category'])) {
                    $vars['sub_category'] = (string)$addons['sub_category'];
                } elseif (!empty($addons['subCat'])) {
                    $vars['sub_category'] = (string)$addons['subCat'];
                }
            }
        }

        if (!isset($vars['sub_category']) || trim((string)$vars['sub_category']) === '') {
            $vars['sub_category'] = trim((string)($vars['category'] ?? ''));
        }

        return $vars;
    }
}

if (!function_exists('warehouse_sync_run_submission')) {
    function warehouse_sync_run_submission(array $connector, array $item): array
    {
        $steps = warehouse_sync_submission_steps($connector);
        if (empty($steps)) {
            throw new RuntimeException('У коннектора не заполнены steps для Операции #2');
        }

        $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
        if (!$scriptPath) {
            throw new RuntimeException('Не найден browser script для sync');
        }

        $tempDir = __DIR__ . '/../../scripts/_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $vars = warehouse_sync_build_vars($connector, $item);
        $payload = [
            'steps' => $steps,
            'vars' => $vars,
            'ssl_ignore' => !empty($connector['ssl_ignore']),
            'cookies' => (string)($connector['auth_cookies'] ?? ''),
            'auth_token' => (string)($connector['auth_token'] ?? ''),
            'temp_dir' => realpath($tempDir) ?: $tempDir,
            'expect_download' => false,
        ];

        $cmd = 'node ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string)json_encode($payload, JSON_UNESCAPED_UNICODE));
        $output = shell_exec($cmd . ' 2>&1');
        $decoded = json_decode(trim((string)$output), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Node sync вернул некорректный ответ: ' . trim((string)$output));
        }
        if (empty($decoded['ok'])) {
            $msg = trim((string)($decoded['message'] ?? 'Sync failed'));
            throw new RuntimeException($msg !== '' ? $msg : 'Sync failed');
        }

        return [
            'vars' => $vars,
            'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
            'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
            'message' => trim((string)($decoded['message'] ?? '')),
            'raw' => $decoded,
        ];
    }
}


if ($action === 'warehouse_sync' || $action === 'warehouse.sync') {
    auth_require_login();
    $current = $user;

    $forwarders = [];
    $sql = "
        SELECT DISTINCT UPPER(TRIM(receiver_company)) AS forwarder
        FROM warehouse_item_stock
        WHERE receiver_company IS NOT NULL
          AND TRIM(receiver_company) <> ''
        ORDER BY forwarder ASC
    ";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $value = trim((string)($row['forwarder'] ?? ''));
            if ($value !== '') {
                $forwarders[] = $value;
            }
        }
        $res->free();
    }

    $smarty->assign('current_user', $current);
    $smarty->assign('sync_forwarders', $forwarders);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_sync.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html' => $html,
    ];
}

if ($action === 'warehouse_sync_missing') {
    auth_require_login();
    $current = $user;

    $forwarderFilter = warehouse_sync_normalize_key((string)($_POST['forwarder'] ?? 'ALL'));
    $search = strtoupper(trim((string)($_POST['search'] ?? '')));
    $limitRaw = $_POST['limit'] ?? '50';
    $limit = $limitRaw === 'all' ? null : max(20, (int)$limitRaw);
    $offset = max(0, (int)($_POST['offset'] ?? 0));

    $conditions = [
        'wi.receiver_company IS NOT NULL',
        "TRIM(wi.receiver_company) <> ''",
    ];
    $params = [];
    $types = '';

    if ($forwarderFilter !== '' && $forwarderFilter !== 'ALL') {
        $conditions[] = 'UPPER(TRIM(wi.receiver_company)) = ?';
        $types .= 's';
        $params[] = $forwarderFilter;
    }

    if ($search !== '') {
        $conditions[] = '(UPPER(wi.tuid) LIKE ? OR UPPER(wi.tracking_no) LIKE ? OR UPPER(wi.receiver_name) LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);

    $sql = "
        SELECT
            wi.id,
            wi.tuid,
            wi.tracking_no,
            wi.receiver_name,
            wi.receiver_country_code,
            wi.receiver_company,
            wi.created_at,
            c.code AS cell_address,
            COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid
        FROM warehouse_item_stock wi
        LEFT JOIN cells c ON c.id = wi.cell_id
        {$whereSql}
        ORDER BY wi.created_at DESC
    ";

    $rows = [];
    if ($types === '') {
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    $missing = [];
    foreach ($rows as $row) {
        $forwarder = warehouse_sync_normalize_key((string)($row['receiver_company'] ?? ''));
        $country = warehouse_sync_normalize_key((string)($row['receiver_country_code'] ?? ''));
        if ($forwarder === '') {
            continue;
        }

        $reportTable = 'connector_report_' . strtolower($forwarder . '_' . $country);
        $row['report_table'] = $reportTable;

        if ($country === '') {
            $row['sync_status_class'] = 'text-warning';
            $row['sync_status_label'] = 'Предупреждение: не указана страна назначения';
            $row['can_sync'] = 0;
            $missing[] = $row;
            continue;
        }

        if (!warehouse_sync_table_exists($dbcnx, $reportTable)) {
            $row['sync_status_class'] = 'text-warning';
            $row['sync_status_label'] = 'Предупреждение: таблица форварда не найдена';
            $row['can_sync'] = 0;
            $missing[] = $row;
            continue;
        }

        $reportIdentifiers = warehouse_sync_report_identifiers($dbcnx, $reportTable);
        if (empty($reportIdentifiers)) {
            $row['sync_status_class'] = 'text-success';
            $row['sync_status_label'] = 'Готов к синхронизации';
            $row['can_sync'] = 1;
            $missing[] = $row;
            continue;
        }

        $candidateIds = [
            strtoupper(trim((string)($row['tuid'] ?? ''))),
            strtoupper(trim((string)($row['tracking_no'] ?? ''))),
            strtoupper(trim((string)($row['parcel_uid'] ?? ''))),
        ];

        $found = false;
        foreach ($candidateIds as $candidateId) {
            if ($candidateId !== '' && isset($reportIdentifiers[$candidateId])) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $row['sync_status_class'] = 'text-success';
            $row['sync_status_label'] = 'Готов к синхронизации';
            $row['can_sync'] = 1;
            $missing[] = $row;
        }
    }

    $total = count($missing);
    $paged = $missing;
    if ($limit !== null) {
        $paged = array_slice($missing, $offset, $limit);
    } elseif ($offset > 0) {
        $paged = [];
    }

    $smarty->assign('sync_missing_items', $paged);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', $offset === 0);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_sync_missing_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html' => $html,
        'total' => $total,
        'items_count' => count($paged),
        'has_more' => $limit !== null ? ($offset + count($paged) < $total) : false,
    ];
}




if ($action === 'warehouse_sync_item') {
    auth_require_login();
    $current = $user;
    $userId = (int)($current['id'] ?? 0);

    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        $response = ['status' => 'error', 'message' => 'item_id required'];
        return;
    }

    $item = warehouse_sync_fetch_item($dbcnx, $itemId);
    if (!$item) {
        $response = ['status' => 'error', 'message' => 'Посылка не найдена', 'item_id' => $itemId];
        return;
    }

    $forwarder = strtoupper(trim((string)($item['receiver_company'] ?? '')));
    $country = strtoupper(trim((string)($item['receiver_country_code'] ?? '')));
    $tracking = trim((string)($item['tracking_no'] ?? ''));

    try {
        $connector = warehouse_sync_fetch_connector($dbcnx, $forwarder, $country);
        if (!$connector) {
            throw new RuntimeException('Не найден активный коннектор для форварда ' . $forwarder);
        }

        $result = warehouse_sync_run_submission($connector, $item);

        $responsePayload = [
            'message' => $result['message'],
            'connector_id' => (int)($connector['id'] ?? 0),
            'step_log' => $result['step_log'],
            'artifacts_dir' => $result['artifacts_dir'],
            'vars' => $result['vars'],
        ];
        $responseJson = json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
        warehouse_sync_audit_log($dbcnx, [
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'status' => 'success',
            'message' => 'sync ok',
            'response_json' => $responseJson === false ? '' : $responseJson,
            'created_by' => $userId,
        ]);

        audit_log($userId, 'WAREHOUSE_SYNC_ITEM_SUCCESS', 'warehouse_item_stock', $itemId, 'Синхронизация посылки выполнена', [
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'connector_id' => (int)($connector['id'] ?? 0),
        ]);

        $response = [
            'status' => 'ok',
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'message' => 'sync выполнен успешно',
            'step_log' => $result['step_log'],
            'artifacts_dir' => $result['artifacts_dir'],
        ];
    } catch (Throwable $e) {
        $errorPayload = ['error' => $e->getMessage()];
        $errorJson = json_encode($errorPayload, JSON_UNESCAPED_UNICODE);
        warehouse_sync_audit_log($dbcnx, [
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'status' => 'error',
            'message' => $e->getMessage(),
            'response_json' => $errorJson === false ? '' : $errorJson,
            'created_by' => $userId,
        ]);

        audit_log($userId, 'WAREHOUSE_SYNC_ITEM_ERROR', 'warehouse_item_stock', $itemId, 'Ошибка синхронизации посылки', [
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'error' => $e->getMessage(),
        ]);

        $response = [
            'status' => 'error',
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'message' => 'sync ошибка: ' . $e->getMessage(),
        ];
    }
}

if ($action === 'warehouse_sync_history') {
    auth_require_login();
    warehouse_sync_ensure_audit_table($dbcnx);

    $rows = [];
    $sql = "SELECT a.id, a.item_id, a.tracking_no, a.forwarder, a.country_code, a.status, a.message, a.created_at, u.full_name AS user_name
"
        . "FROM warehouse_sync_audit a
"
        . "LEFT JOIN users u ON u.id = a.created_by
"
        . "ORDER BY a.id DESC
"
        . "LIMIT 200";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }

    $response = [
        'status' => 'ok',
        'rows' => $rows,
        'total' => count($rows),
    ];
}




if ($action === 'warehouse_sync_reports') {
    auth_require_login();

    $tableName = 'connector_report_colibri_az';
    $items = [];

    if (warehouse_sync_table_exists($dbcnx, $tableName)) {
        $columns = warehouse_sync_table_columns($dbcnx, $tableName);
        $trackingColumn = warehouse_sync_find_column($columns, ['tracking_no', 'tracking_number', 'tracking', 'track_no', 'track_number', 'tuid']);
        $countryColumn = warehouse_sync_find_column($columns, ['receiver_country_code', 'country_code', 'country', 'destination_country']);
        $dateColumn = warehouse_sync_find_column($columns, ['created_at', 'date_created', 'datetime_created', 'updated_at']);

        if ($trackingColumn !== '' && $countryColumn !== '') {
            $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
            $safeTracking = '`' . str_replace('`', '``', $trackingColumn) . '`';
            $safeCountry = '`' . str_replace('`', '``', $countryColumn) . '`';
            $safeDate = $dateColumn !== '' ? ('`' . str_replace('`', '``', $dateColumn) . '`') : 'NULL';

            $reportMap = [];
            $sql = "
                SELECT {$safeTracking} AS tracking_no, {$safeCountry} AS country_code, {$safeDate} AS report_created_at
                FROM {$safeTable}
                WHERE {$safeTracking} IS NOT NULL AND TRIM({$safeTracking}) <> ''
                  AND {$safeCountry} IS NOT NULL AND TRIM({$safeCountry}) <> ''
                ORDER BY " . ($dateColumn !== '' ? "{$safeDate} DESC" : "1") . "
            ";
            if ($res = $dbcnx->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $trackingNo = strtoupper(trim((string)($row['tracking_no'] ?? '')));
                    $countryCode = warehouse_sync_normalize_key((string)($row['country_code'] ?? ''));
                    if ($trackingNo === '' || $countryCode === '') {
                        continue;
                    }
                    $key = $trackingNo . '|' . $countryCode;
                    if (!isset($reportMap[$key])) {
                        $reportMap[$key] = [
                            'tracking_no' => $trackingNo,
                            'country_code' => $countryCode,
                            'report_created_at' => (string)($row['report_created_at'] ?? ''),
                        ];
                    }
                }
                $res->free();
            }

            if (!empty($reportMap)) {
                $sqlWarehouse = "
                    SELECT
                        wi.tuid,
                        wi.tracking_no,
                        wi.receiver_name,
                        wi.receiver_country_code,
                        wi.receiver_company,
                        wi.created_at,
                        c.code AS cell_address,
                        COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid
                    FROM warehouse_item_stock wi
                    LEFT JOIN cells c ON c.id = wi.cell_id
                    WHERE wi.cell_id IS NOT NULL
                      AND wi.receiver_company IS NOT NULL
                      AND TRIM(wi.receiver_company) <> ''
                    ORDER BY wi.created_at DESC
                ";
                if ($res = $dbcnx->query($sqlWarehouse)) {
                    while ($row = $res->fetch_assoc()) {
                        $countryCode = warehouse_sync_normalize_key((string)($row['receiver_country_code'] ?? ''));
                        $candidateIds = [
                            strtoupper(trim((string)($row['tracking_no'] ?? ''))),
                            strtoupper(trim((string)($row['tuid'] ?? ''))),
                            strtoupper(trim((string)($row['parcel_uid'] ?? ''))),
                        ];
                        foreach ($candidateIds as $candidateId) {
                            if ($candidateId === '' || $countryCode === '') {
                                continue;
                            }
                            $key = $candidateId . '|' . $countryCode;
                            if (isset($reportMap[$key])) {
                                $row['report_table'] = $tableName;
                                $row['report_created_at'] = $reportMap[$key]['report_created_at'];
                                $items[] = $row;
                                break;
                            }
                        }
                    }
                    $res->free();
                }
            }
        }
    }

    $smarty->assign('sync_reported_items', $items);
    ob_start();
    $smarty->display('cells_NA_API_warehouse_sync_reports_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html' => $html,
        'total' => count($items),
    ];
}
