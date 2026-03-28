<?php

declare(strict_types=1);

namespace App\Forwarder\Http;

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

final class ForwarderSessionClient
{
    public function __construct(
        private ForwarderConfig $config,
        private ForwarderHttpClient $httpClient,
        private SessionManager $session,
        private LoginService $loginService,
        private ForwarderLogger $logger
    ) {
    }

    /** @return array<string, mixed> */
    public function postJson(string $endpointPath, array $payload): array
    {
        return $this->requestWithSession('POST', $endpointPath, $payload, true);
    }

    /** @return array<string, mixed> */
    public function requestWithSession(string $method, string $endpointPath, array $payload = [], bool $asJson = true): array
    {
        $httpMethod = strtoupper(trim($method));
        if ($httpMethod === '') {
            $httpMethod = 'GET';
        }
        $authStep = $this->ensureSession();
        if (!$authStep['ok']) {
            return [
                'ok' => false,
                'status_code' => (int)($authStep['status_code'] ?? 401),
                'headers_raw' => '',
                'body' => '',
                'json' => null,
                'latency_ms' => (int)($authStep['latency_ms'] ?? 0),
                'error' => 'auth_failed',
            ];
        }

        $headers = $this->session->securityHeaders(true);

        $response = $this->httpClient->request(
            $httpMethod,
            $endpointPath,
            $payload,
            $headers,
            $asJson,
            $this->session->cookieHeader()
        );

        $this->session->updateFromHeaders((string)($response['headers_raw'] ?? ''));
        $this->persistSession();

        if (!$this->isSessionExpiredResponse($response)) {
            return $response;
        }

        $this->logger->info('Forwarder session expired, relogin requested', [
            'status_code' => (int)($response['status_code'] ?? 0),
        ]);

        $relogin = $this->loginService->login()->toArray();
        if (!$relogin['ok']) {
            return $response;
        }

        $headers = $this->session->securityHeaders(true);

        $retryResponse = $this->httpClient->request(
            $httpMethod,
            $endpointPath,
            $payload,
            $headers,
            $asJson,
            $this->session->cookieHeader()
        );

        $this->session->updateFromHeaders((string)($retryResponse['headers_raw'] ?? ''));
        $this->persistSession();

        return $retryResponse;
    }

    /** @return array<string, mixed> */
    public function ensureSession(): array
    {
        if ($this->session->isAuthenticated()) {
            return ['ok' => true, 'status_code' => 200, 'latency_ms' => 0];
        }

        $this->restorePersistedSession();
        if ($this->session->isAuthenticated()) {
            return ['ok' => true, 'status_code' => 200, 'latency_ms' => 0];
        }

        $login = $this->loginService->login()->toArray();
        if ($login['ok']) {
            $this->persistSession();
        }

        return $login;
    }

    /** @param array<string, mixed> $response */
    private function isSessionExpiredResponse(array $response): bool
    {
        $status = (int)($response['status_code'] ?? 0);
        if ($status === 401 || $status === 419) {
            return true;
        }

        if ($status !== 301 && $status !== 302 && $status !== 303 && $status !== 307 && $status !== 308) {
            return false;
        }

        $headersRaw = strtolower((string)($response['headers_raw'] ?? ''));
        return strpos($headersRaw, 'location:') !== false && strpos($headersRaw, '/login') !== false;
    }

    private function persistSession(): void
    {
        $filePath = $this->config->sessionCookieFile();
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'expires_at' => time() + $this->config->sessionTtlSeconds(),
            'session' => $this->session->exportState(),
        ];

        @file_put_contents($filePath, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function restorePersistedSession(): void
    {
        $filePath = $this->config->sessionCookieFile();
        if (!is_file($filePath)) {
            return;
        }

        $raw = @file_get_contents($filePath);
        if (!is_string($raw) || $raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $expiresAt = (int)($decoded['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt <= time()) {
            @unlink($filePath);
            return;
        }

        $sessionState = $decoded['session'] ?? null;
        if (!is_array($sessionState)) {
            return;
        }

        $this->session->importState($sessionState);
    }
}
