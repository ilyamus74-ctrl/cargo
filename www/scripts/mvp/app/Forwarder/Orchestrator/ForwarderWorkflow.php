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
        $startedAt = microtime(true);

        $auth = $this->loginService->ensureAuthenticated()->toArray();
        if (!$auth['ok']) {
            return $this->finalizeResult(
                $this->buildResult('SESSION_EXPIRED', $track, $container),
                $track,
                $container,
                $correlationId,
                $startedAt,
                null
            );
        }

        $position = $this->containerService->checkPosition($container)->toArray();
        if (!$position['ok']) {
            return $this->finalizeResult(
                $this->buildResult('TEMP_ERROR', $track, $container),
                $track,
                $container,
                $correlationId,
                $startedAt,
                null
            );
        }


        $package = $this->containerService->checkPackage($track, $container)->toArray();
        if (!$package['ok']) {

            return $this->finalizeResult(
                $this->buildResult('NOT_DECLARED', $track, $container),
                $track,
                $container,
                $correlationId,
                $startedAt,
                null
            );
        }
        $packagePayload = $package['payload']['raw'] ?? [];
        $internalId = $this->pickField($packagePayload, ['internal_id', 'internalId', 'id', 'cargo_number', 'cargono']);
        $weight = $this->pickField($packagePayload, ['weight', 'actual_weight', 'gross_weight']);
        $clientName = $this->pickField($packagePayload, ['client_name', 'consignee', 'customer_name', 'name']);

        $forwarderRequestId = $this->pickField($packagePayload, ['request_id', 'correlation_id', 'trace_id']);

        return $this->finalizeResult(
            $this->buildResult('ACCEPTED', $track, $container, $internalId, $weight, $clientName),
            $track,
            $container,
            $correlationId,
            $startedAt,
            $forwarderRequestId
        );
    }

    /** @return array<string, mixed> */
    private function finalizeResult(
        array $result,
        string $track,
        string $container,
        string $correlationId,
        float $startedAt,
        ?string $forwarderRequestId
    ): array {
        $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);


        $result['correlation_id'] = $correlationId;
        $result['elapsed_ms'] = $elapsedMs;

        $this->logger->info('Forwarder workflow completed', [
            'track' => $track,
            'container' => $container,
            'final_status' => $result['status'] ?? 'TEMP_ERROR',
            'correlation_id' => $correlationId,
            'forwarder_request_id' => $forwarderRequestId,
            'elapsed_ms' => $elapsedMs,
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    private function buildResult(
        string $status,
        string $track,
        string $container,
        ?string $internalId = null,
        ?string $weight = null,
        ?string $clientName = null
    ): array {
        return [
            'status' => $status,
            'track' => $track,
            'internal_id' => $internalId,
            'weight' => $weight,
            'client_name' => $clientName,
            'label_payload' => [
                'track' => $track,
                'container' => $container,
                'internal_id' => $internalId,
                'weight' => $weight,
                'client_name' => $clientName,
            ],
        ];
    }

    /** @param mixed $payload */
    private function pickField(mixed $payload, array $keys): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return (string)$payload[$key];
            }
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            foreach ($keys as $key) {
                if (array_key_exists($key, $value) && $value[$key] !== null && $value[$key] !== '') {
                    return (string)$value[$key];
                }
            }
        }

        return null;
    }
}
