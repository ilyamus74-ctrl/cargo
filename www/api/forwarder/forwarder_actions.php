<?php

declare(strict_types=1);

require_once __DIR__ . '/../../scripts/mvp/app/Forwarder/bootstrap.php';

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Orchestrator\ForwarderWorkflow;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;


$action = $_POST['action'] ?? $_GET['action'] ?? 'forwarder_scan';
if ($action !== 'forwarder_scan_test' && $action !== 'forwarder_scan') {
    $response = [
        'status' => 'error',
        'message' => 'Unsupported forwarder action',
    ];
    return;
}

$track = trim((string)($_POST['track'] ?? $_GET['track'] ?? ''));
$container = trim((string)($_POST['container'] ?? $_GET['container'] ?? ''));

if ($track === '' || $container === '') {
    $response = forwarder_compact_response('INVALID_TRACK', $track, $container);
    return;
}

$config = new ForwarderConfig();
$correlationId = bin2hex(random_bytes(8));

if (!$config->isConfigured()) {
    $response = forwarder_compact_response('TEMP_ERROR', $track, $container);
    return;
}


$cached = forwarder_read_idempotent($config, $track, $container);
if ($cached !== null) {
    $cached['idempotent_replay'] = true;
    $response = $cached;
    return;
}

$logger = new ForwarderLogger($correlationId);
$session = new SessionManager();
$httpClient = new ForwarderHttpClient($config);
$loginService = new LoginService($config, $httpClient, $session, $logger);


$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);
$containerService = new ContainerService($config, $sessionClient);

$workflow = new ForwarderWorkflow($loginService, $containerService, $logger);
$response = $workflow->runScan($track, $container, $correlationId);
forwarder_store_idempotent($config, $track, $container, $response);

/** @return array<string, mixed>|null */
function forwarder_read_idempotent(ForwarderConfig $config, string $track, string $container): ?array
{
    $path = $config->idempotencyFile();
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $ttl = $config->idempotencyTtlSeconds();
    $now = time();
    $key = forwarder_idempotency_key($track, $container);
    $updated = false;

    foreach ($decoded as $entryKey => $entry) {
        if (!is_array($entry)) {
            unset($decoded[$entryKey]);
            $updated = true;
            continue;
        }

        $createdAt = (int)($entry['created_at'] ?? 0);
        if ($createdAt <= 0 || ($createdAt + $ttl) < $now) {
            unset($decoded[$entryKey]);
            $updated = true;
        }
    }

    if ($updated) {
        @file_put_contents($path, json_encode($decoded, JSON_UNESCAPED_UNICODE));
    }

    if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
        return null;
    }

    $payload = $decoded[$key]['payload'] ?? null;
    return is_array($payload) ? $payload : null;
}

/** @param array<string, mixed> $payload */
function forwarder_store_idempotent(ForwarderConfig $config, string $track, string $container, array $payload): void
{
    $path = $config->idempotencyFile();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $raw = is_file($path) ? (string)@file_get_contents($path) : '';
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $decoded[forwarder_idempotency_key($track, $container)] = [
        'created_at' => time(),
        'payload' => $payload,
    ];

    @file_put_contents($path, json_encode($decoded, JSON_UNESCAPED_UNICODE));
}

function forwarder_idempotency_key(string $track, string $container): string
{
    return hash('sha256', trim($track) . '|' . trim($container));
}

/** @return array<string, mixed> */
function forwarder_compact_response(string $status, string $track, string $container): array
{
    return [
        'status' => $status,
        'track' => $track,
        'internal_id' => null,
        'weight' => null,
        'client_name' => null,
        'label_payload' => [
            'track' => $track,
            'container' => $container,
            'internal_id' => null,
            'weight' => null,
            'client_name' => null,
        ],
    ];
}
