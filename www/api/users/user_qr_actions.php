<?php
declare(strict_types=1);

global $dbcnx;

$response = ['status' => 'error', 'message' => 'Unknown QR action'];

switch ($action) {
    case 'users_regen_qr':
        auth_require_role('ADMIN');
        
        $ok = 0;
        $err = 0;
        
        $sql = "SELECT id, qr_login_token FROM users ORDER BY id";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $fn = ensure_user_qr_image($row);
                if ($fn !== null) {
                    $ok++;
                } else {
                    $err++;
                }
            }
            $res->free();
        }
        
        $response = [
            'status' => 'ok',
            'message' => "QR-коды обновлены. Успешно: {$ok}, ошибок: {$err}"
        ];
        break;
}