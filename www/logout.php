<?php
declare(strict_types=1);

// secure.php уже тянется в index.php, но require_once не помешает
require_once __DIR__ . '/../configs/secure.php';

/**
 * Выход из системы:
 *  - пишем событие LOGOUT в audit_logs (если пользователь есть),
 *  - чистим сессию (внутри auth_logout),
 *  - отправляем на страницу логина.
 */
auth_logout();

// после выхода — на страницу логина
header('Location: /login.html');
exit;
