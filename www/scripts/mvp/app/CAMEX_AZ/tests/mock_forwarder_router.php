<?php

declare(strict_types=1);

$stateFile = getenv('FORWARDER_MOCK_STATE_FILE') ?: sys_get_temp_dir() . '/forwarder_mock_state.json';

$state = [
    'login_get' => 0,
    'login_post' => 0,
    'check_position' => 0,
    'check_package' => 0,
    'force_expired_once' => true,
];

if (is_file($stateFile)) {
    $decoded = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $state = array_merge($state, $decoded);
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$writeState = static function () use (&$state, $stateFile): void {
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE));
};

if ($uri === '/login' && $method === 'GET') {
    $state['login_get']++;
    $writeState();

    header('Set-Cookie: XSRF-TOKEN=mock-xsrf-token; Path=/');
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><meta name="csrf-token" content="mock-csrf-token"></head><body>Login page</body></html>';
    return;
}

if ($uri === '/login' && $method === 'POST') {
    $state['login_post']++;
    $writeState();

    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $token = (string)($_POST['_token'] ?? '');

    if ($username !== 'demo-user' || $password !== 'demo-pass' || $token === '') {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unauthorized';
        return;
    }

    header('Set-Cookie: laravel_session=mock-session; Path=/');
    header('Content-Type: text/html; charset=utf-8');
    echo 'Home page';
    return;
}

if ($uri === '/api/check-position' && $method === 'POST') {
    $state['check_position']++;
    $attempt = (int)$state['check_position'];
    $writeState();

    if ($attempt === 1) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['case' => 'TEMP_ERROR', 'message' => 'mock transient failure']);
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['case' => 'POSITION_OK', 'container' => '24369']);
    return;
}

if ($uri === '/api/check-package' && $method === 'POST') {
    $state['check_package']++;
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $cookie = (string)($headers['cookie'] ?? '');
    $hasSession = strpos($cookie, 'laravel_session=mock-session') !== false;
    $attempt = (int)$state['check_package'];

    if (!$hasSession || ($attempt === 1 && !empty($state['force_expired_once']))) {
        $state['force_expired_once'] = false;
        $writeState();
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['case' => 'SESSION_EXPIRED']);
        return;
    }

    $writeState();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'case' => 'PACKAGE_OK',
        'internal_id' => 'CBR-TEST-001',
        'weight' => '0.700',
        'client_name' => 'Smoke Client',
        'request_id' => 'REQ-MOCK-123',
    ]);
    return;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
