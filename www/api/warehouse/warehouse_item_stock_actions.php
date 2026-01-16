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
    if (auth_has_role('ADMIN')) {
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
