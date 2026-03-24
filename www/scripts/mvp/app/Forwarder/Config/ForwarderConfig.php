<?php

declare(strict_types=1);

namespace App\Forwarder\Config;

final class ForwarderConfig
{
    /** @var mixed[] */
    private array $map;

    public function __construct()
    {
        /** @var mixed[] $map */
        $map = require __DIR__ . '/endpoints.php';
        $this->map = $map;
    }

    public function baseUrl(): string
    {
        $baseUrl = (string)(getenv('DEV_COLIBRI_BASE_URL') ?: getenv('FORWARDER_BASE_URL') ?: '');
        return rtrim($baseUrl, '/');
    }

    public function username(): string
    {
        return (string)(getenv('DEV_COLIBRI_LOGIN') ?: getenv('FORWARDER_LOGIN') ?: '');
    }

    public function password(): string
    {
        return (string)(getenv('DEV_COLIBRI_PASSWORD') ?: getenv('FORWARDER_PASSWORD') ?: '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->username() !== '' && $this->password() !== '';
    }

    public function isFlowEnabled(): bool
    {
        $raw = strtolower(trim((string)(getenv('FORWARDER_FLOW_ENABLED') ?: '1')));
        return !in_array($raw, ['0', 'false', 'off', 'no'], true);
    }

    public function loginPath(): string
    {
        return (string)($this->endpoint('login_get')['path'] ?? '/login');
    }

    public function loginUrl(): string
    {
        return $this->baseUrl() . $this->loginPath();
    }

    /** @return mixed[] */
    public function endpoint(string $name): array
    {
        return $this->map['endpoints'][$name] ?? [];
    }

    public function timeoutConnectMs(): int
    {
        return (int)($this->map['http']['timeout_connect_ms'] ?? 3000);
    }

    public function timeoutTotalMs(): int
    {
        return (int)($this->map['http']['timeout_total_ms'] ?? 10000);
    }

    public function retryCount(): int
    {
        return max(0, (int)($this->map['http']['retry_count'] ?? 1));
    }

    public function retryDelayMs(): int
    {
        return max(0, (int)($this->map['http']['retry_delay_ms'] ?? 250));
    }

    public function sessionCookieFile(): string
    {
        $path = (string)(getenv('FORWARDER_SESSION_FILE') ?: '/tmp/forwarder_session.json');
        return $path !== '' ? $path : '/tmp/forwarder_session.json';
    }

    public function sessionTtlSeconds(): int
    {
        return max(60, (int)(getenv('FORWARDER_SESSION_TTL_SECONDS') ?: 1500));
    }

    public function idempotencyFile(): string
    {
        $path = (string)(getenv('FORWARDER_IDEMPOTENCY_FILE') ?: '/tmp/forwarder_idempotency.json');
        return $path !== '' ? $path : '/tmp/forwarder_idempotency.json';
    }

    public function idempotencyTtlSeconds(): int
    {
        return max(60, (int)(getenv('FORWARDER_IDEMPOTENCY_TTL_SECONDS') ?: 900));
    }
}
