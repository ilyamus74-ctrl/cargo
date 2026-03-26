<?php

declare(strict_types=1);

namespace App\Forwarder\Http;

final class SessionManager
{
    /** @var array<string, string> */
    private array $cookies = [];

    private string $xsrfToken = '';

    private string $csrfToken = '';



    private function decodeCookieToken(string $value): string
    {
        $decoded = urldecode($value);
        $length = strlen($decoded);
        if (
            $length >= 2
            && (($decoded[0] === '"' && $decoded[$length - 1] === '"') || ($decoded[0] === "'" && $decoded[$length - 1] === "'"))
        ) {
            $decoded = substr($decoded, 1, -1);
        }

        return trim($decoded);
    }

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
                $this->xsrfToken = $this->decodeCookieToken($value);
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
            $this->csrfToken = trim((string)$metaMatch[1]);
        } elseif (
            preg_match('/<input[^>]+name=["\\\']_token["\\\'][^>]+value=["\\\']([^"\\\']+)["\\\']/i', $html, $inputMatch) === 1
            && isset($inputMatch[1])
        ) {
            $this->csrfToken = trim((string)$inputMatch[1]);
        }

        if ($this->xsrfToken === '' && $this->csrfToken !== '') {
            $this->xsrfToken = $this->csrfToken;
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

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    /** @return array<string, string> */
    public function securityHeaders(bool $withCsrf = true): array
    {
        $headers = [];
        if ($this->xsrfToken !== '') {
            $headers['X-XSRF-TOKEN'] = $this->xsrfToken;
        }
        if ($withCsrf) {
            $csrf = $this->csrfToken !== '' ? $this->csrfToken : $this->xsrfToken;
            if ($csrf !== '') {
                $headers['X-CSRF-TOKEN'] = $csrf;
            }
        }

        return $headers;
    }

    public function isAuthenticated(): bool
    {
        return $this->cookieHeader() !== '';
    }


    /** @return array<string, mixed> */
    public function exportState(): array
    {
        return [
            'cookies' => $this->cookies,
            'xsrf_token' => $this->xsrfToken,
            'csrf_token' => $this->csrfToken,
        ];
    }

    /** @param array<string, mixed> $state */
    public function importState(array $state): void
    {
        $cookies = $state['cookies'] ?? [];
        $this->cookies = is_array($cookies) ? array_map('strval', $cookies) : [];
        $this->xsrfToken = (string)($state['xsrf_token'] ?? '');
        $this->csrfToken = (string)($state['csrf_token'] ?? '');
    }
}
