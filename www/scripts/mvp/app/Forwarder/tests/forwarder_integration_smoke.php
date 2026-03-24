<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Orchestrator\ForwarderWorkflow;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/../bootstrap.php';

$host = '127.0.0.1';
$port = 18081;
$baseUrl = 'http://' . $host . ':' . $port;
$stateFile = sys_get_temp_dir() . '/forwarder_mock_state_' . getmypid() . '.json';
$sessionFile = sys_get_temp_dir() . '/forwarder_session_' . getmypid() . '.json';

@unlink($stateFile);
@unlink($sessionFile);

putenv('FORWARDER_MOCK_STATE_FILE=' . $stateFile);
putenv('DEV_COLIBRI_BASE_URL=' . $baseUrl);
putenv('DEV_COLIBRI_LOGIN=demo-user');
putenv('DEV_COLIBRI_PASSWORD=demo-pass');
putenv('FORWARDER_SESSION_FILE=' . $sessionFile);
putenv('FORWARDER_SESSION_TTL_SECONDS=120');
putenv('FORWARDER_RETRY_COUNT=1');
putenv('FORWARDER_RETRY_DELAY_MS=10');

$router = __DIR__ . '/mock_forwarder_router.php';
$cmd = sprintf('php -S %s:%d %s', $host, $port, escapeshellarg($router));
$descriptor = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptor, $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start mock forwarder server\n");
    exit(1);
}

usleep(400000);

try {
    $config = new ForwarderConfig();
    $logger = new ForwarderLogger('itest-' . bin2hex(random_bytes(4)));
    $session = new SessionManager();
    $httpClient = new ForwarderHttpClient($config);
    $loginService = new LoginService($config, $httpClient, $session, $logger);
    $sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);
    $containerService = new ContainerService($config, $sessionClient);
    $workflow = new ForwarderWorkflow($loginService, $containerService, $logger);

    $result = $workflow->runScan('TRK123456', '24369', 'itest-correlation-1');
    $status = (string)($result['status'] ?? '');
    if ($status !== 'ACCEPTED') {
        throw new RuntimeException('Expected ACCEPTED status, got: ' . $status);
    }

    if (($result['internal_id'] ?? null) !== 'CBR-TEST-001') {
        throw new RuntimeException('Unexpected internal_id in result');
    }

    if (($result['client_name'] ?? null) !== 'Smoke Client') {
        throw new RuntimeException('Unexpected client_name in result');
    }

    $stateRaw = (string)@file_get_contents($stateFile);
    $state = json_decode($stateRaw, true);
    if (!is_array($state)) {
        throw new RuntimeException('Mock state file missing/invalid');
    }

    if ((int)($state['check_position'] ?? 0) < 2) {
        throw new RuntimeException('Expected retry on technical error for check-position');
    }

    if ((int)($state['login_post'] ?? 0) < 2) {
        throw new RuntimeException('Expected relogin on session expiration response');
    }

    echo "Forwarder integration smoke test passed\n";
    echo json_encode([
        'result_status' => $result['status'] ?? null,
        'check_position_calls' => $state['check_position'] ?? null,
        'login_post_calls' => $state['login_post'] ?? null,
        'check_package_calls' => $state['check_package'] ?? null,
    ], JSON_UNESCAPED_UNICODE) . "\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    @unlink($sessionFile);
    @unlink($stateFile);
}
