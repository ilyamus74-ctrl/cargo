<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\DTO\StepResult;
use App\Forwarder\Http\ForwarderSessionClient;

final class ContainerService
{
    public function __construct(
        private ForwarderConfig $config,
        private ForwarderSessionClient $sessionClient
    ) {
    }

    public function checkPosition(string $container): StepResult
    {
        $path = (string)($this->config->endpoint('check_position')['path'] ?? '/api/check-position');
        $response = $this->sessionClient->postJson($path, ['container' => $container]);

        $ok = (bool)$response['ok'];

        return new StepResult(
            $ok,
            (int)$response['status_code'],
            $ok ? 'POSITION_OK' : 'POSITION_ERROR',
            ['raw' => $response['json'] ?? $response['body']],
            (int)$response['latency_ms']
        );
    }

    public function checkPackage(string $track, string $container): StepResult
    {
        $path = (string)($this->config->endpoint('check_package')['path'] ?? '/api/check-package');
        $response = $this->sessionClient->postJson($path, ['track' => $track, 'container' => $container]);

        $ok = (bool)$response['ok'];

        return new StepResult(
            $ok,
            (int)$response['status_code'],
            $ok ? 'PACKAGE_OK' : 'PACKAGE_NOT_DECLARED',
            ['raw' => $response['json'] ?? $response['body']],
            (int)$response['latency_ms']
        );
    }
}
