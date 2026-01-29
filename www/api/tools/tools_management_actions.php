<?php
declare(strict_types=1);

/**
 * Экран управления инструментами.
 * Actions: tools_management
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown tools management action'];

switch ($action) {
    case 'tools_management':
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_management.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
}
