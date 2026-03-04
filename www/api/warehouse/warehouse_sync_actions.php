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
        'wi.cell_id IS NOT NULL',
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
        if ($forwarder === '' || $country === '') {
            continue;
        }

        $reportTable = 'connector_report_' . strtolower($forwarder . '_' . $country);
        if (!warehouse_sync_table_exists($dbcnx, $reportTable)) {
            continue;
        }

        $reportIdentifiers = warehouse_sync_report_identifiers($dbcnx, $reportTable);
        if (empty($reportIdentifiers)) {
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
            $row['report_table'] = $reportTable;
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
