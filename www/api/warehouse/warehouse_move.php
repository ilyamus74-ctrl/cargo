<?php
declare(strict_types=1);
/**
 * Обработчик действий перемещения по складу.
 * Actions: warehouse_move
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse move action'];

if ($action === 'warehouse_move') {
    auth_require_login();

///    require_once __DIR__ . '/../../ocr_templates.php';
///    require_once __DIR__ . '/../../ocr_dicts.php';

    $cells = [];
    $sql = "SELECT id, code FROM cells ORDER BY code ASC";
    if ($resCells = $dbcnx->query($sql)) {
        while ($row = $resCells->fetch_assoc()) {
            $cells[] = $row;
        }
        $resCells->free();
    }

    $smarty->assign('current_user', $user);
    $smarty->assign('cells', $cells);
    ob_start();
    $smarty->display('cells_NA_API_warehouse_move.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];

}

if ($action === 'warehouse_move_search') {
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $search = trim((string)($_POST['search'] ?? ''));
    if ($search === '') {
        $response = [
            'status' => 'ok',
            'html'   => '',
            'total'  => 0,
        ];
        return;
    }

    $like = '%' . $search . '%';
    $params = [$like, $like];
    $types = 'ss';

    $stockUserSql = '';
    $inUserSql = '';
    if (!$canViewAll) {
        $stockUserSql = 'AND wi.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }

    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';

    if (!$canViewAll) {
        $inUserSql = 'AND wi.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }

    $sql = "
        (
            SELECT
                'stock' AS source,
                wi.id,
                COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
                wi.tracking_no,
                wi.created_at,
                wi.user_id,
                u.full_name AS user_name,
                c.code AS cell_address
            FROM warehouse_item_stock wi
            LEFT JOIN users u ON u.id = wi.user_id
            LEFT JOIN cells c ON c.id = wi.cell_id
            WHERE (wi.tuid LIKE ? OR wi.tracking_no LIKE ?)
            {$stockUserSql}
        )
        UNION ALL
        (
            SELECT
                'in' AS source,
                wi.id,
                COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
                wi.tracking_no,
                wi.created_at,
                wi.user_id,
                u.full_name AS user_name,
                NULL AS cell_address
            FROM warehouse_item_in wi
            LEFT JOIN users u ON u.id = wi.user_id
            WHERE wi.committed = 0
              AND (wi.tuid LIKE ? OR wi.tracking_no LIKE ?)
            {$inUserSql}
        )
        ORDER BY created_at DESC
        LIMIT 50
    ";

    $moveItems = [];
    $stmt = $dbcnx->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $moveItems[] = $row;
    }
    $stmt->close();

    $smarty->assign('move_items', $moveItems);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', true);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_move_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
        'total'  => count($moveItems),
    ];
}


if ($action === 'warehouse_move_batch_search') {
    auth_require_login();
    $current = $user;
    $userId = (int)$current['id'];
    $isWorker = auth_has_role('WORKER');
    $canViewAll = auth_has_permission('warehouse.stock.view_all') || auth_has_role('ADMIN') || $isWorker;

    $search = trim((string)($_POST['search'] ?? ''));
    if ($search === '') {
        $response = [
            'status' => 'ok',
            'html'   => '',
            'total'  => 0,
        ];
        return;
    }

    $like = '%' . $search . '%';
    $params = [$like, $like];
    $types = 'ss';

    $stockUserSql = '';
    $inUserSql = '';
    if (!$canViewAll) {
        $stockUserSql = 'AND wi.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }

    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';

    if (!$canViewAll) {
        $inUserSql = 'AND wi.user_id = ?';
        $types .= 'i';
        $params[] = $userId;
    }

    $sql = "
        (
            SELECT
                'stock' AS source,
                wi.id,
                COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
                wi.tracking_no,
                wi.created_at,
                wi.user_id,
                u.full_name AS user_name,
                c.code AS cell_address
            FROM warehouse_item_stock wi
            LEFT JOIN users u ON u.id = wi.user_id
            LEFT JOIN cells c ON c.id = wi.cell_id
            WHERE (wi.tuid LIKE ? OR wi.tracking_no LIKE ?)
            {$stockUserSql}
        )
        UNION ALL
        (
            SELECT
                'in' AS source,
                wi.id,
                COALESCE(NULLIF(wi.tuid, ''), NULLIF(wi.tracking_no, ''), wi.uid_created) AS parcel_uid,
                wi.tracking_no,
                wi.created_at,
                wi.user_id,
                u.full_name AS user_name,
                NULL AS cell_address
            FROM warehouse_item_in wi
            LEFT JOIN users u ON u.id = wi.user_id
            WHERE wi.committed = 0
              AND (wi.tuid LIKE ? OR wi.tracking_no LIKE ?)
            {$inUserSql}
        )
        ORDER BY created_at DESC
        LIMIT 50
    ";

    $moveItems = [];
    $stmt = $dbcnx->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $moveItems[] = $row;
    }
    $stmt->close();

    $smarty->assign('move_items', $moveItems);
    $smarty->assign('current_user', $current);
    $smarty->assign('show_empty', true);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_move_batch_rows.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
        'total'  => count($moveItems),
    ];
}

if ($action === 'warehouse_move_open_modal') {
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
            size_h_cm
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

    $canAccess = false;
    if ($isAdmin) {
        $canAccess = true;
    } elseif ($isWorker) {
        $canAccess = true;
    } elseif ($itemUserId === $userId) {
        $canAccess = true;
    } elseif ($canManageStock || $canViewAllStock) {
        $canAccess = true;
    }

    if (!$canAccess) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для просмотра этой посылки',
        ];

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
    $smarty->assign('can_edit', $isAdmin || $isWorker || $itemUserId === $userId || $canManageStock);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_move_modal.html');
    $html = ob_get_clean();

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


if ($action === 'warehouse_move_batch_assign') {
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

    $cellId = isset($_POST['cell_id']) ? (int)$_POST['cell_id'] : 0;
    if ($cellId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Выберите ячейку для перемещения',
        ];
        return;
    }

    $checkSql = "
        SELECT
            id,
            user_id,
            cell_id,
            batch_uid
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

    $canEdit = false;
    if ($isAdmin) {
        $canEdit = true;
    } elseif ($isWorker) {
        $canEdit = true;
    } elseif ($itemUserId === $userId) {
        $canEdit = true;
    } elseif ($canManageStock) {
        $canEdit = true;
    }

    if (!$canEdit) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для редактирования этой посылки',
        ];

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

    $sql = "
        UPDATE warehouse_item_stock
           SET cell_id = ?
         WHERE id = ?
    ";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error:  ' . $dbcnx->error,
        ];
        return;
    }
    $stmt->bind_param("ii", $cellId, $itemId);
    $stmt->execute();
    $stmt->close();

    $originalCellId = $existingItem['cell_id'] !== null ? (int)$existingItem['cell_id'] : null;
    $newCellId = $cellId !== null ? (int)$cellId : null;

    if ($originalCellId !== $newCellId) {
        $cellCodeLookup = function (?int $cellId) use ($dbcnx): ?string {
            if ($cellId === null) {
                return null;
            }
            $stmt = $dbcnx->prepare("SELECT code FROM cells WHERE id = ?  LIMIT 1");
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
        'message' => 'Адрес ячейки обновлен',
    ];
}


if ($action === 'warehouse_move_save_cell') {
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

    $checkSql = "
        SELECT
            id,
            user_id,
            cell_id,
            batch_uid
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

    $canEdit = false;
    if ($isAdmin) {
        $canEdit = true;
    } elseif ($isWorker) {
        $canEdit = true;
    } elseif ($itemUserId === $userId) {
        $canEdit = true;
    } elseif ($canManageStock) {
        $canEdit = true;
    }

    if (!$canEdit) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для редактирования этой посылки',
        ];

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

    $cellId = isset($_POST['cell_id']) ? (int)$_POST['cell_id'] : 0;
    $cellId = $cellId > 0 ? $cellId : null;

    $sql = "
        UPDATE warehouse_item_stock
           SET cell_id = ?
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
    $stmt->bind_param("ii", $cellId, $itemId);
    $stmt->execute();
    $stmt->close();

    $originalCellId = $existingItem['cell_id'] !== null ? (int)$existingItem['cell_id'] : null;
    $newCellId = $cellId !== null ? (int)$cellId : null;

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
        'message' => 'Адрес ячейки сохранен',
    ];
}
