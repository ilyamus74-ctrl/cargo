<?php

declare(strict_types=1);

namespace App\Forwarder\Http;

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Logging\ForwarderLogger;

final class CamexSessionClient
{
    public function __construct(
        private ForwarderConfig $config,
        private ForwarderHttpClient $httpClient,
        private SessionManager $session,
        private ForwarderLogger $logger
    ) {
    }

    /** @return array<string, mixed> */
    public function ensureAuthenticated(): array
    {
        if ($this->session->isAuthenticated()) {
            $dashboard = $this->fetchDashboardFollowingRedirects();
            if ($this->isDashboardOk((string)($dashboard['response']['body'] ?? ''))) {
                return $this->successDiagnostics('AUTH_ALREADY', $dashboard);
            }
        }

        $this->restorePersistedSession();
        if ($this->session->isAuthenticated()) {
            $dashboard = $this->fetchDashboardFollowingRedirects();
            if ($this->isDashboardOk((string)($dashboard['response']['body'] ?? ''))) {
                return $this->successDiagnostics('AUTH_RESTORED', $dashboard);
            }
        }

        return $this->login();
    }

    /** @return array<string, mixed> */
    public function requestWithSession(string $method, string $path, array $params = [], bool $asForm = false): array
    {
        $auth = $this->ensureAuthenticated();
        if (empty($auth['ok'])) {
            return [
                'ok' => false,
                'status_code' => (int)($auth['http_status'] ?? 0),
                'headers_raw' => '',
                'body' => '',
                'json' => null,
                'latency_ms' => 0,
                'error' => (string)($auth['message'] ?? 'Authentication failed'),
                'auth' => $auth,
            ];
        }

        $response = $this->sendWithSession($method, $path, $params, $asForm);
        if ($this->responseRequiresRelogin($response)) {
            $this->logger->info('CAMEX session expired, relogin requested', [
                'status_code' => (int)($response['status_code'] ?? 0),
            ]);
            $login = $this->login();
            if (!empty($login['ok'])) {
                $response = $this->sendWithSession($method, $path, $params, $asForm);
            }
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function sendWithSession(string $method, string $path, array $params, bool $asForm): array
    {
        $headers = $this->session->securityHeaders($asForm);
        $response = $this->httpClient->request(strtoupper($method), $this->path($path), $params, $headers, !$asForm, $this->session->cookieHeader());
        $this->session->updateFromHeaders((string)($response['headers_raw'] ?? ''));
        $this->session->updateFromHtml((string)($response['body'] ?? ''));
        $this->persistSession();

        return $response;
    }

    /** @return array<string, mixed> */
    private function login(): array
    {
        $started = microtime(true);
        $loginPath = $this->path($this->config->loginPath());
        $loginPage = $this->httpClient->get($loginPath, [], $this->session->cookieHeader());
        $this->session->updateFromHeaders((string)($loginPage['headers_raw'] ?? ''));
        $loginHtml = (string)($loginPage['body'] ?? '');
        $this->session->updateFromHtml($loginHtml);

        $form = $this->extractLoginFormMetadata($loginHtml);
        $loginPostPath = $form['action'] !== '' ? $this->resolveRelativePath($loginPath, $form['action']) : $this->path($this->config->loginPostPath());
        $csrf = $this->extractCsrf($loginHtml);

        $payload = [
            'user' => $this->config->webLogin(),
            'password' => $this->config->webPassword(),
            'username' => $this->config->webLogin(),
            'login' => $this->config->webLogin(),
            'email' => $this->config->webLogin(),
        ];
        if ($csrf['name'] !== '' && $csrf['value'] !== '') {
            foreach (['_token', 'csrf_token', 'csrf'] as $name) {
                $payload[$name] = $csrf['value'];
            }
        }

        $headers = array_merge($this->session->securityHeaders(true), [
            'Origin' => $this->config->baseUrl(),
            'Referer' => $this->config->baseUrl() . $loginPath,
        ]);
        $loginPost = $this->httpClient->post($loginPostPath, $payload, $headers, false, $this->session->cookieHeader());
        $this->session->updateFromHeaders((string)($loginPost['headers_raw'] ?? ''));
        $this->session->updateFromHtml((string)($loginPost['body'] ?? ''));

        $dashboard = $this->fetchDashboardFollowingRedirects();
        $dashboardBody = (string)($dashboard['response']['body'] ?? '');
        $dashboardLooksLikeLogin = $this->looksLikeLoginPage($dashboardBody);
        $dashboardLooksLikeAdmin = $this->isDashboardOk($dashboardBody);
        $latencyMs = (int)round((microtime(true) - $started) * 1000);
        $loginPostStatus = (int)($loginPost['status_code'] ?? 0);
        $dashboardStatus = (int)($dashboard['response']['status_code'] ?? 0);
        $ok = $loginPostStatus >= 200 && $loginPostStatus < 400 && $dashboardStatus >= 200 && $dashboardStatus < 400 && !$dashboardLooksLikeLogin && $dashboardLooksLikeAdmin;

        if ($ok) {
            $this->persistSession();
        }

        $result = [
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'error',
            'stage' => $ok ? 'auth' : 'web_login',
            'message' => $ok ? 'AUTH_OK' : 'CAMEX web login failed',
            'http_status' => $dashboardStatus > 0 ? $dashboardStatus : $loginPostStatus,
            'latency_ms' => $latencyMs,
            'csrf_found' => $csrf['name'] !== '' && $csrf['value'] !== '',
            'login_page_status' => (int)($loginPage['status_code'] ?? 0),
            'login_post_status' => $loginPostStatus,
            'login_post_location' => $this->extractLocation($loginPost),
            'dashboard_status' => $dashboardStatus,
            'dashboard_location' => (string)($dashboard['location'] ?? ''),
            'dashboard_effective_path' => (string)($dashboard['effective_path'] ?? ''),
            'dashboard_looks_like_login' => $dashboardLooksLikeLogin,
            'dashboard_looks_like_admin' => $dashboardLooksLikeAdmin,
            'login_form' => [
                'action' => $form['action'],
                'method' => $form['method'],
                'input_names' => $form['input_names'],
                'password_fields' => $form['password_fields'],
                'resolved_action_path' => $loginPostPath,
            ],
        ];

        $this->logger->info('CAMEX login completed', [
            'ok' => $ok,
            'status_code' => $result['http_status'],
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    private function successDiagnostics(string $message, array $dashboard): array
    {
        return [
            'ok' => true,
            'status' => 'ok',
            'stage' => 'auth',
            'message' => $message,
            'http_status' => (int)($dashboard['response']['status_code'] ?? 200),
            'dashboard_status' => (int)($dashboard['response']['status_code'] ?? 200),
            'dashboard_location' => (string)($dashboard['location'] ?? ''),
            'dashboard_effective_path' => (string)($dashboard['effective_path'] ?? ''),
            'dashboard_looks_like_login' => false,
            'dashboard_looks_like_admin' => true,
        ];
    }

    /** @return array{response: array<string, mixed>, location: string, effective_path: string} */
    private function fetchDashboardFollowingRedirects(): array
    {
        $path = $this->path($this->config->dashboardPath());
        $firstLocation = '';
        $response = [];

        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $response = $this->httpClient->get($path, $this->session->securityHeaders(false), $this->session->cookieHeader());
            $this->session->updateFromHeaders((string)($response['headers_raw'] ?? ''));
            $this->session->updateFromHtml((string)($response['body'] ?? ''));

            $status = (int)($response['status_code'] ?? 0);
            $location = $this->extractLocation($response);
            if ($firstLocation === '' && $location !== '') {
                $firstLocation = $location;
            }
            if ($status < 300 || $status >= 400 || $location === '') {
                break;
            }
            $path = $this->resolveRelativePath($path, $location);
        }

        return ['response' => $response, 'location' => $firstLocation, 'effective_path' => $path];
    }

    private function responseRequiresRelogin(array $response): bool
    {
        $status = (int)($response['status_code'] ?? 0);
        if (in_array($status, [401, 403, 419], true)) {
            return true;
        }
        if ($status >= 300 && $status < 400 && stripos($this->extractLocation($response), 'login.php') !== false) {
            return true;
        }

        return $this->looksLikeLoginPage((string)($response['body'] ?? ''));
    }

    private function looksLikeLoginPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        $hasPassword = preg_match('/<input\b[^>]*type\s*=\s*(?:"password"|\'password\'|password)\b/i', $html) === 1;
        return $hasPassword && stripos($html, 'login.php?auth=do') !== false;
    }

    private function isDashboardOk(string $html): bool
    {
        if ($html === '') {
            return false;
        }
        foreach (['LogOut', 'index.php?do=logout', 'Camara Express Admin Panel', 'index.php?do=flight', 'index.php?do=tracking_search'] as $marker) {
            if (stripos($html, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return array{name: string, value: string} */
    private function extractCsrf(string $html): array
    {
        if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $metaMatch) === 1) {
            return ['name' => '_token', 'value' => trim((string)$metaMatch[1])];
        }
        foreach (['_token', 'csrf_token', 'csrf'] as $fieldName) {
            $quoted = preg_quote($fieldName, '/');
            if (preg_match('/<input[^>]+name=["\']' . $quoted . '["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $inputMatch) === 1) {
                return ['name' => $fieldName, 'value' => trim((string)$inputMatch[1])];
            }
        }

        return ['name' => '', 'value' => ''];
    }

    /** @return array{action: string, method: string, input_names: list<string>, password_fields: list<string>} */
    private function extractLoginFormMetadata(string $html): array
    {
        $metadata = ['action' => '', 'method' => 'get', 'input_names' => [], 'password_fields' => []];
        if ($html === '') {
            return $metadata;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded) {
            $forms = $dom->getElementsByTagName('form');
            $selected = null;
            foreach ($forms as $form) {
                foreach ($form->getElementsByTagName('input') as $input) {
                    if (strcasecmp((string)$input->getAttribute('type'), 'password') === 0) {
                        $selected = $form;
                        break 2;
                    }
                }
            }
            if ($selected === null && $forms->length > 0) {
                $selected = $forms->item(0);
            }
            if ($selected !== null) {
                $metadata['action'] = html_entity_decode(trim((string)$selected->getAttribute('action')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $method = strtolower(trim((string)$selected->getAttribute('method')));
                $metadata['method'] = $method !== '' ? $method : 'get';
                foreach ($selected->getElementsByTagName('input') as $input) {
                    $name = trim((string)$input->getAttribute('name'));
                    if ($name === '') {
                        continue;
                    }
                    if (!in_array($name, $metadata['input_names'], true)) {
                        $metadata['input_names'][] = $name;
                    }
                    if (strcasecmp((string)$input->getAttribute('type'), 'password') === 0 && !in_array($name, $metadata['password_fields'], true)) {
                        $metadata['password_fields'][] = $name;
                    }
                }
            }
        }

        return $metadata;
    }

    private function extractLocation(array $response): string
    {
        $location = '';
        foreach (preg_split('/\r\n|\r|\n/', (string)($response['headers_raw'] ?? '')) ?: [] as $line) {
            if (stripos((string)$line, 'Location:') === 0) {
                $location = trim(substr((string)$line, strlen('Location:')));
            }
        }

        return $location;
    }

    private function resolveRelativePath(string $currentPath, string $location): string
    {
        $location = trim(html_entity_decode($location, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($location === '') {
            return $this->path($currentPath);
        }
        $location = explode('#', $location, 2)[0];
        if (preg_match('#^https?://#i', $location) === 1) {
            $path = (string)(parse_url($location, PHP_URL_PATH) ?: '/');
            $query = parse_url($location, PHP_URL_QUERY);
            return $this->path($path) . (is_string($query) && $query !== '' ? '?' . $query : '');
        }
        if ($location[0] === '/') {
            return $location;
        }
        if ($location[0] === '?') {
            return explode('?', $this->path($currentPath), 2)[0] . $location;
        }

        $pathOnly = explode('?', $this->path($currentPath), 2)[0];
        $directory = rtrim(str_replace('\\', '/', dirname($pathOnly)), '/');
        if ($directory === '') {
            $directory = '/';
        }

        return rtrim($directory, '/') . '/' . $location;
    }

    private function path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return $path[0] === '/' ? $path : '/' . $path;
    }

    private function persistSession(): void
    {
        $filePath = $this->config->sessionCookieFile();
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($filePath, json_encode([
            'expires_at' => time() + $this->config->sessionTtlSeconds(),
            'session' => $this->session->exportState(),
        ], JSON_UNESCAPED_UNICODE));
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
        if (is_array($sessionState)) {
            $this->session->importState($sessionState);
        }
    }
}
