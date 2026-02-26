<?php
declare(strict_types=1);
/**
 * Обработчик действий с остатками на складе
 * Actions:  item_stock
 */
// Доступны:  $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse stock action'];


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

    $conditions = [
        "wi.cell_id IS NOT NULL",
    ];
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
            weight_kg,
            size_l_cm,
            size_w_cm,
            size_h_cm,
            addons_json
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
            weight_kg,
            size_l_cm,
            size_w_cm,
            size_h_cm,
            addons_json
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
    $senderCode = trim($_POST['sender_name'] ?? '');
    $weightKg = $_POST['weight_kg'] ?? '';
    $sizeL = $_POST['size_l_cm'] ?? '';
    $sizeW = $_POST['size_w_cm'] ?? '';
    $sizeH = $_POST['size_h_cm'] ?? '';
    $addonsJsonRaw = trim((string)($_POST['addons_json'] ?? ''));

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
               cell_id = ?,
               sender_name = ?,
               weight_kg = ?,
               size_l_cm = ?,
               size_w_cm = ?,
               size_h_cm = ?,
               addons_json = ?
         WHERE id = ?
    ";
    $stmt = $dbcnx->prepare($sql);
    $stmt->bind_param(
        "sssssssssisddddssi",
        $tuid,
        $tracking,
        $carrierCode,
        $carrierName,
        $rcCountryCode,
        $receiverCountryName,
        $rcName,
        $rcCompany,
        $rcAddress,
        $cellId,
        $senderCode,
        $weightKg,
        $sizeL,
        $sizeW,
        $sizeH,
        $addonsJsonRaw,
        $itemId
    );
    
    if (! $stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error:  ' . $dbcnx->error,
        ];
        return;
    }
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
