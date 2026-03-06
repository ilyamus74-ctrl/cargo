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


if (!function_exists('warehouse_sync_extract_report_status')) {
    function warehouse_sync_extract_report_status(string $payloadJson): string
    {
        $payloadJson = trim($payloadJson);
        if ($payloadJson === '') {
            return '';
        }

        $decoded = json_decode($payloadJson, true);
        if (is_array($decoded)) {
            $queue = [$decoded];
            while (!empty($queue)) {
                $node = array_shift($queue);
                if (!is_array($node)) {
                    continue;
                }
                foreach ($node as $key => $value) {
                    if (is_array($value)) {
                        $queue[] = $value;
                        continue;
                    }
                    if (!is_scalar($value)) {
                        continue;
                    }
                    $normalizedKey = strtolower(trim((string)$key));
                    if (in_array($normalizedKey, ['status', 'state', 'parcel_status', 'shipment_status'], true)) {
                        $status = trim((string)$value);
                        if ($status !== '') {
                            return $status;
                        }
                    }
                }
            }
        }

        if (preg_match('/"(?:status|state|parcel_status|shipment_status)"\s*:\s*"([^"]+)"/iu', $payloadJson, $m)) {
            return trim((string)($m[1] ?? ''));
        }

        return '';
    }
}

if (!function_exists('warehouse_sync_is_final_report_status')) {
    function warehouse_sync_is_final_report_status(string $status): bool
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return false;
        }

        $finalStatuses = [
            'received', 'delivered', 'accepted', 'done', 'complete', 'completed', 'success', 'ok',
            'получено', 'доставлено', 'принято', 'успешно', 'завершено',
        ];

        return in_array($normalized, $finalStatuses, true);
    }
}

if (!function_exists('warehouse_sync_report_confirmation_by_tracking')) {
    function warehouse_sync_report_confirmation_by_tracking(mysqli $dbcnx, array $trackingList): array
    {
        $result = [];
        $trackingList = array_values(array_unique(array_filter(array_map(static function ($tracking) {
            return strtoupper(trim((string)$tracking));
        }, $trackingList), static function ($tracking) {
            return $tracking !== '';
        })));

        if (empty($trackingList)) {
            return $result;
        }

        $tableName = 'connector_report_dev_colibri_az';
        if (!warehouse_sync_table_exists($dbcnx, $tableName)) {
            foreach ($trackingList as $tracking) {
                $result[$tracking] = ['found' => false, 'status' => '', 'is_final' => false];
            }
            return $result;
        }

        $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
        foreach ($trackingList as $tracking) {
            $safeTracking = $dbcnx->real_escape_string($tracking);
            $sql = "SELECT payload_json FROM {$safeTable} WHERE payload_json IS NOT NULL AND payload_json <> '' AND UPPER(payload_json) LIKE '%{$safeTracking}%' ORDER BY id DESC LIMIT 1";
            $row = null;
            if ($res = $dbcnx->query($sql)) {
                $row = $res->fetch_assoc() ?: null;
                $res->free();
            }

            if (!$row) {
                $result[$tracking] = ['found' => false, 'status' => '', 'is_final' => false];
                continue;
            }

            $status = warehouse_sync_extract_report_status((string)($row['payload_json'] ?? ''));
            $result[$tracking] = [
                'found' => true,
                'status' => $status,
                'is_final' => warehouse_sync_is_final_report_status($status),
            ];
        }

        return $result;
    }
}


if (!function_exists('warehouse_sync_clear_directory')) {
    function warehouse_sync_clear_directory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = @scandir($directory);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                warehouse_sync_clear_directory($path);
                @rmdir($path);
                continue;
            }

            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
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


if (!function_exists('warehouse_sync_ensure_out_table')) {
    function warehouse_sync_ensure_out_table(mysqli $dbcnx): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS warehouse_item_out (
"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
            . " stock_item_id BIGINT UNSIGNED NOT NULL,
"
            . " batch_uid BIGINT UNSIGNED NULL,
"
            . " uid_created BIGINT UNSIGNED NOT NULL DEFAULT 0,
"
            . " tuid VARCHAR(64) NOT NULL DEFAULT '',
"
            . " tracking_no VARCHAR(64) NOT NULL DEFAULT '',
"
            . " carrier_name VARCHAR(64) NULL,
"
            . " receiver_country_code VARCHAR(2) NULL,
"
            . " receiver_company VARCHAR(128) NULL,
"
            . " receiver_address VARCHAR(255) NULL,
"
            . " status VARCHAR(32) NOT NULL DEFAULT 'for_sync',
"
            . " status_message TEXT NULL,
"
            . " status_updated_at DATETIME NULL,
"
            . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
            . " updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
"
            . " PRIMARY KEY (id),
"
            . " UNIQUE KEY uq_stock_item_id (stock_item_id),
"
            . " KEY idx_status_updated (status, status_updated_at),
"
            . " KEY idx_tracking (tracking_no),
"
            . " KEY idx_forwarder_country (receiver_company, receiver_country_code)
"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $dbcnx->query($sql);

        $columns = [];
        if ($res = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_out")) {
            while ($row = $res->fetch_assoc()) {
                $field = strtolower(trim((string)($row['Field'] ?? '')));
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
            $res->free();
        }

        $missingColumnsSql = [
            'stock_item_id' => "ALTER TABLE warehouse_item_out ADD COLUMN stock_item_id BIGINT UNSIGNED NOT NULL",
            'batch_uid' => "ALTER TABLE warehouse_item_out ADD COLUMN batch_uid BIGINT UNSIGNED NULL",
            'uid_created' => "ALTER TABLE warehouse_item_out ADD COLUMN uid_created BIGINT UNSIGNED NOT NULL DEFAULT 0",
            'tuid' => "ALTER TABLE warehouse_item_out ADD COLUMN tuid VARCHAR(64) NOT NULL DEFAULT ''",
            'tracking_no' => "ALTER TABLE warehouse_item_out ADD COLUMN tracking_no VARCHAR(64) NOT NULL DEFAULT ''",
            'carrier_name' => "ALTER TABLE warehouse_item_out ADD COLUMN carrier_name VARCHAR(64) NULL",
            'receiver_country_code' => "ALTER TABLE warehouse_item_out ADD COLUMN receiver_country_code VARCHAR(2) NULL",
            'receiver_company' => "ALTER TABLE warehouse_item_out ADD COLUMN receiver_company VARCHAR(128) NULL",
            'receiver_address' => "ALTER TABLE warehouse_item_out ADD COLUMN receiver_address VARCHAR(255) NULL",
            'status' => "ALTER TABLE warehouse_item_out ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'for_sync'",
            'status_message' => "ALTER TABLE warehouse_item_out ADD COLUMN status_message TEXT NULL",
            'status_updated_at' => "ALTER TABLE warehouse_item_out ADD COLUMN status_updated_at DATETIME NULL",
            'created_at' => "ALTER TABLE warehouse_item_out ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE warehouse_item_out ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        ];

        foreach ($missingColumnsSql as $columnName => $alterSql) {
            if (!isset($columns[$columnName])) {
                $dbcnx->query($alterSql);
            }
        }

        if ($res = $dbcnx->query("SHOW INDEX FROM warehouse_item_out WHERE Key_name = 'uq_stock_item_id'")) {
            $hasUnique = $res->num_rows > 0;
            $res->free();
            if (!$hasUnique) {
                $dbcnx->query("ALTER TABLE warehouse_item_out ADD UNIQUE KEY uq_stock_item_id (stock_item_id)");
            }
        }

        if ($res = $dbcnx->query("SHOW INDEX FROM warehouse_item_out WHERE Key_name = 'idx_status_updated'")) {
            $hasIndex = $res->num_rows > 0;
            $res->free();
            if (!$hasIndex) {
                $dbcnx->query("ALTER TABLE warehouse_item_out ADD KEY idx_status_updated (status, status_updated_at)");
            }
        }
    }
}

if (!function_exists('warehouse_sync_out_upsert_from_stock')) {
    function warehouse_sync_out_upsert_from_stock(mysqli $dbcnx, int $stockItemId): void
    {
        warehouse_sync_ensure_out_table($dbcnx);

        $sql = "INSERT INTO warehouse_item_out
"
            . "(stock_item_id, batch_uid, uid_created, tuid, tracking_no, carrier_name, receiver_country_code, receiver_company, receiver_address)
"
            . "SELECT id, batch_uid, uid_created, tuid, tracking_no, carrier_name, receiver_country_code, receiver_company, receiver_address
"
            . "FROM warehouse_item_stock WHERE id = ?
"
            . "ON DUPLICATE KEY UPDATE
"
            . " batch_uid = VALUES(batch_uid),
"
            . " uid_created = VALUES(uid_created),
"
            . " tuid = VALUES(tuid),
"
            . " tracking_no = VALUES(tracking_no),
"
            . " carrier_name = VALUES(carrier_name),
"
            . " receiver_country_code = VALUES(receiver_country_code),
"
            . " receiver_company = VALUES(receiver_company),
"
            . " receiver_address = VALUES(receiver_address)";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $stockItemId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('warehouse_sync_out_set_status')) {
    function warehouse_sync_out_set_status(mysqli $dbcnx, int $stockItemId, string $status, string $message = ''): void
    {
        warehouse_sync_ensure_out_table($dbcnx);
        warehouse_sync_out_upsert_from_stock($dbcnx, $stockItemId);

        $sql = "UPDATE warehouse_item_out SET status = ?, status_message = ?, status_updated_at = NOW() WHERE stock_item_id = ? LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssi', $status, $message, $stockItemId);
        $stmt->execute();
        $stmt->close();
    }
}


if (!function_exists('warehouse_sync_out_backfill_from_stock')) {
    function warehouse_sync_out_backfill_from_stock(mysqli $dbcnx, int $limit = 500): array
    {
        warehouse_sync_ensure_out_table($dbcnx);

        $limit = max(1, min(5000, $limit));
        $inserted = 0;

        $sql = "INSERT INTO warehouse_item_out
"
            . "(stock_item_id, batch_uid, uid_created, tuid, tracking_no, carrier_name, receiver_country_code, receiver_company, receiver_address)
"
            . "SELECT wi.id, wi.batch_uid, wi.uid_created, wi.tuid, wi.tracking_no, wi.carrier_name, wi.receiver_country_code, wi.receiver_company, wi.receiver_address
"
            . "FROM warehouse_item_stock wi
"
            . "LEFT JOIN warehouse_item_out wo ON wo.stock_item_id = wi.id
"
            . "WHERE wo.stock_item_id IS NULL
"
            . "ORDER BY wi.id ASC
"
            . "LIMIT {$limit}";
        if ($dbcnx->query($sql)) {
            $inserted = (int)$dbcnx->affected_rows;
        }

        $updated = 0;
        $sqlUpdate = "UPDATE warehouse_item_out wo
"
            . "JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
"
            . "SET wo.batch_uid = wi.batch_uid,
"
            . "    wo.uid_created = wi.uid_created,
"
            . "    wo.tuid = wi.tuid,
"
            . "    wo.tracking_no = wi.tracking_no,
"
            . "    wo.carrier_name = wi.carrier_name,
"
            . "    wo.receiver_country_code = wi.receiver_country_code,
"
            . "    wo.receiver_company = wi.receiver_company,
"
            . "    wo.receiver_address = wi.receiver_address
"
            . "WHERE wo.status IN ('for_sync', '')
"
            . "LIMIT {$limit}";
        if ($dbcnx->query($sqlUpdate)) {
            $updated = (int)$dbcnx->affected_rows;
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'processed' => $inserted + $updated,
        ];
    }
}

if (!function_exists('warehouse_sync_is_error_report_status')) {
    function warehouse_sync_is_error_report_status(string $status): bool
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return false;
        }

        $errorStatuses = [
            'error', 'failed', 'fail', 'rejected', 'declined', 'cancelled', 'canceled',
            'ошибка', 'отклонено', 'отменено', 'не принято',
        ];

        return in_array($normalized, $errorStatuses, true);
    }
}

if (!function_exists('warehouse_sync_report_table_for_forwarder')) {
    function warehouse_sync_report_table_for_forwarder(string $forwarderNorm, string $countryNorm): string
    {
        if ($forwarderNorm === '' || $countryNorm === '') {
            return '';
        }

        $map = [
            'colibri' => 'connector_report_colibri_%s',
            'dev_colibri' => 'connector_report_dev_colibri_%s',
            'kolli' => 'connector_report_kolli_%s',
        ];

        $pattern = $map[$forwarderNorm] ?? 'connector_report_' . $forwarderNorm . '_%s';
        return sprintf($pattern, $countryNorm);
    }
}

if (!function_exists('warehouse_sync_find_report_row')) {
    function warehouse_sync_find_report_row(mysqli $dbcnx, string $forwarder, string $countryCode, string $trackingNo): ?array
    {
        $forwarderNorm = strtolower(warehouse_sync_normalize_key($forwarder));
        $countryNorm = strtolower(warehouse_sync_normalize_key($countryCode));
        $trackingNo = strtoupper(trim($trackingNo));
        if ($forwarderNorm === '' || $countryNorm === '' || $trackingNo === '') {
            return null;
        }

        $tableName = warehouse_sync_report_table_for_forwarder($forwarderNorm, $countryNorm);
        if ($tableName === '' || !warehouse_sync_table_exists($dbcnx, $tableName)) {
            return null;
        }
        $columns = warehouse_sync_table_columns($dbcnx, $tableName);
        if (empty($columns)) {
            return null;
        }

        $trackingColumn = warehouse_sync_find_column($columns, ['tracking_no', 'tracking_number', 'tracking', 'track_no', 'track_number', 'tuid']);
        $statusColumn = warehouse_sync_find_column($columns, ['status', 'state', 'parcel_status', 'shipment_status']);
        $dateColumn = warehouse_sync_find_column($columns, ['updated_at', 'created_at', 'date_created', 'datetime_created']);

        if ($trackingColumn !== '') {
            $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
            $safeTracking = '`' . str_replace('`', '``', $trackingColumn) . '`';
            $safeStatus = $statusColumn !== '' ? ('`' . str_replace('`', '``', $statusColumn) . '`') : "''";
            $orderExpr = $dateColumn !== ''
                ? ('`' . str_replace('`', '``', $dateColumn) . '` DESC')
                : '1';

            $sql = "SELECT {$safeTracking} AS tracking_no, {$safeStatus} AS report_status, payload_json"
                . "FROM {$safeTable}
"
                . "WHERE UPPER(TRIM({$safeTracking})) = ?
"
                . "ORDER BY {$orderExpr}
"
                . "LIMIT 1";
            $stmt = $dbcnx->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $trackingNo);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? ($res->fetch_assoc() ?: null) : null;
                $stmt->close();
                if ($row) {
                    $status = trim((string)($row['report_status'] ?? ''));
                    if ($status === '') {
                        $status = warehouse_sync_extract_report_status((string)($row['payload_json'] ?? ''));
                    }

                    return [
                        'table_name' => $tableName,
                        'report_status' => $status,
                        'payload_json' => (string)($row['payload_json'] ?? ''),
                    ];
                }

        }


        if (in_array('payload_json', $columns, true)) {
            $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
            $safeTrackingLike = '%' . $dbcnx->real_escape_string($trackingNo) . '%';
            $sql = "SELECT payload_json FROM {$safeTable} WHERE payload_json IS NOT NULL AND payload_json <> '' AND UPPER(payload_json) LIKE '{$safeTrackingLike}' ORDER BY id DESC LIMIT 1";
            if ($res = $dbcnx->query($sql)) {
                $row = $res->fetch_assoc() ?: null;
                $res->free();
                if ($row) {
                    $status = warehouse_sync_extract_report_status((string)($row['payload_json'] ?? ''));
                    return [
                        'table_name' => $tableName,
                        'report_status' => $status,
                        'payload_json' => (string)($row['payload_json'] ?? ''),
                    ];
                }
            }
        }

        return null;
    }
}

if (!function_exists('warehouse_sync_reconcile_half_sync')) {
    function warehouse_sync_reconcile_half_sync(mysqli $dbcnx, int $limit = 200, int $createdBy = 0): array
    {
        warehouse_sync_ensure_out_table($dbcnx);
        warehouse_sync_ensure_audit_table($dbcnx);

        $limit = max(1, min(2000, $limit));
        $rows = [];
        $sql = "SELECT stock_item_id, tracking_no, receiver_company, receiver_country_code, status
"
            . "FROM warehouse_item_out
"
            . "WHERE status = 'half_sync'
"
            . "ORDER BY status_updated_at ASC, id ASC
"
            . "LIMIT {$limit}";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }

        $stats = [
            'checked' => count($rows),
            'confirmed_sync' => 0,
            'error' => 0,
            'unchanged' => 0,
        ];

        foreach ($rows as $row) {
            $itemId = (int)($row['stock_item_id'] ?? 0);
            $trackingNo = trim((string)($row['tracking_no'] ?? ''));
            $forwarder = strtoupper(trim((string)($row['receiver_company'] ?? '')));
            $countryCode = strtoupper(trim((string)($row['receiver_country_code'] ?? '')));

            if ($itemId <= 0 || $trackingNo === '' || $forwarder === '' || $countryCode === '') {
                $stats['unchanged']++;
                continue;
            }

            $report = warehouse_sync_find_report_row($dbcnx, $forwarder, $countryCode, $trackingNo);
            if (!$report) {
                $stats['unchanged']++;
                continue;
            }

            $reportStatus = trim((string)($report['report_status'] ?? ''));
            $nextStatus = '';
            if (warehouse_sync_is_error_report_status($reportStatus)) {
                $nextStatus = 'error';
            } elseif (warehouse_sync_is_final_report_status($reportStatus) || $reportStatus !== '') {
                $nextStatus = 'confirmed_sync';
            }

            if ($nextStatus === '') {
                $stats['unchanged']++;
                continue;
            }

            $message = $reportStatus !== ''
                ? ('report status: ' . $reportStatus)
                : ('report matched in ' . (string)($report['table_name'] ?? 'connector_report'));

            warehouse_sync_out_set_status($dbcnx, $itemId, $nextStatus, $message);
            warehouse_sync_audit_log($dbcnx, [
                'item_id' => $itemId,
                'tracking_no' => $trackingNo,
                'forwarder' => $forwarder,
                'country_code' => $countryCode,
                'status' => $nextStatus,
                'message' => $message,
                'response_json' => json_encode($report, JSON_UNESCAPED_UNICODE) ?: '',
                'created_by' => $createdBy,
            ]);

            $stats[$nextStatus]++;
        }

        return $stats;
    }
}


if (!function_exists('warehouse_sync_fetch_item')) {
    function warehouse_sync_fetch_item(mysqli $dbcnx, int $itemId): ?array
    {
        $sql = "SELECT id, tuid, tracking_no, receiver_name, receiver_address, receiver_country_code, receiver_company, uid_created, weight_kg, addons_json FROM warehouse_item_stock WHERE id = ? LIMIT 1";
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
        $forwarderNorm = warehouse_sync_normalize_key($forwarder);
        $forwarderCandidates = [];
        if ($forwarderNorm !== '') {
            $forwarderCandidates[] = $forwarderNorm;
            if (strpos($forwarderNorm, 'DEV_') === 0) {
                $forwarderCandidates[] = substr($forwarderNorm, 4);
            }
        }

        $sql = "SELECT id, name, countries, auth_username, auth_password, auth_token, auth_cookies, base_url, ssl_ignore, scenario_json, operations_json, is_active
"
            . "FROM connectors WHERE UPPER(TRIM(name)) = ? AND is_active = 1 ORDER BY id DESC";
        $rows = [];

        foreach ($forwarderCandidates as $candidateNorm) {
            $candidateRaw = strtoupper(trim(str_replace('_', ' ', $candidateNorm)));
            if ($candidateRaw === '') {
                continue;
            }

            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $candidateRaw);
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

        if (empty($rows) && !empty($forwarderCandidates)) {
            $sqlFallback = "SELECT id, name, countries, auth_username, auth_password, auth_token, auth_cookies, base_url, ssl_ignore, scenario_json, operations_json, is_active
"
                . "FROM connectors WHERE is_active = 1 ORDER BY id DESC";
            if ($resAll = $dbcnx->query($sqlFallback)) {
                while ($row = $resAll->fetch_assoc()) {
                    $nameNorm = warehouse_sync_normalize_key((string)($row['name'] ?? ''));
                    if ($nameNorm === '') {
                        continue;
                    }
                    foreach ($forwarderCandidates as $candidateNorm) {
                        if ($candidateNorm === '') {
                            continue;
                        }
                        $namePlain = (strpos($nameNorm, 'DEV_') === 0) ? substr($nameNorm, 4) : $nameNorm;
                        $candidatePlain = (strpos($candidateNorm, 'DEV_') === 0) ? substr($candidateNorm, 4) : $candidateNorm;
                        if ($nameNorm === $candidateNorm || $namePlain === $candidateNorm || $nameNorm === $candidatePlain || $namePlain === $candidatePlain) {
                            $rows[] = $row;
                            break;
                        }
                    }
                }
                $resAll->free();
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
        if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['action'])) {
            return $decoded;
        }

        if (isset($decoded['steps']) && is_array($decoded['steps'])) {
            return $decoded['steps'];
        }

        $submission = isset($decoded['submission']) && is_array($decoded['submission']) ? $decoded['submission'] : [];
        if (isset($submission['steps']) && is_array($submission['steps'])) {
            return $submission['steps'];
        }

        $submissionStepsJson = trim((string)($submission['steps_json'] ?? ''));
        if ($submissionStepsJson !== '') {
            $legacySteps = json_decode($submissionStepsJson, true);
            if (is_array($legacySteps)) {
                return $legacySteps;
            }
        }

        if (isset($decoded['operation_2']) && is_array($decoded['operation_2'])) {
            $operation2 = $decoded['operation_2'];
            if (isset($operation2['steps']) && is_array($operation2['steps'])) {
                return $operation2['steps'];
            }
            $operation2StepsJson = trim((string)($operation2['steps_json'] ?? ''));
            if ($operation2StepsJson !== '') {
                $legacySteps = json_decode($operation2StepsJson, true);
                if (is_array($legacySteps)) {
                    return $legacySteps;
                }
            }
        }
        if (isset($decoded['operation2']) && is_array($decoded['operation2'])) {
            $operation2 = $decoded['operation2'];
            if (isset($operation2['steps']) && is_array($operation2['steps'])) {
                return $operation2['steps'];
            }
            $operation2StepsJson = trim((string)($operation2['steps_json'] ?? ''));
            if ($operation2StepsJson !== '') {
                $legacySteps = json_decode($operation2StepsJson, true);
                if (is_array($legacySteps)) {
                    return $legacySteps;
                }
            }
        }

        if (isset($decoded[2]) && is_array($decoded[2])) {
            $operation2 = $decoded[2];
            if (isset($operation2['steps']) && is_array($operation2['steps'])) {
                return $operation2['steps'];
            }
        }

        return [];
    }
}



if (!function_exists('warehouse_sync_submission_error_selector')) {
    function warehouse_sync_submission_error_selector(array $connector): string
    {
        $raw = trim((string)($connector['operations_json'] ?? ''));
        if ($raw === '') return '';
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return '';

        $submission = isset($decoded['submission']) && is_array($decoded['submission']) ? $decoded['submission'] : [];
        if (!empty($submission['error_selector'])) {
            return trim((string)$submission['error_selector']);
        }

        if (isset($decoded['operation_2']) && is_array($decoded['operation_2']) && !empty($decoded['operation_2']['error_selector'])) {
            return trim((string)$decoded['operation_2']['error_selector']);
        }

        if (isset($decoded['operation2']) && is_array($decoded['operation2']) && !empty($decoded['operation2']['error_selector'])) {
            return trim((string)$decoded['operation2']['error_selector']);
        }

        if (isset($decoded[2]) && is_array($decoded[2]) && !empty($decoded[2]['error_selector'])) {
            return trim((string)$decoded[2]['error_selector']);
        }

        return '';
    }
}



if (!function_exists('warehouse_sync_has_submission_steps')) {
    function warehouse_sync_has_submission_steps(?array $connector): bool
    {
        if (!$connector) {
            return false;
        }
        return !empty(warehouse_sync_submission_steps($connector));
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
            'receiver_address' => trim((string)($item['receiver_address'] ?? '')),
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

if (!function_exists('warehouse_sync_is_positive_feedback_text')) {
    function warehouse_sync_is_positive_feedback_text(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        if ($normalized === '') {
            return false;
        }

        $compact = preg_replace('/\s+/u', '', $normalized);
        if (!is_string($compact)) {
            $compact = $normalized;
        }

        $positiveTokens = ['success', 'успеш', 'успех', 'saved', 'ok', 'done'];
        foreach ($positiveTokens as $token) {
            if (mb_strpos($normalized, $token, 0, 'UTF-8') !== false || mb_strpos($compact, $token, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('warehouse_sync_run_submission')) {
    function warehouse_sync_run_submission(array $connector, array $item, ?array $preparedPayload = null): array
    {

        $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
        if (!$scriptPath) {
            throw new RuntimeException('Не найден browser script для sync');
        }

        $tempDir = __DIR__ . '/../../scripts/_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        warehouse_sync_clear_directory($tempDir);

        if (is_array($preparedPayload)) {
            $payload = $preparedPayload;
        } else {
            $steps = warehouse_sync_submission_steps($connector);
            $vars = warehouse_sync_build_vars($connector, $item);
            $payload = [
                'steps' => $steps,
                'vars' => $vars,
                'ssl_ignore' => !empty($connector['ssl_ignore']),
                'cookies' => (string)($connector['auth_cookies'] ?? ''),
                'auth_token' => (string)($connector['auth_token'] ?? ''),
                'temp_dir' => realpath($tempDir) ?: $tempDir,
                'expect_download' => false,
                'error_selector' => warehouse_sync_submission_error_selector($connector),
                'error_wait_ms' => 1800,
            ];
        }

        $steps = isset($payload['steps']) && is_array($payload['steps']) ? $payload['steps'] : [];
        if (empty($steps)) {
            throw new RuntimeException('У коннектора не заполнены steps для Операции #2');
        }

        $vars = isset($payload['vars']) && is_array($payload['vars']) ? $payload['vars'] : [];

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
        $capturedErrorText = trim((string)($decoded['captured_error_text'] ?? ''));
        $outStatus = 'half_sync';
        if ($capturedErrorText !== '') {
            if (warehouse_sync_is_positive_feedback_text($capturedErrorText)) {
                $outStatus = 'half_sync';
            } else {
                throw new RuntimeException($capturedErrorText);
            }
        }


        return [
            'vars' => $vars,
            'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
            'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
            'message' => trim((string)($decoded['message'] ?? '')),
            'raw' => $decoded,
            'node_payload' => $payload,
            'captured_error_text' => $capturedErrorText,
            'out_status' => $outStatus,
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

    warehouse_sync_ensure_out_table($dbcnx);

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

    $statusMap = [];
    $rowIds = [];
    foreach ($rows as $row) {
        $rowIds[] = (int)($row['id'] ?? 0);
    }
    $rowIds = array_values(array_filter(array_unique($rowIds)));
    if (!empty($rowIds)) {
        $placeholders = implode(',', array_fill(0, count($rowIds), '?'));
        $sqlOut = "SELECT stock_item_id, status, status_message FROM warehouse_item_out WHERE stock_item_id IN ({$placeholders})";
        $stmtOut = $dbcnx->prepare($sqlOut);
        if ($stmtOut) {
            $typesOut = str_repeat('i', count($rowIds));
            $stmtOut->bind_param($typesOut, ...$rowIds);
            $stmtOut->execute();
            $resOut = $stmtOut->get_result();
            while ($resOut && ($outRow = $resOut->fetch_assoc())) {
                $statusMap[(int)$outRow['stock_item_id']] = $outRow;
            }
            $stmtOut->close();
        }
    }

    $statusClassMap = [
        'error' => 'text-danger',
        'for_sync' => 'text-warning',
        'half_sync' => 'text-warning',
        'confirmed_sync' => 'text-success',
        'to_send' => 'text-primary',
        'sended' => 'text-info',
    ];

    $missing = [];

    foreach ($rows as $row) {
        $itemId = (int)($row['id'] ?? 0);
        $forwarder = warehouse_sync_normalize_key((string)($row['receiver_company'] ?? ''));
        $country = warehouse_sync_normalize_key((string)($row['receiver_country_code'] ?? ''));
        if ($forwarder === '') {
            continue;
        }

        $row['report_table'] = '—';

        $out = $statusMap[$itemId] ?? null;
        $status = strtolower(trim((string)($out['status'] ?? 'for_sync')));
        $statusMessage = trim((string)($out['status_message'] ?? ''));

        $row['sync_status_class'] = $statusClassMap[$status] ?? 'text-muted';
        $row['sync_status_label'] = $status;
        if ($statusMessage !== '') {
            $row['report_confirmation_label'] = $statusMessage;
        }

        if ($country === '') {
            $row['sync_status_class'] = 'text-warning';
            $row['sync_status_label'] = 'for_sync';
            $row['report_confirmation_label'] = 'Не указана страна назначения';
            $row['can_sync'] = 0;
        } else {
            $row['can_sync'] = !in_array($status, ['half_sync', 'confirmed_sync', 'to_send', 'sended'], true) ? 1 : 0;
        }
        if (!in_array($status, ['for_sync', 'error'], true)) {
            continue;
        }
        $missing[] = $row;
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

    $connectorId = (int)($_POST['connector_id'] ?? 0);

    $item = warehouse_sync_fetch_item($dbcnx, $itemId);
    if (!$item) {
        $response = ['status' => 'error', 'message' => 'Посылка не найдена', 'item_id' => $itemId];
        return;
    }

    $forwarder = strtoupper(trim((string)($item['receiver_company'] ?? '')));
    $country = strtoupper(trim((string)($item['receiver_country_code'] ?? '')));
    $tracking = trim((string)($item['tracking_no'] ?? ''));

    try {
        $connector = null;
        if ($connectorId > 0) {
            $stmtConnector = $dbcnx->prepare("SELECT id, name, countries, auth_username, auth_password, auth_token, auth_cookies, base_url, ssl_ignore, scenario_json, operations_json, is_active FROM connectors WHERE id = ? AND is_active = 1 LIMIT 1");
            if ($stmtConnector) {
                $stmtConnector->bind_param('i', $connectorId);
                $stmtConnector->execute();
                $resConnector = $stmtConnector->get_result();
                if ($resConnector && ($connectorRow = $resConnector->fetch_assoc())) {
                    $connector = $connectorRow;
                }
                $stmtConnector->close();
            }
        }

        if (!$connector) {
            $connector = warehouse_sync_fetch_connector($dbcnx, $forwarder, $country);
        }
        if (!$connector) {
            throw new RuntimeException('Не найден активный коннектор для форварда ' . $forwarder);
        }

        $debugNodePayload = [
            'steps' => warehouse_sync_submission_steps($connector),
            'vars' => warehouse_sync_build_vars($connector, $item),
            'ssl_ignore' => !empty($connector['ssl_ignore']),
            'cookies' => (string)($connector['auth_cookies'] ?? ''),
            'auth_token' => (string)($connector['auth_token'] ?? ''),
            'temp_dir' => realpath(__DIR__ . '/../../scripts/_tmp') ?: (__DIR__ . '/../../scripts/_tmp'),
            'expect_download' => false,
            'error_selector' => warehouse_sync_submission_error_selector($connector),
            'error_wait_ms' => 1800,
        ];

        $result = warehouse_sync_run_submission($connector, $item, $debugNodePayload);
        $responsePayload = [
            'message' => $result['message'],
            'connector_id' => (int)($connector['id'] ?? 0),
            'step_log' => $result['step_log'],
            'artifacts_dir' => $result['artifacts_dir'],
            'vars' => $result['vars'],
            'node_payload' => isset($result['node_payload']) && is_array($result['node_payload']) ? $result['node_payload'] : $debugNodePayload,
        ];
        $responseJson = json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
        $outStatus = strtolower(trim((string)($result['out_status'] ?? 'half_sync')));
        if ($outStatus === '') {
            $outStatus = 'half_sync';
        }
        $capturedErrorText = trim((string)($result['captured_error_text'] ?? ''));
        $statusMessage = 'sync отправлен, ожидаем подтверждение форварда';
        if ($outStatus === 'half_sync' && $capturedErrorText !== '') {
            $statusMessage = 'sync отправлен, форвард ответил: ' . $capturedErrorText;
        }

        warehouse_sync_audit_log($dbcnx, [
            'item_id' => $itemId,
            'tracking_no' => $tracking,
            'forwarder' => $forwarder,
            'country_code' => $country,
            'status' => $outStatus,
            'message' => $statusMessage,
            'response_json' => $responseJson === false ? '' : $responseJson,
            'created_by' => $userId,
        ]);

        warehouse_sync_out_set_status($dbcnx, $itemId, $outStatus, $statusMessage);
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
            'node_payload' => isset($result['node_payload']) && is_array($result['node_payload']) ? $result['node_payload'] : $debugNodePayload,
        ];
    } catch (Throwable $e) {
        $errorPayload = ['error' => $e->getMessage()];
        if (isset($debugNodePayload) && is_array($debugNodePayload)) {
            $errorPayload['node_payload'] = $debugNodePayload;
        }
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
        warehouse_sync_out_set_status($dbcnx, $itemId, 'error', $e->getMessage());
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
            'node_payload' => isset($debugNodePayload) && is_array($debugNodePayload) ? $debugNodePayload : null,
        ];
    }
}

if ($action === 'warehouse_sync_history') {
    auth_require_login();
    warehouse_sync_ensure_audit_table($dbcnx);


    $statusFilter = strtolower(trim((string)($_POST['status_filter'] ?? 'all')));
    $trackingFilter = strtoupper(trim((string)($_POST['tracking_no'] ?? '')));

    $conditions = [];
    $params = [];
    $types = '';

    $allowedStatuses = ['error', 'for_sync', 'half_sync', 'confirmed_sync', 'to_send', 'sended', 'success'];
    if (in_array($statusFilter, $allowedStatuses, true)) {
        $conditions[] = 'a.status = ?';
        $types .= 's';
        $params[] = $statusFilter;
    }

    if ($trackingFilter !== '') {
        $conditions[] = 'UPPER(a.tracking_no) LIKE ?';
        $types .= 's';
        $params[] = '%' . $trackingFilter . '%';
    }

    $whereSql = '';
    if (!empty($conditions)) {
        $whereSql = 'WHERE ' . implode(' AND ', $conditions);
    } elseif ($statusFilter === 'all') {
        $whereSql = "WHERE a.status <> 'for_sync'";
    }


    $rows = [];
    $sql = "SELECT a.id, a.item_id, a.tracking_no, a.forwarder, a.country_code, a.status, a.message, a.created_at, u.full_name AS user_name
"
        . "FROM warehouse_sync_audit a
"
        . "LEFT JOIN users u ON u.id = a.created_by
"
        . "{$whereSql}
"
        . "ORDER BY a.id DESC
"
        . "LIMIT 200";

    if ($types === '') {
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
    } else {
        $stmt = $dbcnx->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
    }

    $response = [
        'status' => 'ok',
        'rows' => $rows,
        'total' => count($rows),
    ];
}





if ($action === 'warehouse_sync_out_backfill') {
    auth_require_login();

    $current = $user;
    $userId = (int)($current['id'] ?? 0);
    $limit = max(1, (int)($_POST['limit'] ?? 500));

    $result = warehouse_sync_out_backfill_from_stock($dbcnx, $limit);

    audit_log($userId, 'WAREHOUSE_SYNC_OUT_BACKFILL', 'warehouse_item_out', 0, 'Backfill warehouse_item_out выполнен', [
        'limit' => $limit,
        'inserted' => (int)($result['inserted'] ?? 0),
        'updated' => (int)($result['updated'] ?? 0),
        'processed' => (int)($result['processed'] ?? 0),
    ]);

    $response = [
        'status' => 'ok',
        'limit' => $limit,
        'inserted' => (int)($result['inserted'] ?? 0),
        'updated' => (int)($result['updated'] ?? 0),
        'processed' => (int)($result['processed'] ?? 0),
    ];
}

if ($action === 'warehouse_sync_reconcile') {
    auth_require_login();

    $current = $user;
    $userId = (int)($current['id'] ?? 0);
    $limit = max(1, (int)($_POST['limit'] ?? 200));

    $stats = warehouse_sync_reconcile_half_sync($dbcnx, $limit, $userId);

    audit_log($userId, 'WAREHOUSE_SYNC_RECONCILE', 'warehouse_item_out', 0, 'Reconcile статусов warehouse_item_out выполнен', [
        'limit' => $limit,
        'checked' => (int)($stats['checked'] ?? 0),
        'confirmed_sync' => (int)($stats['confirmed_sync'] ?? 0),
        'error' => (int)($stats['error'] ?? 0),
        'unchanged' => (int)($stats['unchanged'] ?? 0),
    ]);

    $response = [
        'status' => 'ok',
        'limit' => $limit,
        'stats' => $stats,
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
