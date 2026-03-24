<?php

declare(strict_types=1);

namespace App\Forwarder\Http;

final class SessionManager
{
    /** @var array<string, string> */
    private array $cookies = [];

    private string $xsrfToken = '';

    public function updateFromHeaders(string $headersRaw): void
    {
        if ($headersRaw === '') {
            return;
        }

        $lines = preg_split('/\r\n|\n|\r/', $headersRaw) ?: [];
        foreach ($lines as $line) {
            if (stripos($line, 'Set-Cookie:') !== 0) {
                continue;
            }

            $cookieLine = trim(substr($line, strlen('Set-Cookie:')));
            if ($cookieLine === '') {
                continue;
            }

            $parts = explode(';', $cookieLine);
            $pair = trim((string)($parts[0] ?? ''));
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $pair, 2));
            if ($name === '') {
                continue;
            }

            $this->cookies[$name] = $value;
            if (strcasecmp($name, 'XSRF-TOKEN') === 0) {
                $this->xsrfToken = urldecode($value);
            }
        }
    }

    public function updateFromHtml(string $html): void
    {
        if ($html === '') {
            return;
        }

        if (
            preg_match('/<meta[^>]+name=["\\\']csrf-token["\\\'][^>]+content=["\\\']([^"\\\']+)["\\\']/i', $html, $metaMatch) === 1
            && isset($metaMatch[1])
        ) {
            $this->xsrfToken = trim((string)$metaMatch[1]);
            return;
        }

        if (
            preg_match('/<input[^>]+name=["\\\']_token["\\\'][^>]+value=["\\\']([^"\\\']+)["\\\']/i', $html, $inputMatch) === 1
            && isset($inputMatch[1])
        ) {
            $this->xsrfToken = trim((string)$inputMatch[1]);
        }
    }

    public function cookieHeader(): string
    {
        if (!$this->cookies) {
            return '';
        }

        $pairs = [];
        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    public function xsrfToken(): string
    {
        return $this->xsrfToken;
    }

    /** @return array<string, string> */
    public function securityHeaders(bool $withCsrf = false): array
    {
        $headers = [];
        if ($this->xsrfToken !== '') {
            $headers['X-XSRF-TOKEN'] = $this->xsrfToken;
            if ($withCsrf) {
                $headers['X-CSRF-TOKEN'] = $this->xsrfToken;
            }
        }

        return $headers;
    }

    public function isAuthenticated(): bool
    {
        return $this->cookieHeader() !== '';
    }
}
