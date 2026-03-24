<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Forwarder/bootstrap.php';

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'forwarder_scan_test') {
    $response = [
        'status' => 'error',
        'message' => 'Unsupported forwarder action',
    ];
    return;
}

$track = trim((string)($_POST['track'] ?? $_GET['track'] ?? ''));
$container = trim((string)($_POST['container'] ?? $_GET['container'] ?? ''));

if ($track === '' || $container === '') {
    $response = [
        'status' => 'error',
        'message' => 'track and container are required',
        'business_status' => 'VALIDATION_ERROR',
    ];
    return;
}

$correlationId = bin2hex(random_bytes(8));
$config = new ForwarderConfig();

if (!$config->isConfigured()) {
    $response = [
        'status' => 'ok',
        'business_status' => 'TEMP_ERROR',
        'message' => 'Forwarder DEV_COLIBRI is not configured. Set DEV_COLIBRI_BASE_URL / DEV_COLIBRI_LOGIN / DEV_COLIBRI_PASSWORD.',
        'data' => [
            'track' => $track,
            'container' => $container,
            'label' => [
                'track' => $track,
                'container' => $container,
            ],
        ],
    ];
    return;
}

$logger = new ForwarderLogger($correlationId);
$session = new SessionManager();
$httpClient = new ForwarderHttpClient($config);
$loginService = new LoginService($config, $httpClient, $session, $logger);
$containerService = new ContainerService($config, $httpClient, $session);

$auth = $loginService->ensureAuthenticated()->toArray();
if (!$auth['ok']) {
    $response = [
        'status' => 'ok',
        'business_status' => 'SESSION_EXPIRED',
        'message' => 'Forwarder authentication failed',
        'correlation_id' => $correlationId,
        'steps' => [
            'auth' => $auth,
        ],
    ];
    return;
}

$position = $containerService->checkPosition($container)->toArray();
$package = $containerService->checkPackage($track, $container)->toArray();

$businessStatus = 'ACCEPTED';
$message = 'Track accepted for label printing';

if (!$position['ok']) {
    $businessStatus = 'TEMP_ERROR';
    $message = 'Position check failed';
} elseif (!$package['ok']) {
    $businessStatus = 'NOT_DECLARED';
    $message = 'Package is not declared in Forwarder';
}

$response = [
    'status' => 'ok',
    'business_status' => $businessStatus,
    'message' => $message,
    'correlation_id' => $correlationId,
    'data' => [
        'track' => $track,
        'container' => $container,
        'label' => [
            'track' => $track,
            'container' => $container,
            'position' => $position['payload']['raw'] ?? null,
        ],
    ],
    'steps' => [
        'auth' => $auth,
        'check_position' => $position,
        'check_package' => $package,
    ],
];
