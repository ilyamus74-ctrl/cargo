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