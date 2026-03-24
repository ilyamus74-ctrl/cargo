<?php

declare(strict_types=1);

namespace App\Forwarder\DTO;

final class StepResult
{
    /** @var mixed[] */
    private array $payload;

    /** @param mixed[] $payload */
    public function __construct(
        private bool $ok,
        private int $statusCode,
        private string $businessCode,
        array $payload,
        private int $latencyMs
    ) {
        $this->payload = $payload;
    }

    /** @return mixed[] */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'status_code' => $this->statusCode,
            'business_code' => $this->businessCode,
            'payload' => $this->payload,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
