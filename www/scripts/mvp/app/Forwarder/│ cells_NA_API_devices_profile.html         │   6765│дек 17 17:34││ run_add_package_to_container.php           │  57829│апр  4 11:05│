<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';

/** @return array<string,string> */
function forwarder_add_package_to_container_cli_kv(array $argv): array
{
    $result = [];
    foreach ($argv as $arg) {
        if (!is_string($arg) || !str_starts_with($arg, '--')) {
            continue;
        }

        $eqPos = strpos($arg, '=');
        if ($eqPos === false) {
            $key = substr($arg, 2);
            if ($key !== '') {
                $result[$key] = '1';
            }
            continue;
        }

        $key = trim(substr($arg, 2, $eqPos - 2));
        if ($key === '') {
            continue;
        }

        $result[$key] = (string)substr($arg, $eqPos + 1);
    }

    return $result;
}

function forwarder_add_package_to_container_arg(array $args, string $primaryKey, string ...$aliases): string
{
    $keys = array_merge([$primaryKey], $aliases);
    foreach ($keys as $key) {
        if (!array_key_exists($key, $args)) {
            continue;
        }

        return trim((string)$args[$key]);
    }

    return '';
}

function forwarder_add_package_to_container_set_env(string $name, string $value): void
{
    if ($value === '') {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function forwarder_add_package_to_container_normalize_base_url(string $rawBaseUrl): string
{
    $value = trim($rawBaseUrl);
    if ($value === '') {
        return '';
    }

    $parts = @parse_url($value);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return rtrim($value, '/');
    }

    $base = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
    if (isset($parts['port']) && (int)$parts['port'] > 0) {
        $base .= ':' . (int)$parts['port'];
    }

    return rtrim($base, '/');
}

function forwarder_add_package_to_container_as_bool(string $value): bool
{
    $value = mb_strtolower(trim($value));
    return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
}

function forwarder_add_package_to_container_as_int(string $value, int $default): int
{
    $trimmed = trim($value);
    if ($trimmed === '' || !preg_match('/^-?\d+$/', $trimmed)) {
        return $default;
    }

    return (int)$trimmed;
}


function forwarder_add_package_to_container_pick_first_non_empty(array $values): string
{
    foreach ($values as $value) {
        $candidate = trim((string)$value);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}



function forwarder_add_package_to_container_normalize_label_base64(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/', $value) === 1) {
        return (string)(preg_replace('/\s+/u', '', $value) ?? $value);
    }

    $clean = (string)(preg_replace('/\s+/u', '', $value) ?? $value);
    if ($clean === '') {
        return '';
    }

    if (base64_decode($clean, true) === false) {
        return '';
    }

    return 'data:image/png;base64,' . $clean;
}

function forwarder_add_package_to_container_resolve_public_base_url(string $baseUrl): string
{
    $normalized = forwarder_add_package_to_container_normalize_base_url($baseUrl);
    if ($normalized !== '') {
        $host = (string)(parse_url($normalized, PHP_URL_HOST) ?? '');
        if ($host !== '' && str_contains($host, 'dev-backend.colibri.az')) {
            return 'https://colibri.az';
        }
        return $normalized;
    }

    return 'https://colibri.az';
}

function forwarder_add_package_to_container_normalize_label_url(string $raw, string $publicBaseUrl): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value) === 1) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return rtrim($publicBaseUrl, '/') . $value;
    }

    return '';
}


function forwarder_add_package_to_container_parse_json_object(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}


function forwarder_add_package_to_container_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

/** @return array<string,string> */
function forwarder_add_package_to_container_request_kv(): array
{
    $result = [];

    $sources = [$_GET ?? [], $_POST ?? []];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }

        foreach ($source as $key => $value) {
            $normalizedKey = trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$normalizedKey] = trim((string)$value);
            }
        }
    }

    $rawInput = file_get_contents('php://input');
    if (is_string($rawInput) && trim($rawInput) !== '') {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            foreach ($json as $key => $value) {
                $normalizedKey = trim((string)$key);
                if ($normalizedKey === '') {
                    continue;
                }
                if (is_scalar($value) || $value === null) {
                    $result[$normalizedKey] = trim((string)$value);
                }
            }
        }
    }

    return $result;
}

function forwarder_add_package_to_container_emit_error_and_exit(string $message, int $exitCode): void
{
    if (forwarder_add_package_to_container_is_cli()) {
        fwrite(STDERR, 'run_add_package_to_container: ' . $message . PHP_EOL);
        exit($exitCode);
    }

    if (!headers_sent()) {
        http_response_code($exitCode >= 400 ? $exitCode : 400);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'status' => 'error',
        'message' => $message,
        'exit_code' => $exitCode,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

function forwarder_add_package_to_container_escape_pdf_text(string $text): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
    if (!is_string($encoded) || $encoded === '') {
        $encoded = preg_replace('/[^\x20-\x7E]/', '?', $normalized) ?? $normalized;
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $encoded);
}

/** @param array<int,string> $lines */
function forwarder_add_package_to_container_build_simple_pdf(array $lines): string
{
    $contentRows = [
        'BT',
        '/F1 12 Tf',
        '50 790 Td',
    ];

    $first = true;
    foreach ($lines as $line) {
        $escaped = forwarder_add_package_to_container_escape_pdf_text((string)$line);
        if ($escaped === '') {
            $escaped = '-';
        }

        if ($first) {
            $contentRows[] = '(' . $escaped . ') Tj';
            $first = false;
            continue;
        }

        $contentRows[] = '0 -18 Td';
        $contentRows[] = '(' . $escaped . ') Tj';
    }

    $contentRows[] = 'ET';
    $stream = implode("\n", $contentRows) . "\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF\n";

    return $pdf;
}

function forwarder_add_package_to_container_convert_html_to_pdf(string $html): array
{

    $tmpDir = sys_get_temp_dir();
    $tmpHtml = tempnam($tmpDir, 'fwd_html_');
    $tmpPdf = tempnam($tmpDir, 'fwd_pdf_');
    if (!is_string($tmpHtml) || !is_string($tmpPdf)) {
        return ['ok' => false, 'error' => 'failed to create temp files', 'pdf_base64' => ''];
    }

    $tmpHtmlFile = $tmpHtml . '.html';
    $tmpPdfFile = $tmpPdf . '.pdf';
    @rename($tmpHtml, $tmpHtmlFile);
    @rename($tmpPdf, $tmpPdfFile);

    if (file_put_contents($tmpHtmlFile, $html) === false) {
        @unlink($tmpHtmlFile);
        @unlink($tmpPdfFile);
        return ['ok' => false, 'error' => 'failed to write temp html', 'pdf_base64' => ''];
    }

    $errors = [];

    $wkhtmltopdf = trim((string)shell_exec('command -v wkhtmltopdf 2>/dev/null'));
    if ($wkhtmltopdf !== '') {
        $cmd = escapeshellarg($wkhtmltopdf) . ' --quiet ' . escapeshellarg($tmpHtmlFile) . ' ' . escapeshellarg($tmpPdfFile) . ' 2>&1';
        $output = shell_exec($cmd);
        $pdfBinary = @file_get_contents($tmpPdfFile);
        if (is_string($pdfBinary) && $pdfBinary !== '') {
            @unlink($tmpHtmlFile);
            @unlink($tmpPdfFile);
            return ['ok' => true, 'error' => '', 'pdf_base64' => base64_encode($pdfBinary)];
        }
        $errors[] = 'wkhtmltopdf failed: ' . trim((string)$output);
        @unlink($tmpPdfFile);
        $tmpPdf = tempnam($tmpDir, 'fwd_pdf_');
        if (!is_string($tmpPdf)) {
            @unlink($tmpHtmlFile);
            return ['ok' => false, 'error' => implode('; ', $errors), 'pdf_base64' => ''];
        }
        $tmpPdfFile = $tmpPdf . '.pdf';
        @rename($tmpPdf, $tmpPdfFile);
    } else {
        $errors[] = 'wkhtmltopdf is not installed';
    }


    $chromeCandidates = ['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable'];
    $chromeBinary = '';
    foreach ($chromeCandidates as $candidate) {
        $resolved = trim((string)shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
        if ($resolved !== '') {
            $chromeBinary = $resolved;
            break;
        }
    }

    if ($chromeBinary !== '') {
        $fileUrl = 'file://' . $tmpHtmlFile;
        $cmd = escapeshellarg($chromeBinary)
            . ' --headless --disable-gpu --no-sandbox --allow-file-access-from-files'
            . ' --print-to-pdf=' . escapeshellarg($tmpPdfFile)
            . ' ' . escapeshellarg($fileUrl) . ' 2>&1';
        $output = shell_exec($cmd);
        $pdfBinary = @file_get_contents($tmpPdfFile);
        if (is_string($pdfBinary) && $pdfBinary !== '') {
            @unlink($tmpHtmlFile);
            @unlink($tmpPdfFile);
            return ['ok' => true, 'error' => '', 'pdf_base64' => base64_encode($pdfBinary)];
        }
        $errors[] = 'headless chrome failed: ' . trim((string)$output);
    } else {
        $errors[] = 'chromium/google-chrome is not installed';
    }


    @unlink($tmpHtmlFile);
    @unlink($tmpPdfFile);
    return ['ok' => false, 'error' => implode('; ', $errors), 'pdf_base64' => ''];}

/** @param mixed $value @param array<int,string> $accumulator */
function forwarder_add_package_to_container_collect_data_url_images($value, array &$accumulator): void
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\r\n]+$/', $trimmed) === 1) {
            $accumulator[] = preg_replace('/\s+/u', '', $trimmed) ?? $trimmed;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $nested) {
        forwarder_add_package_to_container_collect_data_url_images($nested, $accumulator);
    }
}

function forwarder_add_package_to_container_extension_from_mime(string $mime): string
{
    $map = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg',
    ];

    return $map[mb_strtolower(trim($mime))] ?? 'bin';
}

/** @param array<int,string> $dataUrls */
function forwarder_add_package_to_container_save_label_images(string $track, array $dataUrls): array
{
    $baseDir = __DIR__ . '/lable';
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        return [
            'ok' => false,
            'error' => 'failed to create label directory: ' . $baseDir,
            'dir' => $baseDir,
            'saved_files' => [],
            'print_file_name' => '',
            'print_label_base64' => '',
        ];
    }

    $safeTrack = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $track) ?? 'track';
    $stamp = date('Ymd_His');
    $prefix = $safeTrack . '_' . $stamp;
    $savedFiles = [];
    $htmlRows = [];

    foreach ($dataUrls as $index => $dataUrl) {
        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUrl, $matches) !== 1) {
            continue;
        }

        $mime = (string)$matches[1];
        $binary = base64_decode((string)$matches[2], true);
        if ($binary === false) {
            continue;
        }

        $ext = forwarder_add_package_to_container_extension_from_mime($mime);
        $fileName = sprintf('%s_%02d.%s', $prefix, $index + 1, $ext);
        $fullPath = $baseDir . '/' . $fileName;
        if (file_put_contents($fullPath, $binary) === false) {
            continue;
        }

        $savedFiles[] = [
            'name' => $fileName,
            'path' => $fullPath,
            'mime' => $mime,
            'bytes' => strlen($binary),
        ];

        $htmlRows[] = '<div style="margin:0 0 12px 0;page-break-inside:avoid"><img style="max-width:100%;height:auto" src="data:'
            . htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . ';base64,' . base64_encode($binary) . '" alt="label-image-' . ($index + 1) . '"></div>';
    }

    if ($savedFiles === []) {
        return [
            'ok' => false,
            'error' => 'no valid image data-url found to save',
            'dir' => $baseDir,
            'saved_files' => [],
            'print_file_name' => '',
            'print_label_base64' => '',
        ];
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Label ' . htmlspecialchars($track, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</title></head><body style="margin:0;padding:0;">' . implode('', $htmlRows) . '</body></html>';
    $htmlFileName = $prefix . '.html';
    $htmlFullPath = $baseDir . '/' . $htmlFileName;
    file_put_contents($htmlFullPath, $html);

    return [
        'ok' => true,
        'error' => '',
        'dir' => $baseDir,
        'saved_files' => $savedFiles,
        'html_path' => $htmlFullPath,
        'print_file_name' => $htmlFileName,
        'print_label_base64' => base64_encode($html),
    ];
}


function forwarder_add_package_to_container_save_label_from_url(string $track, string $labelUrl): array
{
    $url = trim($labelUrl);
    if ($url === '') {
        return [
            'ok' => false,
            'error' => 'empty label url',
            'saved_file' => null,
        ];
    }

    $baseDir = __DIR__ . '/lable';
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        return [
            'ok' => false,
            'error' => 'failed to create label directory: ' . $baseDir,
            'saved_file' => null,
        ];
    }

    $downloadCandidates = [$url];
    $parsedUrl = parse_url($url);
    $host = (string)($parsedUrl['host'] ?? '');
    $path = (string)($parsedUrl['path'] ?? '');
    $query = (string)($parsedUrl['query'] ?? '');
    if ($host !== '' && str_contains($host, 'dev-backend.colibri.az') && str_starts_with($path, '/uploads/')) {
        $fallback = 'https://colibri.az' . $path;
        if ($query !== '') {
            $fallback .= '?' . $query;
        } else {
            $fallback .= '?read_only=1';
        }
        $downloadCandidates[] = $fallback;
    }

    $binary = false;
    foreach ($downloadCandidates as $candidateUrl) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
            ],
        ]);
        $binary = @file_get_contents($candidateUrl, false, $context);
        if ($binary !== false && $binary !== '') {
            $url = $candidateUrl;
            break;
        }
    }
    if ($binary === false || $binary === '') {
        return [
            'ok' => false,
            'error' => 'failed to download label url (including fallback candidates)',
            'saved_file' => null,
        ];
    }

    $path = (string)parse_url($url, PHP_URL_PATH);
    $ext = pathinfo((string)$path, PATHINFO_EXTENSION);
    $ext = $ext !== '' ? mb_strtolower($ext) : 'bin';
    $safeTrack = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $track) ?? 'track';
    $stamp = date('Ymd_His');
    $fileName = sprintf('%s_%s_url.%s', $safeTrack, $stamp, $ext);
    $fullPath = $baseDir . '/' . $fileName;
    if (file_put_contents($fullPath, $binary) === false) {
        return [
            'ok' => false,
            'error' => 'failed to save downloaded label file',
            'saved_file' => null,
        ];
    }

    return [
        'ok' => true,
        'error' => '',
        'saved_file' => [
            'name' => $fileName,
            'path' => $fullPath,
            'bytes' => strlen($binary),
            'source_url' => $url,
        ],
    ];
}

/** @param array<string,mixed>|null $verifyJson */
function forwarder_add_package_to_container_build_html_label_from_verify(?array $verifyJson, string $track, string $publicBaseUrl): array
{
    if (!is_array($verifyJson)) {
        return ['ok' => false, 'error' => 'verify json is empty', 'label_base64' => '', 'file_name' => ''];
    }

    $package = is_array($verifyJson['package'] ?? null) ? $verifyJson['package'] : [];
    $invoiceDoc = trim((string)($package['invoice_doc'] ?? $verifyJson['invoice_doc'] ?? ''));
    $invoiceUrl = forwarder_add_package_to_container_normalize_label_url($invoiceDoc, $publicBaseUrl);
    if ($invoiceUrl !== '' && !str_contains($invoiceUrl, 'read_only=')) {
        $invoiceUrl .= (str_contains($invoiceUrl, '?') ? '&' : '?') . 'read_only=1';
    }
    $qrUrl = $invoiceUrl !== ''
        ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x75&data=' . rawurlencode($invoiceUrl)
        : '';


    $clientNameRaw = trim((string)($package['client_name'] ?? ''));
    $clientCodeRaw = trim((string)($package['client'] ?? ''));
    $clientIdRaw = trim((string)($package['client_id'] ?? ''));
    $internalIdRaw = trim((string)($package['internal_id'] ?? ''));
    $weightRaw = trim((string)($package['gross_weight'] ?? ''));
    $volumeWeightRaw = trim((string)($package['volume_weight'] ?? ''));
    $amountRaw = trim((string)($package['amount'] ?? ''));
    $amountCurrencyRaw = trim((string)($package['amount_currency'] ?? 'USD'));
    $categoryRaw = trim((string)($package['category'] ?? $package['title'] ?? ''));
    $invoiceUsdRaw = trim((string)($package['invoice_usd'] ?? '0.00'));
    $flightDepartureRaw = trim((string)($package['flight_departure'] ?? ''));
    $flightDestinationRaw = trim((string)($package['flight_destination'] ?? ''));
    $flightNameRaw = trim((string)($package['flight_name'] ?? ''));
    $clientPhoneRaw = trim((string)($package['client_phone'] ?? ''));
    $clientAddressRaw = trim((string)($package['client_address'] ?? ''));
    $descriptionRaw = trim((string)($package['description'] ?? ''));

    $client = htmlspecialchars($clientNameRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clientCode = htmlspecialchars($clientCodeRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clientId = htmlspecialchars($clientIdRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $internalId = htmlspecialchars($internalIdRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $weight = htmlspecialchars($weightRaw !== '' ? $weightRaw : '0.000', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $volumeWeight = htmlspecialchars($volumeWeightRaw !== '' ? $volumeWeightRaw : '0.000', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $amount = htmlspecialchars($amountRaw !== '' ? $amountRaw : ('0.00 ' . $amountCurrencyRaw), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $category = htmlspecialchars($categoryRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $invoiceUsd = htmlspecialchars($invoiceUsdRaw !== '' ? $invoiceUsdRaw : '0.00', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $flightDeparture = htmlspecialchars($flightDepartureRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $flightDestination = htmlspecialchars($flightDestinationRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $flightName = htmlspecialchars($flightNameRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clientPhone = htmlspecialchars($clientPhoneRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clientAddress = htmlspecialchars($clientAddressRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $description = htmlspecialchars($descriptionRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $trackSafe = htmlspecialchars($track, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $qrImg = $qrUrl !== '' ? '<img src="' . htmlspecialchars($qrUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="QR Code" style="width:90px;height:90px;">' : '';
    $barcodeUrl = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($internalIdRaw !== '' ? $internalIdRaw : $track) . '&code=Code128&dpi=96';
    $consigneePhone = $clientPhoneRaw !== '' ? '(' . $clientPhone . ')' : '';
    $amountNumeric = '0.00';
    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $amountRaw, $amountMatch) === 1) {
        $amountNumeric = (string)$amountMatch[1];
    }
    $totalWaybillInvoicePrice = htmlspecialchars($amountNumeric . ' USD', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Waybill ' . $trackSafe . '</title>'
        . '<style>'
        . 'body{font-family:Arial,sans-serif;font-size:14px;margin:0;padding:10px;}'
        . 'table{width:100%;border-collapse:collapse;}td{border:1px solid #000;padding:4px;vertical-align:top;}'
        . '.nob{border:none !important;}'
        . '.center{text-align:center;}'
        . '.title{font-size:38px;font-weight:bold;line-height:1;}'
        . '.small{font-size:12px;}'
        . '.h80{height:80px;}.h70{height:70px;}'
        . '</style></head><body><div style="border:2px solid #000;">'
        . '<table><tr><td style="width:44%;padding:0;">'
        . '<table><caption class="title center">WAYBILL</caption><tr><td style="width:10%;">1</td><td style="width:10%;"></td><td colspan="3">Payer account number</td></tr></table>'
        . '<table><tr><td rowspan="2" class="center" style="width:70%;font-size:22px;">' . $clientId . '</td><td style="width:10%;"></td><td style="width:20%;">Charge Collect</td></tr><tr><td></td><td>Prepaid</td></tr></table>'
        . '<table><tr><td style="width:10%;">2</td><td style="width:10%;"></td><td style="width:20%;">From</td><td>Shipper</td></tr></table>'
        . '<table><tr><td id="waybill_seller" class="center" style="width:40%;"></td><td class="center" style="width:60%;">' . $client . ' ' . $clientCode . '</td></tr></table>'
        . '<table class="h70"><tr><td class="center">Deutschland/Pheinland Pfalz/Sohren Routbuchenweg 3, 55487</td></tr></table>'
        . '<table class="h80"><tr><td style="width:40%;">Postcode / ZIP Code</td><td>Phone, Fax or Email (required)</td></tr><tr><td></td><td></td></tr></table>'
        . '<table><tr><td style="width:10%;">3</td><td style="width:10%;"></td><td colspan="3">To (Consignee)</td></tr></table>'
        . '<table><tr><td>Name</td><td class="center">Personal ID No</td></tr></table>'
        . '<table><tr><td class="center">' . $client . ' ' . $clientCode . '</td></tr><tr><td class="center">' . $consigneePhone . '</td></tr></table>'
        . '<table class="h70"><tr><td>Delivery Address</td></tr><tr><td>(' . $clientAddress . ')</td></tr></table>'
        . '<table><tr><td class="center"><img src="' . htmlspecialchars($barcodeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="barcode" style="width:96%;height:100px;"></td></tr><tr><td class="center" style="font-weight:bold;">' . $internalId . '</td></tr></table>'
        . '<table><tr><td>Postcode/ZIP Code</td><td>Country Azerbaijan</td></tr><tr><td colspan="2">Contact Person</td></tr></table>'
        . '</td><td style="width:56%;padding:0;">'
        . '<table><tr><td rowspan="3" class="center" style="width:58%;font-size:34px;font-weight:bold;color:#f26522;">colibri</td><td class="center" style="width:20%;">CDN</td><td style="width:22%;"></td></tr><tr><td></td><td class="center">' . $qrImg . '</td></tr><tr><td class="center">' . $flightDeparture . '</td><td class="center">' . $flightDestination . '</td></tr><tr><td colspan="3" class="center small"></td></tr></table>'
        . '<table><tr><td style="width:10%;">4</td><td style="width:10%;"></td><td colspan="3">Shipment details</td></tr></table>'
        . '<table><tr><td class="center">Total number of packages</td><td class="center">Total Gross weight (kg)</td><td class="center">Chargeable Volume Weight (kg)</td><td class="center">Shipping Price</td></tr><tr><td class="center">1</td><td class="center">' . $weight . '</td><td class="center">' . $volumeWeight . '</td><td class="center">' . $amount . '</td></tr><tr><td>Transportation mode</td><td colspan="3" class="center">By Air</td></tr></table>'
        . '<table class="h70"><tr><td class="center">MAWB</td><td class="center">Colibri express FLIGHT #</td></tr><tr><td class="center"></td><td class="center">' . $flightName . '</td></tr></table>'
        . '<table><tr><td style="width:10%;">5</td><td style="width:10%;"></td><td colspan="3">Full Description of contents & remarks</td></tr></table>'
        . '<table><tr><td style="height:60px;">' . $description . '</td></tr></table>'
        . '<table><tr><td class="center">Category</td><td class="center">Declared Value for Customs</td><td class="center">Total Price</td></tr><tr><td class="center">' . $category . '</td><td class="center">' . $invoiceUsd . ' USD</td><td class="center">' . $totalWaybillInvoicePrice . '</td></tr></table>'
        . '<table><tr><td class="small">Information on goods filled in by Consignee or by Colibri express on behalf of Shipper</td></tr></table>'
        . '</td></tr></table></div></body></html>';



    $pdfRender = forwarder_add_package_to_container_convert_html_to_pdf($html);
    if (!empty($pdfRender['ok']) && trim((string)($pdfRender['pdf_base64'] ?? '')) !== '') {
        return [
            'ok' => true,
            'error' => '',
            'label_base64' => (string)$pdfRender['pdf_base64'],
            'file_name' => 'waybill_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $track) . '.pdf',
            'invoice_url' => $invoiceUrl,
            'qr_url' => $qrUrl,
            'render_engine' => 'wkhtmltopdf',
        ];
    }

    return [
        'ok' => true,

        'error' => 'html->pdf render unavailable, html label used: ' . (string)($pdfRender['error'] ?? ''),
        'label_base64' => base64_encode($html),
        'file_name' => 'waybill_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $track) . '.html',
        'invoice_url' => $invoiceUrl,
        'qr_url' => $qrUrl,
        'render_engine' => 'html-fallback',
    ];
}

function forwarder_add_package_to_container_select_device_uid(string $directDeviceUid, string $devicesJson, string $deviceKey): array
{
    if ($directDeviceUid !== '') {
        return [
            'device_uid' => $directDeviceUid,
            'error' => '',
            'devices_count' => 1,
            'selected_by' => 'print-device-uid',
        ];
    }

    $normalizedKey = trim($deviceKey);
    if ($normalizedKey !== '' && trim($devicesJson) === '') {
        return [
            'device_uid' => $normalizedKey,
            'error' => '',
            'devices_count' => 1,
            'selected_by' => 'print-device-key-as-uid',
        ];
    }

    $parsed = forwarder_add_package_to_container_parse_json_object($devicesJson);
    $devices = $parsed['devices'] ?? $parsed;
    if (!is_array($devices)) {
        return [
            'device_uid' => '',
            'error' => 'print devices list is invalid or empty',
            'devices_count' => 0,
            'selected_by' => '',
        ];
    }

    $normalizedKey = mb_strtolower(trim($deviceKey));
    $firstUid = '';
    $defaultUid = '';
    $matchedUid = '';

    foreach ($devices as $device) {
        if (!is_array($device)) {
            continue;
        }

        $uid = trim((string)($device['device_uid'] ?? $device['uid'] ?? $device['id'] ?? ''));
        if ($uid === '') {
            continue;
        }

        if ($firstUid === '') {
            $firstUid = $uid;
        }

        $isDefault = !empty($device['default']) || !empty($device['is_default']);
        if ($isDefault && $defaultUid === '') {
            $defaultUid = $uid;
        }

        if ($normalizedKey !== '') {
            $candidates = [
                mb_strtolower(trim((string)($device['key'] ?? ''))),
                mb_strtolower(trim((string)($device['code'] ?? ''))),
                mb_strtolower(trim((string)($device['name'] ?? ''))),
                mb_strtolower(trim($uid)),
            ];
            if (in_array($normalizedKey, $candidates, true)) {
                $matchedUid = $uid;
                break;
            }
        }
    }

    $chosen = $matchedUid !== '' ? $matchedUid : ($defaultUid !== '' ? $defaultUid : $firstUid);
    if ($chosen === '') {
        return [
            'device_uid' => '',
            'error' => 'no usable devices in print list',
            'devices_count' => count($devices),
            'selected_by' => '',
        ];
    }

    return [
        'device_uid' => $chosen,
        'error' => '',
        'devices_count' => count($devices),
        'selected_by' => $matchedUid !== '' ? 'print-device-key' : ($defaultUid !== '' ? 'default-device' : 'first-device'),
    ];
}

/** @param array<string,mixed> $payload */
function forwarder_add_package_to_container_send_print_job(string $printUrl, string $printToken, array $payload): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'http_status' => 0,
            'error' => 'curl extension is not available',
            'response' => null,
        ];
    }

    $ch = curl_init($printUrl);
    if ($ch === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'error' => 'curl_init failed',
            'response' => null,
        ];
    }

    $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($rawPayload === false) {
        return [
            'ok' => false,
            'http_status' => 0,
            'error' => 'failed to encode print payload',
            'response' => null,
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $printToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $rawPayload,
    ]);

    $rawResponse = curl_exec($ch);
    $error = $rawResponse === false ? (string)curl_error($ch) : '';
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'error' => $error !== '' ? $error : 'curl_exec failed',
            'response' => null,
        ];
    }

    $json = forwarder_add_package_to_container_parse_json_object((string)$rawResponse);
    $status = mb_strtolower(trim((string)($json['status'] ?? '')));
    $ok = $httpStatus >= 200 && $httpStatus < 300 && in_array($status, ['ok', 'success'], true);

    return [
        'ok' => $ok,
        'http_status' => $httpStatus,
        'error' => '',
        'response' => $json !== [] ? $json : (string)$rawResponse,
    ];
}

$args = forwarder_add_package_to_container_is_cli()
    ? forwarder_add_package_to_container_cli_kv($_SERVER['argv'] ?? [])
    : forwarder_add_package_to_container_request_kv();

$normalizedBaseUrl = forwarder_add_package_to_container_normalize_base_url(
    forwarder_add_package_to_container_arg($args, 'base-url', 'base_url')
);

forwarder_add_package_to_container_set_env('DEV_COLIBRI_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_to_container_set_env('DEV_COLIBRI_LOGIN', forwarder_add_package_to_container_arg($args, 'login'));
forwarder_add_package_to_container_set_env('DEV_COLIBRI_PASSWORD', forwarder_add_package_to_container_arg($args, 'password'));
forwarder_add_package_to_container_set_env('FORWARDER_BASE_URL', $normalizedBaseUrl);
forwarder_add_package_to_container_set_env('FORWARDER_LOGIN', forwarder_add_package_to_container_arg($args, 'login'));
forwarder_add_package_to_container_set_env('FORWARDER_PASSWORD', forwarder_add_package_to_container_arg($args, 'password'));
forwarder_add_package_to_container_set_env('FORWARDER_SESSION_FILE', forwarder_add_package_to_container_arg($args, 'session-file', 'session_file'));
forwarder_add_package_to_container_set_env('FORWARDER_SESSION_TTL_SECONDS', forwarder_add_package_to_container_arg($args, 'session-ttl-seconds', 'session_ttl_seconds'));

$track = forwarder_add_package_to_container_arg($args, 'track', 'number', 'tracking', 'tracking_number');
$position = forwarder_add_package_to_container_arg($args, 'position', 'container', 'container_id');
$checkPath = forwarder_add_package_to_container_arg($args, 'check-path', 'check_path');
$changePath = forwarder_add_package_to_container_arg($args, 'change-path', 'change_path');
$verifyPath = forwarder_add_package_to_container_arg($args, 'verify-path', 'verify_path');
$verifyRequested = forwarder_add_package_to_container_as_bool(
    forwarder_add_package_to_container_arg($args, 'verify-check-package', 'verify_check_package')
);

$printRequested = forwarder_add_package_to_container_as_bool(
    forwarder_add_package_to_container_arg($args, 'print-label', 'print_label')
);
$printUrl = forwarder_add_package_to_container_arg($args, 'print-url', 'print_url');
$printToken = forwarder_add_package_to_container_arg($args, 'print-token', 'print_token');
$printDeviceUid = forwarder_add_package_to_container_arg($args, 'print-device-uid', 'print_device_uid');
$printDevicesJson = forwarder_add_package_to_container_arg($args, 'print-devices-json', 'print_devices_json');
$printDeviceKey = forwarder_add_package_to_container_arg($args, 'print-device-key', 'print_device_key');
$printFileName = forwarder_add_package_to_container_arg($args, 'print-file-name', 'print_file_name');
$labelBase64Arg = forwarder_add_package_to_container_arg($args, 'label-base64', 'label_base64');

$printLabelRetries = max(0, forwarder_add_package_to_container_as_int(
    forwarder_add_package_to_container_arg($args, 'print-label-retries', 'print_label_retries'),
    2
));
$printLabelRetryDelayMs = max(0, forwarder_add_package_to_container_as_int(
    forwarder_add_package_to_container_arg($args, 'print-label-retry-delay-ms', 'print_label_retry_delay_ms'),
    700
));
$labelUrlArg = forwarder_add_package_to_container_arg($args, 'label-url', 'label_url');
$labelUrlBaseArg = forwarder_add_package_to_container_arg($args, 'label-url-base', 'label_url_base');
$allowLabelUrl = forwarder_add_package_to_container_as_bool(
    forwarder_add_package_to_container_arg($args, 'allow-label-url', 'allow_label_url')
);
$checkPath = $checkPath !== '' ? $checkPath : '/collect/check-position';
$changePath = $changePath !== '' ? $changePath : '/collect/change-position';
$verifyPath = $verifyPath !== '' ? $verifyPath : '/collector/check-package';
$collectorPagePath = '/collector/packages';

if ($track === '') {
    forwarder_add_package_to_container_emit_error_and_exit('missing required track', 2);
}

if ($position === '') {
    forwarder_add_package_to_container_emit_error_and_exit('missing required position', 2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    forwarder_add_package_to_container_emit_error_and_exit('missing config (base-url/login/password)', 3);
}

$correlationId = 'run-add-package-to-container-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$logger = new ForwarderLogger($correlationId);
$httpClient = new ForwarderHttpClient($config);
$session = new SessionManager($config->sessionCookieFile(), $config->sessionTtlSeconds());
$loginService = new LoginService($config, $httpClient, $session, $logger);
$sessionClient = new ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);

$checkPayload = ['position' => $position];
$checkResponse = $sessionClient->requestWithSession('POST', $checkPath, $checkPayload, true);
$checkStatusCode = (int)($checkResponse['status_code'] ?? 0);
if ($checkStatusCode === 419) {
    $sessionClient->requestWithSession('GET', $collectorPagePath, [], false);
    $checkResponse = $sessionClient->requestWithSession('POST', $checkPath, $checkPayload, true);
}
$checkStatusCode = (int)($checkResponse['status_code'] ?? 0);
$checkJson = is_array($checkResponse['json'] ?? null)
    ? $checkResponse['json']
    : json_decode((string)($checkResponse['body'] ?? ''), true);
$checkCase = is_array($checkJson) ? mb_strtolower(trim((string)($checkJson['case'] ?? ''))) : '';
$checkBusinessOk = is_array($checkJson) && in_array($checkCase, ['success', 'warning'], true);
$checkOk = !empty($checkResponse['ok']) && $checkStatusCode >= 200 && $checkStatusCode < 400 && $checkBusinessOk;

$changePayload = [
    'track' => $track,
    'position' => $position,
];

$changeResponse = $sessionClient->requestWithSession('POST', $changePath, $changePayload, false);
$changeStatusCode = (int)($changeResponse['status_code'] ?? 0);
$changeJson = is_array($changeResponse['json'] ?? null)
    ? $changeResponse['json']
    : json_decode((string)($changeResponse['body'] ?? ''), true);
$changeCase = is_array($changeJson) ? mb_strtolower(trim((string)($changeJson['case'] ?? ''))) : '';
$changeBusinessOk = is_array($changeJson) && in_array($changeCase, ['success', 'warning'], true);
$changeOk = !empty($changeResponse['ok']) && $changeStatusCode >= 200 && $changeStatusCode < 400 && $changeBusinessOk;

$verifyResponsePayload = null;
$verifyJson = null;
if ($verifyRequested) {
    $verifyNumber = forwarder_add_package_to_container_arg($args, 'verify-number', 'verify_number', 'check-number', 'check_number');
    if ($verifyNumber === '') {
        $verifyNumber = $track;
    }

    $verifyResponse = $sessionClient->requestWithSession('POST', $verifyPath, ['number' => $verifyNumber], true);
    $verifyResponsePayload = [
        'http_ok' => !empty($verifyResponse['ok']),
        'http_status' => (int)($verifyResponse['status_code'] ?? 0),
        'error' => (string)($verifyResponse['error'] ?? ''),
        'json' => is_array($verifyResponse['json'] ?? null)
            ? $verifyResponse['json']
            : json_decode((string)($verifyResponse['body'] ?? ''), true),
        'raw_body' => (string)($verifyResponse['body'] ?? ''),
    ];
    $verifyJson = is_array($verifyResponsePayload['json'] ?? null) ? $verifyResponsePayload['json'] : null;
}

$overallOk = $checkOk && $changeOk;


$printResponsePayload = null;
if ($printRequested) {
    $printUrl = $printUrl !== '' ? $printUrl : 'https://tls.cargocells.com/api/print/submit.php';
    $printFileName = $printFileName !== '' ? $printFileName : sprintf('label_%s.html', $track);

    $selectedDevice = forwarder_add_package_to_container_select_device_uid($printDeviceUid, $printDevicesJson, $printDeviceKey);
    $selectedDeviceUid = trim((string)($selectedDevice['device_uid'] ?? ''));

    $dataUrlImages = [];
    forwarder_add_package_to_container_collect_data_url_images($labelBase64Arg, $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images(is_array($verifyJson) ? ($verifyJson['label_base64'] ?? '') : '', $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images(is_array($changeJson) ? ($changeJson['label_base64'] ?? '') : '', $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images(is_array($checkJson) ? ($checkJson['label_base64'] ?? '') : '', $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images($verifyJson, $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images($verifyJson, $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images($changeJson, $dataUrlImages);
    forwarder_add_package_to_container_collect_data_url_images($checkJson, $dataUrlImages);
    $dataUrlImages = array_values(array_unique($dataUrlImages));

    $savedLabel = forwarder_add_package_to_container_save_label_images($track, $dataUrlImages);
    $resolvedPrintFileName = trim((string)($savedLabel['print_file_name'] ?? ''));
    if ($resolvedPrintFileName !== '') {
        $printFileName = $resolvedPrintFileName;
    }

    $labelBase64 = forwarder_add_package_to_container_pick_first_non_empty([
        (string)($savedLabel['print_label_base64'] ?? ''),
        forwarder_add_package_to_container_normalize_label_base64(
            is_array($verifyJson) ? (string)($verifyJson['label_base64'] ?? '') : ''
        ),
        forwarder_add_package_to_container_normalize_label_base64(
            is_array($changeJson) ? (string)($changeJson['label_base64'] ?? '') : ''
        ),
        forwarder_add_package_to_container_normalize_label_base64(
            is_array($checkJson) ? (string)($checkJson['label_base64'] ?? '') : ''
        ),
        forwarder_add_package_to_container_normalize_label_base64($labelBase64Arg),
    ]);
    $publicBaseUrl = forwarder_add_package_to_container_resolve_public_base_url(
        $labelUrlBaseArg !== '' ? $labelUrlBaseArg : $config->baseUrl()
    );
    $labelUrl = forwarder_add_package_to_container_pick_first_non_empty([
        forwarder_add_package_to_container_normalize_label_url($labelUrlArg, $publicBaseUrl),
        forwarder_add_package_to_container_normalize_label_url(is_array($verifyJson) ? (string)($verifyJson['label_url'] ?? '') : '', $publicBaseUrl),
        forwarder_add_package_to_container_normalize_label_url(is_array($verifyJson) ? (string)($verifyJson['invoice_doc'] ?? '') : '', $publicBaseUrl),
        forwarder_add_package_to_container_normalize_label_url(is_array($verifyJson) ? (string)($verifyJson['return_label_doc'] ?? '') : '', $publicBaseUrl),
        forwarder_add_package_to_container_normalize_label_url(
            is_array($verifyJson) && is_array($verifyJson['package'] ?? null)
                ? (string)($verifyJson['package']['invoice_doc'] ?? '')
                : '',
            $publicBaseUrl
        ),
        forwarder_add_package_to_container_normalize_label_url(
            is_array($verifyJson) && is_array($verifyJson['package'] ?? null)
                ? (string)($verifyJson['package']['return_label_doc'] ?? '')
                : '',
            $publicBaseUrl
        ),
        forwarder_add_package_to_container_normalize_label_url(is_array($changeJson) ? (string)($changeJson['label_url'] ?? '') : '', $publicBaseUrl),
        forwarder_add_package_to_container_normalize_label_url(is_array($checkJson) ? (string)($checkJson['label_url'] ?? '') : '', $publicBaseUrl),
    ]);

    if ($labelUrl !== '') {
        $labelPath = (string)(parse_url($labelUrl, PHP_URL_PATH) ?? '');
        $labelName = basename($labelPath);
        if ($labelName !== '' && preg_match('/\.(pdf|png|jpe?g|txt|zpl|epl|lbl)$/i', $labelName) === 1) {
            $printFileName = $labelName;
        }
    }
    $labelUrlStorage = [
        'ok' => false,
        'error' => $allowLabelUrl ? 'label url is empty' : 'label url flow disabled by default',
        'saved_file' => null,
    ];
    if ($allowLabelUrl && $labelUrl !== '') {
        $labelUrlStorage = forwarder_add_package_to_container_save_label_from_url($track, $labelUrl);
    }

    $generatedWaybill = forwarder_add_package_to_container_build_html_label_from_verify($verifyJson, $track, $publicBaseUrl);
    if ($labelBase64 === '' && !empty($generatedWaybill['ok']) && trim((string)($generatedWaybill['label_base64'] ?? '')) !== '') {
        $labelBase64 = (string)$generatedWaybill['label_base64'];
        $generatedFileName = trim((string)($generatedWaybill['file_name'] ?? ''));
        if ($generatedFileName !== '') {
            $printFileName = $generatedFileName;
        }
    }
    $labelProbeAttempts = [];

    if ($labelBase64 === '' && (!$allowLabelUrl || $labelUrl === '') && $overallOk && $printLabelRetries > 0) {
        for ($attempt = 1; $attempt <= $printLabelRetries; $attempt++) {
            if ($printLabelRetryDelayMs > 0) {
                usleep($printLabelRetryDelayMs * 1000);
            }

            $retryVerifyResponse = $sessionClient->requestWithSession('POST', $verifyPath, ['number' => $track], true);
            $retryVerifyJson = is_array($retryVerifyResponse['json'] ?? null)
                ? $retryVerifyResponse['json']
                : json_decode((string)($retryVerifyResponse['body'] ?? ''), true);

            $candidate = forwarder_add_package_to_container_pick_first_non_empty([
                forwarder_add_package_to_container_normalize_label_base64(
                    is_array($retryVerifyJson) ? (string)($retryVerifyJson['label_base64'] ?? '') : ''
                ),
                forwarder_add_package_to_container_normalize_label_base64($labelBase64Arg),
            ]);
            $candidateUrl = forwarder_add_package_to_container_pick_first_non_empty([
                forwarder_add_package_to_container_normalize_label_url(
                    is_array($retryVerifyJson) ? (string)($retryVerifyJson['label_url'] ?? '') : '',
                    $publicBaseUrl
                ),
                forwarder_add_package_to_container_normalize_label_url(
                    is_array($retryVerifyJson) ? (string)($retryVerifyJson['invoice_doc'] ?? '') : '',
                    $publicBaseUrl
                ),
                forwarder_add_package_to_container_normalize_label_url(
                    is_array($retryVerifyJson) && is_array($retryVerifyJson['package'] ?? null)
                        ? (string)($retryVerifyJson['package']['invoice_doc'] ?? '')
                        : '',
                    $publicBaseUrl
                ),
                forwarder_add_package_to_container_normalize_label_url($labelUrlArg, $publicBaseUrl),
            ]);

            $labelProbeAttempts[] = [
                'attempt' => $attempt,
                'http_ok' => !empty($retryVerifyResponse['ok']),
                'http_status' => (int)($retryVerifyResponse['status_code'] ?? 0),
                'label_found' => $candidate !== '',
                'label_url_found' => $candidateUrl !== '',
            ];

            if ($candidate !== '' || $candidateUrl !== '') {
                $labelBase64 = $candidate;
                $labelUrl = $candidateUrl;
                $verifyJson = is_array($retryVerifyJson) ? $retryVerifyJson : $verifyJson;
                break;
            }
        }
    }

    if (
        $overallOk
        && $printToken !== ''
        && $selectedDeviceUid !== ''
        && ($labelBase64 !== '' || ($allowLabelUrl && $labelUrl !== ''))
    ) {
        $printPayload = [
            'device_uid' => $selectedDeviceUid,
            'file_name' => $printFileName,
        ];
        if ($labelBase64 !== '') {
            $printPayload['label_base64'] = $labelBase64;
        }
        if ($allowLabelUrl && $labelUrl !== '') {
            $printPayload['label_url'] = $labelUrl;
        }
        $printResponsePayload = forwarder_add_package_to_container_send_print_job($printUrl, $printToken, $printPayload);
        $printResponsePayload['selected_device'] = $selectedDevice;
        $printResponsePayload['label_storage'] = $savedLabel;
        $printResponsePayload['label_url_storage'] = $labelUrlStorage;
        $printResponsePayload['label_url'] = $labelUrl;
        $printResponsePayload['generated_waybill'] = $generatedWaybill;
        $printResponsePayload['label_probe'] = [
            'retries' => $printLabelRetries,
            'delay_ms' => $printLabelRetryDelayMs,
            'attempts' => $labelProbeAttempts,
        ];
    } else {
        $printResponsePayload = [
            'ok' => false,
            'http_status' => 0,
            'error' => 'print skipped: require successful add-flow, print-token, selected device and html/base64 label (url-label only with --allow-label-url=1)',
            'response' => null,
            'selected_device' => $selectedDevice,
            'label_storage' => $savedLabel,
            'label_url_storage' => $labelUrlStorage,
            'label_url' => $labelUrl,
            'generated_waybill' => $generatedWaybill,
            'label_probe' => [
                'retries' => $printLabelRetries,
                'delay_ms' => $printLabelRetryDelayMs,
                'attempts' => $labelProbeAttempts,
            ],
        ];
    }
}

$result = [
    'status' => $overallOk ? 'ok' : 'error',
    'message' => $overallOk
        ? 'Package was added to container position'
        : 'Failed to add package to container position',
    'correlation_id' => $correlationId,
    'base_url' => $config->baseUrl(),
    'check_path' => $checkPath,
    'change_path' => $changePath,
    'track' => $track,
    'position' => $position,
    'check' => [
        'http_ok' => !empty($checkResponse['ok']),
        'http_status' => $checkStatusCode,
        'case' => is_array($checkJson) ? (string)($checkJson['case'] ?? '') : '',
        'change' => is_array($checkJson) ? ($checkJson['change'] ?? null) : null,
        'content' => is_array($checkJson) ? (string)($checkJson['content'] ?? '') : '',
        'sum' => is_array($checkJson) ? ($checkJson['sum'] ?? null) : null,
        'error' => (string)($checkResponse['error'] ?? ''),
        'json' => $checkJson,
        'raw_body' => (string)($checkResponse['body'] ?? ''),
    ],
    'change' => [
        'http_ok' => !empty($changeResponse['ok']),
        'http_status' => $changeStatusCode,
        'case' => is_array($changeJson) ? (string)($changeJson['case'] ?? '') : '',
        'change' => is_array($changeJson) ? ($changeJson['change'] ?? null) : null,
        'content' => is_array($changeJson) ? (string)($changeJson['content'] ?? '') : '',
        'track' => is_array($changeJson) ? (string)($changeJson['track'] ?? '') : '',
        'weight' => is_array($changeJson) ? (string)($changeJson['weight'] ?? '') : '',
        'error' => (string)($changeResponse['error'] ?? ''),
        'json' => $changeJson,
        'raw_body' => (string)($changeResponse['body'] ?? ''),
    ],
    'verification' => $verifyResponsePayload,
    'print' => $printResponsePayload,
    'print_allow_label_url' => $allowLabelUrl,
];


if (!forwarder_add_package_to_container_is_cli() && !headers_sent()) {
    http_response_code($overallOk ? 200 : 422);
    header('Content-Type: application/json; charset=utf-8');
}


echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 8);
