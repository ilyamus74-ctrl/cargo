<?php
declare(strict_types=1);

function print_json_response(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function print_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function print_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) === 1) {
        return trim($m[1]);
    }

    return '';
}

function print_queue_file(string $deviceUid): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceUid);
    return __DIR__ . '/../_tmp/print_queue_' . $safe . '.json';
}
function print_direct_cups_enabled(): bool
{
    if (!defined('PRINT_DIRECT_CUPS_ENABLED') || PRINT_DIRECT_CUPS_ENABLED !== true) {
        return false;
    }

    return trim((string)(defined('PRINT_DIRECT_CUPS_HOST') ? PRINT_DIRECT_CUPS_HOST : '')) !== ''
        && trim((string)(defined('PRINT_DIRECT_CUPS_QUEUE') ? PRINT_DIRECT_CUPS_QUEUE : '')) !== '';
}


function print_direct_cups_print_scaling(): string
{
    $value = strtolower(trim((string)(defined('PRINT_DIRECT_CUPS_PRINT_SCALING') ? PRINT_DIRECT_CUPS_PRINT_SCALING : 'fill')));
    return in_array($value, ['fill', 'fit', 'none'], true) ? $value : 'fill';
}

function print_decode_label_to_temp_file(array $job): array
{
    $fileName = trim((string)($job['file_name'] ?? 'label.pdf'));
    $labelBase64 = trim((string)($job['label_base64'] ?? ''));
    $labelUrl = trim((string)($job['label_url'] ?? ''));
    $binary = '';
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($labelBase64 !== '') {
        if (preg_match('#^data:([^;,]+)?;base64,(.*)$#s', $labelBase64, $m) === 1) {
            $mime = strtolower(trim((string)($m[1] ?? '')));
            $labelBase64 = (string)$m[2];
            $extension = match ($mime) {
                'application/pdf' => 'pdf',
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'application/zpl', 'application/x-zpl', 'text/plain' => 'zpl',
                default => $extension,
            };
        }

        $decoded = base64_decode($labelBase64, true);
        if (!is_string($decoded) || $decoded === '') {
            return ['ok' => false, 'message' => 'invalid label_base64'];
        }
        $binary = $decoded;
    } elseif ($labelUrl !== '') {
        $context = stream_context_create([
            'http' => ['timeout' => 15],
            'https' => ['timeout' => 15],
        ]);
        $downloaded = @file_get_contents($labelUrl, false, $context);
        if (!is_string($downloaded) || $downloaded === '') {
            if (function_exists('curl_init')) {
                $ch = curl_init($labelUrl);
                if ($ch !== false) {
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_TIMEOUT => 15,
                    ]);
                    $downloaded = curl_exec($ch);
                    curl_close($ch);
                }
            }
        }
        if (!is_string($downloaded) || $downloaded === '') {
            return ['ok' => false, 'message' => 'failed to download label_url'];
        }
        $binary = $downloaded;
    } else {
        return ['ok' => false, 'message' => 'label_base64 or label_url required'];
    }

    if (!in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'zpl'], true)) {
        if (str_starts_with($binary, '%PDF')) {
            $extension = 'pdf';
        } elseif (str_starts_with($binary, "\x89PNG")) {
            $extension = 'png';
        } elseif (str_starts_with($binary, "\xFF\xD8")) {
            $extension = 'jpg';
        } else {
            $extension = 'zpl';
        }
    }
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }

    $tmp = tempnam(sys_get_temp_dir(), 'print_label_');
    if (!is_string($tmp)) {
        return ['ok' => false, 'message' => 'failed to create temp file'];
    }
    $tmpFile = $tmp . '.' . $extension;
    @rename($tmp, $tmpFile);
    if (file_put_contents($tmpFile, $binary) === false) {
        @unlink($tmpFile);
        return ['ok' => false, 'message' => 'failed to write temp file'];
    }

    return ['ok' => true, 'path' => $tmpFile, 'extension' => $extension];
}

function print_direct_cups_send(array $job): array
{
    $cupsHost = trim((string)(defined('PRINT_DIRECT_CUPS_HOST') ? PRINT_DIRECT_CUPS_HOST : ''));
    $cupsQueue = trim((string)(defined('PRINT_DIRECT_CUPS_QUEUE') ? PRINT_DIRECT_CUPS_QUEUE : ''));
    $labelWidthCm = isset($job['label_width_cm']) ? (float)$job['label_width_cm'] : 0.0;
    $labelHeightCm = isset($job['label_height_cm']) ? (float)$job['label_height_cm'] : 0.0;
    $rotate = isset($job['rotate']) ? (int)$job['rotate'] : 0;
    if (!in_array($rotate, [0, 90, 180, 270], true)) {
        $rotate = 0;
    }

    $finalWidthCm = $labelWidthCm;
    $finalHeightCm = $labelHeightCm;
    if ($finalWidthCm > 0 && $finalHeightCm > 0 && in_array($rotate, [90, 270], true)) {
        [$finalWidthCm, $finalHeightCm] = [$finalHeightCm, $finalWidthCm];
    }
    $finalWidthMm = $finalWidthCm > 0 ? (int)round($finalWidthCm * 10) : null;
    $finalHeightMm = $finalHeightCm > 0 ? (int)round($finalHeightCm * 10) : null;
    $printScaling = print_direct_cups_print_scaling();

    $diagnostics = [
        'lp_command' => '',
        'command_output' => '',
        'exit_code' => null,
        'cups_host' => $cupsHost,
        'cups_queue' => $cupsQueue,
        'label_width_cm' => $labelWidthCm,
        'label_height_cm' => $labelHeightCm,
        'final_width_mm' => $finalWidthMm,
        'final_height_mm' => $finalHeightMm,
        'rotate' => $rotate,
        'print_scaling' => $printScaling,
        'raw_mode' => false,
        'file_suffix' => '',
    ];

    if ($cupsHost === '' || $cupsQueue === '') {
        return array_merge($diagnostics, [
            'ok' => false,
            'status' => 'error',
            'message' => 'direct CUPS host/queue is not configured',
        ]);
    }

    $decoded = print_decode_label_to_temp_file($job);
    if (!($decoded['ok'] ?? false)) {
        return array_merge($diagnostics, [
            'ok' => false,
            'status' => 'error',
            'message' => (string)($decoded['message'] ?? 'failed to prepare label'),
        ]);
    }

    $tmpFile = (string)$decoded['path'];
    $extension = (string)$decoded['extension'];
    $rawMode = $extension === 'zpl';
    $diagnostics['raw_mode'] = $rawMode;
    $diagnostics['file_suffix'] = $extension;

    $options = [];
    if ($rawMode) {
        $options[] = '-o ' . escapeshellarg('raw');
    } else {
        if ($finalWidthMm !== null && $finalHeightMm !== null) {
            $options[] = '-o ' . escapeshellarg('media=Custom.' . $finalWidthMm . 'x' . $finalHeightMm . 'mm');
        }
        $options[] = '-o ' . escapeshellarg('print-scaling=' . $printScaling);
        foreach (['fit-to-page', 'position=center', 'page-left=0', 'page-right=0', 'page-top=0', 'page-bottom=0'] as $option) {
            $options[] = '-o ' . escapeshellarg($option);
        }
        if ($finalWidthCm > 0 && $finalHeightCm > 0) {
            $orientation = $finalWidthCm >= $finalHeightCm ? 4 : 3;
            $options[] = '-o ' . escapeshellarg('orientation-requested=' . $orientation);
        }
    }

    $cmd = 'lp'
        . ' -h ' . escapeshellarg($cupsHost)
        . ' -d ' . escapeshellarg($cupsQueue)
        . ($options ? ' ' . implode(' ', $options) : '')
        . ' ' . escapeshellarg($tmpFile)
        . ' 2>&1';
    $diagnostics['lp_command'] = $cmd;

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    @unlink($tmpFile);
    $commandOutput = trim(implode("\n", $output));
    $diagnostics['command_output'] = $commandOutput;
    $diagnostics['exit_code'] = $exitCode;

    return array_merge($diagnostics, [
        'ok' => $exitCode === 0,
        'status' => $exitCode === 0 ? 'ok' : 'error',
        'message' => $exitCode === 0 ? 'sent to direct CUPS' : 'lp failed',
    ]);
}

function print_read_queue(string $deviceUid): array
{
    $path = print_queue_file($deviceUid);
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function print_write_queue(string $deviceUid, array $queue): bool
{
    $dir = __DIR__ . '/../_tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $json = json_encode(array_values($queue), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents(print_queue_file($deviceUid), $json, LOCK_EX) !== false;
}

function print_dequeue_for_device(string $deviceUid, int $ttlSeconds = 90): ?array
{
    $queue = print_read_queue($deviceUid);
    if (!$queue) {
        return null;
    }

    $now = time();

    foreach ($queue as &$job) {
        $status = (string)($job['status'] ?? 'queued');

        if ($status === 'delivered') {
            $deliveredAt = isset($job['delivered_at']) ? strtotime((string)$job['delivered_at']) : null;
            if ($deliveredAt !== false && $deliveredAt !== null && ($now - $deliveredAt) > $ttlSeconds) {
                $job['status'] = 'queued';
                unset($job['delivered_at']);
            }
        }

        if (($job['status'] ?? 'queued') !== 'queued') {
            continue;
        }

        $job['status'] = 'delivered';
        $job['delivered_at'] = gmdate('c');

        if (!print_write_queue($deviceUid, $queue)) {
            return null;
        }

        return $job;
    }
    unset($job);

    if (!print_write_queue($deviceUid, $queue)) {
        return null;
    }

    return null;
}

function print_update_job_status(string $deviceUid, string $jobId, string $status, string $message = ''): bool
{
    $queue = print_read_queue($deviceUid);
    if (!$queue) {
        return false;
    }

    $updated = false;

    foreach ($queue as &$job) {
        if ((string)($job['job_id'] ?? '') !== $jobId) {
            continue;
        }

        $job['status'] = $status;
        $job['updated_at'] = gmdate('c');
        if ($message !== '') {
            $job['last_message'] = print_safe_truncate($message, 500);
        }

        $history = is_array($job['history'] ?? null) ? $job['history'] : [];
        $history[] = [
            'status' => $status,
            'message' => print_safe_truncate($message, 500),
            'at' => gmdate('c'),
        ];
        $job['history'] = $history;

        $updated = true;
        break;
    }
    unset($job);

    if (!$updated) {
        return false;
    }

    return print_write_queue($deviceUid, $queue);
}

function print_safe_truncate(string $value, int $length): string
{
    if ($length <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return (string)mb_substr($value, 0, $length);
    }

    return substr($value, 0, $length);
}
