<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/api/core_helpers.php';

auth_require_login();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user = auth_current_user();

// Маппинг action → файл обработчика
$routes = [
    // ========== USERS ==========
    'view_users'          => 'api/users/user_standard_actions.php',
    'form_new_user'       => 'api/users/user_standard_actions.php',
    'form_edit_user'      => 'api/users/user_standard_actions.php',
    'save_user'           => 'api/users/user_standard_actions.php',
    'users_regen_qr'      => 'api/users/user_qr_actions.php',
    'get_user_info'       => 'api/users/user_settings_actions.php',
    'set_lang'            => 'api/users/user_settings_actions.php',
    'get_user_panel_html' => 'api/users/user_settings_actions.php',
];

if (!isset($routes[$action])) {
    // ВРЕМЕННО: fallback на старую логику для не-users действий
    // После рефакторинга tools/warehouse этот блок будет удален
    require_once __DIR__ . '/core_api.php.backup.20260113';
    exit;
}

try {
    $handler = __DIR__ . '/' . $routes[$action];
    
    if (!is_file($handler)) {
        throw new RuntimeException("Handler file not found: {$handler}");
    }
    
    // Подключаем файл — он установит $response
    require $handler;
    
    if (!isset($response)) {
        throw new RuntimeException("Handler did not set \$response variable");
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log('core_api exception: ' . $e->getMessage());
    http_response_code(500);
    
    $errorResponse = [
        'status' => 'error',
        'message' => 'Internal server error'
    ];
    
    // Only include debug info if in development mode
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorResponse['debug'] = $e->getMessage();
    }
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}
