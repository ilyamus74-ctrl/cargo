<?php
declare(strict_types=1);

require_once __DIR__ . '/../configs/secure.php';
//require_once __DIR__ . '/bootstrap.php';
// если надо — только для залогиненных
if (auth_is_logged_in()) {
    auth_logout();  // внутри уже есть audit_log(LOGOUT)
}
//auth_logout();
header('Location: /login');
exit;
?>