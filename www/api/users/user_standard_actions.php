<?php
declare(strict_types=1);

// $user уже доступен из core_api.php
global $dbcnx, $smarty;

$response = ['status' => 'error', 'message' => 'Unknown user action'];

switch ($action) {
    case 'view_users':
        $users = fetch_users_list($dbcnx);
        
        $smarty->assign('users', $users);
        $smarty->assign('current_user', $user);
        
        ob_start();
        $smarty->display('cells_NA_API_users.html');
        $html = ob_get_clean();
        
        $response = [
            'status' => 'ok',
            'html' => $html
        ];
        break;
    
    case 'form_new_user':
        $roles = [];
        $sql = "SELECT code, name FROM roles WHERE is_active = 1 ORDER BY id";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $roles[] = $row;
            }
            $res->free();
        }
        
        $editUser = [
            'id' => '',
            'full_name' => '',
            'email' => '',
            'role' => '',
            'settings' => [],
        ];
        
        $smarty->assign('roles', $roles);
        $smarty->assign('edit_user', $editUser);
        $smarty->assign('current_user', $user);
        
        ob_start();
        $smarty->display('cells_NA_API_profile.html');
        $html = ob_get_clean();
        
        $response = [
            'status' => 'ok',
            'html' => $html
        ];
        break;
    
    case 'form_edit_user':
        $editId = (int)($_POST['user_id'] ?? 0);
        if ($editId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'Не передан user_id'
            ];
            break;
        }
        
        // ... код из твоего core_api.php для form_edit_user
        
        break;
    
    case 'save_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        $deleteFlag = !empty($_POST['delete']);
        
        // ... код из твоего core_api.php для save_user
        
        break;
}

// $response будет подхвачен в core_api.php