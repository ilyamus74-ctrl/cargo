<?php

declare(strict_types=1);

namespace App\Forwarder\Logging;

final class ForwarderLogger
{
    public function __construct(private string $correlationId)
    {
    }

    /** @param mixed[] $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /** @param mixed[] $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /** @param mixed[] $context */
    private function log(string $level, string $message, array $context): void
    {
        $masked = $this->maskSecrets($context);
        $suffix = $masked ? ' ' . json_encode($masked, JSON_UNESCAPED_UNICODE) : '';
        error_log(sprintf('[Forwarder][%s][%s] %s%s', $level, $this->correlationId, $message, $suffix));
    }

    /** @param mixed[] $context
     *  @return mixed[]
     */
    private function maskSecrets(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $keyStr = strtolower((string)$key);
            if (preg_match('/password|token|cookie|authorization|secret/', $keyStr) === 1) {
                $result[$key] = '***';
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
