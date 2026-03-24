<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\DTO\StepResult;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;

final class LoginService
{
    public function __construct(
        private ForwarderConfig $config,
        private ForwarderHttpClient $httpClient,
        private SessionManager $session,
        private ForwarderLogger $logger
    ) {
    }

    public function ensureAuthenticated(): StepResult
    {
        if ($this->session->isAuthenticated()) {
            return new StepResult(true, 200, 'AUTH_ALREADY', [], 0);
        }

        return $this->login();
    }

    public function login(): StepResult
    {
        $started = microtime(true);

        $loginGetPath = $this->config->loginPath();
        $bootstrapResponse = $this->httpClient->get($loginGetPath);
        $this->session->updateFromHeaders((string)$bootstrapResponse['headers_raw']);
        $this->session->updateFromHtml((string)$bootstrapResponse['body']);

        $payload = [
            'username' => $this->config->username(),
            'password' => $this->config->password(),
            '_token' => $this->session->xsrfToken(),
        ];

        $loginPostPath = (string)($this->config->endpoint('login_post')['path'] ?? $this->config->loginPath());
        $headers = array_merge(
            $this->session->securityHeaders(true),
            [
                'Origin' => $this->config->baseUrl(),
                'Referer' => $this->config->loginUrl(),
            ]
        );
        $authResponse = $this->httpClient->post(
            $loginPostPath,
            $payload,
            $headers,
            false,
            $this->session->cookieHeader()
        );

        $this->session->updateFromHeaders((string)$authResponse['headers_raw']);
        $this->session->updateFromHtml((string)$authResponse['body']);

        $latencyMs = (int)round((microtime(true) - $started) * 1000);
        $statusCode = (int)$authResponse['status_code'];
        $isStatusOk = $statusCode >= 200 && $statusCode < 400;
        $responseBody = (string)($authResponse['body'] ?? '');
        $hasHomeMarker = stripos($responseBody, 'Home page') !== false;
        $isOk = $isStatusOk && $this->session->isAuthenticated() && ($hasHomeMarker || $statusCode === 302);

        $this->logger->info('Forwarder login completed', [
            'ok' => $isOk,
            'status_code' => $statusCode,
        ]);

        return new StepResult(
            $isOk,
            (int)$authResponse['status_code'],
            $isOk ? 'AUTH_OK' : 'AUTH_FAILED',
            ['error' => (string)$authResponse['error']],
            $latencyMs
        );
    }
}
