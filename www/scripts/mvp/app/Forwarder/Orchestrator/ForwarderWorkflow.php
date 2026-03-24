<?php

declare(strict_types=1);

namespace App\Forwarder\Orchestrator;

use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\ContainerService;
use App\Forwarder\Services\LoginService;

final class ForwarderWorkflow
{
    public function __construct(
        private LoginService $loginService,
        private ContainerService $containerService,
        private ForwarderLogger $logger
    ) {
    }

    /** @return array<string, mixed> */
    public function runScan(string $track, string $container, string $correlationId): array
    {
        $auth = $this->loginService->ensureAuthenticated()->toArray();
        if (!$auth['ok']) {
            return [
                'status' => 'ok',
                'business_status' => 'SESSION_EXPIRED',
                'message' => 'Forwarder authentication failed',
                'correlation_id' => $correlationId,
                'steps' => [
                    'auth' => $auth,
                ],
            ];
        }

        $position = $this->containerService->checkPosition($container)->toArray();
        $package = $this->containerService->checkPackage($track, $container)->toArray();

        $businessStatus = 'ACCEPTED';
        $message = 'Track accepted for label printing';

        if (!$position['ok']) {
            $businessStatus = 'TEMP_ERROR';
            $message = 'Position check failed';
        } elseif (!$package['ok']) {
            $businessStatus = 'NOT_DECLARED';
            $message = 'Package is not declared in Forwarder';
        }

        $this->logger->info('Forwarder workflow completed', [
            'business_status' => $businessStatus,
            'track' => $track,
            'container' => $container,
        ]);

        return [
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
    }
}
