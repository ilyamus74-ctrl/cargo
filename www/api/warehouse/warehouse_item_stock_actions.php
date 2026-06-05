<?php
declare(strict_types=1);
/**
 * Обработчик действий с остатками на складе
 * Actions:  item_stock
 */
// Доступны:  $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse stock action'];
require_once __DIR__ . '/warehouse_forwarder_registration_helpers.php';


if (!function_exists('warehouse_stock_ensure_addons_column')) {
    function warehouse_stock_ensure_addons_column(mysqli $dbcnx): void
    {
        $check = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE 'addons_json'");
        if ($check instanceof mysqli_result) {
            $exists = $check->num_rows > 0;
            $check->free();
            if ($exists) {
                return;
            }
        }

        $dbcnx->query("ALTER TABLE warehouse_item_stock ADD COLUMN addons_json LONGTEXT NULL AFTER box_image");
    }
}

if (!function_exists('warehouse_stock_decode_connector_addons')) {
    function warehouse_stock_decode_connector_addons(string $rawAddons): array
    {
        $decoded = json_decode($rawAddons, true);
        if (!is_array($decoded)) {
            return [];
        }

        $extra = $decoded['extra'] ?? [];
        return is_array($extra) ? $extra : [];
    }
}

if (!function_exists('warehouse_stock_decode_item_addons')) {
    function warehouse_stock_decode_item_addons(string $rawAddons): array
    {
        if ($rawAddons === '') {
            return [];
        }
        $decoded = json_decode($rawAddons, true);
        return is_array($decoded) ? $decoded : [];
    }
}



if (!function_exists('warehouse_stock_has_out_table')) {
    function warehouse_stock_has_out_table(mysqli $dbcnx): bool
    {
        $res = $dbcnx->query("SHOW TABLES LIKE 'warehouse_item_out'");
        if (!($res instanceof mysqli_result)) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('warehouse_stock_normalize_image_json')) {
    function warehouse_stock_normalize_image_json(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $clean = [];
        foreach ($decoded as $path) {
            if (!is_string($path)) {
                continue;
            }
            $path = trim($path);
            if ($path === '') {
                continue;
            }
            $clean[] = $path;
        }

        if (empty($clean)) {
            return null;
        }

        $encoded = json_encode(array_values($clean), JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : null;
    }
}

if (!function_exists('warehouse_stock_decode_image_paths')) {
    function warehouse_stock_decode_image_paths(?string $raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $path) {
            if (!is_string($path)) {
                continue;
            }
            $path = trim($path);
            if ($path === '') {
                continue;
            }
            $result[] = $path;
        }

        return array_values(array_unique($result));
    }
}

if (!function_exists('warehouse_stock_ensure_photo_dir')) {
    function warehouse_stock_ensure_photo_dir(string $absDir): bool
    {
        if (is_dir($absDir)) {
            return true;
        }
        return @mkdir($absDir, 0775, true);
    }
}


if (!function_exists('warehouse_stock_table_exists')) {
    function warehouse_stock_table_exists(mysqli $dbcnx, string $table): bool
    {
        static $cache = [];
        $key = $table;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $safe = $dbcnx->real_escape_string($table);
        $res = $dbcnx->query("SHOW TABLES LIKE '{$safe}'");
        if (!($res instanceof mysqli_result)) {
            $cache[$key] = false;
            return false;
        }
        $cache[$key] = $res->num_rows > 0;
        $res->free();
        return $cache[$key];
    }
}

if (!function_exists('warehouse_stock_column_exists')) {
    function warehouse_stock_column_exists(mysqli $dbcnx, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (!warehouse_stock_table_exists($dbcnx, $table)) {
            $cache[$key] = false;
            return false;
        }
        $safeTable = str_replace('`', '``', $table);
        $safeColumn = $dbcnx->real_escape_string($column);
        $res = $dbcnx->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!($res instanceof mysqli_result)) {
            $cache[$key] = false;
            return false;
        }
        $cache[$key] = $res->num_rows > 0;
        $res->free();
        return $cache[$key];
    }
}

if (!function_exists('warehouse_stock_registry_col')) {
    function warehouse_stock_registry_col(mysqli $dbcnx, string $table, string $alias, string $column, string $fallback = 'NULL'): string
    {
        return warehouse_stock_column_exists($dbcnx, $table, $column) ? "{$alias}.{$column}" : $fallback;
    }
}

if (!function_exists('warehouse_stock_stmt_fetch_all')) {
    function warehouse_stock_stmt_fetch_all(mysqli $dbcnx, string $sql, string $types = '', array $params = []): array
    {
        $rows = [];
        if ($types === '') {
            $res = $dbcnx->query($sql);
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
            return $rows;
        }

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('warehouse_stock_registry_bind_concat')) {
    function warehouse_stock_registry_bind_concat(string &$types, array &$params, string $addTypes, array $addParams): void
    {
        $types .= $addTypes;
        foreach ($addParams as $param) {
            $params[] = $param;
        }
    }
}


if (!function_exists('warehouse_stock_history_mask_sensitive')) {
    function warehouse_stock_history_mask_sensitive($data)
    {
        if (is_array($data)) {
            $masked = [];
            foreach ($data as $key => $value) {
                $keyString = is_string($key) ? $key : (string)$key;
                if (preg_match('/(^|_)(password|auth_password|token|csrf|cookie)$|_token$/i', $keyString)) {
                    $masked[$key] = '***';
                    continue;
                }
                $masked[$key] = warehouse_stock_history_mask_sensitive($value);
            }
            return $masked;
        }

        return $data;
    }
}

if (!function_exists('warehouse_stock_history_details_json')) {
    function warehouse_stock_history_details_json(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $masked = warehouse_stock_history_mask_sensitive($decoded);
            $encoded = json_encode($masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded !== false ? $encoded : $raw;
        }

        return $raw;
    }
}

if (!function_exists('warehouse_stock_history_source_label')) {
    function warehouse_stock_history_source_label(string $source): string
    {
        switch ($source) {
            case 'audit':
                return 'Аудит';
            case 'sync_audit':
                return 'Синхронизация';
            case 'forwarder_report':
                return 'Репорт форварда';
            case 'stock':
                return 'Склад';
            case 'forwarder_registration':
                return 'Регистрация у форварда';
            case 'stock_out':
                return 'Отгрузка';
            default:
                return $source;
        }
    }
}

if (!function_exists('warehouse_stock_history_normalize_event')) {
    function warehouse_stock_history_normalize_event(array $event): array
    {
        $source = (string)($event['source'] ?? '');
        return [
            'event_time' => (string)($event['event_time'] ?? ''),
            'source' => $source,
            'source_label' => warehouse_stock_history_source_label($source),
            'title' => (string)($event['title'] ?? 'Событие'),
            'description' => (string)($event['description'] ?? ''),
            'actor_name' => (string)($event['actor_name'] ?? ''),
            'actor_id' => (string)($event['actor_id'] ?? ''),
            'details_json' => warehouse_stock_history_details_json(isset($event['details_json']) ? (string)$event['details_json'] : ''),
            'report_table' => (string)($event['report_table'] ?? ''),
            'report_row_id' => (string)($event['report_row_id'] ?? ''),
            'source_file' => (string)($event['source_file'] ?? ''),
        ];
    }
}

if (!function_exists('warehouse_stock_history_json_extract_available')) {
    function warehouse_stock_history_json_extract_available(mysqli $dbcnx): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        $res = @$dbcnx->query("SELECT JSON_EXTRACT('{\"item_id\":1}', '$.item_id') AS value");
        $available = $res instanceof mysqli_result;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $available;
    }
}

if (!function_exists('warehouse_stock_history_audit_events_fast')) {
    function warehouse_stock_history_audit_events_fast(mysqli $dbcnx, array $item): array
    {
        if (!warehouse_stock_table_exists($dbcnx, 'audit_logs')) {
            return [];
        }

        $itemId = (int)($item['id'] ?? 0);
        if ($itemId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                al.event_time AS event_time,
                'audit' AS source,
                al.event_type AS title,
                al.description AS description,
                al.extra_data AS details_json,
                COALESCE(u.full_name, '') AS actor_name,
                al.user_id AS actor_id
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.entity_type = 'WAREHOUSE_STOCK'
              AND al.entity_id = ?
            ORDER BY al.event_time DESC
            LIMIT 50
        ";

        return warehouse_stock_stmt_fetch_all($dbcnx, $sql, 'i', [$itemId]);
    }
}

if (!function_exists('warehouse_stock_history_stock_events')) {
    function warehouse_stock_history_stock_events(mysqli $dbcnx, array $item): array
    {
        $events = [];
        $itemId = (int)($item['id'] ?? 0);
        $createdAt = trim((string)($item['created_at'] ?? ''));

        if ($createdAt !== '') {
            $details = [
                'item_id' => $itemId,
                'batch_uid' => (string)($item['batch_uid'] ?? ''),
                'uid_created' => (string)($item['uid_created'] ?? ''),
                'tuid' => (string)($item['tuid'] ?? ''),
                'tracking_no' => (string)($item['tracking_no'] ?? ''),
            ];
            $events[] = [
                'event_time' => $createdAt,
                'source' => 'stock',
                'title' => 'Посылка на складе',
                'description' => 'Карточка warehouse_item_stock создана',
                'actor_name' => '',
                'actor_id' => '',
                'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ];
        }

        $forwarderRegisteredAt = trim((string)($item['forwarder_registered_at'] ?? ''));
        if ($forwarderRegisteredAt !== '') {
            $status = trim((string)($item['forwarder_registration_status'] ?? ''));
            $message = trim((string)($item['forwarder_registration_message'] ?? ''));
            $events[] = [
                'event_time' => $forwarderRegisteredAt,
                'source' => 'forwarder_registration',
                'title' => 'Регистрация у форварда' . ($status !== '' ? ': ' . $status : ''),
                'description' => $message,
                'actor_name' => '',
                'actor_id' => '',
                'details_json' => (string)($item['forwarder_registration_response_json'] ?? ''),
            ];
        }

        if ($itemId > 0 && warehouse_stock_table_exists($dbcnx, 'warehouse_item_out')) {
            $statusUpdatedAt = warehouse_stock_column_exists($dbcnx, 'warehouse_item_out', 'status_updated_at') ? 'status_updated_at' : 'NULL AS status_updated_at';
            $containerName = warehouse_stock_column_exists($dbcnx, 'warehouse_item_out', 'shipped_container_name') ? 'shipped_container_name' : "'' AS shipped_container_name";
            $flightNo = warehouse_stock_column_exists($dbcnx, 'warehouse_item_out', 'shipped_flight_no') ? 'shipped_flight_no' : "'' AS shipped_flight_no";
            $sql = "
                SELECT
                    id,
                    status,
                    created_at,
                    {$statusUpdatedAt},
                    {$containerName},
                    {$flightNo}
                FROM warehouse_item_out
                WHERE stock_item_id = ?
                ORDER BY id DESC
                LIMIT 5
            ";
            $rows = warehouse_stock_stmt_fetch_all($dbcnx, $sql, 'i', [$itemId]);
            foreach ($rows as $row) {
                $status = trim((string)($row['status'] ?? ''));
                $eventTime = trim((string)($row['status_updated_at'] ?? '')) ?: trim((string)($row['created_at'] ?? ''));
                $details = [
                    'warehouse_item_out_id' => (int)($row['id'] ?? 0),
                    'status' => $status,
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'status_updated_at' => (string)($row['status_updated_at'] ?? ''),
                    'shipped_container_name' => (string)($row['shipped_container_name'] ?? ''),
                    'shipped_flight_no' => (string)($row['shipped_flight_no'] ?? ''),
                ];
                $descriptionParts = [];
                if (trim((string)($row['shipped_container_name'] ?? '')) !== '') {
                    $descriptionParts[] = 'Контейнер: ' . trim((string)$row['shipped_container_name']);
                }
                if (trim((string)($row['shipped_flight_no'] ?? '')) !== '') {
                    $descriptionParts[] = 'Рейс: ' . trim((string)$row['shipped_flight_no']);
                }
                $events[] = [
                    'event_time' => $eventTime,
                    'source' => 'stock_out',
                    'title' => 'Отгрузка' . ($status !== '' ? ': ' . $status : ''),
                    'description' => implode(' / ', $descriptionParts),
                    'actor_name' => '',
                    'actor_id' => '',
                    'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                ];
            }
        }

        return $events;
    }
}

if (!function_exists('warehouse_stock_history_audit_events')) {
    function warehouse_stock_history_audit_events(mysqli $dbcnx, array $item): array
    {
        if (!warehouse_stock_table_exists($dbcnx, 'audit_logs')) {
            return [];
        }

        $itemId = (int)($item['id'] ?? 0);
        $trackingNo = trim((string)($item['tracking_no'] ?? ''));
        $tuid = trim((string)($item['tuid'] ?? ''));

        $conditions = ['(al.entity_type = ? AND al.entity_id = ?)'];
        $types = 'si';
        $params = ['WAREHOUSE_STOCK', $itemId];

        if (warehouse_stock_history_json_extract_available($dbcnx)) {
            $conditions[] = "(JSON_VALID(al.extra_data) AND CAST(JSON_UNQUOTE(JSON_EXTRACT(al.extra_data, '$.item_id')) AS UNSIGNED) = ?)";
            $conditions[] = "(JSON_VALID(al.extra_data) AND CAST(JSON_UNQUOTE(JSON_EXTRACT(al.extra_data, '$.stock_item_id')) AS UNSIGNED) = ?)";
            $types .= 'ii';
            $params[] = $itemId;
            $params[] = $itemId;
            foreach ([$trackingNo, $tuid] as $track) {
                if ($track === '') {
                    continue;
                }
                foreach ([
                    '$.tracking_no',
                    '$.tracking_number',
                    '$.tuid',
                    '$.response.tracking_no',
                    '$.response.tracking_number',
                ] as $jsonPath) {
                    $conditions[] = "(JSON_VALID(al.extra_data) AND JSON_UNQUOTE(JSON_EXTRACT(al.extra_data, '{$jsonPath}')) = ?)";
                    $types .= 's';
                    $params[] = $track;
                }
            }
        }

        foreach ([$trackingNo, $tuid] as $track) {
            if ($track === '') {
                continue;
            }
            foreach (['tracking_number', 'tracking_no', 'tuid'] as $key) {
                foreach ([false, true] as $spaceAfterColon) {
                    $conditions[] = 'al.extra_data LIKE ?';
                    $types .= 's';
                    $params[] = '%"' . $key . '":' . ($spaceAfterColon ? ' ' : '') . '"' . $track . '"%';
                }
            }
        }

        $sql = "
            SELECT
                al.event_time AS event_time,
                'audit' AS source,
                al.event_type AS title,
                al.description AS description,
                al.entity_type,
                al.entity_id,
                al.extra_data AS details_json,
                COALESCE(u.full_name, '') AS actor_name,
                al.user_id AS actor_id
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE " . implode(' OR ', $conditions) . "
            ORDER BY al.event_time DESC
            LIMIT 80
        ";

        return warehouse_stock_stmt_fetch_all($dbcnx, $sql, $types, $params);
    }
}

if (!function_exists('warehouse_stock_history_sync_audit_events')) {
    function warehouse_stock_history_sync_audit_events(mysqli $dbcnx, array $item): array
    {
        if (!warehouse_stock_table_exists($dbcnx, 'warehouse_sync_audit')) {
            return [];
        }

        $itemId = (int)($item['id'] ?? 0);
        $trackingNo = trim((string)($item['tracking_no'] ?? ''));
        $tuid = trim((string)($item['tuid'] ?? ''));

        $conditions = ['item_id = ?'];
        $types = 'i';
        $params = [$itemId];
        foreach ([$trackingNo, $tuid] as $track) {
            if ($track === '') {
                continue;
            }
            $conditions[] = 'tracking_no = ?';
            $types .= 's';
            $params[] = $track;
        }

        $actorSelect = warehouse_stock_column_exists($dbcnx, 'warehouse_sync_audit', 'created_by') ? 'wsa.created_by' : 'NULL';
        $responseSelect = warehouse_stock_column_exists($dbcnx, 'warehouse_sync_audit', 'response_json') ? 'wsa.response_json' : 'NULL';
        $messageSelect = warehouse_stock_column_exists($dbcnx, 'warehouse_sync_audit', 'message') ? 'wsa.message' : "''";
        $statusSelect = warehouse_stock_column_exists($dbcnx, 'warehouse_sync_audit', 'status') ? 'wsa.status' : "'sync'";

        $sql = "
            SELECT
                wsa.created_at AS event_time,
                'sync_audit' AS source,
                {$statusSelect} AS title,
                {$messageSelect} AS description,
                {$responseSelect} AS details_json,
                COALESCE(u.full_name, '') AS actor_name,
                {$actorSelect} AS actor_id
            FROM warehouse_sync_audit wsa
            LEFT JOIN users u ON u.id = {$actorSelect}
            WHERE " . implode(' OR ', $conditions) . "
            ORDER BY wsa.created_at DESC
            LIMIT 50
        ";

        return warehouse_stock_stmt_fetch_all($dbcnx, $sql, $types, $params);
    }
}

if (!function_exists('warehouse_stock_history_report_table_has_payload')) {
    function warehouse_stock_history_report_table_has_payload(mysqli $dbcnx, string $table): bool
    {
        return preg_match('/^connector_report_[a-z0-9_]+$/i', $table) === 1
            && warehouse_stock_table_exists($dbcnx, $table)
            && warehouse_stock_column_exists($dbcnx, $table, 'id')
            && warehouse_stock_column_exists($dbcnx, $table, 'payload_json');
    }
}

if (!function_exists('warehouse_stock_history_report_table_candidates')) {
    function warehouse_stock_history_report_table_candidates(mysqli $dbcnx, array $item): array
    {

        $forwarder = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)($item['receiver_company'] ?? ''))));
        $forwarder = trim($forwarder, '_');
        $country = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)($item['receiver_country_code'] ?? ''))));
        $country = trim($country, '_');

        $tables = [];
        if ($forwarder !== '' && $country !== '') {
            $exact = 'connector_report_' . $forwarder . '_' . $country;
            if (warehouse_stock_history_report_table_has_payload($dbcnx, $exact)) {
                return [$exact];
            }
        }

        if ($forwarder !== '') {
            $safePrefix = $dbcnx->real_escape_string('connector_report_' . $forwarder . '%');
            $res = $dbcnx->query("SHOW TABLES LIKE '{$safePrefix}'");
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_row()) {
                    $table = (string)($row[0] ?? '');
                    if (warehouse_stock_history_report_table_has_payload($dbcnx, $table)) {
                        $tables[] = $table;
                    }
                }
                $res->free();
            }
            if (!empty($tables)) {
                sort($tables, SORT_STRING);
                return array_values(array_unique($tables));
            }
        }
        $res = $dbcnx->query("SHOW TABLES LIKE 'connector_report_%'");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_row()) {
                $table = (string)($row[0] ?? '');
                if (warehouse_stock_history_report_table_has_payload($dbcnx, $table)) {
                    $tables[] = $table;
                }
                if (count($tables) >= 3) {
                    break;
                }
            }
            $res->free();
        }

        return array_values(array_unique($tables));
    }
}

if (!function_exists('warehouse_stock_history_report_description')) {
    function warehouse_stock_history_report_description(array $payload): string
    {
        $parts = [];
        $keys = [
            'status' => 'Статус',
            'storage' => 'storage',
            'container' => 'container',
            'container_no' => 'container',
            'flight' => 'flight',
            'flight_no' => 'flight',
        ];
        foreach ($keys as $key => $label) {
            if (!isset($payload[$key]) || trim((string)$payload[$key]) === '') {
                continue;
            }
            $parts[] = $label . ': ' . (string)$payload[$key];
        }
        return implode(' / ', array_values(array_unique($parts)));
    }
}

if (!function_exists('warehouse_stock_history_forwarder_report_events')) {
    function warehouse_stock_history_forwarder_report_events(mysqli $dbcnx, array $item): array
    {
        $trackingNo = trim((string)($item['tracking_no'] ?? ''));
        $tuid = trim((string)($item['tuid'] ?? ''));
        $needles = array_values(array_unique(array_filter([$trackingNo, $tuid], static fn($value) => $value !== '')));
        if (empty($needles)) {
            return [];
        }

        $events = [];
        foreach (warehouse_stock_history_report_table_candidates($dbcnx, $item) as $table) {
            if (preg_match('/^connector_report_[a-z0-9_]+$/i', $table) !== 1) {
                continue;
            }
            $safeTable = str_replace('`', '``', $table);
            $selectSourceFile = warehouse_stock_column_exists($dbcnx, $table, 'source_file') ? 'source_file' : "'' AS source_file";
            $selectCreatedAt = warehouse_stock_column_exists($dbcnx, $table, 'created_at') ? 'created_at' : 'NULL AS created_at';
            $selectLastSeenAt = warehouse_stock_column_exists($dbcnx, $table, 'last_seen_at') ? 'last_seen_at' : 'NULL AS last_seen_at';

            $conditions = [];
            $types = '';
            $params = [];
            foreach ($needles as $needle) {
                $conditions[] = 'recent.payload_json LIKE ?';
                $types .= 's';
                $params[] = '%' . $needle . '%';
            }

            $sql = "
                SELECT *
                FROM (
                    SELECT
                        id,
                        payload_json,
                        {$selectSourceFile},
                        {$selectCreatedAt},
                        {$selectLastSeenAt}
                    FROM `{$safeTable}`
                    ORDER BY id DESC
                    LIMIT 5000
                ) recent
                WHERE " . implode(' OR ', $conditions) . "
                ORDER BY COALESCE(recent.last_seen_at, recent.created_at) DESC, recent.id DESC
                LIMIT 30
            ";
            $rows = warehouse_stock_stmt_fetch_all($dbcnx, $sql, $types, $params);
            foreach ($rows as $row) {
                $payloadRaw = (string)($row['payload_json'] ?? '');
                $payload = json_decode($payloadRaw, true);
                if (!is_array($payload)) {
                    $payload = [];
                }
                $status = trim((string)($payload['status'] ?? ''));
                $events[] = [
                    'event_time' => (string)($row['last_seen_at'] ?: ($row['created_at'] ?? '')),
                    'source' => 'forwarder_report',
                    'title' => 'Репорт форварда' . ($status !== '' ? ': ' . $status : ''),
                    'description' => warehouse_stock_history_report_description($payload),
                    'actor_name' => 'forwarder_report_bot',
                    'actor_id' => '',
                    'details_json' => $payloadRaw,
                    'report_table' => $table,
                    'report_row_id' => (string)($row['id'] ?? ''),
                    'source_file' => (string)($row['source_file'] ?? ''),
                ];
            }
        }

        return $events;
    }
}

if ($action === 'item_stock') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;
    $batches = [];
    if ($canViewAll) {
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count,
                wi.user_id,
                u.full_name        AS user_name
            FROM warehouse_item_stock wi
            LEFT JOIN users u ON u.id = wi.user_id
            GROUP BY wi.batch_uid, wi.user_id
            ORDER BY started_at DESC
        ";
        //
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $batches[] = $row;
            }
            $res->free();
        }
    } else {
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count
            FROM warehouse_item_stock wi
            WHERE wi.user_id = ? 
            GROUP BY wi.batch_uid
            ORDER BY started_at DESC
        ";
//            GROUP BY wi.batch_uid
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
    }
    $smarty->assign('batches',      $batches);
    $smarty->assign('current_user', $current);
    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock.html');
    $html = ob_get_clean();
    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
}


if ($action === 'warehouse_items_registry') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();

    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $allowedStates = ['all', 'without_cells', 'to_send', 'in_storage', 'without_addons', 'registration_errors', 'not_registered', 'registered', 'sended'];
    $warehouseState = trim((string)($_POST['warehouse_state'] ?? 'all'));
    if (!in_array($warehouseState, $allowedStates, true)) {
        $warehouseState = 'all';
    }

    $allowedSources = ['all', 'in', 'stock', 'out'];
    $sourceTable = trim((string)($_POST['source_table'] ?? 'all'));
    if (!in_array($sourceTable, $allowedSources, true)) {
        $sourceTable = 'all';
    }

    $allowedForwarderStatuses = ['all', 'empty', 'ok', 'validation_error', 'error', 'forwarder_error', 'connector_error', 'skipped'];
    $forwarderStatus = trim((string)($_POST['forwarder_registration_status'] ?? 'all'));
    if (!in_array($forwarderStatus, $allowedForwarderStatuses, true)) {
        $forwarderStatus = 'all';
    }

    $allowedRegistered = ['all', 'filled', 'empty'];
    $registeredFilter = trim((string)($_POST['forwarder_registered_filter'] ?? 'all'));
    if (!in_array($registeredFilter, $allowedRegistered, true)) {
        $registeredFilter = 'all';
    }

    $allowedLimits = ['50' => 50, '100' => 100, '200' => 200];
    $limitRaw = trim((string)($_POST['limit'] ?? '50'));
    $limit = null;
    if ($limitRaw !== 'all') {
        $limit = $allowedLimits[$limitRaw] ?? 50;
    }
    $offset = max(0, (int)($_POST['offset'] ?? 0));

    $sortRaw = strtoupper(trim((string)($_POST['sort'] ?? 'DESC')));
    $sort = $sortRaw === 'ASC' ? 'ASC' : 'DESC';
    $search = trim((string)($_POST['search'] ?? ''));

    $hasOutTable = warehouse_stock_table_exists($dbcnx, 'warehouse_item_out');
    $hasInTable = warehouse_stock_table_exists($dbcnx, 'warehouse_item_in');
    $hasConnectorsAddons = warehouse_stock_table_exists($dbcnx, 'connectors_addons');

    $stockBatch = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'batch_uid');
    $stockUidCreated = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'uid_created', '0');
    $stockUserId = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'user_id', '0');
    $stockTuid = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'tuid', "''");
    $stockTracking = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'tracking_no', "''");
    $stockReceiverName = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'receiver_name', "''");
    $stockReceiverCompany = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'receiver_company', "''");
    $stockCarrierName = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'carrier_name', "''");
    $stockCreatedAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'created_at');
    $stockCellId = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'cell_id');
    $stockAddons = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'addons_json');
    $stockRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'forwarder_registered_at');
    $stockRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'forwarder_registration_status', "''");
    $stockRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'forwarder_registration_message', "''");
    $stockRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'wi', 'forwarder_registration_response_json');
    $stockOutJoin = $hasOutTable ? 'LEFT JOIN warehouse_item_out wo ON wo.stock_item_id = wi.id' : '';
    $stockOutStatus = $hasOutTable ? warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'status', "''") : "''";
    $stockContainer = $hasOutTable ? warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'shipped_container_name', "''") : "''";
    $stockHasAddons = $hasConnectorsAddons ? "EXISTS (
                    SELECT 1
                      FROM connectors_addons ca
                     WHERE ca.connector_name = COALESCE(NULLIF({$stockReceiverCompany}, ''), {$stockCarrierName})
                       AND ca.addons_json IS NOT NULL
                       AND TRIM(ca.addons_json) <> ''
                       AND TRIM(ca.addons_json) <> '{}'
                       AND TRIM(ca.addons_json) <> '[]'
                )" : '0';

    $subqueries = [];
    $subqueries[] = "
        SELECT
            'stock' AS source_table,
            wi.id AS item_id,
            wi.id AS stock_item_id,
            {$stockBatch} AS batch_uid,
            {$stockUidCreated} AS uid_created,
            {$stockUserId} AS user_id,
            {$stockTuid} AS tuid,
            {$stockTracking} AS tracking_no,
            {$stockReceiverName} AS receiver_name,
            {$stockReceiverCompany} AS receiver_company,
            COALESCE(NULLIF({$stockReceiverCompany}, ''), NULLIF({$stockCarrierName}, '')) AS forwarder_name,
            {$stockCreatedAt} AS created_at,
            {$stockCellId} AS cell_id,
            c.code AS cell_address,
            {$stockContainer} AS container_name,
            {$stockOutStatus} AS out_status,
            CASE
                WHEN LOWER(TRIM(COALESCE({$stockOutStatus}, ''))) = 'sended' THEN 'sended'
                WHEN LOWER(TRIM(COALESCE({$stockOutStatus}, ''))) = 'to_send' THEN 'to_send'
                WHEN {$stockCellId} IS NULL THEN 'without_cells'
                ELSE 'in_storage'
            END AS warehouse_state,
            {$stockRegisteredAt} AS forwarder_registered_at,
            {$stockRegStatus} AS forwarder_registration_status,
            {$stockRegMessage} AS forwarder_registration_message,
            {$stockRegResponse} AS forwarder_registration_response_json,
            CASE
                WHEN ({$stockAddons} IS NULL OR TRIM({$stockAddons}) = '' OR TRIM({$stockAddons}) = '{}' OR TRIM({$stockAddons}) = '[]')
                 AND {$stockHasAddons}
                THEN 1 ELSE 0
            END AS is_without_addons,
            u.full_name AS user_name
        FROM warehouse_item_stock wi
        {$stockOutJoin}
        LEFT JOIN cells c ON c.id = {$stockCellId}
        LEFT JOIN users u ON u.id = {$stockUserId}
    ";

    if ($hasInTable) {
        $inBatch = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'batch_uid');
        $inUidCreated = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'uid_created', '0');
        $inUserId = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'user_id', '0');
        $inTuid = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'tuid', "''");
        $inTracking = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'tracking_no', "''");
        $inReceiverName = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'receiver_name', "''");
        $inReceiverCompany = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'receiver_company', "''");
        $inCarrierName = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'carrier_name', "''");
        $inCreatedAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'created_at');
        $inRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'forwarder_registered_at');
        $inRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'forwarder_registration_status', "''");
        $inRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'forwarder_registration_message', "''");
        $inRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_in', 'wii', 'forwarder_registration_response_json');
        $inCommittedWhere = warehouse_stock_column_exists($dbcnx, 'warehouse_item_in', 'committed') ? 'WHERE COALESCE(wii.committed, 0) = 0' : '';
        $subqueries[] = "
            SELECT
                'in' AS source_table,
                wii.id AS item_id,
                NULL AS stock_item_id,
                {$inBatch} AS batch_uid,
                {$inUidCreated} AS uid_created,
                {$inUserId} AS user_id,
                {$inTuid} AS tuid,
                {$inTracking} AS tracking_no,
                {$inReceiverName} AS receiver_name,
                {$inReceiverCompany} AS receiver_company,
                COALESCE(NULLIF({$inReceiverCompany}, ''), NULLIF({$inCarrierName}, '')) AS forwarder_name,
                {$inCreatedAt} AS created_at,
                NULL AS cell_id,
                NULL AS cell_address,
                NULL AS container_name,
                NULL AS out_status,
                'in_progress' AS warehouse_state,
                {$inRegisteredAt} AS forwarder_registered_at,
                {$inRegStatus} AS forwarder_registration_status,
                {$inRegMessage} AS forwarder_registration_message,
                {$inRegResponse} AS forwarder_registration_response_json,
                0 AS is_without_addons,
                u.full_name AS user_name
            FROM warehouse_item_in wii
            LEFT JOIN users u ON u.id = {$inUserId}
            {$inCommittedWhere}
        ";
    }

    if ($hasOutTable) {
        $outBatch = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'batch_uid', $stockBatch);
        $outUidCreated = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'uid_created', $stockUidCreated);
        $outTuid = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'tuid', $stockTuid);
        $outTracking = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'tracking_no', $stockTracking);
        $outReceiverCompany = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'receiver_company', $stockReceiverCompany);
        $outCreatedAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'created_at');
        $outStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'status', "''");
        $outContainer = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'shipped_container_name', "''");
        $outRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'forwarder_registered_at', $stockRegisteredAt);
        $outRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'forwarder_registration_status', $stockRegStatus);
        $outRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'forwarder_registration_message', $stockRegMessage);
        $outRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_out', 'wo', 'forwarder_registration_response_json', $stockRegResponse);
        $subqueries[] = "
            SELECT
                'out' AS source_table,
                wo.id AS item_id,
                wo.stock_item_id AS stock_item_id,
                {$outBatch} AS batch_uid,
                {$outUidCreated} AS uid_created,
                {$stockUserId} AS user_id,
                {$outTuid} AS tuid,
                {$outTracking} AS tracking_no,
                {$stockReceiverName} AS receiver_name,
                {$outReceiverCompany} AS receiver_company,
                COALESCE(NULLIF({$outReceiverCompany}, ''), NULLIF({$stockCarrierName}, '')) AS forwarder_name,
                {$outCreatedAt} AS created_at,
                {$stockCellId} AS cell_id,
                c.code AS cell_address,
                {$outContainer} AS container_name,
                {$outStatus} AS out_status,
                CASE
                    WHEN LOWER(TRIM(COALESCE({$outStatus}, ''))) = 'sended' THEN 'sended'
                    WHEN LOWER(TRIM(COALESCE({$outStatus}, ''))) = 'to_send' THEN 'to_send'
                    ELSE LOWER(TRIM(COALESCE({$outStatus}, '')))
                END AS warehouse_state,
                {$outRegisteredAt} AS forwarder_registered_at,
                {$outRegStatus} AS forwarder_registration_status,
                {$outRegMessage} AS forwarder_registration_message,
                {$outRegResponse} AS forwarder_registration_response_json,
                0 AS is_without_addons,
                u.full_name AS user_name
            FROM warehouse_item_out wo
            LEFT JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
            LEFT JOIN cells c ON c.id = {$stockCellId}
            LEFT JOIN users u ON u.id = {$stockUserId}
        ";
    }

    $unionSql = implode("\nUNION ALL\n", $subqueries);
    $conditions = [];
    $types = '';
    $params = [];

    if (!$canViewAll) {
        $conditions[] = 'registry.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }
    if ($sourceTable !== 'all') {
        $conditions[] = 'registry.source_table = ?';
        $types .= 's';
        $params[] = $sourceTable;
    }
    if ($warehouseState !== 'all') {
        if ($warehouseState === 'registration_errors') {
            $conditions[] = "LOWER(TRIM(COALESCE(registry.forwarder_registration_status, ''))) IN ('validation_error', 'error', 'forwarder_error', 'connector_error')";
        } elseif ($warehouseState === 'not_registered') {
            $conditions[] = 'registry.forwarder_registered_at IS NULL';
        } elseif ($warehouseState === 'registered') {
            $conditions[] = "(registry.forwarder_registered_at IS NOT NULL OR LOWER(TRIM(COALESCE(registry.forwarder_registration_status, ''))) = 'ok')";
        } elseif ($warehouseState === 'without_addons') {
            $conditions[] = "registry.source_table = 'stock' AND registry.is_without_addons = 1";
        } else {
            $conditions[] = 'registry.warehouse_state = ?';
            $types .= 's';
            $params[] = $warehouseState;
        }
    }
    if ($forwarderStatus !== 'all') {
        if ($forwarderStatus === 'empty') {
            $conditions[] = "(registry.forwarder_registration_status IS NULL OR TRIM(registry.forwarder_registration_status) = '')";
        } else {
            $conditions[] = 'LOWER(TRIM(COALESCE(registry.forwarder_registration_status, \'\'))) = ?';
            $types .= 's';
            $params[] = $forwarderStatus;
        }
    }
    if ($registeredFilter === 'filled') {
        $conditions[] = 'registry.forwarder_registered_at IS NOT NULL';
    } elseif ($registeredFilter === 'empty') {
        $conditions[] = 'registry.forwarder_registered_at IS NULL';
    }
    if ($search !== '') {
        $conditions[] = "(
            registry.tracking_no LIKE ?
            OR registry.tuid LIKE ?
            OR registry.receiver_name LIKE ?
            OR registry.receiver_company LIKE ?
            OR CAST(registry.uid_created AS CHAR) LIKE ?
            OR CAST(registry.batch_uid AS CHAR) LIKE ?
            OR registry.forwarder_registration_message LIKE ?
        )";
        $like = '%' . $search . '%';
        $types .= 'sssssss';
        for ($i = 0; $i < 7; $i++) {
            $params[] = $like;
        }
    }

    $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $baseSql = "FROM (\n{$unionSql}\n) registry\n{$whereSql}";

    $countRows = warehouse_stock_stmt_fetch_all($dbcnx, "SELECT COUNT(*) AS total {$baseSql}", $types, $params);
    $total = (int)($countRows[0]['total'] ?? 0);

    $selectTypes = $types;
    $selectParams = $params;
    $sql = "
        SELECT
            registry.*,
            COALESCE(NULLIF(registry.tuid, ''), NULLIF(registry.tracking_no, ''), CAST(registry.uid_created AS CHAR)) AS parcel_uid
        {$baseSql}
        ORDER BY registry.created_at {$sort}, registry.item_id {$sort}
    ";
    if ($limit !== null) {
        $sql .= ' LIMIT ? OFFSET ?';
        $selectTypes .= 'ii';
        $selectParams[] = $limit;
        $selectParams[] = $offset;
    }

    $items = warehouse_stock_stmt_fetch_all($dbcnx, $sql, $selectTypes, $selectParams);
    foreach ($items as &$item) {
        $message = trim((string)($item['forwarder_registration_message'] ?? ''));
        if ($message === '') {
            $item['forwarder_registration_message_short'] = '—';
        } elseif (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $item['forwarder_registration_message_short'] = mb_strlen($message, 'UTF-8') > 80
                ? mb_substr($message, 0, 77, 'UTF-8') . '...'
                : $message;
        } else {
            $item['forwarder_registration_message_short'] = strlen($message) > 80
                ? substr($message, 0, 77) . '...'
                : $message;
        }
    }
    unset($item);

    $smarty->assign('warehouse_items_registry', $items);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', $offset === 0);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_items_registry_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html' => $html,
        'total' => $total,
        'items_count' => count($items),
        'has_more' => $limit === null ? false : ($offset + count($items) < $total),
    ];
}

if ($action === 'item_stock_without_cells') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $limitRaw = $_POST['limit'] ?? '20';
    $limit = null;
    if ($limitRaw !== 'all') {
        $limit = max(20, (int)$limitRaw);
    }
    $offset = max(0, (int)($_POST['offset'] ?? 0));

    $sortRaw = strtoupper(trim((string)($_POST['sort'] ?? 'DESC')));
    $sort = $sortRaw === 'ASC' ? 'ASC' : 'DESC';

    $search = trim((string)($_POST['search'] ??  ''));

    $conditions = [
       "wi.cell_id IS NULL",
    ];
    $params = [];
    $types = '';

    // Если нет доступа к просмотру всех - показываем только свои посылки
    if (!$canViewAll) {
        $conditions[] = "wi.user_id = ?";
        $types .= 'i';
        $params[] = $userId;
    }

    if ($search !== '') {
        $conditions[] = "(wi.receiver_name LIKE ? OR wi.tracking_no LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);

    $countSql = "SELECT COUNT(*) AS total FROM warehouse_item_stock wi {$whereSql}";
    $total = 0;
    if ($types === '') {
        if ($res = $dbcnx->query($countSql)) {
            $row = $res->fetch_assoc();
            $total = (int)($row['total'] ?? 0);
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        $stmt->close();
    }

    $sql = "
        SELECT
            wi.id,
            COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
            wi.receiver_name,
            wi.tracking_no,
            wi.created_at,
            wi.user_id,
            u.full_name AS user_name
        FROM warehouse_item_stock wi
        LEFT JOIN users u ON u.id = wi.user_id
        {$whereSql}
        ORDER BY wi.created_at {$sort}
    ";

    if ($limit !== null) {
        $sql .= " LIMIT ?  OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
    }

    $parcels = [];
    if ($types === '') {
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $parcels[] = $row;
            }
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $parcels[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('parcels_without_cells', $parcels);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', $offset === 0);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock_without_cells_rows.html');
    $html = ob_get_clean();

    $response = [
        'status'      => 'ok',
        'html'        => $html,
        'total'       => $total,
        'items_count' => count($parcels),
        'has_more'    => $limit !== null ?  ($offset + count($parcels) < $total) : false,
    ];
}


if ($action === 'item_stock_without_addons') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $limitRaw = $_POST['limit'] ?? '20';
    $limit = null;
    if ($limitRaw !== 'all') {
        $limit = max(20, (int)$limitRaw);
    }
    $offset = max(0, (int)($_POST['offset'] ?? 0));

    $sortRaw = strtoupper(trim((string)($_POST['sort'] ?? 'DESC')));
    $sort = $sortRaw === 'ASC' ? 'ASC' : 'DESC';

    $search = trim((string)($_POST['search'] ?? ''));

    $conditions = [
        "wi.cell_id IS NULL",
        "(wi.addons_json IS NULL OR TRIM(wi.addons_json) = '' OR TRIM(wi.addons_json) = '{}' OR TRIM(wi.addons_json) = '[]')",
        "EXISTS (
            SELECT 1
              FROM connectors_addons ca
             WHERE ca.connector_name = COALESCE(NULLIF(wi.receiver_company, ''), wi.carrier_name)
               AND ca.addons_json IS NOT NULL
               AND TRIM(ca.addons_json) <> ''
               AND TRIM(ca.addons_json) <> '{}'
               AND TRIM(ca.addons_json) <> '[]'
        )",
    ];
    $params = [];
    $types = '';

    if (!$canViewAll) {
        $conditions[] = "wi.user_id = ?";
        $types .= 'i';
        $params[] = $userId;
    }

    if ($search !== '') {
        $conditions[] = "(wi.receiver_name LIKE ? OR wi.tracking_no LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);

    $countSql = "SELECT COUNT(*) AS total FROM warehouse_item_stock wi {$whereSql}";
    $total = 0;
    if ($types === '') {
        if ($res = $dbcnx->query($countSql)) {
            $row = $res->fetch_assoc();
            $total = (int)($row['total'] ?? 0);
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        $stmt->close();
    }

    $sql = "
        SELECT
            wi.id,
            COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
            wi.receiver_name,
            wi.tracking_no,
            wi.created_at,
            wi.user_id,
            u.full_name AS user_name
        FROM warehouse_item_stock wi
        LEFT JOIN users u ON u.id = wi.user_id
        {$whereSql}
        ORDER BY wi.created_at {$sort}
    ";

    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
    }

    $parcels = [];
    if ($types === '') {
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $parcels[] = $row;
            }
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $parcels[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('parcels_without_addons', $parcels);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', $offset === 0);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock_without_addons_rows.html');
    $html = ob_get_clean();

    $response = [
        'status'      => 'ok',
        'html'        => $html,
        'total'       => $total,
        'items_count' => count($parcels),
        'has_more'    => $limit !== null ? ($offset + count($parcels) < $total) : false,
    ];
}


if ($action === 'item_stock_in_storage') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $limitRaw = $_POST['limit'] ?? '20';
    $limit = null;
    if ($limitRaw !== 'all') {
        $limit = max(20, (int)$limitRaw);
    }
    $offset = max(0, (int)($_POST['offset'] ?? 0));

    $sortRaw = strtoupper(trim((string)($_POST['sort'] ?? 'DESC')));
    $sort = $sortRaw === 'ASC' ? 'ASC' : 'DESC';

    $search = trim((string)($_POST['search'] ?? ''));
    $hasOutTable = warehouse_stock_has_out_table($dbcnx);
    $outJoinSql = $hasOutTable ? 'LEFT JOIN warehouse_item_out wo ON wo.stock_item_id = wi.id' : '';

    $conditions = [
        "wi.cell_id IS NOT NULL",
    ];
    if ($hasOutTable) {
        $conditions[] = "(wo.status IS NULL OR LOWER(TRIM(wo.status)) <> 'sended')";
    }
    $params = [];
    $types = '';

    // Если нет доступа к просмотру всех - показываем согласно правам (пока что свои)
    // TODO: добавить проверку прав из таблицы разрешений
    if (!$canViewAll) {
        $conditions[] = "wi.user_id = ?";
        $types .= 'i';
        $params[] = $userId;
    }

    if ($search !== '') {
        $conditions[] = "(wi.receiver_name LIKE ? OR wi.tracking_no LIKE ?)";
        $like = '%' .  $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = 'WHERE ' .  implode(' AND ', $conditions);

    $countSql = "
        SELECT COUNT(*) AS total
        FROM warehouse_item_stock wi
        {$outJoinSql}
        LEFT JOIN cells c ON c.id = wi.cell_id
        {$whereSql}
    ";
    $total = 0;
    if ($types === '') {
        if ($res = $dbcnx->query($countSql)) {
            $row = $res->fetch_assoc();
            $total = (int)($row['total'] ?? 0);
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
        $stmt->close();
    }

    $sql = "
        SELECT
            wi.id,
            COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
            wi.receiver_name,
            wi.tracking_no,
            wi.created_at AS stored_at,
            wi.user_id,
            u.full_name AS user_name,
            c.code AS cell_address
        FROM warehouse_item_stock wi
        {$outJoinSql}
        LEFT JOIN users u ON u.id = wi.user_id
        LEFT JOIN cells c ON c.id = wi.cell_id
        {$whereSql}
        ORDER BY wi.created_at {$sort}
    ";

    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
    }

    $parcels = [];
    if ($types === '') {
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $parcels[] = $row;
            }
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $parcels[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('parcels_in_storage', $parcels);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', $offset === 0);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock_in_storage_rows.html');
    $html = ob_get_clean();

    $response = [
        'status'      => 'ok',
        'html'        => $html,
        'total'       => $total,
        'items_count' => count($parcels),
        'has_more'    => $limit !== null ? ($offset + count($parcels) < $total) : false,
    ];
}

if ($action === 'open_item_stock_modal') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');
    $isWorker = auth_has_role('WORKER');
    $canManageStock = auth_has_permission('warehouse.stock.manage') || $isWorker;
    $canViewAllStock = auth_has_permission('warehouse.stock.view_all') || $isWorker;

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    if ($itemId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }

    $stockForwarderRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registered_at');
    $stockForwarderRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_status', "''");
    $stockForwarderRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_message', "''");
    $stockForwarderRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_response_json');
    
    // Сначала проверим существование посылки и права доступа
    $checkSql = "
        SELECT
            id,
            user_id,
            cell_id,
            batch_uid,
            tuid,
            tracking_no,
            carrier_code,
            carrier_name,
            receiver_country_code,
            receiver_country_name,
            receiver_name,
            receiver_company,
            receiver_address,
            sender_name,
            sender_company,
            uid_created,
            weight_kg,
            size_l_cm,
            size_w_cm,
            size_h_cm,
            addons_json,
            label_image,
            box_image,
            {$stockForwarderRegisteredAt} AS forwarder_registered_at,
            {$stockForwarderRegStatus} AS forwarder_registration_status,
            {$stockForwarderRegMessage} AS forwarder_registration_message,
            {$stockForwarderRegResponse} AS forwarder_registration_response_json
        FROM warehouse_item_stock
        WHERE id = ?
        LIMIT 1
    ";
    $stmt = $dbcnx->prepare($checkSql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $res = $stmt->get_result();
    $item = $res->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        $response = [
            'status'  => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }
    
    $itemUserId = (int)$item['user_id'];
    $itemCellId = $item['cell_id'];
    
    // Проверка прав доступа
    $canAccess = false;
    
    // ADMIN всегда может
    if ($isAdmin) {
        $canAccess = true;
    }
    // WORKER может просматривать все посылки
    elseif ($isWorker) {
        $canAccess = true;
    }
    // Создатель всегда может (для "Посылки без ячеек")
    elseif ($itemUserId === $userId) {
        $canAccess = true;
    }
    // Посылка (с ячейкой или без) - проверяем права warehouse.stock
    elseif ($canManageStock || $canViewAllStock) {
        $canAccess = true;
    }
    
    if (!$canAccess) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для просмотра этой посылки',
        ];
        
        // Аудит попытки доступа
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_ACCESS_DENIED',
            'WAREHOUSE_STOCK',
            $itemId,
            'Попытка доступа к посылке без прав',
            [
                'item_id' => $itemId,
                'item_user_id' => $itemUserId,
                'has_cell' => ($itemCellId !== null),
            ]
        );
        
        return;
    }
    
    // Загружаем справочники
    $dest_country = [];
    $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
    if ($res3 = $dbcnx->query($sql)) {
        while ($row = $res3->fetch_assoc()) {
            $dest_country[] = $row;
        }
        $res3->free();
    }
    
    $stand_devices = [];
    $sql = "SELECT device_uid, name, device_token
              FROM devices
             WHERE name LIKE 'stand\\_%'
             ORDER BY name ASC, device_uid ASC";
    if ($resStand = $dbcnx->query($sql)) {
        while ($row = $resStand->fetch_assoc()) {
            $stand_devices[] = $row;
        }
        $resStand->free();
    }
    
    $cells = [];
    $sql = "SELECT id, code FROM cells ORDER BY code ASC";
    if ($resCells = $dbcnx->query($sql)) {
        while ($row = $resCells->fetch_assoc()) {
            $cells[] = $row;
        }
        $resCells->free();
    }


    $itemAddonsRaw = trim((string)($item['addons_json'] ?? ''));
    $itemAddons = warehouse_stock_decode_item_addons($itemAddonsRaw);
    $itemForwarder = strtoupper(trim((string)($item['receiver_company'] ?? '')));

    $addonsMap = [];
    $addonsRawMap = [];
    $sql = "
        SELECT connector_name, addons_json
          FROM connectors_addons
         WHERE addons_json IS NOT NULL
           AND TRIM(addons_json) <> ''
           AND TRIM(addons_json) <> '{}'
           AND TRIM(addons_json) <> '[]'
    ";
    if ($resAddons = $dbcnx->query($sql)) {
        while ($row = $resAddons->fetch_assoc()) {
            $name = strtoupper(trim((string)($row['connector_name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $rawAddonsJson = trim((string)($row['addons_json'] ?? ''));
            $addonsRawMap[$name] = $rawAddonsJson;

            $options = warehouse_stock_decode_connector_addons($rawAddonsJson);
            if (!empty($options)) {
                $addonsMap[$name] = $options;
            }
        }
        $resAddons->free();
    }

    $smarty->assign('item', $item);
    $smarty->assign('dest_country', $dest_country);
    $smarty->assign('stand_devices', $stand_devices);
    $smarty->assign('cells', $cells);

    $smarty->assign('addons_map', $addonsMap);
    $smarty->assign('addons_raw_map', $addonsRawMap);
    $smarty->assign('item_addons_json', $itemAddonsRaw);
    $smarty->assign('item_addons', $itemAddons);
    $smarty->assign('item_forwarder', $itemForwarder);
    $forwarderRegStatus = strtolower(trim((string)($item['forwarder_registration_status'] ?? '')));
    $canRegisterForwarder = in_array($forwarderRegStatus, [
        '',
        'validation_error',
        'error',
        'forwarder_error',
        'connector_error',
        'skipped',
    ], true);
    $smarty->assign('can_register_forwarder', $canRegisterForwarder);
    $canForwarderOperations = $isAdmin || $isWorker || $itemUserId === $userId || $canManageStock;
    $smarty->assign('can_forwarder_operations', $canForwarderOperations);
    $smarty->assign('can_edit', $isAdmin || $isWorker || $itemUserId === $userId || $canManageStock);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock_modal.html');
    $html = ob_get_clean();
    
    // Аудит просмотра
    audit_log(
        $userId,
        'WAREHOUSE_STOCK_VIEW_PARCEL',
        'WAREHOUSE_STOCK',
        $itemId,
        'Просмотр данных посылки',
        [
            'item_id' => $itemId,
            'batch_uid' => $item['batch_uid'],
        ]
    );
    
    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
}


if ($action === 'warehouse_stock_history_modal') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');
    $isWorker = auth_has_role('WORKER');
    $canManageStock = auth_has_permission('warehouse.stock.manage') || $isWorker;
    $canViewAllStock = auth_has_permission('warehouse.stock.view_all') || $isWorker;

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    if ($itemId <= 0) {
        $response = [
            'status' => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }

    $stockForwarderRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registered_at');
    $stockForwarderRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_status', "''");
    $stockForwarderRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_message', "''");
    $stockForwarderRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_response_json');

    $sql = "
        SELECT
            id,
            batch_uid,
            uid_created,
            user_id,
            tuid,
            tracking_no,
            receiver_name,
            receiver_company,
            receiver_country_code,
            receiver_address,
            created_at,
            {$stockForwarderRegisteredAt} AS forwarder_registered_at,
            {$stockForwarderRegStatus} AS forwarder_registration_status,
            {$stockForwarderRegMessage} AS forwarder_registration_message,
            {$stockForwarderRegResponse} AS forwarder_registration_response_json
        FROM warehouse_item_stock
        WHERE id = ?
        LIMIT 1
    ";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        $response = [
            'status' => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        return;
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $response = [
            'status' => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }

    $itemUserId = (int)($item['user_id'] ?? 0);
    $canAccess = $isAdmin || $isWorker || $itemUserId === $userId || $canManageStock || $canViewAllStock;
    if (!$canAccess) {
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_HISTORY_ACCESS_DENIED',
            'WAREHOUSE_STOCK',
            $itemId,
            'Попытка просмотра истории посылки без прав',
            [
                'item_id' => $itemId,
                'item_user_id' => $itemUserId,
            ]
        );
        $response = [
            'status' => 'error',
            'message' => 'Недостаточно прав для просмотра истории этой посылки',
        ];
        return;
    }


    $auditEvents = warehouse_stock_history_audit_events_fast($dbcnx, $item);
    $syncAuditEvents = warehouse_stock_history_sync_audit_events($dbcnx, $item);
    $stockEvents = warehouse_stock_history_stock_events($dbcnx, $item);

    $timeline = array_merge(
        $stockEvents,
        $auditEvents,
        $syncAuditEvents
    );
    $timeline = array_map('warehouse_stock_history_normalize_event', $timeline);
    usort($timeline, static function (array $a, array $b): int {
        return strcmp((string)($b['event_time'] ?? ''), (string)($a['event_time'] ?? ''));
    });
    $timeline = array_slice($timeline, 0, 200);

    $smarty->assign('item', $item);
    $smarty->assign('timeline', $timeline);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_history_modal.html');
    $html = ob_get_clean();

    audit_log(
        $userId,
        'WAREHOUSE_STOCK_VIEW_HISTORY',
        'WAREHOUSE_STOCK',
        $itemId,
        'Просмотр истории посылки',
        [
            'item_id' => $itemId,
            'tracking_no' => $item['tracking_no'] ?? '',
            'tuid' => $item['tuid'] ?? '',
        ]
    );

    $response = [
        'status' => 'ok',
        'html' => $html,
        'timeline_count' => count($timeline),
        'history_sources' => [
            'stock' => count($stockEvents),
            'audit' => count($auditEvents),
            'sync_audit' => count($syncAuditEvents),
            'forwarder_report' => 0,
        ],
    ];
}


if ($action === 'warehouse_stock_history_forwarder_reports') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');
    $isWorker = auth_has_role('WORKER');
    $canManageStock = auth_has_permission('warehouse.stock.manage') || $isWorker;
    $canViewAllStock = auth_has_permission('warehouse.stock.view_all') || $isWorker;

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 2000;
    if ($limit <= 0) {
        $limit = 2000;
    }
    $limit = min($limit, 5000);

    if ($itemId <= 0) {
        $response = [
            'status' => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }

    $stockForwarderRegisteredAt = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registered_at');
    $stockForwarderRegStatus = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_status', "''");
    $stockForwarderRegMessage = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_message', "''");
    $stockForwarderRegResponse = warehouse_stock_registry_col($dbcnx, 'warehouse_item_stock', 'warehouse_item_stock', 'forwarder_registration_response_json');

    $sql = "
        SELECT
            id,
            batch_uid,
            uid_created,
            user_id,
            tuid,
            tracking_no,
            receiver_name,
            receiver_company,
            receiver_country_code,
            receiver_address,
            created_at,
            {$stockForwarderRegisteredAt} AS forwarder_registered_at,
            {$stockForwarderRegStatus} AS forwarder_registration_status,
            {$stockForwarderRegMessage} AS forwarder_registration_message,
            {$stockForwarderRegResponse} AS forwarder_registration_response_json
        FROM warehouse_item_stock
        WHERE id = ?
        LIMIT 1
    ";
    $itemRows = warehouse_stock_stmt_fetch_all($dbcnx, $sql, 'i', [$itemId]);
    $item = $itemRows[0] ?? null;
    if (!$item) {
        $response = [
            'status' => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }

    $itemUserId = (int)($item['user_id'] ?? 0);
    $canAccess = $isAdmin || $isWorker || $itemUserId === $userId || $canManageStock || $canViewAllStock;
    if (!$canAccess) {
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_HISTORY_REPORTS_ACCESS_DENIED',
            'WAREHOUSE_STOCK',
            $itemId,
            'Попытка просмотра репортов форварда без прав',
            [
                'item_id' => $itemId,
                'item_user_id' => $itemUserId,
            ]
        );
        $response = [
            'status' => 'error',
            'message' => 'Недостаточно прав для просмотра репортов этой посылки',
        ];
        return;
    }

    $forwarder = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)($item['receiver_company'] ?? ''))));
    $forwarder = trim($forwarder, '_');
    $country = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)($item['receiver_country_code'] ?? ''))));
    $country = trim($country, '_');
    $table = ($forwarder !== '' && $country !== '') ? 'connector_report_' . $forwarder . '_' . $country : '';

    $events = [];
    $rows = [];
    if ($table !== '' && warehouse_stock_history_report_table_has_payload($dbcnx, $table)) {
        $safeTable = str_replace('`', '``', $table);
        $selectSourceFile = warehouse_stock_column_exists($dbcnx, $table, 'source_file') ? 'source_file' : "'' AS source_file";
        $selectCreatedAt = warehouse_stock_column_exists($dbcnx, $table, 'created_at') ? 'created_at' : 'NULL AS created_at';
        $selectLastSeenAt = warehouse_stock_column_exists($dbcnx, $table, 'last_seen_at') ? 'last_seen_at' : 'NULL AS last_seen_at';

        $sql = "
            SELECT
                id,
                payload_json,
                {$selectSourceFile},
                {$selectCreatedAt},
                {$selectLastSeenAt}
            FROM `{$safeTable}`
            ORDER BY id DESC
            LIMIT {$limit}
        ";
        $rows = warehouse_stock_stmt_fetch_all($dbcnx, $sql);

        $trackingNo = trim((string)($item['tracking_no'] ?? ''));
        $tuid = trim((string)($item['tuid'] ?? ''));
        foreach ($rows as $row) {
            $payloadRaw = (string)($row['payload_json'] ?? '');
            $payload = json_decode($payloadRaw, true);
            if (!is_array($payload)) {
                continue;
            }
            $payloadTrack = trim((string)($payload['tracking_number'] ?? ''), '" ');
            if ($payloadTrack === '' || ($payloadTrack !== $trackingNo && $payloadTrack !== $tuid)) {
                continue;
            }
            $status = trim((string)($payload['status'] ?? ''));
            $events[] = warehouse_stock_history_normalize_event([
                'event_time' => (string)($row['last_seen_at'] ?: ($row['created_at'] ?? '')),
                'source' => 'forwarder_report',
                'title' => 'Репорт форварда' . ($status !== '' ? ': ' . $status : ''),
                'description' => warehouse_stock_history_report_description($payload),
                'actor_name' => 'forwarder_report_bot',
                'actor_id' => '',
                'details_json' => $payloadRaw,
                'report_table' => $table,
                'report_row_id' => (string)($row['id'] ?? ''),
                'source_file' => (string)($row['source_file'] ?? ''),
            ]);
        }
        usort($events, static function (array $a, array $b): int {
            return strcmp((string)($b['event_time'] ?? ''), (string)($a['event_time'] ?? ''));
        });
    }

    $smarty->assign('events', $events);
    $smarty->assign('report_table', $table);
    $smarty->assign('scanned_rows', count($rows));

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_history_forwarder_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html' => $html,
        'items_count' => count($events),
        'scanned_rows' => count($rows),
        'report_table' => $table,
    ];
}

if ($action === 'warehouse_stock_register_forwarder') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');
    $isWorker = auth_has_role('WORKER');
    $canManageStock = auth_has_permission('warehouse.stock.manage') || $isWorker;

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $force = trim((string)($_POST['force'] ?? '')) === '1';
    if ($itemId <= 0) {
        $response = [
            'status' => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }

    warehouse_forwarder_ensure_stock_registration_columns($dbcnx);
    $stmt = $dbcnx->prepare('SELECT id, user_id, forwarder_registration_status FROM warehouse_item_stock WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $response = [
            'status' => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        return;
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $stockAccessItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$stockAccessItem) {
        $response = [
            'status' => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }

    $itemUserId = (int)($stockAccessItem['user_id'] ?? 0);
    $canRegister = $isAdmin || $isWorker || $canManageStock || $itemUserId === $userId;
    if (!$canRegister) {
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_FORWARDER_REGISTER_DENIED',
            'WAREHOUSE_STOCK',
            $itemId,
            'Попытка ручной регистрации посылки у форварда без прав',
            [
                'item_id' => $itemId,
                'item_user_id' => $itemUserId,
            ]
        );
        $response = [
            'status' => 'error',
            'message' => 'Недостаточно прав для регистрации этой посылки у форварда',
        ];
        return;
    }

    $registrationResult = warehouse_forwarder_register_stock_item($dbcnx, $itemId, $userId, $force ? 'manual_button_force' : 'manual_button', $force);
    $apiStatus = in_array($registrationResult['status'] ?? '', ['ok', 'validation_error', 'connector_error', 'forwarder_error'], true) ? 'ok' : 'error';
    $response = array_merge($registrationResult, [
        'status' => $apiStatus,
        'registration_status' => $registrationResult['status'] ?? 'error',
    ]);
}



if ($action === 'save_item_stock') {
    warehouse_stock_ensure_addons_column($dbcnx);
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');
    $isWorker = auth_has_role('WORKER');
    $canManageStock = auth_has_permission('warehouse.stock.manage') || $isWorker;

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    if ($itemId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }
    
    // Сначала загружаем существующую посылку для проверки прав
    $checkSql = "
        SELECT
            id,
            user_id,
            cell_id,
            batch_uid,
            tuid,
            tracking_no,
            carrier_code,
            carrier_name,
            receiver_country_code,
            receiver_country_name,
            receiver_name,
            receiver_company,
            receiver_address,
            sender_name,
            uid_created,
            weight_kg,
            size_l_cm,
            size_w_cm,
            size_h_cm,
            addons_json,
            label_image,
            box_image
        FROM warehouse_item_stock
        WHERE id = ? 
        LIMIT 1
    ";
    $stmt = $dbcnx->prepare($checkSql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $existingItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$existingItem) {
        $response = [
            'status'  => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }
    
    $itemUserId = (int)$existingItem['user_id'];
    $itemCellId = $existingItem['cell_id'];
    
    // Проверка прав на редактирование
    $canEdit = false;
    
    // ADMIN всегда может
    if ($isAdmin) {
        $canEdit = true;
    }
    // WORKER может редактировать все посылки
    elseif ($isWorker) {
        $canEdit = true;
    }
    // Создатель всегда может редактировать свою посылку
    elseif ($itemUserId === $userId) {
        $canEdit = true;
    }
    // Посылка (с ячейкой или без) - нужно право warehouse.stock.manage
    elseif ($canManageStock) {
        $canEdit = true;
    }
    
    if (!$canEdit) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для редактирования этой посылки',
        ];
        
        // Аудит попытки редактирования
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_EDIT_DENIED',
            'WAREHOUSE_STOCK',
            $itemId,
            'Попытка редактирования посылки без прав',
            [
                'item_id' => $itemId,
                'item_user_id' => $itemUserId,
                'has_cell' => ($itemCellId !== null),
            ]
        );
        
        return;
    }
    
    // Получаем данные из формы
    $tuid = trim($_POST['tuid'] ?? '');
    $tracking = trim($_POST['tracking_no'] ?? '');
    $carrierCode = trim($_POST['carrier_code'] ?? '');
    $carrierName = trim($_POST['carrier_name'] ?? '');
    $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
    $rcName = trim($_POST['receiver_name'] ?? '');
    $rcCompany = trim($_POST['receiver_company'] ?? '');
    $rcAddress = trim($_POST['receiver_address'] ?? '');
    $cellId = isset($_POST['cell_id']) ? (int)$_POST['cell_id'] : 0;
    $cellId = $cellId > 0 ? $cellId : null;
    $senderCode = trim($_POST['sender_code'] ?? '');
    $weightKg = $_POST['weight_kg'] ?? '';
    $sizeL = $_POST['size_l_cm'] ?? '';
    $sizeW = $_POST['size_w_cm'] ?? '';
    $sizeH = $_POST['size_h_cm'] ?? '';
    $addonsJsonRaw = trim((string)($_POST['addons_json'] ?? ''));
    $labelImageJsonRaw = trim((string)($_POST['label_image'] ?? ''));
    $boxImageJsonRaw = trim((string)($_POST['box_image'] ?? ''));

    if ($addonsJsonRaw !== '') {
        $decodedAddons = json_decode($addonsJsonRaw, true);
        if (!is_array($decodedAddons)) {
            $response = [
                'status'  => 'error',
                'message' => 'Некорректный JSON в ДопИнфо',
            ];
            return;
        }
        $addonsJsonRaw = json_encode($decodedAddons, JSON_UNESCAPED_UNICODE);
        if ($addonsJsonRaw === false) {
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось сериализовать ДопИнфо',
            ];
            return;
        }
    } else {
        $addonsJsonRaw = null;
    }


    $labelImageJsonRaw = warehouse_stock_normalize_image_json($labelImageJsonRaw);
    $boxImageJsonRaw = warehouse_stock_normalize_image_json($boxImageJsonRaw);

    $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
    $sizeL = ($sizeL === '' || $sizeL === null) ? 0.0 : (float)$sizeL;
    $sizeW = ($sizeW === '' || $sizeW === null) ? 0.0 : (float)$sizeW;
    $sizeH = ($sizeH === '' || $sizeH === null) ? 0.0 : (float)$sizeH;
    
    $receiverCountryName = '';
    if ($rcCountryCode !== '') {
        $stmt = $dbcnx->prepare("SELECT name_en FROM dest_countries WHERE code_iso2 = ?  LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $rcCountryCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $receiverCountryName = (string)($row['name_en'] ?? '');
            }
            $stmt->close();
        }
    }
    
    if ($tuid === '' || $tracking === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Нужны хотя бы TUID и трек-номер',
        ];
        return;
    }

    $originalCellId = $existingItem['cell_id'] !== null ? (int)$existingItem['cell_id'] : null;
    $newCellId = $cellId !== null ? (int)$cellId : null;

    // Для nullable INT в bind_param используем 0 и преобразуем в NULL на SQL-стороне.
    // Иначе пустой cell_id может попасть как 0 и вызвать ошибку FK/валидации.
    $cellIdForBind = $cellId ?? 0;

    // Обновляем запись
    $sql = "
        UPDATE warehouse_item_stock
           SET tuid = ?,
               tracking_no = ?,
               carrier_code = ?,
               carrier_name = ?,
               receiver_country_code = ?,
               receiver_country_name = ?,
               receiver_name = ?,
               receiver_company = ?,
               receiver_address = ?,
               cell_id = NULLIF(?, 0),
               sender_name = ?,
               weight_kg = ?,
               size_l_cm = ?,
               size_w_cm = ?,
               size_h_cm = ?,
               addons_json = ?,
               label_image = ?,
               box_image = ?
         WHERE id = ?
    ";
    $stmt = $dbcnx->prepare($sql);
    if (! $stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error:  ' . $dbcnx->error,
        ];
        return;
    }

    $stmt->bind_param(
        "sssssssssisddddsssi",
        $tuid,
        $tracking,
        $carrierCode,
        $carrierName,
        $rcCountryCode,
        $receiverCountryName,
        $rcName,
        $rcCompany,
        $rcAddress,
        $cellIdForBind,
        $senderCode,
        $weightKg,
        $sizeL,
        $sizeW,
        $sizeH,
        $addonsJsonRaw,
        $labelImageJsonRaw,
        $boxImageJsonRaw,
        $itemId
    );

    $stmt->execute();
    $stmt->close();

    // Формируем изменения для аудита
    $changes = [];
    $fieldMap = [
        'tuid' => $tuid,
        'tracking_no' => $tracking,
        'carrier_code' => $carrierCode,
        'carrier_name' => $carrierName,
        'receiver_country_code' => $rcCountryCode,
        'receiver_country_name' => $receiverCountryName,
        'receiver_name' => $rcName,
        'receiver_company' => $rcCompany,
        'receiver_address' => $rcAddress,
        'cell_id' => $newCellId,
        'sender_name' => $senderCode,
        'weight_kg' => $weightKg,
        'size_l_cm' => $sizeL,
        'size_w_cm' => $sizeW,
        'size_h_cm' => $sizeH,
        'addons_json' => $addonsJsonRaw,
        'label_image' => $labelImageJsonRaw,
        'box_image' => $boxImageJsonRaw,
    ];
    
    foreach ($fieldMap as $field => $newValue) {
        $oldValue = $existingItem[$field] ?? null;
        if ($field === 'cell_id') {
            $oldValue = $originalCellId;
        }
        if ($oldValue != $newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    if (! empty($changes)) {
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_UPDATE_PARCEL',
            'WAREHOUSE_STOCK',
            $itemId,
            'Отредактированы данные посылки на складе',
            [
                'item_id'   => $itemId,
                'batch_uid' => $existingItem['batch_uid'],
                'changes'   => $changes,
                'edited_by_admin' => $isAdmin && ($itemUserId !== $userId),
            ]
        );
    }

    if (array_key_exists('addons_json', $changes)) {
        $oldAddons = warehouse_stock_decode_item_addons((string)($existingItem['addons_json'] ?? ''));
        $newAddons = warehouse_stock_decode_item_addons((string)($addonsJsonRaw ?? ''));
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_ADDONS_UPDATE',
            'WAREHOUSE_STOCK',
            $itemId,
            'Обновлена ДопИнфо у посылки',
            [
                'item_id' => $itemId,
                'batch_uid' => $existingItem['batch_uid'],
                'receiver_company' => $rcCompany,
                'addons_old' => $oldAddons,
                'addons_new' => $newAddons,
                'changed_by_admin' => $isAdmin && ($itemUserId !== $userId),
            ]
        );
    }

    // Аудит изменения ячейки
    if ($originalCellId !== $newCellId) {
        $cellCodeLookup = function (? int $cellId) use ($dbcnx): ?string {
            if ($cellId === null) {
                return null;
            }
            $stmt = $dbcnx->prepare("SELECT code FROM cells WHERE id = ?  LIMIT 1");
            if (! $stmt) {
                return null;
            }
            $stmt->bind_param("i", $cellId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?  (string)$row['code'] : null;
        };
        
        $oldCellCode = $cellCodeLookup($originalCellId);
        $newCellCode = $cellCodeLookup($newCellId);
        
        $eventType = 'WAREHOUSE_STOCK_CELL_UPDATE';
        $description = 'Изменена адресация посылки';
        if ($originalCellId === null && $newCellId !== null) {
            $eventType = 'WAREHOUSE_STOCK_CELL_ASSIGN';
            $description = 'Назначена адресация посылки';
        } elseif ($originalCellId !== null && $newCellId === null) {
            $eventType = 'WAREHOUSE_STOCK_CELL_REMOVE';
            $description = 'Удалена адресация посылки';
        }
        
        audit_log(
            $userId,
            $eventType,
            'WAREHOUSE_STOCK',
            $itemId,
            $description,
            [
                'item_id' => $itemId,
                'batch_uid' => $existingItem['batch_uid'],
                'cell_id_old' => $originalCellId,
                'cell_id_new' => $newCellId,
                'cell_code_old' => $oldCellCode,
                'cell_code_new' => $newCellCode,
                'changed_by_admin' => $isAdmin && ($itemUserId !== $userId),
            ]
        );
    }

    $response = [
        'status'  => 'ok',
        'message' => 'Данные посылки сохранены',
    ];
}


if ($action === 'upload_item_stock_photo') {
    auth_require_login();

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
    if ($itemId <= 0) {
        $response = ['status' => 'error', 'message' => 'Некорректный item_id'];
        return;
    }
    if (!in_array($photoType, ['label', 'box'], true)) {
        $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
        return;
    }
    if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
        $response = ['status' => 'error', 'message' => 'Файл не передан'];
        return;
    }

    $stmt = $dbcnx->prepare("SELECT id, uid_created, label_image, box_image FROM warehouse_item_stock WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
        return;
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$item) {
        $response = ['status' => 'error', 'message' => 'Посылка не найдена'];
        return;
    }

    $upload = $_FILES['photo'];
    $errorCode = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $response = ['status' => 'error', 'message' => 'Ошибка загрузки файла'];
        return;
    }

    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $response = ['status' => 'error', 'message' => 'Некорректный временный файл'];
        return;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        $response = ['status' => 'error', 'message' => 'Разрешены только JPEG/PNG/WEBP'];
        return;
    }

    $uidCreated = trim((string)($item['uid_created'] ?? ''));
    if ($uidCreated === '') {
        $uidCreated = (string)$itemId;
    }

    $baseRelDir = 'img/warehouse_item_stock/' . $uidCreated;
    $baseAbsDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $baseRelDir;
    if (!warehouse_stock_ensure_photo_dir($baseAbsDir)) {
        $response = ['status' => 'error', 'message' => 'Не удалось создать каталог для фото'];
        return;
    }

    $fileName = date('Ymd_His') . '_' . $photoType . '.' . $allowed[$mime];
    $destAbs = $baseAbsDir . '/' . $fileName;
    $publicPath = '/' . $baseRelDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $destAbs)) {
        $response = ['status' => 'error', 'message' => 'Не удалось сохранить файл'];
        return;
    }

    $field = $photoType === 'label' ? 'label_image' : 'box_image';
    $json = json_encode([$publicPath], JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        @unlink($destAbs);
        $response = ['status' => 'error', 'message' => 'Ошибка сериализации пути'];
        return;
    }

    $sql = "UPDATE warehouse_item_stock SET {$field} = ? WHERE id = ? LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        @unlink($destAbs);
        $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
        return;
    }
    $stmt->bind_param('si', $json, $itemId);
    $stmt->execute();
    $stmt->close();

    $userId = (int)($user['id'] ?? 0);
    $oldJson = (string)($item[$field] ?? '');
    audit_log(
        $userId,
        'WAREHOUSE_STOCK_PHOTO_UPLOAD',
        'WAREHOUSE_STOCK',
        $itemId,
        'Загружено фото посылки',
        [
            'item_id' => $itemId,
            'photo_type' => $photoType,
            'path_added' => $publicPath,
            'old_paths' => warehouse_stock_decode_image_paths($oldJson),
            'new_paths' => warehouse_stock_decode_image_paths($json),
        ]
    );


    $response = [
        'status' => 'ok',
        'message' => 'Фото загружено',
        'photo_type' => $photoType,
        'path' => $publicPath,
        'json' => $json,
    ];
}


if ($action === 'delete_item_stock_photo') {
    auth_require_login();

    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
    if ($itemId <= 0) {
        $response = ['status' => 'error', 'message' => 'Некорректный item_id'];
        return;
    }
    if (!in_array($photoType, ['label', 'box'], true)) {
        $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
        return;
    }

    $field = $photoType === 'label' ? 'label_image' : 'box_image';
    $stmt = $dbcnx->prepare("SELECT id, {$field} FROM warehouse_item_stock WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
        return;
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $response = ['status' => 'error', 'message' => 'Посылка не найдена'];
        return;
    }

    $oldJson = (string)($item[$field] ?? '');
    $oldPaths = warehouse_stock_decode_image_paths($oldJson);

    $stmt = $dbcnx->prepare("UPDATE warehouse_item_stock SET {$field} = NULL WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
        return;
    }
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $stmt->close();

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    foreach ($oldPaths as $path) {
        if (strpos($path, '/img/warehouse_item_stock/') !== 0) {
            continue;
        }
        if ($docRoot === '') {
            continue;
        }
        $abs = $docRoot . $path;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    $userId = (int)($user['id'] ?? 0);
    audit_log(
        $userId,
        'WAREHOUSE_STOCK_PHOTO_DELETE',
        'WAREHOUSE_STOCK',
        $itemId,
        'Удалено фото посылки',
        [
            'item_id' => $itemId,
            'photo_type' => $photoType,
            'deleted_paths' => $oldPaths,
        ]
    );

    $response = [
        'status' => 'ok',
        'message' => 'Фото удалено',
        'photo_type' => $photoType,
        'json' => '',
    ];
}
