<?php
declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty

auth_require_role('ADMIN');

$response = ['status' => 'error', 'message' => 'Unknown roles/permissions action'];

switch ($action) {
    case 'view_role_permissions':
        $roles = [];
        $sqlRoles = "SELECT code, name, description FROM roles WHERE is_active = 1 ORDER BY id";
        if ($res = $dbcnx->query($sqlRoles)) {
            while ($row = $res->fetch_assoc()) {
                $roles[] = $row;
            }
            $res->free();
        }

        $permissions = [];
        $sqlPermissions = "SELECT id, code, name, description FROM permissions ORDER BY code";
        if ($res = $dbcnx->query($sqlPermissions)) {
            while ($row = $res->fetch_assoc()) {
                $permissions[] = $row;
            }
            $res->free();
        }

        $rolePermissions = [];
        $sqlRolePerms = "SELECT role_code, permission_code FROM role_permissions";
        if ($res = $dbcnx->query($sqlRolePerms)) {
            while ($row = $res->fetch_assoc()) {
                $role = $row['role_code'];
                $perm = $row['permission_code'];
                if (!isset($rolePermissions[$role])) {
                    $rolePermissions[$role] = [];
                }
                $rolePermissions[$role][$perm] = true;
            }
            $res->free();
        }

        $smarty->assign('roles', $roles);
        $smarty->assign('permissions', $permissions);
        $smarty->assign('role_permissions', $rolePermissions);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_roles_permissions.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'save_permission':
        $permissionId = (int)($_POST['permission_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($code === '' || $name === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Код и название права обязательны',
            ];
            break;
        }

        if ($permissionId > 0) {
            $stmt = $dbcnx->prepare("SELECT code FROM permissions WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (permissions select)');
            }
            $stmt->bind_param('i', $permissionId);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Право не найдено',
                ];
                break;
            }

            $stmt = $dbcnx->prepare("SELECT id FROM permissions WHERE code = ? AND id != ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (permissions check)');
            }
            $stmt->bind_param('si', $code, $permissionId);
            $stmt->execute();
            $res = $stmt->get_result();
            $duplicate = $res->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Право с таким кодом уже существует',
                ];
                break;
            }

            $oldCode = $existing['code'];

            $dbcnx->begin_transaction();
            try {
                $stmt = $dbcnx->prepare("UPDATE permissions SET code = ?, name = ?, description = ? WHERE id = ?");
                if (!$stmt) {
                    throw new RuntimeException('DB prepare error (permissions update)');
                }
                $stmt->bind_param('sssi', $code, $name, $description, $permissionId);
                $stmt->execute();
                $stmt->close();

                if ($oldCode !== $code) {
                    $stmt = $dbcnx->prepare("UPDATE role_permissions SET permission_code = ? WHERE permission_code = ?");
                    if (!$stmt) {
                        throw new RuntimeException('DB prepare error (role_permissions update)');
                    }
                    $stmt->bind_param('ss', $code, $oldCode);
                    $stmt->execute();
                    $stmt->close();
                }

                $dbcnx->commit();
            } catch (Throwable $e) {
                $dbcnx->rollback();
                throw $e;
            }

            $response = [
                'status'  => 'ok',
                'message' => 'Право обновлено',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (permissions insert)');
        }
        $stmt->bind_param('sss', $code, $name, $description);

        if (!$stmt->execute()) {
            $stmt->close();
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось сохранить право',
            ];
            break;
        }

        $stmt->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Право добавлено',
        ];
        break;

    case 'delete_permission':
        $permissionCode = trim($_POST['permission_code'] ?? '');
        if ($permissionCode === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Не указан код права',
            ];
            break;
        }

        $dbcnx->begin_transaction();
        try {
            $stmt = $dbcnx->prepare("DELETE FROM role_permissions WHERE permission_code = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (role_permissions delete)');
            }
            $stmt->bind_param('s', $permissionCode);
            $stmt->execute();
            $stmt->close();

            $stmt = $dbcnx->prepare("DELETE FROM permissions WHERE code = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (permissions delete)');
            }
            $stmt->bind_param('s', $permissionCode);
            $stmt->execute();
            $stmt->close();

            $dbcnx->commit();
        } catch (Throwable $e) {
            $dbcnx->rollback();
            throw $e;
        }

        $response = [
            'status'  => 'ok',
            'message' => 'Право удалено',
        ];
        break;

    case 'toggle_role_permission':
        $roleCode = trim($_POST['role_code'] ?? '');
        $permissionCode = trim($_POST['permission_code'] ?? '');
        $isAllowed = (int)($_POST['is_allowed'] ?? 0) === 1;

        if ($roleCode === '' || $permissionCode === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Не указана роль или право',
            ];
            break;
        }

        if ($isAllowed) {
            $stmt = $dbcnx->prepare("INSERT IGNORE INTO role_permissions (role_code, permission_code) VALUES (?, ?)");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (role_permissions insert)');
            }
            $stmt->bind_param('ss', $roleCode, $permissionCode);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $dbcnx->prepare("DELETE FROM role_permissions WHERE role_code = ? AND permission_code = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (role_permissions delete)');
            }
            $stmt->bind_param('ss', $roleCode, $permissionCode);
            $stmt->execute();
            $stmt->close();
        }

        $response = [
            'status'  => 'ok',
            'message' => 'Права обновлены',
        ];
        break;
}