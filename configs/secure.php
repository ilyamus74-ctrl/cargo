<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/connectDB.php'; // тут появляется $dbcnx (mysqli)

/**
 * Текущий пользователь из сессии или null.
 */
function auth_current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Залогинен ли пользователь.
 */
function auth_is_logged_in(): bool {
    return isset($_SESSION['user']);
}

/**
 * Проверка роли по коду ('ADMIN', 'WORKER', 'ANALYST').
 */
function auth_has_role(string $roleCode): bool {
    $user = auth_current_user();
    if (!$user) {
        return false;
    }
    return in_array($roleCode, $user['roles'] ?? [], true);
}

/**
 * Обязателен логин, иначе редирект на страницу логина.
 * $lang — текущий язык ('uk', 'ru', 'en', 'de').
 */
function auth_require_login(string $lang): void {
    if (!auth_is_logged_in()) {
        header("Location: /{$lang}/login.html");
        exit;
    }
}

/**
 * Обязательная роль (например, только админ).
 */
function auth_require_role(string $lang, string $roleCode): void {
    auth_require_login($lang);
    if (!auth_has_role($roleCode)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

/**
 * Логин: проверка username/password, загрузка ролей, обновление статистики, запись в сессию и аудит.
 */
function auth_login(string $username, string $password): bool {
    global $dbcnx;

    // 1) забираем пользователя
    $sql = "SELECT id, username, password_hash, full_name, email, is_active
            FROM users
            WHERE username = ?
            LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        // можно залогировать ошибку
        return false;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user || (int)$user['is_active'] !== 1) {
        return false;
    }

    // 2) проверяем пароль (bcrypt)
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $userId = (int)$user['id'];

    // 3) забираем роли пользователя
    $sqlRoles = "SELECT r.code
                 FROM user_roles ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ?";
    $stmtR = $dbcnx->prepare($sqlRoles);
    $stmtR->bind_param("i", $userId);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    $roles = [];
    while ($row = $resR->fetch_assoc()) {
        $roles[] = $row['code'];
    }
    $stmtR->close();

    // 4) обновляем статистику входа
    $ip = $_SERVER['REMOTE_ADDR']     ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sqlUpd = "UPDATE users
               SET last_login_at = CURRENT_TIMESTAMP(6),
                   login_count   = login_count + 1,
                   last_login_ip = ?,
                   last_user_agent = ?
               WHERE id = ?";
    $stmtU = $dbcnx->prepare($sqlUpd);
    $stmtU->bind_param("ssi", $ip, $ua, $userId);
    $stmtU->execute();
    $stmtU->close();

    // 5) сохраняем в сессию
    $_SESSION['user'] = [
        'id'        => $userId,
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'roles'     => $roles,
    ];

    // 6) логируем успешный логин
    audit_log($userId, 'LOGIN', null, null, 'Пользователь вошёл в систему');

    return true;
}

/**
 * Логаут + аудит.
 */
function auth_logout(): void {
    $user = auth_current_user();
    if ($user) {
        audit_log((int)$user['id'], 'LOGOUT', null, null, 'Пользователь вышел из системы');
    }

    $_SESSION['user'] = null;
    unset($_SESSION['user']);

    // По желанию можно полностью грохнуть сессию
    /*
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    */
}

/**
 * Запись события в таблицу audit_logs.
 */
function audit_log(
    ?int $userId,
    string $eventType,
    ?string $entityType,
    ?int $entityId,
    string $description,
    array $extra = []
): void {
    global $dbcnx;

    $uid  = (int) (microtime(true) * 1000000); // UID на основе времени
    $ip   = $_SERVER['REMOTE_ADDR']     ?? null;
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $json = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

    $sql = "INSERT INTO audit_logs (
                uid_created, event_time, user_id, event_type,
                entity_type, entity_id, ip_address, user_agent,
                description, extra_data
            ) VALUES (
                ?, CURRENT_TIMESTAMP(6), ?, ?, ?, ?, ?, ?, ?, ?
            )";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return;
    }

    // i i s s i s s s s
    $stmt->bind_param(
        "iississsss",
        $uid,
        $userId,
        $eventType,
        $entityType,
        $entityId,
        $ip,
        $ua,
        $description,
        $json
    );
    $stmt->execute();
    $stmt->close();
}
