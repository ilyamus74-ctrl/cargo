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

function connector_engine_request(
    string $url,
    string $method,
    array $fields,
    string $cookieFile
): array {
    $ch = curl_init();
    $method = strtoupper($method);

    if ($method === 'GET' && !empty($fields)) {
        $query = http_build_query($fields);
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'CargoConnector/1.0',
    ]);

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

function connector_engine_update_status(mysqli $dbcnx, int $connectorId, bool $ok, string $message): void
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
    $login = $scenario['login'] ?? [];
    $loginUrl = (string)($login['url'] ?? '');
    if ($loginUrl === '') {
        return [
            'ok' => false,
            'message' => 'Не указан login.url',
        ];
    }

    $method = (string)($login['method'] ?? 'POST');
    $fields = connector_engine_fill_fields((array)($login['fields'] ?? []), $connector);
    $cookieFile = tempnam(sys_get_temp_dir(), 'connector_cookie_');

    $loginResponse = connector_engine_request($loginUrl, $method, $fields, $cookieFile);
    if (!$loginResponse['ok']) {
        @unlink($cookieFile);
        return [
            'ok' => false,
            'message' => 'Ошибка запроса: ' . $loginResponse['message'],
        ];
    }

    $success = $scenario['success'] ?? [];
    $successUrl = (string)($success['url'] ?? '');
    $successSelector = (string)($success['selector'] ?? '');
    $successText = (string)($success['text'] ?? '');
    $successBody = $loginResponse['body'];

    if ($successUrl !== '') {
        $successResponse = connector_engine_request($successUrl, 'GET', [], $cookieFile);
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

function connector_engine_run_by_id(mysqli $dbcnx, int $connectorId): array
{
    $sql = "SELECT
                id,
                name,
                base_url,
                auth_type,
                auth_username,
                auth_password,
                api_token,
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
    connector_engine_update_status($dbcnx, $connectorId, $result['ok'], $result['message']);
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
    require_once __DIR__ . '/../../../configs/secure.php';

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
