<?php

declare(strict_types=1);

namespace App\Forwarder\Http;

use App\Forwarder\Config\ForwarderConfig;
use RuntimeException;

final class ForwarderHttpClient
{
    public function __construct(private ForwarderConfig $config)
    {
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function get(string $endpointPath, array $headers = [], string $cookieHeader = ''): array
    {
        return $this->request('GET', $endpointPath, [], $headers, false, $cookieHeader);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function post(string $endpointPath, array $payload, array $headers = [], bool $asJson = true, string $cookieHeader = ''): array
    {
        return $this->request('POST', $endpointPath, $payload, $headers, $asJson, $cookieHeader);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $endpointPath,
        array $payload = [],
        array $headers = [],
        bool $asJson = true,
        string $cookieHeader = ''
    ): array {
        return $this->send($method, $endpointPath, $headers, $payload, $asJson, $cookieHeader);

    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $endpointPath, array $headers, array $payload, bool $asJson, string $cookieHeader): array
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            $method = 'GET';
        }
        $url = $this->config->baseUrl() . $endpointPath;
        if ($method === 'GET' && $payload !== []) {
            $query = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
            if ($query !== '') {
                $separator = str_contains($url, '?') ? '&' : '?';
                $url .= $separator . $query;
            }
        }
        $attempt = 0;
        $maxAttempts = $this->config->retryCount() + 1;
        $lastError = '';

        while ($attempt < $maxAttempts) {
            $attempt++;
            $started = microtime(true);
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Unable to initialize cURL');
            }

            $httpHeaders = [
                'X-Requested-With: XMLHttpRequest',
            ];

            if ($cookieHeader !== '') {
                $httpHeaders[] = 'Cookie: ' . $cookieHeader;
            }

            foreach ($headers as $name => $value) {
                $httpHeaders[] = $name . ': ' . $value;
            }


            if ($method !== 'GET') {
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }
                if ($asJson) {
                    $httpHeaders[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
                } else {
                    $httpHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                }
            }

            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_CONNECTTIMEOUT_MS => $this->config->timeoutConnectMs(),
                CURLOPT_TIMEOUT_MS => $this->config->timeoutTotalMs(),
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            if ($this->config->sslIgnore()) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $latencyMs = (int)round((microtime(true) - $started) * 1000);

            if ($raw === false || $errno !== 0) {
                $lastError = $error !== '' ? $error : ('curl_errno=' . $errno);
                if ($attempt < $maxAttempts) {
                    usleep($this->config->retryDelayMs() * 1000);
                    continue;
                }

                return [
                    'ok' => false,
                    'status_code' => 0,
                    'headers_raw' => '',
                    'body' => '',
                    'json' => null,
                    'latency_ms' => $latencyMs,
                    'error' => $lastError,
                ];
            }

            $headersRaw = substr($raw, 0, $headerSize) ?: '';
            $body = substr($raw, $headerSize) ?: '';

            $decoded = json_decode($body, true);
            $json = is_array($decoded) ? $decoded : null;
            $ok = $statusCode >= 200 && $statusCode < 300;

            if (!$ok && $statusCode >= 500 && $attempt < $maxAttempts) {
                usleep($this->config->retryDelayMs() * 1000);
                continue;
            }

            return [
                'ok' => $ok,
                'status_code' => $statusCode,
                'headers_raw' => $headersRaw,
                'body' => $body,
                'json' => $json,
                'latency_ms' => $latencyMs,
                'error' => $ok ? '' : ('http_status=' . $statusCode),
            ];
        }

        return [
            'ok' => false,
            'status_code' => 0,
            'headers_raw' => '',
            'body' => '',
            'json' => null,
            'latency_ms' => 0,
            'error' => $lastError,
        ];
    }
}
