<?php

declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown connector action'];

require_once __DIR__ . '/connector_engine.php';

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
            auth_token TEXT NULL,
            auth_token_expires_at DATETIME NULL,
            auth_cookies TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_sync_at DATETIME NULL,
            last_success_at DATETIME NULL,
            last_error TEXT NULL,
            ssl_ignore TINYINT(1) NOT NULL DEFAULT 0,
            scenario_json TEXT NULL,
            last_manual_confirm_at DATETIME NULL,
            last_manual_confirm_by INT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$dbcnx->query($sql)) {
        error_log('connectors schema create error: ' . $dbcnx->error);
    }

    $columnsToEnsure = [
        'scenario_json' => "ALTER TABLE connectors ADD COLUMN scenario_json TEXT NULL AFTER last_error",
        'auth_token' => "ALTER TABLE connectors ADD COLUMN auth_token TEXT NULL AFTER api_token",
        'auth_token_expires_at' => "ALTER TABLE connectors ADD COLUMN auth_token_expires_at DATETIME NULL AFTER auth_token",
        'auth_cookies' => "ALTER TABLE connectors ADD COLUMN auth_cookies TEXT NULL AFTER auth_token_expires_at",
        'last_manual_confirm_at' => "ALTER TABLE connectors ADD COLUMN last_manual_confirm_at DATETIME NULL AFTER scenario_json",
        'last_manual_confirm_by' => "ALTER TABLE connectors ADD COLUMN last_manual_confirm_by INT NULL AFTER last_manual_confirm_at",
    ];

    foreach ($columnsToEnsure as $column => $alterSql) {
        $columnCheck = $dbcnx->query("SHOW COLUMNS FROM connectors LIKE '{$column}'");
        if ($columnCheck instanceof mysqli_result) {
            if ($columnCheck->num_rows === 0) {
                if (!$dbcnx->query($alterSql)) {
                    error_log('connectors schema alter error: ' . $dbcnx->error);
                }
            }
            $columnCheck->free();
        } elseif ($columnCheck === false) {
            error_log('connectors schema check error: ' . $dbcnx->error);
        }
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
                auth_token,
                auth_token_expires_at,
                auth_cookies,
                is_active,
                last_sync_at,
                last_success_at,
                last_error,
                ssl_ignore,
                scenario_json,
                last_manual_confirm_at,
                last_manual_confirm_by,
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
                    auth_token,
                    is_active,
                    last_sync_at,
                    last_success_at,
                    last_error,
                    ssl_ignore,
                    scenario_json,
                    last_manual_confirm_at,
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
            'auth_token' => '',
            'auth_token_expires_at' => null,
            'auth_cookies' => '',
            'is_active' => 1,
            'last_sync_at' => null,
            'last_success_at' => null,
            'last_error' => '',
            'ssl_ignore' => 0,
            'scenario_json' => '',
            'last_manual_confirm_at' => null,
            'last_manual_confirm_by' => null,
            'manual_instruction' => '',
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

        $connector['manual_instruction'] = '';
        if (!empty($connector['scenario_json'])) {
            $scenario = json_decode((string)$connector['scenario_json'], true);
            if (is_array($scenario) && isset($scenario['manual_confirm']['instruction'])) {
                $connector['manual_instruction'] = (string)$scenario['manual_confirm']['instruction'];
            }
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


    case 'test_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $userId = (int)($user['id'] ?? 0);
        $result = connector_engine_run_by_id($dbcnx, $connectorId, $userId);
        audit_log(
            $userId,
            $result['ok'] ? 'CONNECTOR_TEST_OK' : 'CONNECTOR_TEST_FAIL',
            'connector',
            $connectorId,
            $result['message'] ?? 'Проверка коннектора'
        );
        $response = [
            'status' => 'ok',
            'ok' => $result['ok'],
            'message' => $result['message'],
            'connector_id' => $connectorId,
        ];
        break;

    case 'manual_confirm_connector':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) {
            $response = [
                'status' => 'error',
                'message' => 'connector_id required',
            ];
            break;
        }

        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение токена');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены',
            'connector_id' => $connectorId,
        ];
        break;


    case 'manual_confirm_puppeteer':
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

        $baseUrl = trim((string)($connector['base_url'] ?? ''));
        $login = trim((string)($connector['auth_username'] ?? ''));
        $password = trim((string)($connector['auth_password'] ?? ''));
        $scenarioJson = (string)($connector['scenario_json'] ?? '');
        $scenario = json_decode($scenarioJson, true);

        $loginUrl = '';
        if (is_array($scenario) && isset($scenario['login']['url'])) {
            $loginUrl = trim((string)$scenario['login']['url']);
        }
        if ($loginUrl === '' && $baseUrl !== '') {
            $loginUrl = $baseUrl;
        }

        if ($loginUrl === '') {
            $response = [
                'status' => 'error',
                'message' => 'Укажите base_url или login.url в сценарии',
            ];
            break;
        }

        $scriptDir = realpath(__DIR__ . '/../../scripts');
        $scriptPath = $scriptDir ? $scriptDir . '/manual_confirm_puppeteer.js' : '';
        if (!$scriptPath || !is_file($scriptPath)) {
            $response = [
                'status' => 'error',
                'message' => 'Скрипт Puppeteer не найден',
            ];
            break;
        }

        $command = sprintf(
            'node %s %s %s %s',
            escapeshellarg($scriptPath),
            escapeshellarg($loginUrl),
            escapeshellarg($login),
            escapeshellarg($password)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $rawOutput = trim(implode("\n", $output));
        $payload = $rawOutput !== '' ? json_decode($rawOutput, true) : null;

        if ($exitCode !== 0 || !is_array($payload)) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось получить токен/куки через Puppeteer',
                'details' => $rawOutput,
            ];
            break;
        }

        $authToken = trim((string)($payload['auth_token'] ?? ''));
        $authCookies = trim((string)($payload['auth_cookies'] ?? ''));
        $authTokenExpiresAt = trim((string)($payload['auth_token_expires_at'] ?? ''));
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение (Puppeteer)');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены через браузер',
            'connector_id' => $connectorId,
        ];
        break;

    case 'manual_confirm_extension':
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

        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $expiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;

        if ($authToken === '' && $authCookies === '') {
            $response = [
                'status' => 'error',
                'message' => 'Нужен токен или cookies',
            ];
            break;
        }

        $sql = "UPDATE connectors
                   SET auth_token = ?,
                       auth_cookies = ?,
                       auth_token_expires_at = ?,
                       last_manual_confirm_at = NOW(),
                       last_manual_confirm_by = ?,
                       last_error = ''
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (manual confirm)',
            ];
            break;
        }
        $userId = (int)($user['id'] ?? 0);
        $stmt->bind_param('sssii', $authToken, $authCookies, $expiresAt, $userId, $connectorId);
        $stmt->execute();
        $stmt->close();

        audit_log($userId, 'CONNECTOR_MANUAL_CONFIRM', 'connector', $connectorId, 'Ручное подтверждение (Extension)');
        $response = [
            'status' => 'ok',
            'message' => 'Токен/куки обновлены через расширение',
            'connector_id' => $connectorId,
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

            $userId = (int)($user['id'] ?? 0);
            audit_log($userId, 'CONNECTOR_DELETE', 'connector', $connectorId, 'Коннектор удалён');

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
        $authToken = trim($_POST['auth_token'] ?? '');
        $authCookies = trim($_POST['auth_cookies'] ?? '');
        $authTokenExpiresAt = trim($_POST['auth_token_expires_at'] ?? '');
        $scenarioJson = trim($_POST['scenario_json'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $sslIgnore = !empty($_POST['ssl_ignore']) ? 1 : 0;

        $isNew = $connectorId <= 0;
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
                           auth_token = ?,
                           auth_cookies = ?,
                           auth_token_expires_at = ?,
                           is_active = ?,
                           ssl_ignore = ?,
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
                'ssssssssssiissi',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $authToken,
                $authCookies,
                $authTokenExpiresAt,
                $isActive,
                $sslIgnore,
                $scenarioJson,
                $notes,
                $connectorId
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO connectors
                        (name, countries, base_url, auth_type, auth_username, auth_password, api_token, auth_token, auth_cookies, auth_token_expires_at, is_active, ssl_ignore, scenario_json, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'ssssssssssiiss',
                $name,
                $countries,
                $baseUrl,
                $authType,
                $authUsername,
                $authPassword,
                $apiToken,
                $authToken,
                $authCookies,
                $authTokenExpiresAt,
                $isActive,
                $sslIgnore,
                $scenarioJson,
                $notes
            );
            $stmt->execute();
            $connectorId = (int)$stmt->insert_id;
            $stmt->close();
        }


        $userId = (int)($user['id'] ?? 0);
        if ($isNew) {
            audit_log($userId, 'CONNECTOR_CREATE', 'connector', $connectorId, 'Коннектор создан');
        } else {
            audit_log($userId, 'CONNECTOR_UPDATE', 'connector', $connectorId, 'Коннектор обновлён');
        }

        $response = [
            'status' => 'ok',
            'message' => 'Коннектор сохранён',
            'connector_id' => $connectorId,
        ];
        break;
}
