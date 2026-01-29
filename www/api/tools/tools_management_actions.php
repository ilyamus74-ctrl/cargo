<?php
declare(strict_types=1);

/**
 * Экран управления инструментами.
 * Actions: tools_management
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown tools management action'];

switch ($action) {
    case 'tools_management':
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_management.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
    case 'tools_management_search':
        auth_require_login();
        $search = trim((string)($_POST['search'] ?? ''));

        $tools = [];
        if ($search === '') {
            $sql = "
                SELECT tr.id,
                       tr.uid,
                       tr.name,
                       tr.serial_number,
                       tr.location,
                       tr.assigned_user_id,
                       tr.updated_at,
                       COALESCE(NULLIF(u.full_name, ''), u.username) AS user_name
                  FROM tool_resources tr
             LEFT JOIN users u ON u.id = tr.assigned_user_id
              ORDER BY tr.registered_at DESC
            ";
            if ($res = $dbcnx->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $tools[] = $row;
                }
                $res->free();
            }
        } else {
            $like = '%' . $search . '%';
            $sql = "
                SELECT tr.id,
                       tr.uid,
                       tr.name,
                       tr.serial_number,
                       tr.location,
                       tr.assigned_user_id,
                       tr.updated_at,
                       COALESCE(NULLIF(u.full_name, ''), u.username) AS user_name
                  FROM tool_resources tr
             LEFT JOIN users u ON u.id = tr.assigned_user_id
                 WHERE tr.uid LIKE ?
                    OR tr.name LIKE ?
                    OR tr.serial_number LIKE ?
              ORDER BY tr.registered_at DESC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param('sss', $like, $like, $like);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $tools[] = $row;
            }
            $stmt->close();
        }

        $smarty->assign('tools', $tools);
        $smarty->assign('show_empty', true);

        ob_start();
        $smarty->display('cells_NA_API_tools_management_rows.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
            'total'  => count($tools),
        ];
        break;
    case 'tools_management_open_modal':
        auth_require_login();
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'Не указан инструмент.',
            ];
            break;
        }

        $tool = null;
        $stmt = $dbcnx->prepare("
            SELECT id,
                   uid,
                   name,
                   serial_number,
                   location,
                   assigned_user_id
              FROM tool_resources
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $tool = $res->fetch_assoc();
        }
        $stmt->close();

        if (!$tool) {
            $response = [
                'status' => 'error',
                'message' => 'Инструмент не найден.',
            ];
            break;
        }

        $cells = [];
        $sql = "SELECT id, code FROM cells ORDER BY code ASC";
        if ($resCells = $dbcnx->query($sql)) {
            while ($row = $resCells->fetch_assoc()) {
                $cells[] = $row;
            }
            $resCells->free();
        }

        $users = fetch_users_list($dbcnx);

        $smarty->assign('tool', $tool);
        $smarty->assign('cells', $cells);
        $smarty->assign('users', $users);

        ob_start();
        $smarty->display('cells_NA_API_tools_management_modal.html');
        $html = ob_get_clean();


        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
    case 'tools_management_open_user_modal':
        auth_require_login();
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'Не указан инструмент.',
            ];
            break;
        }

        $tool = null;
        $stmt = $dbcnx->prepare("
            SELECT id,
                   uid,
                   name,
                   serial_number,
                   location,
                   assigned_user_id
              FROM tool_resources
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $tool = $res->fetch_assoc();
        }
        $stmt->close();

        if (!$tool) {
            $response = [
                'status' => 'error',
                'message' => 'Инструмент не найден.',
            ];
            break;
        }

        $users = fetch_users_list($dbcnx);

        $smarty->assign('tool', $tool);
        $smarty->assign('users', $users);

        ob_start();
        $smarty->display('cells_NA_API_tools_management_user_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
    case 'tools_management_open_cell_modal':
        auth_require_login();
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'Не указан инструмент.',
            ];
            break;
        }

        $tool = null;
        $stmt = $dbcnx->prepare("
            SELECT id,
                   uid,
                   name,
                   serial_number,
                   location,
                   assigned_user_id
              FROM tool_resources
             WHERE id = ?
             LIMIT 1
        ");
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $tool = $res->fetch_assoc();
        }
        $stmt->close();

        if (!$tool) {
            $response = [
                'status' => 'error',
                'message' => 'Инструмент не найден.',
            ];
            break;
        }

        $cells = [];
        $sql = "SELECT id, code FROM cells ORDER BY code ASC";
        if ($resCells = $dbcnx->query($sql)) {
            while ($row = $resCells->fetch_assoc()) {
                $cells[] = $row;
            }
            $resCells->free();
        }

        $smarty->assign('tool', $tool);
        $smarty->assign('cells', $cells);

        ob_start();
        $smarty->display('cells_NA_API_tools_management_cell_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
    case 'tools_management_save_move':
        auth_require_login();
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'Не указан инструмент.',
            ];
            break;
        }

        $cellId = trim((string)($_POST['cell_id'] ?? ''));
        $userIdRaw = trim((string)($_POST['user_id'] ?? ''));

        $tool = null;
        $stmt = $dbcnx->prepare("SELECT id, location FROM tool_resources WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $tool = $res->fetch_assoc();
        }
        $stmt->close();

        if (!$tool) {
            $response = [
                'status' => 'error',
                'message' => 'Инструмент не найден.',
            ];
            break;
        }

        $location = (string)($tool['location'] ?? '');
        if ($cellId !== '') {
            $cellIdInt = (int)$cellId;
            $stmt = $dbcnx->prepare("SELECT code FROM cells WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $cellIdInt);
            $stmt->execute();
            $res = $stmt->get_result();
            $cellRow = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($cellRow && isset($cellRow['code'])) {
                $location = (string)$cellRow['code'];
            }
        }

        $assignedUserId = null;
        if ($userIdRaw !== '') {
            $assignedUserId = (int)$userIdRaw;
        }
        $assignedAt = $assignedUserId ? date('Y-m-d H:i:s') : null;

        $stmt = $dbcnx->prepare("
            UPDATE tool_resources
               SET location = ?,
                   assigned_user_id = ?,
                   assigned_at = ?
             WHERE id = ?
        ");
        $stmt->bind_param('sisi', $location, $assignedUserId, $assignedAt, $toolId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'status' => 'ok',
            'message' => 'Сохранено',
        ];
        break;
}
