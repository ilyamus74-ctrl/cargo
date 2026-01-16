<?php
declare(strict_types=1);
/**
 * Обработчик действий с остатками на складе
 * Actions: item_stock
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse stock action'];
if ($action === 'item_stock') {
    auth_require_login();
    $current = $user;
    $batches = [];
    if (auth_has_permission('warehouse.stock.view_all')) {
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
    auth_require_login();
    $current = $user;
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
    ];
    $params = [];
    $types = '';

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
        'has_more'    => $limit !== null ? ($offset + count($parcels) < $total) : false,
    ];
}


if ($action === 'item_stock_in_storage') {
    auth_require_login();
    $current = $user;
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

    if ($search !== '') {
        $conditions[] = "(wi.receiver_name LIKE ? OR wi.tracking_no LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);

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
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $canManageStock = auth_has_permission('warehouse.stock.manage');
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    if ($itemId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }
    $item = null;
    if ($canManageStock) {
        $sql = "
            SELECT
                id,
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
                cell_id,
                sender_name,
                sender_company,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm
            FROM warehouse_item_stock
            WHERE id = ?
            LIMIT 1
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $itemId);
    } else {
        $sql = "
            SELECT
                id,
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
                cell_id,
                sender_name,
                sender_company,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm
            FROM warehouse_item_stock
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("ii", $itemId, $userId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $item = $row;
    }
    $stmt->close();
    if (!$item) {
        $response = [
            'status'  => 'error',
            'message' => 'Посылка не найдена',
        ];
        return;
    }
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
    $smarty->assign('item', $item);
    $smarty->assign('dest_country', $dest_country);
    $smarty->assign('stand_devices', $stand_devices);
    $smarty->assign('cells', $cells);
    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock_modal.html');
    $html = ob_get_clean();
    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
}

if ($action === 'save_item_stock') {
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $canManageStock = auth_has_permission('warehouse.stock.manage');
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    if ($itemId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Некорректный идентификатор посылки',
        ];
        return;
    }
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
    $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
    $sizeL = ($sizeL === '' || $sizeL === null) ? 0.0 : (float)$sizeL;
    $sizeW = ($sizeW === '' || $sizeW === null) ? 0.0 : (float)$sizeW;
    $sizeH = ($sizeH === '' || $sizeH === null) ? 0.0 : (float)$sizeH;
    $receiverCountryName = '';
    if ($rcCountryCode !== '') {
        $stmt = $dbcnx->prepare("SELECT name_en FROM dest_countries WHERE code_iso2 = ? LIMIT 1");
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

    if ($canManageStock) {
        $sql = "
            SELECT
                id,
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
                cell_id,
                sender_name,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm
            FROM warehouse_item_stock
            WHERE id = ?
            LIMIT 1
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $itemId);
    } else {
        $sql = "
            SELECT
                id,
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
                cell_id,
                sender_name,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm
            FROM warehouse_item_stock
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("ii", $itemId, $userId);
    }
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        return;
    }
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

    $originalCellId = $existingItem['cell_id'] !== null ? (int)$existingItem['cell_id'] : null;
    $newCellId = $cellId !== null ? (int)$cellId : null;

    if ($canManageStock) {
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
                   size_h_cm = ?
             WHERE id = ?
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param(
            "sssssssssisddddi",
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
            $itemId
        );
    } else {
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
                   size_h_cm = ?
             WHERE id = ?
               AND user_id = ?
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param(
            "sssssssssisddddii",
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
            $itemId,
            $userId
        );
    }
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        return;
    }
    $stmt->execute();
    $stmt->close();


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

    if (!empty($changes)) {
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_UPDATE_PARCEL',
            'WAREHOUSE_STOCK',
            $itemId,
            'Отредактированы данные посылки на складе',
            [
                'item_id'   => $itemId,
                'batch_uid' => (int)$existingItem['batch_uid'],
                'changes'   => $changes,
            ]
        );
    }

    if ($originalCellId !== $newCellId) {
        $cellCodeLookup = function (?int $cellId) use ($dbcnx): ?string {
            if ($cellId === null) {
                return null;
            }
            $stmt = $dbcnx->prepare("SELECT code FROM cells WHERE id = ? LIMIT 1");
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param("i", $cellId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ? (string)$row['code'] : null;
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
                'batch_uid' => (int)$existingItem['batch_uid'],
                'cell_id_old' => $originalCellId,
                'cell_id_new' => $newCellId,
                'cell_code_old' => $oldCellCode,
                'cell_code_new' => $newCellCode,
            ]
        );
    }

    $response = [
        'status'  => 'ok',
        'message' => 'Данные посылки сохранены',
    ];
}
