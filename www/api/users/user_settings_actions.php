<?php
declare(strict_types=1);

global $dbcnx, $smarty;

$response = ['status' => 'error', 'message' => 'Unknown settings action'];

switch ($action) {
    case 'get_user_info':
        $response = [
            'status' => 'ok',
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'ui_lang' => $user['ui_lang'] ?? null,
            ]
        ];
        break;
    
    case 'set_lang':
        $newLang = $_POST['lang'] ?? '';
        $allowed = ['uk', 'ru', 'en', 'de'];
        
        if (!in_array($newLang, $allowed, true)) {
            $response = [
                'status' => 'error',
                'message' => 'Недопустимый язык'
            ];
            break;
        }
        
        $_SESSION['lang'] = $newLang;
        $_SESSION['user']['ui_lang'] = $newLang;
        
        $stmt = $dbcnx->prepare("UPDATE users SET ui_lang = ? WHERE id = ?");
        $stmt->bind_param("si", $newLang, $user['id']);
        $stmt->execute();
        $stmt->close();
        
        $response = [
            'status' => 'ok',
            'message' => 'Язык обновлён'
        ];
        break;
    
    case 'get_user_panel_html':
        ob_start();
        $smarty->assign('current_user', $user);
        $smarty->display('cells_NA_API_users_panel.html');
        $html = ob_get_clean();
        
        $response = [
            'status' => 'ok',
            'html' => $html
        ];
        break;
}