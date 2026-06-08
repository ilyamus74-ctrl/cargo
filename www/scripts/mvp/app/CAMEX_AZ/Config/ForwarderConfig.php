<?php

declare(strict_types=1);

namespace App\Forwarder\Config;

final class ForwarderConfig
{
    /** @var mixed[] */
    private array $map;

    /** @var array<string, mixed> */
    private array $overrides;

    /** @param array<string, mixed> $overrides */
    public function __construct(array $overrides)
    {
        /** @var mixed[] $map */
        $map = require __DIR__ . '/endpoints.php';
        $this->map = $map;

        $this->overrides = $overrides;
    }

    private function firstValue(string $key, array $envNames, string $default = ''): string
    {
        if (array_key_exists($key, $this->overrides)) {
            return (string)$this->overrides[$key];
        }

        foreach ($envNames as $envName) {
            $raw = getenv((string)$envName);
            if ($raw !== false && (string)$raw !== '') {
                return (string)$raw;
            }
        }

        return $default;
    }

    private function boolValue(string $key, array $envNames, bool $default = false): bool
    {
        $raw = $this->firstValue($key, $envNames, $default ? '1' : '0');
        return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }

    public function baseUrl(): string
    {
        $baseUrl = $this->firstValue('base_url', ['DEV_COLIBRI_BASE_URL', 'FORWARDER_BASE_URL']);
        return rtrim($baseUrl, '/');
    }

    public function username(): string
    {
        return $this->webLogin();
    }

    public function password(): string
    {
        return $this->webPassword();
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
        return $this->firstValue('login_path', ['CAMEX_AZ_LOGIN_PATH', 'FORWARDER_LOGIN_PATH'], (string)($this->endpoint('login_get')['path'] ?? '/login'));
    }

    public function loginUrl(): string
    {
        return $this->baseUrl() . $this->loginPath();
    }

    public function loginPostPath(): string
    {
        return $this->firstValue('login_post_path', ['CAMEX_AZ_LOGIN_POST_PATH', 'FORWARDER_LOGIN_POST_PATH'], (string)($this->endpoint('login_post')['path'] ?? $this->loginPath()));
    }

    public function dashboardPath(): string
    {
        return $this->firstValue('dashboard_path', ['CAMEX_AZ_DASHBOARD_PATH', 'FORWARDER_DASHBOARD_PATH'], '/');
    }

    public function webLogin(): string
    {
        return $this->firstValue('web_login', ['CAMEX_AZ_WEB_LOGIN', 'FORWARDER_WEB_LOGIN', 'DEV_COLIBRI_LOGIN', 'FORWARDER_LOGIN']);
    }

    public function webPassword(): string
    {
        return $this->firstValue('web_password', ['CAMEX_AZ_WEB_PASSWORD', 'FORWARDER_WEB_PASSWORD', 'DEV_COLIBRI_PASSWORD', 'FORWARDER_PASSWORD']);
    }

    public function httpAuthEnabled(): bool
    {
        if (array_key_exists('http_auth_enabled', $this->overrides)) {
            return filter_var($this->overrides['http_auth_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        $raw = $this->firstValue('http_auth_enabled', ['CAMEX_AZ_HTTP_AUTH_ENABLED', 'FORWARDER_HTTP_AUTH_ENABLED'], '');
        if ($raw !== '') {
            return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
        }

        if ($this->httpAuthType() === 'none') {
            return false;
        }

        return $this->httpAuthLogin() !== '' || $this->httpAuthPassword() !== '';
    }

    public function httpAuthType(): string
    {
        $type = strtolower(trim($this->firstValue('http_auth_type', ['CAMEX_AZ_HTTP_AUTH_TYPE', 'FORWARDER_HTTP_AUTH_TYPE'], 'none')));
        return in_array($type, ['basic', 'digest', 'none'], true) ? $type : 'basic';
    }

    public function httpAuthLogin(): string
    {
        return $this->firstValue('http_auth_login', ['CAMEX_AZ_HTTP_AUTH_LOGIN', 'FORWARDER_HTTP_AUTH_LOGIN']);
    }

    public function httpAuthPassword(): string
    {
        return $this->firstValue('http_auth_password', ['CAMEX_AZ_HTTP_AUTH_PASSWORD', 'FORWARDER_HTTP_AUTH_PASSWORD']);
    }

    public function timeoutSeconds(): int
    {
        return max(1, (int)ceil($this->timeoutTotalMs() / 1000));
    }


    /** @return mixed[] */
    public function endpoint(string $name): array
    {
        return $this->map['endpoints'][$name] ?? [];
    }

    public function timeoutConnectMs(): int
    {
        return (int)$this->firstValue('timeout_connect_ms', ['CAMEX_AZ_TIMEOUT_CONNECT_MS', 'FORWARDER_TIMEOUT_CONNECT_MS'], (string)($this->map['http']['timeout_connect_ms'] ?? 3000));
    }

    public function timeoutTotalMs(): int
    {
        if (array_key_exists('timeout', $this->overrides)) {
            return max(1, (int)$this->overrides['timeout']) * 1000;
        }

        return (int)$this->firstValue('timeout_total_ms', ['CAMEX_AZ_TIMEOUT_TOTAL_MS', 'FORWARDER_TIMEOUT_TOTAL_MS'], (string)($this->map['http']['timeout_total_ms'] ?? 10000));
    }

    public function retryCount(): int
    {
        return max(0, (int)($this->map['http']['retry_count'] ?? 1));
    }

    public function retryDelayMs(): int
    {
        return max(0, (int)($this->map['http']['retry_delay_ms'] ?? 250));
    }

    public function sslIgnore(): bool
    {
        return $this->boolValue('insecure', ['DEV_COLIBRI_SSL_IGNORE', 'FORWARDER_SSL_IGNORE'], false);
    }
    public function sessionCookieFile(): string
    {
        $path = $this->firstValue('session_file', ['CAMEX_AZ_SESSION_FILE', 'FORWARDER_SESSION_FILE'], '/tmp/forwarder_session.json');
        return $path !== '' ? $path : '/tmp/forwarder_session.json';
    }

    public function sessionTtlSeconds(): int
    {
        return max(60, (int)$this->firstValue('session_ttl_seconds', ['CAMEX_AZ_SESSION_TTL_SECONDS', 'FORWARDER_SESSION_TTL_SECONDS'], '1500'));
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
