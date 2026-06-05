<?php

declare(strict_types=1);

function connector_engine_parse_scenario(string $scenarioJson): array
{
    if ($scenarioJson === '') {
        return [
            'ok' => false,
            'message' => 'Сценарий входа пустой',
        ];
    }

    $scenario = json_decode($scenarioJson, true);
    if (!is_array($scenario)) {
        return [
            'ok' => false,
            'message' => 'Сценарий входа содержит некорректный JSON',
        ];
    }

    return [
        'ok' => true,
        'scenario' => $scenario,
    ];
}

function connector_engine_fill_fields(array $fields, array $connector): array
{
    $replacements = [
        '${login}' => (string)($connector['auth_username'] ?? ''),
        '${password}' => (string)($connector['auth_password'] ?? ''),
        '${token}' => (string)($connector['api_token'] ?? ''),
    ];

    $result = [];
    foreach ($fields as $key => $value) {
        if (is_array($value)) {
            $result[$key] = connector_engine_fill_fields($value, $connector);
            continue;
        }
        $value = (string)$value;
        $result[$key] = str_replace(array_keys($replacements), array_values($replacements), $value);
    }
    return $result;
}

function connector_engine_resolve_url(string $url, array $connector): string
{
    $url = trim($url);
    if ($url === '') {
        return $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $baseUrl = trim((string)($connector['base_url'] ?? ''));
    if ($baseUrl === '') {
        return $url;
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

function connector_engine_css_to_xpath(string $selector): string
{
    $selector = trim($selector);
    if ($selector === '') {
        return '//*';
    }

    $parts = preg_split('/\s+/', $selector);
    $xpathParts = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        $tag = '*';
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*/', $part, $matches)) {
            $tag = $matches[0];
        }

        $conditions = [];

        if (preg_match('/#([a-zA-Z0-9_-]+)/', $part, $matches)) {
            $conditions[] = "@id='{$matches[1]}'";
        }

        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $part, $matches)) {
            foreach ($matches[1] as $className) {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$className} ')";
            }
        }

        if (preg_match_all('/\\[([a-zA-Z0-9_-]+)\\*=\"([^\"]+)\"\\]/', $part, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $conditions[] = "contains(@{$match[1]}, '{$match[2]}')";
            }
        }

        if (preg_match_all('/\\[([a-zA-Z0-9_-]+)=\"([^\"]+)\"\\]/', $part, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $conditions[] = "@{$match[1]}='{$match[2]}'";
            }
        }

        $xpath = $tag;
        if (!empty($conditions)) {
            $xpath .= '[' . implode(' and ', $conditions) . ']';
        }

        $xpathParts[] = $xpath;
    }

    if (empty($xpathParts)) {
        return '//*';
    }

    return '//' . implode('//', $xpathParts);
}

function connector_engine_match_success(string $html, string $selector, string $text = ''): array
{
    if ($selector === '') {
        return [
            'ok' => false,
            'message' => 'Не указан selector для проверки успеха',
        ];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $query = connector_engine_css_to_xpath($selector);
    $nodes = $xpath->query($query);

    if (!$nodes || $nodes->length === 0) {
        return [
            'ok' => false,
            'message' => 'Элемент успеха не найден по selector',
        ];
    }

    if ($text !== '') {
        $foundText = false;
        foreach ($nodes as $node) {
            if (mb_stripos($node->textContent, $text) !== false) {
                $foundText = true;
                break;
            }
        }
        if (!$foundText) {
            return [
                'ok' => false,
                'message' => 'Элемент найден, но текст не совпадает',
            ];
        }
    }

    return [
        'ok' => true,
        'message' => 'Успешный вход подтверждён',
    ];
}

function connector_engine_extract_json_path(array $data, string $path)
{
    if ($path === '') {
        return null;
    }
    $segments = explode('.', $path);
    $current = $data;
    foreach ($segments as $segment) {
        if (is_array($current) && array_key_exists($segment, $current)) {
            $current = $current[$segment];
            continue;
        }
        return null;
    }
    return $current;
}

function connector_engine_expect_match(array $response, array $expect): array
{
    $statusExpected = $expect['status_code'] ?? null;
    if ($statusExpected !== null && (int)$response['status'] !== (int)$statusExpected) {
        return [
            'ok' => false,
            'message' => 'Код ответа не совпадает',
        ];
    }

    $jsonPath = (string)($expect['json_path'] ?? '');
    if ($jsonPath !== '') {
        $json = json_decode((string)$response['body'], true);
        if (!is_array($json)) {
            return [
                'ok' => false,
                'message' => 'Ответ не является JSON',
            ];
        }
        $actual = connector_engine_extract_json_path($json, $jsonPath);
        $operator = (string)($expect['operator'] ?? '=');
        $expectedValue = $expect['value'] ?? null;

        $compareOk = false;
        switch ($operator) {
            case '=':
            case '==':
                $compareOk = $actual == $expectedValue;
                break;
            case '!=':
                $compareOk = $actual != $expectedValue;
                break;
            case '>':
                $compareOk = $actual > $expectedValue;
                break;
            case '<':
                $compareOk = $actual < $expectedValue;
                break;
            case '>=':
                $compareOk = $actual >= $expectedValue;
                break;
            case '<=':
                $compareOk = $actual <= $expectedValue;
                break;
            case 'contains':
                if (is_array($actual)) {
                    $compareOk = in_array($expectedValue, $actual, true);
                } else {
                    $compareOk = mb_stripos((string)$actual, (string)$expectedValue) !== false;
                }
                break;
            default:
                return [
                    'ok' => false,
                    'message' => 'Неизвестный оператор сравнения',
                ];
        }

        if (!$compareOk) {
            return [
                'ok' => false,
                'message' => 'Проверка JSON не прошла',
            ];
        }
    }

    return [
        'ok' => true,
        'message' => 'Проверка ответа прошла',
    ];
}

function connector_engine_request(
    string $url,
    string $method,
    array $fields,
    string $cookieFile,
    string $cookieValue = '',
    bool $sslIgnore = false
): array {
    $ch = curl_init();
    $method = strtoupper($method);

    if ($method === 'GET' && !empty($fields)) {
        $query = http_build_query($fields);
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    }

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'CargoConnector/1.0',
    ];

    if ($cookieValue !== '') {
        $options[CURLOPT_COOKIE] = $cookieValue;
    }

    curl_setopt_array($ch, $options);


    if ($sslIgnore) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok' => false,
            'message' => $error !== '' ? $error : 'Не удалось выполнить запрос',
            'status' => $status,
            'body' => '',
        ];
    }

    return [
        'ok' => true,
        'message' => 'ok',
        'status' => $status,
        'body' => (string)$body,
    ];
}

function connector_engine_update_status(
    mysqli $dbcnx,
    int $connectorId,
    bool $ok,
    string $message,
    int $userId = 0
): void
{
    if ($ok) {
        $sql = "UPDATE connectors
                SET last_sync_at = NOW(),
                    last_success_at = NOW(),
                    last_error = ''
                WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $connectorId);
            $stmt->execute();
            $stmt->close();
        }
        if (function_exists('audit_log')) {
            audit_log($userId, 'CONNECTOR_STATUS_OK', 'connector', $connectorId, $message);
        }
        return;
    }

    $sql = "UPDATE connectors
            SET last_sync_at = NOW(),
                last_error = ?
            WHERE id = ?";
    $stmt = $dbcnx->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('si', $message, $connectorId);
        $stmt->execute();
        $stmt->close();
    }
    if (function_exists('audit_log')) {
        audit_log($userId, 'CONNECTOR_STATUS_FAIL', 'connector', $connectorId, $message);
    }
}

function connector_engine_run(mysqli $dbcnx, array $connector): array
{
    $scenarioJson = (string)($connector['scenario_json'] ?? '');
    $parsed = connector_engine_parse_scenario($scenarioJson);
    if (!$parsed['ok']) {
        return [
            'ok' => false,
            'message' => $parsed['message'],
        ];
    }

    $scenario = $parsed['scenario'];
    $manualConfirm = $scenario['manual_confirm'] ?? [];
    $manualRequired = (bool)($manualConfirm['required'] ?? false);
    $manualInstruction = (string)($manualConfirm['instruction'] ?? 'Требуется ручное подтверждение');
    $skipLogin = (bool)($manualConfirm['skip_login'] ?? false);
    $authCookies = (string)($connector['auth_cookies'] ?? '');
    $authToken = (string)($connector['auth_token'] ?? '');
    $sslIgnore = (bool)($connector['ssl_ignore'] ?? false);
    $cookieFile = tempnam(sys_get_temp_dir(), 'connector_cookie_');

    if ($manualRequired && $authCookies === '' && $authToken === '') {
        @unlink($cookieFile);
        return [
            'ok' => false,
            'message' => $manualInstruction,
        ];
    }

    $loginResponse = ['ok' => true, 'body' => ''];
    $login = $scenario['login'] ?? [];
    $loginUrl = $skipLogin ? '' : connector_engine_resolve_url((string)($login['url'] ?? ''), $connector);
    if ($loginUrl !== '') {
        $method = (string)($login['method'] ?? 'POST');
        $fields = connector_engine_fill_fields((array)($login['fields'] ?? []), $connector);
        $loginResponse = connector_engine_request($loginUrl, $method, $fields, $cookieFile, $authCookies, $sslIgnore);
        if (!$loginResponse['ok']) {
            @unlink($cookieFile);
            return [
                'ok' => false,
                'message' => 'Ошибка запроса: ' . $loginResponse['message'],
            ];
        }
    }

    $steps = $scenario['steps'] ?? [];
    if (is_array($steps) && count($steps) > 0) {
        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $stepUrl = connector_engine_resolve_url((string)($step['url'] ?? ''), $connector);
            $stepMethod = (string)($step['method'] ?? 'GET');
            $stepFields = connector_engine_fill_fields((array)($step['fields'] ?? []), $connector);
            $stepResponse = connector_engine_request($stepUrl, $stepMethod, $stepFields, $cookieFile, $authCookies, $sslIgnore);
            if (!$stepResponse['ok']) {
                @unlink($cookieFile);
                return [
                    'ok' => false,
                    'message' => 'Шаг ' . ($index + 1) . ': ' . $stepResponse['message'],
                ];
            }

            $expect = $step['expect'] ?? [];
            if (is_array($expect) && count($expect) > 0) {
                $check = connector_engine_expect_match($stepResponse, $expect);
                if (!$check['ok']) {
                    @unlink($cookieFile);
                    return [
                        'ok' => false,
                        'message' => 'Шаг ' . ($index + 1) . ': ' . $check['message'],
                    ];
                }
            }

            $success = $step['success'] ?? [];
            if (is_array($success) && count($success) > 0) {
                $successSelector = (string)($success['selector'] ?? '');
                $successText = (string)($success['text'] ?? '');
                $match = connector_engine_match_success($stepResponse['body'], $successSelector, $successText);
                if (!$match['ok']) {
                    @unlink($cookieFile);
                    return [
                        'ok' => false,
                        'message' => 'Шаг ' . ($index + 1) . ': ' . $match['message'],
                    ];
                }
            }
        }

        @unlink($cookieFile);
        return [
            'ok' => true,
            'message' => 'Все шаги выполнены успешно',
        ];
    }

    $success = $scenario['success'] ?? [];
    $successUrl = connector_engine_resolve_url((string)($success['url'] ?? ''), $connector);
    $successSelector = (string)($success['selector'] ?? '');
    $successText = (string)($success['text'] ?? '');
    $successBody = $loginResponse['body'];

    if ($successUrl !== '') {
        $successResponse = connector_engine_request($successUrl, 'GET', [], $cookieFile, $authCookies, $sslIgnore);
        if (!$successResponse['ok']) {
            @unlink($cookieFile);
            return [
                'ok' => false,
                'message' => 'Ошибка запроса страницы успеха: ' . $successResponse['message'],
            ];
        }
        $successBody = $successResponse['body'];
    }

    @unlink($cookieFile);

    $match = connector_engine_match_success($successBody, $successSelector, $successText);
    return [
        'ok' => $match['ok'],
        'message' => $match['message'],
    ];
}

function connector_engine_run_by_id(mysqli $dbcnx, int $connectorId, int $userId = 0): array
{
    $sql = "SELECT
                id,
                name,
                base_url,
                auth_type,
                auth_username,
                auth_password,
                api_token,
                auth_token,
                auth_token_expires_at,
                auth_cookies,
                ssl_ignore,
                scenario_json
            FROM connectors
            WHERE id = ?
            LIMIT 1";
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return [
            'ok' => false,
            'message' => 'DB error (fetch connector)',
        ];
    }
    $stmt->bind_param('i', $connectorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $connector = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$connector) {
        return [
            'ok' => false,
            'message' => 'Коннектор не найден',
        ];
    }

    $result = connector_engine_run($dbcnx, $connector);
    connector_engine_update_status($dbcnx, $connectorId, $result['ok'], $result['message'], $userId);
    return $result + ['connector_id' => $connectorId];
}

function connector_engine_run_all(mysqli $dbcnx): array
{
    $results = [];
    $sql = "SELECT id FROM connectors ORDER BY id DESC";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $connectorId = (int)$row['id'];
            $results[] = connector_engine_run_by_id($dbcnx, $connectorId);
        }
        $res->free();
    }
    return $results;
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../../configs/secure.php';

    $options = getopt('', ['id::', 'all']);
    $id = isset($options['id']) ? (int)$options['id'] : 0;
    $runAll = isset($options['all']);

    if (!$runAll && $id <= 0) {
        fwrite(STDERR, "Usage: php connector_engine.php --id=123 | --all\n");
        exit(1);
    }

    if ($runAll) {
        $results = connector_engine_run_all($dbcnx);
        echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    $result = connector_engine_run_by_id($dbcnx, $id);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($result['ok'] ? 0 : 2);
}
