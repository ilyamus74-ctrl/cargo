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

    public function checkPackageSingle(string $track): StepResult
    {
        $path = (string)($this->config->endpoint('check_package_single')['path'] ?? '/collector/check-package');
        $this->primeCollectorCsrf();
        $response = $this->sessionClient->requestWithSession('POST', $path, $this->buildSingleCheckPayload($track), false);
        if ((int)($response['status_code'] ?? 0) === 419) {
            $this->primeCollectorCsrf();
            $response = $this->sessionClient->requestWithSession('POST', $path, $this->buildSingleCheckPayload($track), false);
        }

        $json = $response['json'];
        if (!is_array($json)) {
            $decoded = json_decode((string)($response['body'] ?? ''), true);
            $json = is_array($decoded) ? $decoded : null;
        }

        $isBusinessSuccess = is_array($json) && (($json['case'] ?? '') === 'success');
        $ok = (bool)$response['ok'] && $isBusinessSuccess;

        return new StepResult(
            $ok,
            (int)$response['status_code'],
            $ok ? 'PACKAGE_SINGLE_OK' : 'PACKAGE_SINGLE_ERROR',
            ['raw' => $json ?? $response['body']],
            (int)$response['latency_ms']
        );
    }

    /** @return array<string, string> */
    private function buildSingleCheckPayload(string $track): array
    {
        $payload = ['number' => $track];
        $csrf = $this->sessionClient->csrfToken();
        if ($csrf !== '') {
            $payload['_token'] = $csrf;
        }

        return $payload;
    }

    private function primeCollectorCsrf(): void
    {
        $collectorPath = (string)($this->config->endpoint('collector_page')['path'] ?? '/collector');
        $this->sessionClient->requestWithSession('GET', $collectorPath, [], false);
    }
}
