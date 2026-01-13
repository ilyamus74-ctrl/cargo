<?php
declare(strict_types=1);

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
            'html'   => $html,
        ];
        break;

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

        $editUser = [
            'id'        => '',
            'full_name' => '',
            'email'     => '',
            'role'      => '',
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
        $current = $user;
        $userId = (int)($_POST['user_id'] ?? 0);
        $deleteFlag = !empty($_POST['delete']);

        if ($userId > 0 && $deleteFlag) {
            if (($current['id'] ?? null) === $userId) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Нельзя удалить самого себя',
                ];
                break;
            }

            $stmt = $dbcnx->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }

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

        $fullName = trim($_POST['fullName'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $company  = trim($_POST['company'] ?? '');
        $job      = trim($_POST['job'] ?? '');
        $country  = trim($_POST['country'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $about    = trim($_POST['about'] ?? '');

        $roleCode = trim($_POST['role'] ?? '');
        $newPasswordPlain = trim($_POST['newpassword'] ?? '');

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
            $username = $email;

            if ($newPasswordPlain === '') {
                $generatedPassword = bin2hex(random_bytes(4));
                $newPasswordPlain  = $generatedPassword;
            }
            $passHash = password_hash($newPasswordPlain, PASSWORD_BCRYPT);

            $qrLoginToken = bin2hex(random_bytes(16));

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

    default:
        http_response_code(400);
        $response = [
            'status'  => 'error',
            'message' => 'Unknown user action',
        ];
        break;
}