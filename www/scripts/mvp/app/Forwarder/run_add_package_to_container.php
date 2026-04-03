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

function forwarder_add_package_to_container_parse_json_object(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

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

$argv = $_SERVER['argv'] ?? [];
$args = forwarder_add_package_to_container_cli_kv($argv);

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

$checkPath = $checkPath !== '' ? $checkPath : '/collect/check-position';
$changePath = $changePath !== '' ? $changePath : '/collect/change-position';
$verifyPath = $verifyPath !== '' ? $verifyPath : '/collector/check-package';
$collectorPagePath = '/collector/packages';

if ($track === '') {
    fwrite(STDERR, "run_add_package_to_container: missing required --track\n");
    exit(2);
}

if ($position === '') {
    fwrite(STDERR, "run_add_package_to_container: missing required --position\n");
    exit(2);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    fwrite(STDERR, "run_add_package_to_container: missing config (base-url/login/password)\n");
    exit(3);
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
        is_array($verifyJson) ? (string)($verifyJson['label_base64'] ?? '') : '',
        is_array($changeJson) ? (string)($changeJson['label_base64'] ?? '') : '',
        is_array($checkJson) ? (string)($checkJson['label_base64'] ?? '') : '',
        $labelBase64Arg,
    ]);

    if (
        $overallOk
        && $printToken !== ''
        && $selectedDeviceUid !== ''
        && $labelBase64 !== ''
        && !empty($savedLabel['ok'])
    ) {
        $printResponsePayload = forwarder_add_package_to_container_send_print_job($printUrl, $printToken, [
            'device_uid' => $selectedDeviceUid,
            'file_name' => $printFileName,
            'label_base64' => $labelBase64,
        ]);
        $printResponsePayload['selected_device'] = $selectedDevice;
        $printResponsePayload['label_storage'] = $savedLabel;
    } else {
        $printResponsePayload = [
            'ok' => false,
            'http_status' => 0,
            'error' => 'print skipped: require success add, print-token, selected device, saved label and label_base64',
            'response' => null,
            'selected_device' => $selectedDevice,
            'label_storage' => $savedLabel,
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
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallOk ? 0 : 8);
