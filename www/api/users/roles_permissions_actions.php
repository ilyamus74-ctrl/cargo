<?php
declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty

auth_require_role('ADMIN');

$response = ['status' => 'error', 'message' => 'Unknown roles/permissions action'];

$getTableColumns = static function (mysqli $dbcnx, string $table): array {
    $columns = [];
    $tableSafe = str_replace('`', '``', $table);
    $res = $dbcnx->query("SHOW COLUMNS FROM `{$tableSafe}`");
    if (!$res) {
        return $columns;
    }

    while ($row = $res->fetch_assoc()) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '') {
            $columns[$field] = true;
        }
    }
    $res->free();

    return $columns;
};

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

        $menuGroups = [];
        try {
            $menuGroupColumns = $getTableColumns($dbcnx, 'menu_groups');
            $hasMenuGroupId = isset($menuGroupColumns['id']);
            $hasMenuGroupIcon = isset($menuGroupColumns['icon']);
            $hasMenuGroupSort = isset($menuGroupColumns['sort_order']);
            $hasMenuGroupActive = isset($menuGroupColumns['is_active']);

            $sqlMenuGroups = "SELECT " . ($hasMenuGroupId ? 'id' : 'NULL AS id') . ",
                                     code,
                                     title,
                                     " . ($hasMenuGroupIcon ? 'icon' : "'' AS icon") . ",
                                     " . ($hasMenuGroupSort ? 'sort_order' : '0 AS sort_order') . ",
                                     " . ($hasMenuGroupActive ? 'is_active' : '1 AS is_active') . "
                               FROM menu_groups
                           ORDER BY " . ($hasMenuGroupSort ? 'sort_order, ' : '') . "code";
            if ($res = $dbcnx->query($sqlMenuGroups)) {
                while ($row = $res->fetch_assoc()) {
                    $menuGroups[] = $row;
                }
                $res->free();
            }
        } catch (Throwable $e) {
            // На старых инсталляциях раздел меню может отсутствовать или иметь старую схему.
            // Не блокируем открытие экрана прав доступа из-за необязательных данных.
            $menuGroups = [];
        }

        $menuItems = [];
        try {
            $sqlMenuItems = "SELECT id, menu_key, group_code, title, icon, action, sort_order, is_active
                               FROM menu_items
                           ORDER BY group_code, sort_order, id";
            if ($res = $dbcnx->query($sqlMenuItems)) {
                while ($row = $res->fetch_assoc()) {
                    $menuItems[] = $row;
                }
                $res->free();
            }
        } catch (Throwable $e) {
            // Аналогично: если таблица menu_items ещё не развернута, основной экран должен открыться.
            $menuItems = [];
        }

        $smarty->assign('menu_groups', $menuGroups);
        $smarty->assign('menu_items', $menuItems);

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

                    $stmt = $dbcnx->prepare("UPDATE role_menu SET menu_key = ? WHERE menu_key = ?");
                    if (!$stmt) {
                        throw new RuntimeException('DB prepare error (role_menu update)');
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


            $stmt = $dbcnx->prepare("DELETE FROM role_menu WHERE menu_key = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (role_menu delete)');
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
        $dbcnx->begin_transaction();
        try {
            if ($isAllowed) {
                $stmt = $dbcnx->prepare("INSERT IGNORE INTO role_permissions (role_code, permission_code) VALUES (?, ?)");
                if (!$stmt) {
                    throw new RuntimeException('DB prepare error (role_permissions insert)');
                }
                $stmt->bind_param('ss', $roleCode, $permissionCode);
                $stmt->execute();
                $stmt->close();

                $stmt = $dbcnx->prepare("INSERT INTO role_menu (role_code, menu_key, is_allowed)
                                         VALUES (?, ?, 1)
                                         ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
                if (!$stmt) {
                    throw new RuntimeException('DB prepare error (role_menu insert)');
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

                $stmt = $dbcnx->prepare("DELETE FROM role_menu WHERE role_code = ? AND menu_key = ?");
                if (!$stmt) {
                    throw new RuntimeException('DB prepare error (role_menu delete)');
                }
                $stmt->bind_param('ss', $roleCode, $permissionCode);
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
            'message' => 'Права обновлены',
        ];
        break;


    case 'save_menu_item':
        $menuItemId = (int)($_POST['menu_item_id'] ?? 0);
        $menuKey = trim($_POST['menu_key'] ?? '');
        $groupCode = trim($_POST['group_code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $actionCode = trim($_POST['action_code'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($menuKey === '' || $groupCode === '' || $title === '' || $actionCode === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Ключ, группа, название и action обязательны',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT code FROM menu_groups WHERE code = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_groups select)');
        }
        $stmt->bind_param('s', $groupCode);
        $stmt->execute();
        $res = $stmt->get_result();
        $group = $res->fetch_assoc();
        $stmt->close();

        if (!$group) {
            $response = [
                'status'  => 'error',
                'message' => 'Группа меню не найдена',
            ];
            break;
        }

        if ($menuItemId > 0) {
            $stmt = $dbcnx->prepare("SELECT menu_key FROM menu_items WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (menu_items select)');
            }
            $stmt->bind_param('i', $menuItemId);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Пункт меню не найден',
                ];
                break;
            }

            $stmt = $dbcnx->prepare("SELECT id FROM menu_items WHERE menu_key = ? AND id != ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (menu_items duplicate)');
            }
            $stmt->bind_param('si', $menuKey, $menuItemId);
            $stmt->execute();
            $res = $stmt->get_result();
            $duplicate = $res->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Пункт меню с таким ключом уже существует',
                ];
                break;
            }

            $stmt = $dbcnx->prepare("UPDATE menu_items
                                        SET menu_key = ?, group_code = ?, title = ?, icon = ?, action = ?, sort_order = ?, is_active = ?
                                      WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (menu_items update)');
            }
            $stmt->bind_param('sssssiii', $menuKey, $groupCode, $title, $icon, $actionCode, $sortOrder, $isActive, $menuItemId);
            $stmt->execute();
            $stmt->close();

            $response = [
                'status'  => 'ok',
                'message' => 'Пункт меню обновлён',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT id FROM menu_items WHERE menu_key = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_items insert check)');
        }
        $stmt->bind_param('s', $menuKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $duplicate = $res->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            $response = [
                'status'  => 'error',
                'message' => 'Пункт меню с таким ключом уже существует',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("INSERT INTO menu_items (menu_key, group_code, title, icon, action, sort_order, is_active)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_items insert)');
        }
        $stmt->bind_param('sssssii', $menuKey, $groupCode, $title, $icon, $actionCode, $sortOrder, $isActive);
        if (!$stmt->execute()) {
            $stmt->close();
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось сохранить пункт меню',
            ];
            break;
        }
        $stmt->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Пункт меню добавлен',
        ];
        break;


    case 'save_menu_group':
        $menuGroupId = (int)($_POST['menu_group_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $title === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Код и название группы обязательны',
            ];
            break;
        }

        if ($menuGroupId > 0) {
            $stmt = $dbcnx->prepare("SELECT code FROM menu_groups WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (menu_groups select by id)');
            }
            $stmt->bind_param('i', $menuGroupId);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Группа меню не найдена',
                ];
                break;
            }

            $stmt = $dbcnx->prepare("SELECT id FROM menu_groups WHERE code = ? AND id != ?");
            if (!$stmt) {
                throw new RuntimeException('DB prepare error (menu_groups duplicate update)');
            }
            $stmt->bind_param('si', $code, $menuGroupId);
            $stmt->execute();
            $res = $stmt->get_result();
            $duplicate = $res->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Группа меню с таким кодом уже существует',
                ];
                break;
            }

            $oldCode = $existing['code'];

            $dbcnx->begin_transaction();
            try {
                $menuGroupColumns = $getTableColumns($dbcnx, 'menu_groups');
                $setParts = ['code = ?', 'title = ?'];
                $updateValues = [$code, $title];
                $updateTypes = 'ss';

                if (isset($menuGroupColumns['icon'])) {
                    $setParts[] = 'icon = ?';
                    $updateValues[] = $icon;
                    $updateTypes .= 's';
                }
                if (isset($menuGroupColumns['sort_order'])) {
                    $setParts[] = 'sort_order = ?';
                    $updateValues[] = $sortOrder;
                    $updateTypes .= 'i';
                }
                if (isset($menuGroupColumns['is_active'])) {
                    $setParts[] = 'is_active = ?';
                    $updateValues[] = $isActive;
                    $updateTypes .= 'i';
                }

                $updateTypes .= 'i';
                $updateValues[] = $menuGroupId;

                $setSql = implode(', ', $setParts);
                $stmt = $dbcnx->prepare("UPDATE menu_groups SET {$setSql} WHERE id = ?");
                if (!$stmt) {
                    throw new RuntimeException('DB prepare error (menu_groups update)');
                }
                $stmt->bind_param($updateTypes, ...$updateValues);
                $stmt->execute();
                $stmt->close();

                if ($oldCode !== $code) {
                    $stmt = $dbcnx->prepare("UPDATE menu_items SET group_code = ? WHERE group_code = ?");
                    if (!$stmt) {
                        throw new RuntimeException('DB prepare error (menu_items group_code update)');
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
                'message' => 'Группа меню обновлена',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT id FROM menu_groups WHERE code = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_groups duplicate insert)');
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $res = $stmt->get_result();
        $duplicate = $res->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            $response = [
                'status'  => 'error',
                'message' => 'Группа меню с таким кодом уже существует',
            ];
            break;
        }

        $menuGroupColumns = $getTableColumns($dbcnx, 'menu_groups');
        $insertColumns = ['code', 'title'];
        $insertValues = [$code, $title];
        $insertTypes = 'ss';

        if (isset($menuGroupColumns['icon'])) {
            $insertColumns[] = 'icon';
            $insertValues[] = $icon;
            $insertTypes .= 's';
        }
        if (isset($menuGroupColumns['sort_order'])) {
            $insertColumns[] = 'sort_order';
            $insertValues[] = $sortOrder;
            $insertTypes .= 'i';
        }
        if (isset($menuGroupColumns['is_active'])) {
            $insertColumns[] = 'is_active';
            $insertValues[] = $isActive;
            $insertTypes .= 'i';
        }

        $columnsSql = implode(', ', $insertColumns);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
        $stmt = $dbcnx->prepare("INSERT INTO menu_groups ({$columnsSql}) VALUES ({$placeholders})");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_groups insert)');
        }
        $stmt->bind_param($insertTypes, ...$insertValues);
        if (!$stmt->execute()) {
            $stmt->close();
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось сохранить группу меню',
            ];
            break;
        }
        $stmt->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Группа меню добавлена',
        ];
        break;

    case 'delete_menu_group':
        $menuGroupId = (int)($_POST['menu_group_id'] ?? 0);
        if ($menuGroupId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Не указана группа меню',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT code FROM menu_groups WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_groups code select)');
        }
        $stmt->bind_param('i', $menuGroupId);
        $stmt->execute();
        $res = $stmt->get_result();
        $group = $res->fetch_assoc();
        $stmt->close();

        if (!$group) {
            $response = [
                'status'  => 'error',
                'message' => 'Группа меню не найдена',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT COUNT(*) AS cnt FROM menu_items WHERE group_code = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_items count by group)');
        }
        $stmt->bind_param('s', $group['code']);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = (int)($res->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        if ($count > 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Нельзя удалить группу, пока в ней есть пункты меню',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("DELETE FROM menu_groups WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_groups delete)');
        }
        $stmt->bind_param('i', $menuGroupId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Группа меню удалена',
        ];
        break;

    case 'delete_menu_item':
        $menuItemId = (int)($_POST['menu_item_id'] ?? 0);
        if ($menuItemId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Не указан пункт меню',
            ];
            break;
        }

        $stmt = $dbcnx->prepare("DELETE FROM menu_items WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (menu_items delete)');
        }
        $stmt->bind_param('i', $menuItemId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Пункт меню удалён',
        ];
        break;
}
