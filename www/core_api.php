<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// все эти операции только для залогиненных
auth_require_login();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$response = [
    'status'  => 'error',
    'message' => 'Unknown action',
];

/**
 * Получить список пользователей для таблиц (используется в нескольких разделах).
 */
function fetch_users_list(mysqli $dbcnx): array
{
    $users = [];

    $sql = "SELECT id,
                   username,
                   full_name,
                   email,
                   is_active,
                   created_at,
                   last_login_at,
                   login_count
              FROM users
          ORDER BY id";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        $res->free();
    }

    return $users;
}

function build_tool_qr_path_json(string $uid): string
{
    $dir = __DIR__ . '/img/tools_stock/' . $uid;

    // сначала пробуем собрать то, что уже лежит
    $qr = collect_tool_qr_images($uid, $dir);

    // если пусто — генерим и собираем заново (чтобы попал и qr_raw.png)
    if (!$qr) {
        generate_tool_qr_images($uid, get_subdomain_label());
        $qr = collect_tool_qr_images($uid, $dir);
    }

    if (!$qr) {
        return '{}';
    }

    return json_encode($qr, JSON_UNESCAPED_SLASHES) ?: '{}';
}

/**
 * @param mixed $raw
 */
function parse_tool_photos($raw): array
{
    if (is_array($raw)) {
        return array_values(array_filter(array_map('strval', $raw)));
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded)));
    }

    return [trim($raw)];
}


function fetch_tools_list(mysqli $dbcnx): array
{
    $tools = [];

    $sql = "SELECT id,
                   name,
                   serial_number,
                   registered_at,
                   status
              FROM tool_resources
          ORDER BY id";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $tools[] = $row;
        }
        $res->free();
    }

    return $tools;
}

function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function cm_to_px(float $cm, int $dpi = 300): int
{
    return (int)round($cm * $dpi / 2.54);
}

function get_subdomain_label(): string
{
    $host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $parts = array_filter(explode('.', $host));

    return strtoupper($parts[0] ?? '');
}

function remove_directory_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_directory_recursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function collect_tool_qr_images(string $uid, string $baseDir): array
{
    $result = [];

    foreach (glob($baseDir . '/qr_*.png') as $filePath) {
        $fileName = basename($filePath);

        if (preg_match('/^qr_([^\\.]+)\\.png$/', $fileName, $m)) {
            $suffix = $m[1];
            $result[$suffix] = sprintf('/img/tools_stock/%s/%s', $uid, $fileName);
        }
    }

    ksort($result);

    return $result;
}


function generate_tool_qr_images(string $uid, string $labelText): array
{
    $baseDir = __DIR__ . '/img/tools_stock/' . $uid;
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        error_log("QR: cannot create dir {$baseDir}");
        return [];
    }

    $qrPayload = $uid;
    $rawPath   = $baseDir . '/qr_raw.png';

    $cmd = sprintf(
        'qrencode -o %s -s 8 -l M %s 2>/dev/null',
        escapeshellarg($rawPath),
        escapeshellarg($qrPayload)
    );
    exec($cmd, $out, $ret);
    if ($ret !== 0 || !is_file($rawPath)) {
        error_log("QR: qrencode failed for tool {$uid}, rc={$ret}");
        return [];
    }

    $rawImage = imagecreatefrompng($rawPath);
    if (!$rawImage) {
        error_log("QR: cannot open raw QR for tool {$uid}");
        return [];
    }

    $qrWidth     = imagesx($rawImage);
    $qrHeight    = imagesy($rawImage);
    $labelHeight = 36;
    $canvas      = imagecreatetruecolor($qrWidth, $qrHeight + $labelHeight);

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 0, 0, 0);

    imagefill($canvas, 0, 0, $white);
    imagecopy($canvas, $rawImage, 0, 0, 0, 0, $qrWidth, $qrHeight);

    $font      = 5;
    $text      = $labelText ?: 'QR';
    $textWidth = imagefontwidth($font) * strlen($text);
    $textX     = max(0, (int)(($qrWidth - $textWidth) / 2));
    $textY     = $qrHeight + (int)(($labelHeight - imagefontheight($font)) / 2);
    imagestring($canvas, $font, $textX, $textY, $text, $black);

    $sizesCm = [
        '5x5' => 5.0,
        '3x3' => 3.0,
        '2x2' => 2.0,
    ];

    $result = [];

    foreach ($sizesCm as $suffix => $cm) {
        $targetSize = cm_to_px($cm);
        $resized    = imagecreatetruecolor($targetSize, $targetSize);

        imagefill($resized, 0, 0, $white);
        imagecopyresampled(
            $resized,
            $canvas,
            0,
            0,
            0,
            0,
            $targetSize,
            $targetSize,
            imagesx($canvas),
            imagesy($canvas)
        );

        $fileName = sprintf('qr_%s.png', $suffix);
        $filePath = $baseDir . '/' . $fileName;

        imagepng($resized, $filePath);
        imagedestroy($resized);

        $result[$suffix] = sprintf('/img/tools_stock/%s/%s', $uid, $fileName);
    }

    imagedestroy($canvas);
    imagedestroy($rawImage);

    if (!$result) {
        $result = collect_tool_qr_images($uid, $baseDir);
    }

    return $result;
}
function map_tool_to_template(array $tool): array
{
//    $qrPathsRaw = $tool['qr_path'] ?? '';
    $qrPathsRaw = $tool['qr_path'] ?? $tool['qr_patch'] ?? '';
    $qrCodes    = [];
    $toolUid    = $tool['uid'] ?? '';

    if ($qrPathsRaw !== '') {
        $decoded = json_decode((string)$qrPathsRaw, true);

        if (is_array($decoded)) {
            foreach ($decoded as $size => $path) {
                if (is_string($path)) {
                    $qrCodes[] = [
                        'size' => (string)$size,
                        'path' => $path,
                    ];
                }
            }
        } elseif (is_string($qrPathsRaw)) {
            $qrCodes[] = [
                'size' => 'default',
                'path' => (string)$qrPathsRaw,
            ];
        }
    }


    if (!$qrCodes && $toolUid !== '') {
        $qrCodes = collect_tool_qr_images($toolUid, __DIR__ . '/img/tools_stock/' . $toolUid);

        $qrCodes = array_map(
            static function ($path, $size): array {
                return [
                    'size' => (string)$size,
                    'path' => $path,
                ];
            },
            $qrCodes,
            array_keys($qrCodes)
        );
    }

    $formatDate = static function ($value): string {
        if (!$value) {
            return '';
        }

        try {
            $dt = new DateTime($value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    };


    $photos = parse_tool_photos($tool['img_path'] ?? $tool['img_patch'] ?? null);
    return [
        'id'              => $tool['id'] ?? '',
        'NameTool'        => $tool['name'] ?? '',
        'SerialNumber'    => $tool['serial_number'] ?? '',
        'WarantyDays'     => $tool['warranty_days'] ?? 0,
        'PriceBuy'        => $tool['price_buy'] ?? '',
        'DateBuy'         => $formatDate($tool['purchase_date'] ?? ''),
        'AddInSystem'     => $formatDate($tool['registered_at'] ?? ''),
        'ResourceDays'    => $tool['resource_days'] ?? 0,
        'ResourceEndDate' => $formatDate($tool['operational_until'] ?? ''),
        'status'          => $tool['status'] ?? 'active',
        'notes'           => $tool['notes'] ?? '',
        'qr_codes'        => $qrCodes,
        'img_path'        => $photos,
    ];
}




// текущий пользователь
$user = auth_current_user();

try {
switch ($action) {

    case 'get_user_info':
        // простой пример — отдать краткую инфу по пользователю
        $response = [
            'status' => 'ok',
            'data'   => [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'ui_lang'   => $user['ui_lang'] ?? null,
            ],
        ];
        break;

    case 'set_lang':
        // смена языка интерфейса пользователем
        $newLang = $_POST['lang'] ?? '';
        $allowed = ['uk','ru','en','de'];

        if (!in_array($newLang, $allowed, true)) {
            $response = [
                'status'  => 'error',
                'message' => 'Недопустимый язык',
            ];
            break;
        }

        // обновляем в сессии
        $_SESSION['lang']            = $newLang;
        $_SESSION['user']['ui_lang'] = $newLang;

        // обновляем в БД
        global $dbcnx;
        $stmt = $dbcnx->prepare("UPDATE users SET ui_lang = ? WHERE id = ?");
        $stmt->bind_param("si", $newLang, $user['id']);
        $stmt->execute();
        $stmt->close();

        // можно пересчитать локаль в текущем запросе, если нужно
        $response = [
            'status'  => 'ok',
            'message' => 'Язык обновлён',
        ];
        break;

    case 'get_user_panel_html':
        // пример, когда ты хочешь вернуть целый кусок HTML (div)
        // подгружаем Smarty-шаблон и возвращаем как строку
        ob_start();
        $smarty->assign('current_user', $user);
        $smarty->display('cells_NA_API_users_panel.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    // === 1) Показать список пользователей (центральный main) ===
    case 'view_users':
/*        $users = [];

        // тянем пользователей из БД
        $sql = "SELECT id,
                       username,
                       full_name,
                       email,
                       is_active,
                       created_at,
                       last_login_at,
                       login_count
                  FROM users
              ORDER BY id";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $users[] = $row;
            }
            $res->free();
        }
*/
        $users = fetch_users_list($dbcnx);
        // отдаём в шаблон
        $smarty->assign('users', $users);
//        $smarty->assign('current_user', $currentUser);
        $smarty->assign('current_user', $user);

        // рендерим ТОЛЬКО внутренность main в отдельный шаблон
        ob_start();
        $smarty->display('cells_NA_API_users.html'); // этот шаблон сделаешь сам
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    // === 1) Показать список пользователей (центральный main) ===
case 'form_new_user':
    $currentUser = $user;

    $roles = [];
    $sql = "SELECT code, name FROM roles WHERE is_active = 1 ORDER BY id";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $roles[] = $row;
        }
        $res->free();
    }

    // Пустой пользователь для шаблона
    $editUser = [
        'id'        => '',
        'full_name' => '',
        'email'     => '',
        'role'      => '',   // нет роли пока
        'settings'  => [],
    ];

    $smarty->assign('roles', $roles);
    $smarty->assign('edit_user', $editUser);
    $smarty->assign('current_user', $currentUser);

    ob_start();
    $smarty->display('cells_NA_API_profile.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

case 'save_user':
    $current = $user; // кто сохраняет

    $userId = (int)($_POST['user_id'] ?? 0);

    // Флаг удаления
    $deleteFlag = !empty($_POST['delete']);

    // Если пришёл delete для существующего пользователя — обрабатываем сразу
    if ($userId > 0 && $deleteFlag) {

        // Не даём удалить самого себя
        if (($current['id'] ?? null) === $userId) {
            $response = [
                'status'  => 'error',
                'message' => 'Нельзя удалить самого себя',
            ];
            break;
        }

        // Удаляем пользователя
        $stmt = $dbcnx->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }

        // (Если оставишь user_roles, можно также подчистить:
        // $stmt = $dbcnx->prepare("DELETE FROM user_roles WHERE user_id = ?");
        // ... )

        audit_log(
            $current['id'] ?? null,
            'USER_DELETE',
            'USER',
            $userId,
            'Пользователь удалён из профиля',
            []
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Пользователь удалён',
            'deleted' => true,
        ];
        break;
    }

    // Основные поля
    $fullName = trim($_POST['fullName'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $company  = trim($_POST['company'] ?? '');
    $job      = trim($_POST['job'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $about    = trim($_POST['about'] ?? '');

    // Роль: теперь строка (код роли)
    $roleCode = trim($_POST['role'] ?? '');

    // Новый пароль
    $newPasswordPlain = trim($_POST['newpassword'] ?? '');

    // Уведомления / галочки
    $notifyChanges  = !empty($_POST['changesMade']);
    $notifyProducts = !empty($_POST['newProducts']);
    $notifyOffers   = !empty($_POST['proOffers']);
    $notifySecurity = !empty($_POST['securityNotify']);

    $extra = [
        'about'    => $about,
        'company'  => $company,
        'job'      => $job,
        'country'  => $country,
        'address'  => $address,
        'phone'    => $phone,
        'notifications' => [
            'changes_made'   => $notifyChanges,
            'new_products'   => $notifyProducts,
            'promo_offers'   => $notifyOffers,
            'security_alert' => $notifySecurity,
        ],
    ];
    $uiSettingsJson = json_encode($extra, JSON_UNESCAPED_UNICODE);

    if ($fullName === '' || $email === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Имя и Email обязательны',
        ];
        break;
    }

    $generatedPassword = null;

    if ($userId > 0) {
        // --- UPDATE ---
        $sql = "UPDATE users
                   SET full_name   = ?,
                       email       = ?,
                       ui_settings = ?,
                       role        = ?
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (users update)');
        }
        $stmt->bind_param("ssssi", $fullName, $email, $uiSettingsJson, $roleCode, $userId);
        $stmt->execute();
        $stmt->close();

        if ($newPasswordPlain !== '') {
            $newHash = password_hash($newPasswordPlain, PASSWORD_BCRYPT);
            $sqlP = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmtP = $dbcnx->prepare($sqlP);
            if (!$stmtP) {
                throw new RuntimeException('DB prepare error (users pwd update)');
            }
            $stmtP->bind_param("si", $newHash, $userId);
            $stmtP->execute();
            $stmtP->close();
        }

        audit_log(
            $current['id'] ?? null,
            'USER_UPDATE',
            'USER',
            $userId,
            'Обновление данных пользователя из профиля',
            [
                'full_name'   => $fullName,
                'email'       => $email,
                'extra'       => $extra,
                'pwd_changed' => ($newPasswordPlain !== ''),
                'role'        => $roleCode,
            ]
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Пользователь обновлён',
            'user_id' => $userId,
        ];

    } else {
        // --- INSERT нового пользователя ---
        $username = $email;        // логин = email, при желании потом изменишь

        // Пароль
        if ($newPasswordPlain === '') {
            $generatedPassword = bin2hex(random_bytes(4)); // 8 символов hex
            $newPasswordPlain  = $generatedPassword;
        }
        $passHash = password_hash($newPasswordPlain, PASSWORD_BCRYPT);

        // QR-токен для логина
        $qrLoginToken = bin2hex(random_bytes(16)); // 32 символа hex

        $uid = (int)(microtime(true) * 1000000);

        $sql = "INSERT INTO users (
                    uid_created,
                    username,
                    password_hash,
                    full_name,
                    email,
                    ui_settings,
                    is_active,
                    created_at,
                    login_count,
                    qr_login_token,
                    qr_login_enabled
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP(6), 0, ?, 1
                )";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (users insert)');
        }
        $stmt->bind_param(
            "issssss",
            $uid,
            $username,
            $passHash,
            $fullName,
            $email,
            $uiSettingsJson,
            $qrLoginToken
        );
        $stmt->execute();
        $newUserId = $stmt->insert_id;
        $stmt->close();

        audit_log(
            $current['id'] ?? null,
            'USER_CREATE',
            'USER',
            $newUserId,
            'Создан новый пользователь из профиля',
            [
                'full_name'      => $fullName,
                'email'          => $email,
                'extra'          => $extra,
                'pwd_generated'  => ($generatedPassword !== null),
                'role'           => $roleCode,
            ]
        );

        $resp = [
            'status'  => 'ok',
            'message' => 'Пользователь создан',
            'user_id' => $newUserId,
        ];
        if ($generatedPassword !== null) {
            $resp['temp_password'] = $generatedPassword;
        }

        $response = $resp;
    }

    break;

case 'form_edit_user':
    $editId = (int)($_POST['user_id'] ?? 0);
    if ($editId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Не передан user_id',
        ];
        break;
    }

    $sql = "SELECT id,
                   username,
                   full_name,
                   email,
                   ui_settings,
                   role,
                   qr_login_token,
                   qr_login_enabled
              FROM users
             WHERE id = ?
             LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $resU = $stmt->get_result();
    $editUser = $resU->fetch_assoc();
    $stmt->close();

    if (!$editUser) {
        $response = [
            'status'  => 'error',
            'message' => 'Пользователь не найден',
        ];
        break;
    }

    $settingsArr = [];
    if (!empty($editUser['ui_settings'])) {
        $tmp = json_decode($editUser['ui_settings'], true);
        if (is_array($tmp)) {
            $settingsArr = $tmp;
        }
    }
    $qrImageUrl = null;
    if (!empty($editUser['qr_login_token'])) {
        $qrImageUrl = sprintf(
            '/img/users/qr/%d_qr%s.png',
            $editUser['id'],
            $editUser['qr_login_token']
        );
    }
    $editUser['qr_image_url'] = $qrImageUrl;
    $editUser['settings'] = $settingsArr;

    // справочник ролей
    $roles = [];
    $sqlR = "SELECT code, name
             FROM roles
             WHERE is_active = 1
             ORDER BY id";
    if ($resR = $dbcnx->query($sqlR)) {
        while ($row = $resR->fetch_assoc()) {
            $roles[] = $row;
        }
        $resR->free();
    }

    $smarty->assign('edit_user',    $editUser);
    $smarty->assign('roles',        $roles);
    $smarty->assign('current_user', $user);

    ob_start();
    $smarty->display('cells_NA_API_profile.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

    // === Ресурсы -> Инструменты ===
    case 'view_tools_stock':
    case 'tools_stock':
        $tools = fetch_tools_list($dbcnx);

        $smarty->assign('tools', $tools);
        $smarty->assign('current_tool', $tool);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_new_tool_stock':
            $emptyTool = [
            'id'              => '',
            'NameTool'        => '',
            'SerialNumber'    => '',
            'WarantyDays'     => 0,
            'PriceBuy'        => '',
            'DateBuy'         => '',
            'AddInSystem'     => date('Y-m-d'),
            'ResourceDays'    => 0,
            'ResourceEndDate' => '',
            'status'          => 'active',
            'notes'           => '',
            'img_path'       => [],
            'qr_codes'        => [],
        ];

        $smarty->assign('edit_tool', $emptyTool);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock_form.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_edit_tool_stock':
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'tool_id required',
            ];
            break;
        }

        $stmt = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ?');
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        $toolRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$toolRow) {
            $response = [
                'status'  => 'error',
                'message' => 'Инструмент не найден',
            ];
            break;
        }

        $smarty->assign('edit_tool', map_tool_to_template($toolRow));
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock_form.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

case 'save_tool':
    $toolId        = (int)($_POST['tool_id'] ?? 0);
    $name          = trim($_POST['NameTool'] ?? '');
    $serialNumber  = trim($_POST['SerialNumber'] ?? '');
    $warrantyDays  = (int)($_POST['WarantyDays'] ?? 0);
    $priceBuyRaw   = $_POST['PriceBuy'] ?? '';
    $purchaseDate  = trim($_POST['DateBuy'] ?? '');
    $registeredAt  = trim($_POST['AddInSystem'] ?? '') ?: date('Y-m-d');
    $resourceDays  = (int)($_POST['ResourceDays'] ?? 0);
//    $resourceEnd   = trim($_POST['ResourceEndDate'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');
    $status        = !empty($_POST['status']) ? 'active' : 'inactive';
    $deleteFlag    = !empty($_POST['delete']);

    // Удаление инструмента
    if ($toolId > 0 && $deleteFlag) {

        $stmtOld = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ? LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $toolId);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            $oldTool = $resOld ? $resOld->fetch_assoc() : null;
            $stmtOld->close();
        } else {
            $oldTool = null;
        }

        $stmtDel = $dbcnx->prepare('DELETE FROM tool_resources WHERE id = ?');
        if ($stmtDel) {
            $stmtDel->bind_param('i', $toolId);
            $stmtDel->execute();
            $stmtDel->close();
        }
        $toolUid = $oldTool['uid'] ?? '';
        if ($toolUid !== '') {
            $toolDir = __DIR__ . '/img/tools_stock/' . $toolUid;
            remove_directory_recursive($toolDir);
        }

        audit_log(
            $user['id'] ?? null,
            'TOOL_DELETE',
            'TOOL',
            $toolId,
            'Инструмент удалён из профиля',
            $oldTool ?: []
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент удалён',
            'deleted' => true,
        ];
        break;
    }

    

    if ($name === '' || $serialNumber === '' || $purchaseDate === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Название, серийный номер и дата покупки обязательны',
        ];
        break;
    }

    $purchaseDateObj = DateTime::createFromFormat('Y-m-d', $purchaseDate);
    if (!$purchaseDateObj) {
        $purchaseDateObj = DateTime::createFromFormat('Y-m-d H:i:s', $purchaseDate);
    }

    if (!$purchaseDateObj) {
        try {
            $purchaseDateObj = new DateTime($purchaseDate);
        } catch (Exception $e) {
            $purchaseDateObj = null;
        }
    }

    if (!$purchaseDateObj) {
        $response = [
            'status'  => 'error',
            'message' => 'Неверный формат даты покупки',
        ];
        break;
    }

    $purchaseDate = $purchaseDateObj->format('Y-m-d');

    try {
        $registeredAt = (new DateTime($registeredAt))->format('Y-m-d');
    } catch (Exception $e) {
        $registeredAt = date('Y-m-d');
        }
   $priceBuy = ($priceBuyRaw === '' ? null : (float)$priceBuyRaw);

   $resourceEnd = null;
 
   if ($resourceDays > 0) {
    $resourceDays = max(0, (int)($_POST['ResourceDays'] ?? 0));

    $resourceEndObj = clone $purchaseDateObj;
        if ($resourceDays > 0) {
          $resourceEndObj->modify('+' . $resourceDays . ' days');
        }
    }
   $resourceEnd = $resourceEndObj->format('Y-m-d');
//print_r($resourceEnd);
    if ($toolId > 0) {
            $oldTool = null;
        $stmtOld = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ? LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $toolId);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            $oldTool = $resOld ? $resOld->fetch_assoc() : null;
            $stmtOld->close();
        }


        $deletePhotosRaw = $_POST['delete_photos'] ?? [];
        $deletePhotos    = [];

        if (is_array($deletePhotosRaw)) {
            foreach ($deletePhotosRaw as $photoPath) {
                $photoPath = trim((string)$photoPath);
                if ($photoPath !== '') {
                    $deletePhotos[] = $photoPath;
                }
            }
        }

        $photos = parse_tool_photos($oldTool['img_path'] ?? []);

        if ($deletePhotos) {
            $photos = array_values(array_filter($photos, static function ($path) use ($deletePhotos) {
                return !in_array($path, $deletePhotos, true);
            }));

            foreach ($deletePhotos as $photoPath) {
                if (!str_starts_with($photoPath, '/img/tools_stock/')) {
                    continue;
                }

                $absolutePath = __DIR__ . $photoPath;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        $photosJson = json_encode($photos, JSON_UNESCAPED_SLASHES);

$toolUid = (string)($oldTool['uid'] ?? '');
$qrPath  = null;

if ($toolUid !== '') {
    $qrImages = collect_tool_qr_images($toolUid, __DIR__ . '/img/tools_stock/' . $toolUid);
    if (!$qrImages) {
        $qrImages = generate_tool_qr_images($toolUid, get_subdomain_label());
    }
    $qrPath = json_encode($qrImages, JSON_UNESCAPED_SLASHES);
    //$qrPath = build_tool_qr_path_json($uid);
}
//$qrPath = build_tool_qr_path_json($uid);

        $newValues = [
            'name'              => $name,
            'serial_number'     => $serialNumber,
            'warranty_days'     => $warrantyDays,
            'price_buy'         => $priceBuy,
            'purchase_date'     => $purchaseDate,
            'registered_at'     => $registeredAt,
            'resource_days'     => $resourceDays,
            'operational_until' => $resourceEnd,
            'qr_path'           => $qrPath,
            'img_path'          => $photosJson,
            'notes'             => $notes,
            'status'            => $status,
        ];

        $sql = "UPDATE tool_resources
                   SET name = ?,
                       serial_number = ?,
                       warranty_days = ?,
                       price_buy = ?,
                       purchase_date = ?,
                       registered_at = ?,
                       resource_days = ?,
                       operational_until = ?,
                       qr_path = ?,
                       img_path = ?,
                       notes = ?,
                       status = ?,
                       updated_at = CURRENT_TIMESTAMP()
                 WHERE id = ?";

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (tool_resources update)');
        }

        $stmt->bind_param(
            'ssidssisssssi',
            $name,
            $serialNumber,
            $warrantyDays,
            $priceBuy,
            $purchaseDate,
            $registeredAt,
            $resourceDays,
            $resourceEnd,
            $qrPath,
            $photosJson,
            $notes,
            $status,
            $toolId
        );
        $stmt->execute();
        $stmt->close();

        $changes = [];
        if ($oldTool) {
            foreach ($newValues as $field => $value) {
                $oldValue = $oldTool[$field] ?? null;
                if ($oldValue != $value) {
                    $changes[$field] = ['old' => $oldValue, 'new' => $value];
                }
            }
        }

        audit_log(
            $user['id'] ?? null,
            'TOOL_UPDATE',
            'TOOL',
            $toolId,
            'Обновление инструмента',
            $changes ?: $newValues
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент обновлён',
            'tool_id' => $toolId,
        ];

    } else {
        $uid         = generate_uuid();
        $domainLabel = get_subdomain_label();
        $qrImages    = generate_tool_qr_images($uid, $domainLabel);
        if (!$qrImages) {
            $qrImages = collect_tool_qr_images($uid, __DIR__ . '/img/tools_stock/' . $uid);
        }
        $qrPath = json_encode($qrImages, JSON_UNESCAPED_SLASHES);
        //$qrPath = build_tool_qr_path_json($uid);

        $imgPatch = json_encode([], JSON_UNESCAPED_SLASHES);
        $location = 'warehouse';

        $newValues = [
            'name'              => $name,
            'serial_number'     => $serialNumber,
            'warranty_days'     => $warrantyDays,
            'price_buy'         => $priceBuy,
            'purchase_date'     => $purchaseDate,
            'registered_at'     => $registeredAt,
            'resource_days'     => $resourceDays,
            'operational_until' => $resourceEnd,
            'notes'             => $notes,
            'status'            => $status,
        ];


        $sql = "INSERT INTO tool_resources (
                    uid,
                    name,
                    serial_number,
                    price_buy,
                    warranty_days,
                    purchase_date,
                    registered_at,
                    location,
                    passport_service_months,
                    resource_days,
                    actual_usage_days,
                    operational_until,
                    status,
                    qr_path,
                    img_path,
                    notes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?, ?
                )";

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (tool_resources insert)');
        }

        $stmt->bind_param(
            'sssdisssisssss',
            $uid,
            $name,
            $serialNumber,
            $priceBuy,
            $warrantyDays,
            $purchaseDate,
            $registeredAt,
            $location,
            $resourceDays,
            $resourceEnd,
            $status,
            $qrPath,
            $imgPatch,
            $notes
        );
        $stmt->execute();
        $newToolId = $stmt->insert_id;
        $stmt->close();


        audit_log(
            $user['id'] ?? null,
            'TOOL_CREATE',
            'TOOL',
            $newToolId,
            'Создание инструмента',
            $newValues
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент создан',
            'tool_id' => $newToolId,
        ];
    }

    break;


    case 'upload_tool_photo':
        $toolId = (int)($_POST['tool_id'] ?? 0);

        if ($toolId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'tool_id required',
            ];
            break;
        }

        if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $response = [
                'status'  => 'error',
                'message' => 'Файл изображения не получен',
            ];
            break;
        }

        $stmtTool = $dbcnx->prepare('SELECT id, uid, img_path FROM tool_resources WHERE id = ? LIMIT 1');
        if (!$stmtTool) {
            throw new RuntimeException('DB prepare error (upload_tool_photo select)');
        }

        $stmtTool->bind_param('i', $toolId);
        $stmtTool->execute();
        $resTool = $stmtTool->get_result();
        $toolRow = $resTool ? $resTool->fetch_assoc() : null;
        $stmtTool->close();

        if (!$toolRow || empty($toolRow['uid'])) {
            $response = [
                'status'  => 'error',
                'message' => 'Инструмент не найден',
            ];
            break;
        }

        $tmpName = $_FILES['photo']['tmp_name'];
        $fileInfo = @getimagesize($tmpName);
        if (!$fileInfo || ($fileInfo[2] ?? 0) === IMAGETYPE_UNKNOWN) {
            $response = [
                'status'  => 'error',
                'message' => 'Файл не является изображением',
            ];
            break;
        }

        $imgData = file_get_contents($tmpName);
        $srcImage = @imagecreatefromstring($imgData);

        if (!$srcImage) {
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось прочитать изображение',
            ];
            break;
        }

        $srcWidth  = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        $maxSide   = 1600;

        $scale = ($srcWidth > $maxSide || $srcHeight > $maxSide)
            ? min($maxSide / max($srcWidth, 1), $maxSide / max($srcHeight, 1))
            : 1;

        $dstWidth  = (int)max(1, round($srcWidth * $scale));
        $dstHeight = (int)max(1, round($srcHeight * $scale));

        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        $toolUid    = $toolRow['uid'];
        $photoDir   = __DIR__ . '/img/tools_stock/' . $toolUid . '/photo';
        $photoName  = 'photo_' . date('Ymd_His') . '.jpg';
        $photoPath  = $photoDir . '/' . $photoName;
        $publicPath = sprintf('/img/tools_stock/%s/photo/%s', $toolUid, $photoName);

        if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось создать каталог для фото',
            ];
            imagedestroy($dstImage);
            imagedestroy($srcImage);
            break;
        }

        imagejpeg($dstImage, $photoPath, 90);

        imagedestroy($dstImage);
        imagedestroy($srcImage);

        $photos     = parse_tool_photos($toolRow['img_path'] ?? null);
        $photos[]   = $publicPath;
        $photos     = array_values(array_unique($photos));
        $photosJson = json_encode($photos, JSON_UNESCAPED_SLASHES);

        $stmtUpdate = $dbcnx->prepare('UPDATE tool_resources SET img_path = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('DB prepare error (upload_tool_photo update)');
        }

        $stmtUpdate->bind_param('si', $photosJson, $toolId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Фото добавлено',
            'photos'  => $photos,
        ];

        break;
    case 'view_devices':
        $devices = [];

        $sql = "SELECT id, device_uid, name, serial, model, app_version,
                       is_active, last_seen_at, last_ip,
                       created_at, activated_at
                  FROM devices
              ORDER BY created_at DESC";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $devices[] = $row;
            }
            $res->free();
        }

        $smarty->assign('devices', $devices);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_devices.html'); // сделаешь по аналогии с users
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'activate_device':
        // только админ, например
        auth_require_role('ADMIN');

        $deviceId  = (int)($_POST['device_id'] ?? 0);
        $isActive  = (int)($_POST['is_active'] ?? 0) ? 1 : 0;

        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        $sql = "UPDATE devices
                   SET is_active = ?,
                       activated_at = CASE
                           WHEN ? = 1 AND activated_at IS NULL
                           THEN CURRENT_TIMESTAMP(6)
                           ELSE activated_at
                       END
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("iii", $isActive, $isActive, $deviceId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            $isActive ? 'DEVICE_ACTIVATE' : 'DEVICE_DEACTIVATE',
            'DEVICE',
            $deviceId,
            $isActive ? 'Активация устройства' : 'Деактивация устройства'
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Статус устройства обновлён',
        ];
        break;


    case 'form_edit_device':
        // Можно ограничить ролью, если нужно
        // auth_require_role('ADMIN');

        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        $sql = "SELECT
                    id,
                    device_uid,
                    name,
                    serial,
                    model,
                    app_version,
                    device_token,
                    is_active,
                    last_seen_at,
                    last_ip,
                    created_at,
                    updated_at,
                    activated_at,
                    notes
                FROM devices
               WHERE id = ?
               LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare form_edit_device)',
            ];
            break;
        }
        $stmt->bind_param("i", $deviceId);
        $stmt->execute();
        $resD   = $stmt->get_result();
        $device = $resD->fetch_assoc();
        $stmt->close();

        if (!$device) {
            $response = [
                'status'  => 'error',
                'message' => 'Устройство не найдено',
            ];
            break;
        }

        // --- ДОБАВЛЯЕМ: последние 20 записей из audit_logs по этому устройству ---
        $logs = [];

        $sqlL = "SELECT
                    event_time,
                    user_id,
                    event_type,
                    entity_id,
                    ip_address,
                    user_agent,
                    description
                 FROM audit_logs
                 WHERE entity_type = 'DEVICE'
                   AND entity_id   = ?
                 ORDER BY event_time DESC
                 LIMIT 20";

        if ($stmtL = $dbcnx->prepare($sqlL)) {
            $stmtL->bind_param("i", $deviceId);
            $stmtL->execute();
            $resL = $stmtL->get_result();
            while ($row = $resL->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmtL->close();
        }

        // кладём как подмассив
        $device['logs'] = $logs;

        $smarty->assign('device',       $device);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_devices_profile.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

 case 'save_device':
        auth_require_role('ADMIN');

        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        // Если не нужны редактируемые name/notes — можно вообще не трогать их.
        // Я оставляю поддержку, раз они уже были.
        $name  = trim($_POST['name']  ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Чекбокс статуса: если есть и не пустой — считаем активным
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE devices
                   SET notes      = ?,
                       is_active  = ?,
                       activated_at = CASE
                           WHEN ? = 1 AND activated_at IS NULL
                           THEN CURRENT_TIMESTAMP(6)
                           ELSE activated_at
                       END
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare save_device)',
            ];
            break;
        }

        $stmt->bind_param("siii", $notes, $isActive, $isActive, $deviceId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            'DEVICE_UPDATE',
            'DEVICE',
            $deviceId,
            'Изменение данных устройства',
            [
                'notes'     => $notes,
                'is_active' => $isActive,
            ]
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Устройство обновлено',
        ];
        break;

    // === Настройки ячеек склада ===
    case 'setting_cells':
        // если нужно ограничить только админами – раскомментируешь
        // if (!auth_has_role('ADMIN')) {
        //     $response = [
        //         'status'  => 'error',
        //         'message' => 'Недостаточно прав для просмотра ячеек',
        //     ];
        //     break;
        // }

        // если нужно что-то подтянуть из БД — потом сюда добавим SELECT
        // пример заготовки:
         $cells = [];
         if ($res = $dbcnx->query("SELECT id, code, qr_payload, description FROM cells ORDER BY code")) {
             while ($row = $res->fetch_assoc()) {
                 $cells[] = $row;
             }
             $res->free();
         }
        $smarty->assign('cells', $cells);

        // рендерим твой готовый шаблон с ячейками
        $smarty->assign('current_user', $user);
        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html'); // ПОДСТАВЬ имя своего шаблона
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

case 'add_new_cells':
        // auth_require_role('ADMIN'); // при желании

        $first = trim($_POST['first_code'] ?? '');
        $last  = trim($_POST['last_code'] ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if ($first === '' || $last === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Укажи первую и последнюю ячейку, например A10 и A99',
            ];
            break;
        }

        // Нормализуем в верхний регистр
        $first = strtoupper($first);
        $last  = strtoupper($last);

        // Парсим вида A10, B25 и т.п.
        $re = '/^([A-Z])(\d{1,4})$/u';

        if (!preg_match($re, $first, $m1) || !preg_match($re, $last, $m2)) {
            $response = [
                'status'  => 'error',
                'message' => 'Коды ячеек должны быть вида A10, A99 и т.п.',
            ];
            break;
        }

        $prefix1 = $m1[1];
        $n1      = (int)$m1[2];

        $prefix2 = $m2[1];
        $n2      = (int)$m2[2];

        if ($prefix1 !== $prefix2) {
            $response = [
                'status'  => 'error',
                'message' => 'Буквенная часть должна совпадать (например A10–A99, а не A10–B20)',
            ];
            break;
        }

        if ($n1 >= $n2) {
            $response = [
                'status'  => 'error',
                'message' => 'Первая ячейка должна быть меньше последней по номеру (например A10 и A99)',
            ];
            break;
        }

        $prefix = $prefix1;

        // Смотрим уже существующие коды в этом диапазоне, чтобы не делать дубли
        $fromCode = $prefix . $n1;
        $toCode   = $prefix . $n2;

        $stmt = $dbcnx->prepare(
            "SELECT code FROM cells WHERE code BETWEEN ? AND ?"
        );
        $stmt->bind_param("ss", $fromCode, $toCode);
        $stmt->execute();
        $res = $stmt->get_result();

        $existing = [];
        while ($row = $res->fetch_assoc()) {
            $existing[$row['code']] = true;
        }
        $stmt->close();

        // Папка для файлов
        $dir = __DIR__ . '/img/cells';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $created = 0;

        for ($i = $n1; $i <= $n2; $i++) {
            $code = $prefix . $i;        // A10, A11, ...

            if (isset($existing[$code])) {
                // Уже есть такая ячейка — пропускаем
                continue;
            }

            $qrPayload  = 'CELL:' . $code;
            $qrFileName = $code . '.png';                 // ИМЯ ФАЙЛА = КОД ЯЧЕЙКИ
            $fullPath   = $dir . '/' . $qrFileName;

            // Вставляем в БД: code, qr_payload, qr_file, description
            $stmtIns = $dbcnx->prepare(
                "INSERT INTO cells (code, qr_payload, qr_file, description)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtIns->bind_param("ssss", $code, $qrPayload, $qrFileName, $desc);
            $stmtIns->execute();
            $stmtIns->close();

            // Генерируем PNG через qrencode
            $cmd = sprintf(
                'qrencode -o %s -s 4 %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($qrPayload)
            );
            $out = [];
            $rc  = 0;
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                error_log('qrencode error for cell ' . $code . ': ' . implode("\n", $out));
            }

            $created++;
        }

        // Перечитываем список ячеек
        $cells = [];
        $sql = "SELECT id, code, qr_payload, qr_file, description
                  FROM cells
              ORDER BY code";
        if ($res2 = $dbcnx->query($sql)) {
            while ($row = $res2->fetch_assoc()) {
                $cells[] = $row;
            }
            $res2->free();
        }

        $smarty->assign('cells', $cells);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html');
        $html = ob_get_clean();

        $response = [
            'status'  => 'ok',
            'message' => 'Создано новых ячеек: ' . $created,
            'html'    => $html,
        ];
        break;

    case 'delete_cell':
        $cellId = (int)($_POST['cell_id'] ?? 0);
        if ($cellId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Некорректный ID ячейки',
            ];
            break;
        }

        $stmt = $dbcnx->prepare(
            "SELECT id, code, qr_payload, qr_file, description
               FROM cells
              WHERE id = ?
              LIMIT 1"
        );
        $stmt->bind_param("i", $cellId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cell = $res->fetch_assoc();
        $stmt->close();

        if (!$cell) {
            $response = [
                'status'  => 'error',
                'message' => 'Ячейка не найдена',
            ];
            break;
        }

        // Удаляем файл, если есть
        $dir = __DIR__ . '/img/cells';
        if (!empty($cell['qr_file'])) {
            $path = $dir . '/' . $cell['qr_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        } else {
            // запасной вариант по коду
            $fallback = $dir . '/' . $cell['code'] . '.png';
            if (is_file($fallback)) {
                @unlink($fallback);
            }
        }

        // Удаляем из БД
        $stmt = $dbcnx->prepare("DELETE FROM cells WHERE id = ?");
        $stmt->bind_param("i", $cellId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            'CELL_DELETE',
            'CELL',
            $cellId,
            'Удаление ячейки склада',
            [
                'code'        => $cell['code'],
                'qr_payload'  => $cell['qr_payload'],
                'qr_file'     => $cell['qr_file'],
                'description' => $cell['description'],
            ]
        );

        // Перечитываем список ячеек
        $cells = [];
        $sql = "SELECT id, code, qr_payload, qr_file, description
                  FROM cells
              ORDER BY code";
        if ($res2 = $dbcnx->query($sql)) {
            while ($row = $res2->fetch_assoc()) {
                $cells[] = $row;
            }
            $res2->free();
        }

        $smarty->assign('cells', $cells);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html');
        $html = ob_get_clean();

        $response = [
            'status'  => 'ok',
            'message' => 'Ячейка удалена',
            'html'    => $html,
        ];
        break;

    case 'users_regen_qr':
        // Только админам позволяем массовую регенерацию
        auth_require_role('ADMIN');

        $ok  = 0;
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
            'status'  => 'ok',
            'message' => "QR-коды обновлены. Успешно: {$ok}, ошибок: {$err}",
        ];
        break;

case 'warehouse_item_in':
case 'item_in':
    auth_require_login();
    $current = $user;
    $userId  = (int)$current['id'];

    $batches = [];

    if (auth_has_role('ADMIN')) {
        // Админ видит ВСЕ незавершённые партии + можем сразу знать, чей это приход
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count,
                wi.user_id,
                u.full_name        AS user_name
            FROM warehouse_item_in wi
            LEFT JOIN users u ON u.id = wi.user_id
            WHERE wi.committed = 0
            GROUP BY wi.batch_uid, wi.user_id
            ORDER BY started_at DESC
        ";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $batches[] = $row;
            }
            $res->free();
        }
    } else {
        // Обычный пользователь — только свои партии
        $sql = "
            SELECT
                wi.batch_uid,
            MIN(wi.created_at) AS started_at,
            COUNT(*)           AS parcel_count
            FROM warehouse_item_in wi
            WHERE wi.committed = 0
              AND wi.user_id   = ?
            GROUP BY wi.batch_uid
            ORDER BY started_at DESC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('batches',      $batches);
    $smarty->assign('current_user', $current);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_in.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

case 'open_item_in_batch':
    auth_require_login();
    $current = $user;
    $userId  = (int)$current['id'];

    $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
    if ($batchUid <= 0) {
        // новая партия
        $batchUid = (int)(microtime(true) * 1000000);
    }

    $items = [];

    if (auth_has_role('ADMIN')) {
        // Админ видит ВСЕ посылки в партии, кто бы их ни создавал
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $batchUid);
    } else {
        // Обычный юзер — только свои записи
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND user_id   = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("ii", $batchUid, $userId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $smarty->assign('batch_uid',    $batchUid);
    $smarty->assign('items',        $items);
    $smarty->assign('current_user', $current);

        $dest_country = [];
        $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
        $stmt = $dbcnx->prepare($sql);
        $stmt->execute();
        $res3 = $stmt->get_result();


        if ($res3 = $dbcnx->query($sql)) {
            while ($row = $res3->fetch_assoc()) {
                $dest_country[] = $row;
            }
            $res3->free();
        }
    $stmt->close();

    $smarty->assign('dest_country', $dest_country);

    require_once __DIR__ . '/ocr_templates.php';
    require_once __DIR__ . '/ocr_dicts.php';


    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_in_batch.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

case 'add_new_item_in':
    auth_require_login();
    $current = $user;

    // Кто сейчас залогинен — ОПЕРАТОР
    $operatorUserId = (int)$current['id'];

    // 1) партия
    $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
    if ($batchUid <= 0) {
        // новая партия — владелец = текущий пользователь
        $batchUid     = (int)(microtime(true) * 1000000); // uid_created
        $ownerUserId  = $operatorUserId;
    } else {
        // существующая партия — ищем её владельца
        $ownerUserId = $operatorUserId; // по умолчанию

        $stmtOwner = $dbcnx->prepare(
            "SELECT user_id
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0
              ORDER BY created_at ASC
              LIMIT 1"
        );
        if ($stmtOwner) {
            $stmtOwner->bind_param("i", $batchUid);
            $stmtOwner->execute();
            $resOwner = $stmtOwner->get_result();
            if ($rowOwner = $resOwner->fetch_assoc()) {
                $ownerUserId = (int)$rowOwner['user_id'];
            }
            $stmtOwner->close();
        }
    }

    // 2) поля из формы
    $tuid        = trim($_POST['tuid']        ?? '');
    $tracking    = trim($_POST['tracking_no'] ?? '');

    $carrierCode = trim($_POST['carrier_code'] ?? '');
    $carrierName = trim($_POST['carrier_name'] ?? '');

    $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
    // имя страны сейчас не приходит, можно оставить пустым
    $rcCountryName = '';

    $rcName        = trim($_POST['receiver_name']    ?? '');
    $rcCompany     = trim($_POST['receiver_company'] ?? '');
    $rcAddress     = trim($_POST['receiver_address'] ?? '');

    $sndName       = trim($_POST['sender_name']    ?? '');
    $sndCompany    = trim($_POST['sender_company'] ?? '');

    // вес и габариты: если пусто → 0
    $weightKg = $_POST['weight_kg'] ?? '';
    $sizeL    = $_POST['size_l_cm'] ?? '';
    $sizeW    = $_POST['size_w_cm'] ?? '';
    $sizeH    = $_POST['size_h_cm'] ?? '';

    $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
    $sizeL    = ($sizeL    === '' || $sizeL    === null) ? 0.0 : (float)$sizeL;
    $sizeW    = ($sizeW    === '' || $sizeW    === null) ? 0.0 : (float)$sizeW;
    $sizeH    = ($sizeH    === '' || $sizeH    === null) ? 0.0 : (float)$sizeH;

    // пока не используем
    $labelImage = null;
    $boxImage   = null;

    if ($tuid === '' || $tracking === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Нужны хотя бы TUID и трек-номер',
        ];
        break;
    }

    $uidCreated = (int)(microtime(true) * 1000000);
    $deviceId   = 0; // для веба 0, для мобилки можно класть реальный device_id

    $sql = "INSERT INTO warehouse_item_in (
                batch_uid, uid_created, user_id, device_id, committed,
                tuid, tracking_no, carrier_code, carrier_name,
                receiver_country_code, receiver_country_name,
                receiver_name, receiver_company, receiver_address,
                sender_name, sender_company,
                weight_kg, size_l_cm, size_w_cm, size_h_cm,
                label_image, box_image
            ) VALUES (
                ?, ?, ?, ?, 0,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?
            )";

    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        break;
    }

    // iiiisssssssssssddddss  = 21 параметр
    $stmt->bind_param(
        "iiiisssssssssssddddss",
        $batchUid,
        $uidCreated,
        $ownerUserId,    // владелец партии
        $deviceId,
        $tuid,
        $tracking,
        $carrierCode,
        $carrierName,
        $rcCountryCode,
        $rcCountryName,
        $rcName,
        $rcCompany,
        $rcAddress,
        $sndName,
        $sndCompany,
        $weightKg,
        $sizeL,
        $sizeW,
        $sizeH,
        $labelImage,
        $boxImage
    );
    $stmt->execute();
    $stmt->close();

    audit_log(
        $operatorUserId,                 // кто реально добавил
        'WAREHOUSE_IN_ADD_PARCEL',
        'WAREHOUSE_IN',
        $batchUid,                       // entity_id = batch_uid
        'Добавлена посылка в партию прихода',
        [
            'batch_uid'        => $batchUid,
            'owner_user_id'    => $ownerUserId,
            'operator_user_id' => $operatorUserId,
            'tuid'             => $tuid,
            'tracking_no'      => $tracking,
        ]
    );

    $response = [
        'status'    => 'ok',
        'message'   => 'Посылка добавлена',
        'batch_uid' => $batchUid,
    ];
    break;


case 'commit_item_in_batch':
    auth_require_login();
    $current  = $user;
    $userId   = (int)$current['id'];
    $batchUid = (int)($_POST['batch_uid'] ?? 0);

    if ($batchUid <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'batch_uid не задан',
        ];
        break;
    }

    $isAdmin = auth_has_role('ADMIN');

    // 1) сколько незакоммиченных
    $stmt = $dbcnx->prepare(
        "SELECT COUNT(*) AS cnt
           FROM warehouse_item_in
          WHERE batch_uid = ?
            AND committed = 0"
    );
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare count): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cnt = (int)($res['cnt'] ?? 0);

    if ($cnt === 0) {
        $response = [
            'status'  => 'ok',
            'message' => 'Партия уже была завершена или пустая',
        ];
        break;
    }

    // 2) копируем в stock
    $sqlCopy = "INSERT INTO warehouse_item_stock (
                    batch_uid, uid_created, user_id, device_id, created_at,
                    tuid, tracking_no, carrier_code, carrier_name,
                    receiver_country_code, receiver_country_name,
                    receiver_name, receiver_company, receiver_address,
                    sender_name, sender_company,
                    weight_kg, size_l_cm, size_w_cm, size_h_cm,
                    label_image, box_image
                )
                SELECT
                    batch_uid, uid_created, user_id, device_id, created_at,
                    tuid, tracking_no, carrier_code, carrier_name,
                    receiver_country_code, receiver_country_name,
                    receiver_name, receiver_company, receiver_address,
                    sender_name, sender_company,
                    weight_kg, size_l_cm, size_w_cm, size_h_cm,
                    label_image, box_image
                  FROM warehouse_item_in
                 WHERE batch_uid = ?
                   AND committed = 0";

    $stmt = $dbcnx->prepare($sqlCopy);
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare copy): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $stmt->close();

    // 3) помечаем как committed
    $stmt = $dbcnx->prepare(
        "UPDATE warehouse_item_in
            SET committed = 1
          WHERE batch_uid = ?
            AND committed = 0"
    );
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare update): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $stmt->close();

    // 4) аудит
    audit_log(
        $userId,
        'WAREHOUSE_IN_COMMIT',
        'WAREHOUSE_IN',
        $batchUid,
        'Партия прихода переведена на склад',
        [
            'batch_uid'    => $batchUid,
            'committed_by' => $userId,
            'is_admin'     => $isAdmin,
            'items_count'  => $cnt,
        ]
    );

    $response = [
        'status'  => 'ok',
        'message' => 'Партия прихода завершена и перенесена на склад',
    ];
    break;


    // === 2) Заготовка под "Устройства" ===
/*    case 'view_devices':
        // потом сюда добавишь SELECT по устройствам и свой шаблон
        ob_start();
        $smarty->display('NiceAdmin/devices_list.html'); // создашь позже
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
*/
    // сюда можешь добавить другие действия:
    // case 'set_lang': ... (то, что мы делали до этого)
    // case 'get_user_panel_html': ...
    // сюда добавишь остальные действия:
    // case 'save_user_settings':
    // case 'get_parcel_list':
    // case 'render_label_block':
    // и т.д.
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


/**
 * Генерирует/пересоздаёт QR-картинку для одного пользователя.
 *
 * - Удаляет все старые файлы вида {id}_qr*.png
 * - Если нет токена в базе — генерирует новый, сохраняет в users.qr_login_token
 * - Создаёт PNG через qrencode
 *
 * Возвращает имя файла (без пути) или null при ошибке.
 */
function ensure_user_qr_image(array $userRow): ?string {
    global $dbcnx;

    $userId = (int)$userRow['id'];
    $token  = trim((string)($userRow['qr_login_token'] ?? ''));

    // Каталог с картинками
    $qrDir = __DIR__ . '/img/users/qr';
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0775, true) && !is_dir($qrDir)) {
            error_log("QR: cannot create dir $qrDir");
            return null;
        }
    }

    // Чистим старые файлы для этого пользователя
    foreach (glob($qrDir . '/' . $userId . '_qr*.png') as $old) {
        @unlink($old);
    }

    // Если в БД нет токена — генерим
    if ($token === '') {
        $token = bin2hex(random_bytes(16)); // 32 hex-символа

        $stmt = $dbcnx->prepare("UPDATE users SET qr_login_token = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $token, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Имя файла строго по твоей схеме: {id}_qr{token}.png
    $fileName = $userId . '_qr' . $token . '.png';
    $filePath = $qrDir . '/' . $fileName;

    // Что кодируем в QR — тут можно хоть чистый токен.
    // Потом твой APP по скану отправит этот токен на сервер.
    $payload = $token;

    // qrencode должен быть в PATH. Можно проверить: `which qrencode`
    $cmd = sprintf(
        'qrencode -o %s -s 6 -l M %s 2>/dev/null',
        escapeshellarg($filePath),
        escapeshellarg($payload)
    );
    exec($cmd, $out, $ret);

    if ($ret !== 0 || !is_file($filePath)) {
        error_log("QR: qrencode failed for user $userId, rc=$ret");
        return null;
    }

    return $fileName;
}