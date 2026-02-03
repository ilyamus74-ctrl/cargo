<?php

declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown connector action'];

function connectors_ensure_schema(mysqli $dbcnx): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS connectors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL,
            countries VARCHAR(255) NOT NULL DEFAULT '',
            base_url VARCHAR(255) NOT NULL DEFAULT '',
            auth_type VARCHAR(32) NOT NULL DEFAULT 'login',
            auth_username VARCHAR(128) NOT NULL DEFAULT '',
            auth_password VARCHAR(255) NOT NULL DEFAULT '',
            api_token VARCHAR(255) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sync_at DATETIME NULL,
            last_success_at DATETIME NULL,
            last_error TEXT NULL,
            scenario_json TEXT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($sql)) {
        error_log('connectors schema create error: ' . $dbcnx->error);
    }

    $columnCheck = $dbcnx->query("SHOW COLUMNS FROM connectors LIKE 'scenario_json'");
    if ($columnCheck instanceof mysqli_result) {
        if ($columnCheck->num_rows === 0) {
            $alterSql = "ALTER TABLE connectors ADD COLUMN scenario_json TEXT NULL AFTER last_error";
            if (!$dbcnx->query($alterSql)) {
                error_log('connectors schema alter error: ' . $dbcnx->error);
            }
        }
        $columnCheck->free();
    } elseif ($columnCheck === false) {
        error_log('connectors schema check error: ' . $dbcnx->error);
    }
}

function connectors_build_status(array $connector): array
{
    $isActive = (int)($connector['is_active'] ?? 0) === 1;
    $lastError = trim((string)($connector['last_error'] ?? ''));

    if (!$isActive) {
        $connector['status_label'] = 'Отключен';
        $connector['status_class'] = 'secondary';
        return $connector;
    }

    if ($lastError !== '') {
        $connector['status_label'] = 'Ошибка';
        $connector['status_class'] = 'danger';
        return $connector;
    }

    if (!empty($connector['last_success_at'])) {
        $connector['status_label'] = 'Работает';
        $connector['status_class'] = 'success';
        return $connector;
    }

    $connector['status_label'] = 'Ожидание';
    $connector['status_class'] = 'warning';
    return $connector;
}

function connectors_fetch_one(mysqli $dbcnx, int $connectorId): ?array
{
    $sql = "SELECT
                id,
                name,
                countries,
                base_url,
                auth_type,
                auth_username,
                auth_password,
                api_token,
                is_active,
                last_sync_at,
                last_success_at,
                last_error,
                scenario_json,
                notes,
                created_at,
                updated_at
            FROM connectors
            WHERE id = ?
            LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        error_log('connectors fetch prepare error: ' . $dbcnx->error);
        return null;
    }
    $stmt->bind_param('i', $connectorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

connectors_ensure_schema($dbcnx);

switch ($action) {
    case 'view_connectors':
        $connectors = [];
        $sql = "SELECT
                    id,
                    name,
                    countries,
                    base_url,
                    auth_type,
                    auth_username,
                    is_active,
                    last_sync_at,
                    last_success_at,
                    last_error,
                    scenario_json,
                    created_at,
                    updated_at
                FROM connectors
                ORDER BY id DESC";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $connectors[] = connectors_build_status($row);
            }
            $res->free();
        }

        $smarty->assign('connectors', $connectors);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_connectors.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_new_connector':
        $connector = [
            'id' => 0,
            'name' => '',
            'countries' => '',
            'base_url' => '',
            'auth_type' => 'login',
            'auth_username' => '',
            'auth_password' => '',
            'api_token' => '',
            'is_active' => 1,
            'last_sync_at' => null,
            'last_success_at' => null,
            'last_error' => '',
            'scenario_json' => '',
            'notes' => '',
            'created_at' => null,
            'updated_at' => null,
        ];

        $smarty->assign('connector', $connector);

        ob_start();
        $smarty->display('cells_NA_API_connector_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_edit_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $connector = connectors_fetch_one($dbcnx, $connectorId);
        if (!$connector) {
            $response = [
                'status' => 'error',
                'message' => 'Коннектор не найден',
            ];
            break;
        }

        $smarty->assign('connector', $connector);

        ob_start();
        $smarty->display('cells_NA_API_connector_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'save_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        $deleteFlag = !empty($_POST['delete']);

        if ($deleteFlag && $connectorId > 0) {
            $stmt = $dbcnx->prepare("DELETE FROM connectors WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $connectorId);
                $stmt->execute();
                $stmt->close();
            }

            $response = [
                'status' => 'ok',
                'message' => 'Коннектор удалён',
                'deleted' => true,
            ];
            break;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $response = [
                'status' => 'error',
                'message' => 'Название обязательно',
            ];
            break;
        }

        $countries = trim($_POST['countries'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $authType = trim($_POST['auth_type'] ?? 'login');
        $authUsername = trim($_POST['auth_username'] ?? '');
        $authPassword = trim($_POST['auth_password'] ?? '');
        $apiToken = trim($_POST['api_token'] ?? '');
        $scenarioJson = trim($_POST['scenario_json'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($connectorId > 0) {
            $existing = connectors_fetch_one($dbcnx, $connectorId);
            if (!$existing) {
                $response = [
                    'status' => 'error',
                    'message' => 'Коннектор не найден',
                ];
                break;
            }

            if ($authPassword === '') {
                $authPassword = $existing['auth_password'];
            }
            if ($apiToken === '') {
                $apiToken = $existing['api_token'];
            }

            $sql = "UPDATE connectors
                       SET name = ?,
                           countries = ?,
                           base_url = ?,
                           auth_type = ?,
                           auth_username = ?,
                           auth_password = ?,
                           api_token = ?,
                           is_active = ?,
                           scenario_json = ?,
                           notes = ?
                     WHERE id = ?";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (update connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'sssssssissi',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $isActive,
                $scenarioJson,
                $notes,
                $connectorId
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO connectors
                        (name, countries, base_url, auth_type, auth_username, auth_password, api_token, is_active, scenario_json, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'sssssssiss',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $isActive,
                $scenarioJson,
                $notes
            );
            $stmt->execute();
            $connectorId = (int)$stmt->insert_id;
            $stmt->close();
        }

        $response = [
            'status' => 'ok',
            'message' => 'Коннектор сохранён',
            'connector_id' => $connectorId,
        ];
        break;
}
