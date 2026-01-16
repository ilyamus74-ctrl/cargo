<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

auth_require_login();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$response = [
    'status'  => 'error',
    'message' => 'Unknown action',
];

$user = auth_current_user();

try {
    // === РОУТИНГ по модулям ===
    
    // 1) User actions
    if (in_array($action, ['view_users', 'form_new_user', 'form_edit_user', 'save_user', 'get_user_info', 'set_lang', 'get_user_panel_html', 'users_regen_qr'], true)) {
        require_once __DIR__ . '/user_actions.php';
        
        switch ($action) {
            case 'get_user_info':
                $response = handle_get_user_info($user);
                break;
            case 'set_lang':
                $response = handle_set_lang($user);
                break;
            case 'get_user_panel_html':
                $response = handle_get_user_panel_html($user);
                break;
            case 'view_users':
                $response = handle_view_users($user);
                break;
            case 'form_new_user':
                $response = handle_form_new_user($user);
                break;
            case 'form_edit_user':
                $response = handle_form_edit_user($user);
                break;
            case 'save_user':
                $response = handle_save_user($user);
                break;
            case 'users_regen_qr':
                $response = handle_users_regen_qr();
                break;
        }
    }
    
    // 2) Device actions
    elseif (in_array($action, ['view_devices', 'activate_device', 'form_edit_device', 'save_device'], true)) {
        require_once __DIR__ . '/device_actions.php';
        $response = handle_device_action($action, $user);
    }
    
    // 3) Tools stock
    elseif (in_array($action, ['view_tools_stock', 'tools_stock', 'form_new_tool_stock', 'form_edit_tool_stock', 'save_tool', 'upload_tool_photo'], true)) {
        require_once __DIR__ . '/tools_actions.php'; // СОЗДАТЬ!
        $response = handle_tools_action($action, $user);
    }
    
    // 4) Warehouse / cells
    elseif (in_array($action, ['setting_cells', 'add_new_cells', 'delete_cell', 'warehouse_item_in', 'item_in', 'item_stock', 'open_item_in_batch', 'add_new_item_in', 'delete_item_in', 'commit_item_in_batch'], true)) {
        require_once __DIR__ . '/core_actions.php';
        require_once __DIR__ . '/core_helpers.php';
        
        ob_start();
        include __DIR__ . '/core_actions.php'; // там уже есть switch
        ob_end_clean();
        // Если core_actions установил $response - используем
    }
    
    else {
        http_response_code(400);
        $response = [
            'status'  => 'error',
            'message' => 'Unknown action: ' . $action,
        ];
    }

} catch (Throwable $e) {
    error_log('core_api exception: ' . $e->getMessage());

    http_response_code(500);
    $response = [
        'status'  => 'error',
        'message' => 'Внутренняя ошибка сервера',
        'error'   => $e->getMessage(),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

