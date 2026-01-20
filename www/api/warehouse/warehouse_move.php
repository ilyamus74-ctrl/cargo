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


    $smarty->assign('current_user', $user);
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
