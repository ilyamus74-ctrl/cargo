<?php

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Orchestrator\ForwarderWorkflow;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/**
 * Production entrypoint for Forwarder workflow.
 *
 * Usage:
 *   php run_report.php --track=AA123 --container=CNTR001
 *   php run_report.php AA123 CNTR001
 *   php run_report.php   (auth preflight mode)
 */

function forwarder_read_arg(string $name, array $argv): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            $value = substr($arg, strlen($name) + 3);
            return $value !== '' ? $value : null;
        }
    }

    return null;
}

$argv = $_SERVER['argv'] ?? [];
$track = forwarder_read_arg('track', $argv) ?? ($argv[1] ?? null) ?? getenv('FORWARDER_TRACK') ?: null;
$container = forwarder_read_arg('container', $argv) ?? ($argv[2] ?? null) ?? getenv('FORWARDER_CONTAINER') ?: null;

$track = trim((string)$track);
$container = trim((string)$container);
$hasScanArgs = $track !== '' && $container !== '';
$result = [];

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    if ($hasScanArgs) {
        fwrite(STDERR, "Forwarder run_report: missing env config (FORWARDER_BASE_URL/FORWARDER_LOGIN/FORWARDER_PASSWORD)\n");
        exit(3);
    }
    $result = [
        'status' => 'AUTH_SKIPPED',
        'message' => 'Forwarder auth preflight skipped: missing env config (FORWARDER_BASE_URL/FORWARDER_LOGIN/FORWARDER_PASSWORD)',
        'mode' => 'auth_preflight',
        'track' => null,
        'container' => null,
    ];
}
if ($result === [] && !$config->isFlowEnabled()) {
    fwrite(STDERR, "Forwarder run_report: flow disabled by FORWARDER_FLOW_ENABLED\n");
    exit(4);
}

$correlationId = 'run-report-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$result['correlation_id'] = $result['correlation_id'] ?? $correlationId;

if ($result === []) {
    $httpClient = new ForwarderHttpClient($config);
    $session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
    $loginService = new LoginService($config, $httpClient, $session, $logger);
    if ($hasScanArgs) {
        $sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);
        $containerService = new ContainerService($config, $sessionClient);
        $workflow = new ForwarderWorkflow($loginService, $containerService, $logger);
        $result = $workflow->runScan($track, $container, $correlationId);
    } else {
        $auth = $loginService->ensureAuthenticated()->toArray();
        $result = [
            'status' => !empty($auth['ok']) ? 'AUTH_OK' : 'SESSION_EXPIRED',
            'message' => !empty($auth['ok'])
                ? 'Forwarder auth preflight passed (track/container not provided)'
                : 'Forwarder auth preflight failed',
            'mode' => 'auth_preflight',
            'track' => null,
            'container' => null,
            'correlation_id' => $correlationId,
        ];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

$status = strtoupper(trim((string)($result['status'] ?? 'TEMP_ERROR')));
$okStatuses = ['ACCEPTED', 'NOT_DECLARED', 'AUTH_OK', 'AUTH_SKIPPED'];
exit(in_array($status, $okStatuses, true) ? 0 : 1);