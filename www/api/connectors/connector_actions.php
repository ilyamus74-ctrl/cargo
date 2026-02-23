<?php

declare(strict_types=1);

// Доступны: $action, $user, $dbcnx, $smarty
//$response = ['status' => 'error', 'message' => 'Unknown connector action'];

$normalizedAction = trim((string)$action);
$response = ['status' => 'error', 'message' => 'Unknown connector action: ' . $normalizedAction];

require_once __DIR__ . '/connector_engine.php';

final class ConnectorStepLogException extends RuntimeException
{
    /** @var array<int,array<string,mixed>> */
    private array $stepLog;
    private string $artifactsDir;

    public function __construct(string $message, array $stepLog = [], int $code = 0, ?Throwable $previous = null, string $artifactsDir = '')
    {
        parent::__construct($message, $code, $previous);
        $this->stepLog = $stepLog;
        $this->artifactsDir = trim($artifactsDir);
    }

    /** @return array<int,array<string,mixed>> */
    public function getStepLog(): array
    {
        return $this->stepLog;
    }

    public function getArtifactsDir(): string
    {
        return $this->artifactsDir;
    }
}

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
        'operations_json' => "ALTER TABLE connectors ADD COLUMN operations_json LONGTEXT NULL AFTER scenario_json",
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
                operations_json,
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


function connectors_default_operations(array $connector): array
{
    $defaultTarget = '';
    $name = strtolower(trim((string)($connector['name'] ?? '')));
    $countries = strtolower(trim((string)($connector['countries'] ?? '')));

    if ($name !== '' && $countries !== '') {
        $country = trim(explode(',', $countries)[0]);
        $country = preg_replace('/[^a-z0-9_]+/', '_', $country ?? '');
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $defaultTarget = trim($name . '_' . $country, '_');
    }

    return [
        'report' => [
            'enabled' => 0,
            'page_url' => '',
            'file_extension' => 'xlsx',
            'download_mode' => 'browser',
            'log_steps' => 0,
            'steps_json' => '',
            'curl_config_json' => '',
            'target_table' => $defaultTarget,
            'field_mapping_json' => '',
        ],
    ];
}

function connectors_decode_operations(array $connector): array
{
    $operations = connectors_default_operations($connector);
    $raw = (string)($connector['operations_json'] ?? '');
    if ($raw === '') {
        return $operations;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $operations;
    }

    if (isset($decoded['report']) && is_array($decoded['report'])) {
        $report = $decoded['report'];
        $operations['report']['enabled'] = !empty($report['enabled']) ? 1 : 0;
        $operations['report']['page_url'] = trim((string)($report['page_url'] ?? ''));
        $operations['report']['file_extension'] = trim((string)($report['file_extension'] ?? 'xlsx')) ?: 'xlsx';
        $operations['report']['download_mode'] = in_array(($report['download_mode'] ?? 'browser'), ['browser', 'curl'], true)
            ? (string)$report['download_mode']
            : 'browser';
        $operations['report']['log_steps'] = !empty($report['log_steps']) ? 1 : 0;

        if (isset($report['steps'])) {
            $operations['report']['steps_json'] = json_encode($report['steps'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
        if (isset($report['curl_config'])) {
            $operations['report']['curl_config_json'] = json_encode($report['curl_config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        $operations['report']['target_table'] = trim((string)($report['target_table'] ?? $operations['report']['target_table']));

        if (isset($report['field_mapping'])) {
            $operations['report']['field_mapping_json'] = json_encode($report['field_mapping'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }
    }

    return $operations;
}



function connectors_validate_iso_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD');
    }

    return $value;
}

function connectors_normalize_report_table_name(string $table): string
{
    $table = strtolower(trim($table));
    $table = preg_replace('/[^a-z0-9_]+/', '_', $table) ?? '';
    $table = trim($table, '_');

    if ($table === '') {
        throw new InvalidArgumentException('Не указана целевая таблица отчета');
    }

    if (strpos($table, 'connector_report_') !== 0) {
        $table = 'connector_report_' . $table;
    }

    return $table;
}

function connectors_ensure_report_table(mysqli $dbcnx, string $tableName): void
{
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = "CREATE TABLE IF NOT EXISTS {$safeTable} (
"
        . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
        . " connector_id INT UNSIGNED NOT NULL,
"
        . " period_from DATE NULL,
"
        . " period_to DATE NULL,
"
        . " payload_json LONGTEXT NULL,
"
        . " source_file VARCHAR(255) NOT NULL DEFAULT '',
"
        . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
        . " PRIMARY KEY (id),
"
        . " KEY idx_connector_period (connector_id, period_from, period_to)
"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$dbcnx->query($sql)) {
        throw new RuntimeException('DB error (create report table): ' . $dbcnx->error);
    }
}


function connectors_apply_vars($value, array $vars)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = connectors_apply_vars($v, $vars);
        }
        return $result;
    }

    if (!is_string($value)) {
        return $value;
    }

    return preg_replace_callback('/\$\{([a-zA-Z0-9_]+)\}/', static function ($m) use ($vars) {
        $key = $m[1] ?? '';
        return array_key_exists($key, $vars) ? (string)$vars[$key] : '';
    }, $value) ?? $value;
}


function connectors_extract_browser_login_steps(array $connector): array
{
    $scenarioRaw = trim((string)($connector['scenario_json'] ?? ''));
    if ($scenarioRaw === '') {
        return [];
    }

    $scenario = json_decode($scenarioRaw, true);
    if (!is_array($scenario)) {
        return [];
    }

    if (isset($scenario['browser_login_steps']) && is_array($scenario['browser_login_steps'])) {
        return $scenario['browser_login_steps'];
    }

    if (isset($scenario['login']['browser_steps']) && is_array($scenario['login']['browser_steps'])) {
        return $scenario['login']['browser_steps'];
    }

    return [];
}


function connectors_parse_set_cookie_header(array $headers): string
{
    $cookies = [];
    foreach ($headers as $headerLine) {
        if (stripos((string)$headerLine, 'Set-Cookie:') !== 0) {
            continue;
        }
        $raw = trim(substr((string)$headerLine, strlen('Set-Cookie:')));
        if ($raw === '') {
            continue;
        }
        $firstPart = trim(explode(';', $raw, 2)[0] ?? '');
        if ($firstPart === '' || strpos($firstPart, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $firstPart, 2));
        if ($k === '') {
            continue;
        }
        $cookies[$k] = $v;
    }

    $pairs = [];
    foreach ($cookies as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return implode('; ', $pairs);
}

function connectors_curl_request(array $cfg, array $vars, bool $sslIgnore): array
{
    $url = trim((string)connectors_apply_vars((string)($cfg['url'] ?? ''), $vars));
    if ($url === '') {
        throw new InvalidArgumentException('В cURL-запросе отсутствует url');
    }

    $method = strtoupper(trim((string)($cfg['method'] ?? 'GET')));
    if ($method === '') {
        $method = 'GET';
    }

    $headers = [];
    if (isset($cfg['headers']) && is_array($cfg['headers'])) {
        foreach ($cfg['headers'] as $k => $v) {
            $headers[] = $k . ': ' . (string)connectors_apply_vars((string)$v, $vars);
        }
    }

    $bodyData = [];
    $rawBody = [];
    if (isset($cfg['body']) && is_array($cfg['body'])) {
        $rawBody = $cfg['body'];
    } elseif (isset($cfg['fields']) && is_array($cfg['fields'])) {
        $rawBody = $cfg['fields'];
    }
    foreach ($rawBody as $k => $v) {
        $bodyData[$k] = connectors_apply_vars($v, $vars);
    }

    $responseHeaders = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, $line) use (&$responseHeaders) {
        $responseHeaders[] = trim((string)$line);
        return strlen($line);
    });
    if ($sslIgnore) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($bodyData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyData));
        }
    }

    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Ошибка cURL: ' . $curlErr);
    }

    return [
        'http_code' => $httpCode,
        'body' => (string)$body,
        'headers' => $responseHeaders,
        'cookies' => connectors_parse_set_cookie_header($responseHeaders),
    ];
}


function connectors_download_report_file(array $connector, array $reportCfg, ?string $periodFrom, ?string $periodTo): array
{
    $ext = strtolower(trim((string)($reportCfg['file_extension'] ?? 'xlsx')));
    if ($ext === '') {
        $ext = 'xlsx';
    }

    $today = date('Y-m-d');
    $vars = [
        'date_from' => $periodFrom ?? '',
        'date_to' => $periodTo ?? '',
        'today' => $today,
        'base_url' => trim((string)($connector['base_url'] ?? '')),
        'login' => trim((string)($connector['auth_username'] ?? '')),
        'password' => trim((string)($connector['auth_password'] ?? '')),
    ];


    $logStepsEnabled = !empty($reportCfg['log_steps']);
    $stepLog = [];
    $appendStepLog = static function (string $step, string $message, array $meta = []) use (&$stepLog, $logStepsEnabled): void {
        if (!$logStepsEnabled) {
            return;
        }

        $stepLog[] = [
            'time' => date('c'),
            'step' => $step,
            'message' => $message,
            'meta' => $meta,
        ];
    };

    if (($reportCfg['download_mode'] ?? 'browser') === 'browser') {
        $reportSteps = isset($reportCfg['steps']) && is_array($reportCfg['steps']) ? $reportCfg['steps'] : [];
        $loginSteps = connectors_extract_browser_login_steps($connector);
        $steps = array_merge($loginSteps, $reportSteps);
        if (empty($steps)) {
            throw new InvalidArgumentException('Для режима browser заполните report_steps_json или browser_login_steps в scenario_json');
        }

        $scriptPath = realpath(__DIR__ . '/../../scripts/test_connector_operations_browser.js');
        if (!$scriptPath) {
            throw new RuntimeException('Не найден browser script для теста скачивания');
        }


        $tempDir = __DIR__ . '/../../scripts/_tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }


        $payload = [
            'steps' => $steps,
            'vars' => $vars,
            'file_extension' => $ext,
            'ssl_ignore' => !empty($connector['ssl_ignore']),
            'cookies' => (string)($connector['auth_cookies'] ?? ''),
            'auth_token' => (string)($connector['auth_token'] ?? ''),
            'temp_dir' => realpath($tempDir) ?: $tempDir,
        ];

        $cmd = 'node ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg((string)json_encode($payload, JSON_UNESCAPED_UNICODE));
        $output = shell_exec($cmd . ' 2>&1');
        $decoded = json_decode(trim((string)$output), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Не удалось выполнить browser-тест скачивания: ' . trim((string)$output));
        }
        if (empty($decoded['ok'])) {
           $browserStepLog = isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [];
           $browserArtifactsDir = trim((string)($decoded['artifacts_dir'] ?? ''));
           if (!empty($browserStepLog)) {
              throw new ConnectorStepLogException((string)($decoded['message'] ?? 'Browser test failed'), $browserStepLog, 0, null, $browserArtifactsDir);
              }
            throw new RuntimeException((string)($decoded['message'] ?? 'Browser test failed'));
        }

        $filePath = (string)($decoded['file_path'] ?? '');
        if ($filePath === '' || !is_file($filePath)) {
            throw new RuntimeException('Browser-тест не вернул путь к скачанному файлу');
        }

        return [
            'file_path' => $filePath,
            'file_size' => (int)filesize($filePath),
            'file_extension' => $ext,
            'download_mode' => 'browser',
            'step_log' => isset($decoded['step_log']) && is_array($decoded['step_log']) ? $decoded['step_log'] : [],
            'artifacts_dir' => trim((string)($decoded['artifacts_dir'] ?? '')),
        ];
    }

    $curlCfg = isset($reportCfg['curl_config']) && is_array($reportCfg['curl_config']) ? $reportCfg['curl_config'] : [];
    $url = trim((string)($curlCfg['url'] ?? ''));
    if ($url === '') {
        throw new InvalidArgumentException('Для режима cURL нужен JSON-объект в report_curl_config_json с полем url (например {"url":"https://.../export","method":"POST"}).');
    }

    $url = (string)connectors_apply_vars($url, $vars);
    $method = strtoupper(trim((string)($curlCfg['method'] ?? 'GET')));
    if ($method === '') {
        $method = 'GET';
    }

    $headers = [];
    $hasAuthorizationHeader = false;
    $hasCookieHeader = false;
    if (isset($curlCfg['headers']) && is_array($curlCfg['headers'])) {
        foreach ($curlCfg['headers'] as $k => $v) {
            $headerName = trim((string)$k);
            if (strcasecmp($headerName, 'Authorization') === 0) {
                $hasAuthorizationHeader = true;
            }
            if (strcasecmp($headerName, 'Cookie') === 0) {
                $hasCookieHeader = true;
            }
            $headers[] = $headerName . ': ' . (string)connectors_apply_vars((string)$v, $vars);
        }
    }

    $bodyData = [];
    if (isset($curlCfg['body']) && is_array($curlCfg['body'])) {
        foreach ($curlCfg['body'] as $k => $v) {
            $bodyData[$k] = connectors_apply_vars($v, $vars);
        }
    }


    if (!$hasAuthorizationHeader && !empty($connector['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . trim((string)$connector['auth_token']);
    }

    $cookieParts = [];
    if (!empty($connector['auth_cookies'])) {
        $cookieParts[] = trim((string)$connector['auth_cookies']);
    }

    if (isset($curlCfg['login']) && is_array($curlCfg['login'])) {
        $appendStepLog('login', 'Выполняем login-запрос через cURL', [
            'url' => (string)($curlCfg['login']['url'] ?? ''),
            'method' => strtoupper((string)($curlCfg['login']['method'] ?? 'GET')),
        ]);
        $loginResponse = connectors_curl_request($curlCfg['login'], $vars, !empty($connector['ssl_ignore']));
        $loginHttp = (int)($loginResponse['http_code'] ?? 0);
        $appendStepLog('login', 'Login-запрос выполнен', ['http_code' => $loginHttp]);
        if ($loginHttp >= 400) {
            throw new ConnectorStepLogException('Ошибка логина через cURL: HTTP ' . $loginHttp, $stepLog);
        }

        $successCfg = isset($curlCfg['login']['success']) && is_array($curlCfg['login']['success'])
            ? $curlCfg['login']['success']
            : [];
        $successSelector = trim((string)($successCfg['selector'] ?? ''));
        if ($successSelector !== '') {
            $loginBody = (string)($loginResponse['body'] ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('*', $successSelector)), static fn($part) => $part !== ''));
            $found = true;
            foreach ($parts as $part) {
                if (stripos($loginBody, $part) === false) {
                    $found = false;
                    break;
                }
            }

            $appendStepLog('login', 'Проверка login.success.selector', [
                'selector' => $successSelector,
                'matched' => $found,
            ]);

            if (!$found) {
                throw new ConnectorStepLogException('Логин через cURL не прошёл проверку success.selector', $stepLog);
            }
        }
        $loginCookies = trim((string)($loginResponse['cookies'] ?? ''));
        if ($loginCookies !== '') {
            $cookieParts[] = $loginCookies;
        }
    }

    if (!$hasCookieHeader && !empty($cookieParts)) {
        $headers[] = 'Cookie: ' . implode('; ', $cookieParts);
    }

    $appendStepLog('download_prepare', 'Подготовлен запрос на скачивание файла', [
        'url' => $url,
        'method' => $method,
        'has_cookie_header' => $hasCookieHeader || !empty($cookieParts),
    ]);

    $tmpFile = tempnam(sys_get_temp_dir(), 'connector-report-');
    if ($tmpFile === false) {
        throw new RuntimeException('Не удалось создать временный файл');
    }
    $filePath = $tmpFile . '.' . $ext;
    @rename($tmpFile, $filePath);

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Не удалось открыть временный файл для записи');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_FILE, $fh);
    if (!empty($connector['ssl_ignore'])) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($bodyData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyData));
        }
    }

    $ok = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if (!$ok || $httpCode >= 400) {
        @unlink($filePath);
        $appendStepLog('download', 'Скачивание завершилось ошибкой', ['http_code' => $httpCode]);
        throw new ConnectorStepLogException('Ошибка скачивания через cURL: HTTP ' . $httpCode . ' ' . $curlErr, $stepLog);
    }

    $size = (int)filesize($filePath);
    if ($size <= 0) {
        @unlink($filePath);
        $appendStepLog('download', 'Скачивание завершилось пустым файлом', ['http_code' => $httpCode]);
        throw new ConnectorStepLogException('Скачанный файл пустой', $stepLog);
    }

    $appendStepLog('download', 'Файл успешно скачан', [
        'http_code' => $httpCode,
        'file_size' => $size,
        'file_path' => $filePath,
    ]);

    return [
        'file_path' => $filePath,
        'file_size' => $size,
        'file_extension' => $ext,
        'download_mode' => 'curl',
        'step_log' => $logStepsEnabled ? $stepLog : [],
    ];
}

function connectors_import_csv_into_report_table(mysqli $dbcnx, string $tableName, string $filePath, int $connectorId, ?string $periodFrom, ?string $periodTo, array $fieldMapping): int
{
    $fh = fopen($filePath, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Не удалось открыть CSV для импорта');
    }

    $header = fgetcsv($fh);
    if (!is_array($header) || empty($header)) {
        fclose($fh);
        throw new RuntimeException('CSV не содержит заголовок');
    }

    $headerMap = [];
    foreach ($header as $idx => $name) {
        $headerMap[trim((string)$name)] = (int)$idx;
    }

    if (empty($fieldMapping)) {
        fclose($fh);
        throw new InvalidArgumentException('Для CSV-импорта заполните "Маппинг полей"');
    }

    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = 'INSERT INTO ' . $safeTable . ' (connector_id, period_from, period_to, payload_json, source_file) VALUES (?, ?, ?, ?, ?)';
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        fclose($fh);
        throw new RuntimeException('DB error (prepare import): ' . $dbcnx->error);
    }

    $count = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $payload = [];
        foreach ($fieldMapping as $targetField => $csvColumnName) {
            $csvColumnName = trim((string)$csvColumnName);
            if ($csvColumnName === '' || !array_key_exists($csvColumnName, $headerMap)) {
                $payload[$targetField] = null;
                continue;
            }
            $payload[$targetField] = $row[$headerMap[$csvColumnName]] ?? null;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            continue;
        }

        $sourceFile = basename($filePath);
        $stmt->bind_param('issss', $connectorId, $periodFrom, $periodTo, $payloadJson, $sourceFile);
        if ($stmt->execute()) {
            $count++;
        }
    }

    $stmt->close();
    fclose($fh);
    return $count;
}


function connectors_build_operations_payload_from_post(): array
{
    $enabled = !empty($_POST['report_enabled']) ? 1 : 0;
    $pageUrl = trim((string)($_POST['report_page_url'] ?? ''));
    $fileExtension = strtolower(trim((string)($_POST['report_file_extension'] ?? 'xlsx')));
    $downloadMode = trim((string)($_POST['report_download_mode'] ?? 'browser'));
    $logSteps = !empty($_POST['report_log_steps']) ? 1 : 0;
    $stepsJson = trim((string)($_POST['report_steps_json'] ?? ''));
    $curlConfigJson = trim((string)($_POST['report_curl_config_json'] ?? ''));
    $targetTable = strtolower(trim((string)($_POST['report_target_table'] ?? '')));
    $fieldMappingJson = trim((string)($_POST['report_field_mapping_json'] ?? ''));


    $targetTable = preg_replace('/[^a-z0-9_]+/', '_', $targetTable ?? '');
    if ($targetTable !== '') {
        $targetTable = trim($targetTable, '_');
    }

    $steps = [];
    if ($stepsJson !== '') {
        $decoded = json_decode($stepsJson, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Некорректный JSON в "Шаги формы/кнопок"');
        }
        $steps = $decoded;
    }

    $curlConfig = [];
    if ($curlConfigJson !== '') {
        $decoded = json_decode($curlConfigJson, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Некорректный JSON в "PHP cURL конфиг"');
        }
        $isList = array_keys($decoded) === range(0, count($decoded) - 1);
        if ($isList) {
            throw new InvalidArgumentException('"PHP cURL конфиг" должен быть JSON-объектом вида {"url":"...","method":"GET|POST",...}, а не массивом browser-шагов. Шаги нужно указывать в "Шаги формы/кнопок".');
        }
        $curlConfig = $decoded;
    }

    $fieldMapping = [];
    if ($fieldMappingJson !== '') {
        $decoded = json_decode($fieldMappingJson, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Некорректный JSON в "Маппинг полей"');
        }
        $fieldMapping = $decoded;
    }

    if (!in_array($downloadMode, ['browser', 'curl'], true)) {
        $downloadMode = 'browser';
    }

    if ($fileExtension === '') {
        $fileExtension = 'xlsx';
    }

    return [
        'report' => [
            'enabled' => $enabled,
            'page_url' => $pageUrl,
            'file_extension' => $fileExtension,
            'download_mode' => $downloadMode,
            'log_steps' => $logSteps,
            'steps' => $steps,
            'curl_config' => $curlConfig,
            'target_table' => $targetTable,
            'field_mapping' => $fieldMapping,
        ],
    ];
}


connectors_ensure_schema($dbcnx);

switch ($normalizedAction) {
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
            'operations_json' => '',
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
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
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
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
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


    case 'form_connector_operations':
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

        $operations = connectors_decode_operations($connector);
        $smarty->assign('connector', $connector);
        $smarty->assign('operations', $operations);

        ob_start();
        $smarty->display('cells_NA_API_connector_operations_modal.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html' => $html,
        ];
        break;

    case 'save_connector_operations':
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

        try {
            $operationsPayload = connectors_build_operations_payload_from_post();
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            break;
        }

        $operationsJson = json_encode($operationsPayload, JSON_UNESCAPED_UNICODE);
        if ($operationsJson === false) {
            $response = [
                'status' => 'error',
                'message' => 'Не удалось сериализовать операции',
            ];
            break;
        }

        $stmt = $dbcnx->prepare('UPDATE connectors SET operations_json = ? WHERE id = ?');
        if (!$stmt) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save operations)',
            ];
            break;
        }
        $stmt->bind_param('si', $operationsJson, $connectorId);
        if (!$stmt->execute()) {
            $response = [
                'status' => 'error',
                'message' => 'DB error (save operations): ' . $stmt->error,
            ];
            $stmt->close();
            break;
        }
        $stmt->close();

        $userId = (int)($user['id'] ?? 0);
        audit_log($userId, 'CONNECTOR_OPERATIONS_UPDATE', 'connector', $connectorId, 'Операции коннектора обновлены');

        $response = [
            'status' => 'ok',
            'message' => 'Операции сохранены',
            'connector_id' => $connectorId,
        ];
        break;





    case 'test_connector_operations':
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

        try {
            $operationsPayload = connectors_build_operations_payload_from_post();
            $reportCfg = (array)($operationsPayload['report'] ?? []);

            $periodFrom = connectors_validate_iso_date($_POST['test_period_from'] ?? null);
            $periodTo = connectors_validate_iso_date($_POST['test_period_to'] ?? null);
            if ($periodFrom !== null && $periodTo !== null && $periodFrom > $periodTo) {
                throw new InvalidArgumentException('Дата начала периода больше даты окончания');
            }

            $targetTable = connectors_normalize_report_table_name((string)($reportCfg['target_table'] ?? ''));
            connectors_ensure_report_table($dbcnx, $targetTable);
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
            ];
            break;
        } catch (RuntimeException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
            ];
            break;
        }

        $periodMessage = '';
        if ($periodFrom !== null || $periodTo !== null) {
            $periodMessage = ' Период: ' . ($periodFrom ?? '...') . ' — ' . ($periodTo ?? '...') . '.';
        }

        try {
            $downloadInfo = connectors_download_report_file($connector, $reportCfg, $periodFrom, $periodTo);

            $importedRows = 0;
            $importMessage = ' Парсинг не выполнен: поддержан авто-импорт только CSV.';
            $fieldMapping = isset($reportCfg['field_mapping']) && is_array($reportCfg['field_mapping']) ? $reportCfg['field_mapping'] : [];
            if (($downloadInfo['file_extension'] ?? '') === 'csv') {
                $importedRows = connectors_import_csv_into_report_table(
                    $dbcnx,
                    $targetTable,
                    (string)$downloadInfo['file_path'],
                    $connectorId,
                    $periodFrom,
                    $periodTo,
                    $fieldMapping
                );
                $importMessage = ' Импортировано строк: ' . $importedRows . '.';
            }

            $response = [
                'status' => 'ok',
                'message' => 'Тест операции пройден. Файл скачан (' . (int)($downloadInfo['file_size'] ?? 0) . ' байт). Таблица `' . $targetTable . '` готова.' . $periodMessage . $importMessage,
                'connector_id' => $connectorId,
                'target_table' => $targetTable,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'download' => $downloadInfo,
                'step_log' => isset($downloadInfo['step_log']) && is_array($downloadInfo['step_log']) ? $downloadInfo['step_log'] : [],
                'imported_rows' => $importedRows,
            ];
        } catch (InvalidArgumentException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'target_table' => $targetTable,
            ];
        } catch (ConnectorStepLogException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'target_table' => $targetTable,
                'step_log' => $e->getStepLog(),
                'artifacts_dir' => $e->getArtifactsDir(),
            ];
        } catch (RuntimeException $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'connector_id' => $connectorId,
                'target_table' => $targetTable,
            ];
        } catch (Throwable $e) {
            error_log('test_connector_operations fatal: ' . $e->getMessage());
            $response = [
                'status' => 'error',
                'message' => 'Ошибка во время теста операции: ' . $e->getMessage(),
                'connector_id' => $connectorId,
                'target_table' => $targetTable,
            ];
        }
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
        $authTokenExpiresAt = $authTokenExpiresAt !== '' ? $authTokenExpiresAt : null;
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
                           operations_json = ?,
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
                'ssssssssssiisssi',
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
            if (!$stmt->execute()) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (update connector): ' . $stmt->error,
                ];
                $stmt->close();
                break;
            }
            $stmt->close();
        } else {
            $sql = "INSERT INTO connectors
                        (name, countries, base_url, auth_type, auth_username, auth_password, api_token, auth_token, auth_cookies, auth_token_expires_at, is_active, ssl_ignore, scenario_json, operations_json, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector)',
                ];
                break;
            }
            $stmt->bind_param(
                'ssssssssssiisss',
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
                '',
                $notes
            );
            $stmt->execute();
            if (!$stmt->execute()) {
                $response = [
                    'status' => 'error',
                    'message' => 'DB error (insert connector): ' . $stmt->error,
                ];
                $stmt->close();
                break;
            }
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

    default:
        $response = [
            'status' => 'error',
            'message' => 'Unknown connector action: ' . $normalizedAction,
        ];
        break;
}

