<?php

declare(strict_types=1);

/**
 * CLI-утилита для пошаговой диагностики login/report через cURL.
 *
 * Пример:
 * php www/api/connectors/debug_colibri_curl_login.php \
 *   --login='user@example.com' \
 *   --password='secret' \
 *   --from='2026-01-01' \
 *   --to='2026-03-25'
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Этот скрипт предназначен только для CLI.\n");
    exit(1);
}

function dbg_parse_cli_options(array $argv): array
{
    $parsed = [];
    foreach (array_slice($argv, 1) as $arg) {
        $arg = trim((string)$arg);
        if ($arg === '') {
            continue;
        }

        if (strpos($arg, '--') === 0) {
            $arg = substr($arg, 2);
        }

        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $k = trim((string)$k);
            if ($k !== '') {
                $parsed[$k] = (string)$v;
            }
            continue;
        }

        if ($arg === 'help' || $arg === '-h' || $arg === '--help') {
            $parsed['help'] = '1';
        }
    }

    return $parsed;
}

$options = dbg_parse_cli_options($argv);
if (isset($options['help']) || empty($options['login']) || empty($options['password'])) {
    $script = basename(__FILE__);
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php {$script} login='email' password='pass' [from='YYYY-MM-DD'] [to='YYYY-MM-DD'] [login_field='username'] [password_field='password'] [remember='1']\n");
    fwrite(STDOUT, "  php {$script} --login='email' --password='pass' [--from='YYYY-MM-DD'] [--to='YYYY-MM-DD'] [--login_field='username'] [--password_field='password'] [--remember='1']\n");
    fwrite(STDOUT, "  Если login_field не передан, скрипт попытается автоматически определить поле логина из формы /login.\n");
    fwrite(STDOUT, "Примечание: формат key=value поддерживается специально для запуска в стиле: php {$script} login=... password=...\n");
    exit(isset($options['help']) ? 0 : 1);
}

$login = (string)$options['login'];
$password = (string)$options['password'];
$loginField = isset($options['login_field']) && trim((string)$options['login_field']) !== ''
    ? trim((string)$options['login_field'])
    : '';
$passwordField = isset($options['password_field']) && trim((string)$options['password_field']) !== ''
    ? trim((string)$options['password_field'])
    : 'password';
$remember = isset($options['remember']) ? trim((string)$options['remember']) : '';
$fromDate = isset($options['from']) ? (string)$options['from'] : date('Y-m-d', strtotime('-7 days'));
$toDate = isset($options['to']) ? (string)$options['to'] : date('Y-m-d');

$baseUrl = 'https://dev-backend.colibri.az';
$csrfUrl = $baseUrl . '/login';
$loginUrl = $baseUrl . '/login';
$reportUrl = $baseUrl . '/collector/reports/all_packages';

$cookieJar = [];
$vars = [
    'login' => $login,
    'password' => $password,
    'login_field' => $loginField,
    'password_field' => $passwordField,
    'remember' => $remember,
    'login_field_source' => $loginField !== '' ? 'cli' : 'auto',
    'date_from' => $fromDate,
    'date_to' => $toDate,
    'csrf_token' => '',
    '_token' => '',
    'xsrf_token' => '',
];

function dbg_step(string $title): void
{
    fwrite(STDOUT, "\n========== {$title} ==========" . PHP_EOL);
}

function dbg_print_kv(string $key, $value): void
{
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    fwrite(STDOUT, sprintf("%-24s: %s\n", $key, (string)$value));
}

function dbg_cookie_names(array $cookieJar): array
{
    return array_values(array_map('strval', array_keys($cookieJar)));
}

function dbg_parse_csrf_from_html(string $html): string
{
    $patterns = [
        '/<input\\b[^>]*\\bname\\s*=\\s*["\']_token["\'][^>]*\\bvalue\\s*=\\s*["\']([^"\']+)["\'][^>]*>/iu',
        '/<input\\b[^>]*\\bvalue\\s*=\\s*["\']([^"\']+)["\'][^>]*\\bname\\s*=\\s*["\']_token["\'][^>]*>/iu',
        '/<meta\\b[^>]*\\bname\\s*=\\s*["\']csrf-token["\'][^>]*\\bcontent\\s*=\\s*["\']([^"\']+)["\'][^>]*>/iu',
        '/<meta\\b[^>]*\\bcontent\\s*=\\s*["\']([^"\']+)["\'][^>]*\\bname\\s*=\\s*["\']csrf-token["\'][^>]*>/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            return trim((string)($m[1] ?? ''));
        }
    }

    return '';
}

function dbg_decode_cookie_value(string $value): string
{
    $decoded = rawurldecode($value);
    if ((str_starts_with($decoded, '"') && str_ends_with($decoded, '"')) || (str_starts_with($decoded, "'") && str_ends_with($decoded, "'"))) {
        $decoded = substr($decoded, 1, -1);
    }

    return trim($decoded);
}

function dbg_extract_xsrf(array $cookieJar): string
{
    foreach ($cookieJar as $name => $value) {
        if (strcasecmp((string)$name, 'XSRF-TOKEN') === 0) {
            return dbg_decode_cookie_value((string)$value);
        }
    }

    return '';
}

function dbg_cookie_header(array $cookieJar): string
{
    $pairs = [];
    foreach ($cookieJar as $name => $value) {
        $pairs[] = $name . '=' . $value;
    }

    return implode('; ', $pairs);
}

function dbg_curl_request(string $url, string $method, array $headers = [], array $body = [], array $cookieJar = []): array
{
    $responseHeaders = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $line) use (&$responseHeaders): int {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            $responseHeaders[] = $trimmed;
        }

        return strlen($line);
    });

    if (!empty($cookieJar)) {
        $headers['Cookie'] = dbg_cookie_header($cookieJar);
    }

    if (!empty($headers)) {
        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[] = $k . ': ' . $v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flatHeaders);
    }

    $method = strtoupper(trim($method));
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        }
    }

    $bodyRaw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $redirectCount = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $error = curl_error($ch);
    curl_close($ch);

    if ($bodyRaw === false) {
        throw new RuntimeException('cURL error: ' . $error);
    }

    $updatedCookieJar = $cookieJar;
    foreach ($responseHeaders as $line) {
        if (stripos($line, 'Set-Cookie:') !== 0) {
            continue;
        }

        $cookieLine = trim(substr($line, strlen('Set-Cookie:')));
        $cookiePart = explode(';', $cookieLine, 2)[0] ?? '';
        if ($cookiePart === '' || strpos($cookiePart, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $cookiePart, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '') {
            $updatedCookieJar[$name] = $value;
        }
    }

    return [
        'http_code' => $httpCode,
        'effective_url' => $effectiveUrl,
        'redirect_count' => $redirectCount,
        'body' => (string)$bodyRaw,
        'headers' => $responseHeaders,
        'cookies' => $updatedCookieJar,
    ];
}


function dbg_location_headers(array $headers): array
{
    $locations = [];
    foreach ($headers as $line) {
        if (stripos((string)$line, 'Location:') === 0) {
            $locations[] = trim(substr((string)$line, strlen('Location:')));
        }
    }

    return $locations;
}

function dbg_header_values(array $headers, string $headerName): array
{
    $values = [];
    $needle = strtolower(trim($headerName)) . ':';
    foreach ($headers as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        if (str_starts_with(strtolower($line), $needle)) {
            $values[] = trim(substr($line, strlen($needle)));
        }
    }

    return $values;
}

function dbg_detect_download_filename(array $headers): string
{
    $contentDisposition = (string)(dbg_header_values($headers, 'Content-Disposition')[0] ?? '');
    if ($contentDisposition === '') {
        return '';
    }

    if (preg_match("/filename\\*\\s*=\\s*UTF-8''([^;]+)/iu", $contentDisposition, $m)) {
        return trim(rawurldecode((string)($m[1] ?? '')));
    }
    if (preg_match('/filename\\s*=\\s*"([^"]+)"/iu', $contentDisposition, $m)) {
        return trim((string)($m[1] ?? ''));
    }
    if (preg_match('/filename\\s*=\\s*([^;]+)/iu', $contentDisposition, $m)) {
        return trim(trim((string)($m[1] ?? ''), "\"' "));
    }

    return '';
}

function dbg_is_binary_download(array $headers, string $body): bool
{
    $contentType = strtolower((string)(dbg_header_values($headers, 'Content-Type')[0] ?? ''));
    if ($contentType !== '') {
        foreach ([
            'application/vnd.openxmlformats-officedocument',
            'application/vnd.ms-excel',
            'text/csv',
            'application/octet-stream',
            'application/zip',
        ] as $needle) {
            if (strpos($contentType, $needle) !== false) {
                return true;
            }
        }
    }

    $contentDisposition = strtolower((string)(dbg_header_values($headers, 'Content-Disposition')[0] ?? ''));
    if (strpos($contentDisposition, 'attachment') !== false) {
        return true;
    }

    if ($body !== '' && str_starts_with($body, 'PK')) {
        return true;
    }

    return false;
}

function dbg_detect_login_fields(string $html): array
{
    $action = '';
    $inputNames = [];
    $candidateLoginFields = [];

    if (preg_match('/<form\\b[^>]*\\baction\\s*=\\s*["\']([^"\']+)["\'][^>]*>/iu', $html, $formMatch)) {
        $action = trim((string)($formMatch[1] ?? ''));
    }

    if (preg_match_all('/<input\\b[^>]*>/iu', $html, $inputMatches)) {
        foreach ((array)($inputMatches[0] ?? []) as $tag) {
            $name = '';
            $type = 'text';

            if (preg_match('/\\bname\\s*=\\s*["\']([^"\']+)["\']/iu', (string)$tag, $mName)) {
                $name = trim((string)($mName[1] ?? ''));
            }
            if (preg_match('/\\btype\\s*=\\s*["\']([^"\']+)["\']/iu', (string)$tag, $mType)) {
                $type = strtolower(trim((string)($mType[1] ?? 'text')));
            }

            if ($name === '') {
                continue;
            }
            $inputNames[] = $name;
            if (in_array($name, ['_token', 'password', 'remember'], true)) {
                continue;
            }
            if (in_array($type, ['text', 'email'], true)) {
                $candidateLoginFields[] = $name;
            }
        }
    }

    return [
        'form_action' => $action,
        'input_names' => array_values(array_unique($inputNames)),
        'candidate_login_fields' => array_values(array_unique($candidateLoginFields)),
    ];
}
function dbg_detect_report_form_token(string $html, string $fallback = ''): string
{
    if (
        preg_match('/<form\\b[^>]*\\baction\\s*=\\s*["\'][^"\']*\\/collector\\/reports\\/all_packages[^"\']*["\'][^>]*>(.*?)<\\/form>/isu', $html, $formMatch) === 1
        && isset($formMatch[1])
    ) {
        $token = dbg_parse_csrf_from_html((string)$formMatch[1]);
        if ($token !== '') {
            return $token;
        }
    }

    $token = dbg_parse_csrf_from_html($html);
    return $token !== '' ? $token : $fallback;
}

try {
    dbg_step('STEP 1: CSRF preflight GET /login');
    $csrfResponse = dbg_curl_request(
        $csrfUrl,
        'GET',
        [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => $csrfUrl,
        ],
        [],
        $cookieJar
    );

    $cookieJar = $csrfResponse['cookies'];
    $csrfToken = dbg_parse_csrf_from_html((string)$csrfResponse['body']);
    $xsrfToken = dbg_extract_xsrf($cookieJar);
    $loginFormMeta = dbg_detect_login_fields((string)$csrfResponse['body']);

    if ($vars['login_field'] === '') {
        $vars['login_field'] = (string)($loginFormMeta['candidate_login_fields'][0] ?? 'email');
    }
    if ($csrfToken !== '') {
        $vars['csrf_token'] = $csrfToken;
        $vars['_token'] = $csrfToken;
    }
    if ($xsrfToken !== '') {
        $vars['xsrf_token'] = $xsrfToken;
        if ($vars['csrf_token'] === '') {
            $vars['csrf_token'] = $xsrfToken;
            $vars['_token'] = $xsrfToken;
        }
    }

    dbg_print_kv('http_code', $csrfResponse['http_code']);
    dbg_print_kv('redirect_count', $csrfResponse['redirect_count']);
    dbg_print_kv('effective_url', $csrfResponse['effective_url']);
    dbg_print_kv('location_headers', dbg_location_headers($csrfResponse['headers']));
    dbg_print_kv('cookies_count', count($cookieJar));
    dbg_print_kv('cookie_names', dbg_cookie_names($cookieJar));
    dbg_print_kv('login_form_action', $loginFormMeta['form_action']);
    dbg_print_kv('login_form_inputs', $loginFormMeta['input_names']);
    dbg_print_kv('candidate_login_fields', $loginFormMeta['candidate_login_fields']);
    dbg_print_kv('login_field_selected', $vars['login_field']);
    dbg_print_kv('login_field_source', $vars['login_field_source']);
    dbg_print_kv('csrf_token_found', $vars['csrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('xsrf_token_found', $vars['xsrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('body_preview', mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$csrfResponse['body']) ?? ''), 0, 260, 'UTF-8'));

    dbg_step('STEP 2: POST /login');

    $loginPayload = [
        '_token' => $vars['_token'],
        $vars['login_field'] => $vars['login'],
        $vars['password_field'] => $vars['password'],
    ];
    if ($vars['remember'] !== '') {
        $loginPayload['remember'] = $vars['remember'];
    }

    $loginResponse = dbg_curl_request(
        $loginUrl,
        'POST',
        [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => $baseUrl,
            'Referer' => $csrfUrl,
            'X-CSRF-TOKEN' => $vars['csrf_token'],
            'X-XSRF-TOKEN' => $vars['xsrf_token'],
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
        $loginPayload,
        $cookieJar
    );

    $cookieJar = $loginResponse['cookies'];
    $loginCsrf = dbg_parse_csrf_from_html((string)$loginResponse['body']);
    $loginXsrf = dbg_extract_xsrf($cookieJar);
    if ($loginCsrf !== '') {
        $vars['csrf_token'] = $loginCsrf;
        $vars['_token'] = $loginCsrf;
    }
    if ($loginXsrf !== '') {
        $vars['xsrf_token'] = $loginXsrf;
    }

    dbg_print_kv('http_code', $loginResponse['http_code']);
    dbg_print_kv('redirect_count', $loginResponse['redirect_count']);
    dbg_print_kv('effective_url', $loginResponse['effective_url']);
    dbg_print_kv('location_headers', dbg_location_headers($loginResponse['headers']));
    dbg_print_kv('cookies_count', count($cookieJar));
    dbg_print_kv('cookie_names', dbg_cookie_names($cookieJar));
    dbg_print_kv('login_payload_keys', array_keys($loginPayload));
    dbg_print_kv('csrf_token_after_login', $vars['csrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('xsrf_token_after_login', $vars['xsrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('body_preview', mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$loginResponse['body']) ?? ''), 0, 260, 'UTF-8'));

    dbg_step('STEP 3: CSRF preflight GET report endpoint');
    $reportGetResponse = dbg_curl_request(
        $reportUrl,
        'GET',
        [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => $baseUrl . '/',
        ],
        [],
        $cookieJar
    );

    $cookieJar = $reportGetResponse['cookies'];
    $reportXsrf = dbg_extract_xsrf($cookieJar);
    if ($reportXsrf !== '') {
        $vars['xsrf_token'] = $reportXsrf;
    }
    $reportFormToken = dbg_detect_report_form_token((string)$reportGetResponse['body'], (string)$vars['_token']);
    if ($reportFormToken !== '') {
        $vars['csrf_token'] = $reportFormToken;
        $vars['_token'] = $reportFormToken;
    }

    dbg_print_kv('http_code', $reportGetResponse['http_code']);
    dbg_print_kv('redirect_count', $reportGetResponse['redirect_count']);
    dbg_print_kv('effective_url', $reportGetResponse['effective_url']);
    dbg_print_kv('location_headers', dbg_location_headers($reportGetResponse['headers']));
    dbg_print_kv('cookies_count', count($cookieJar));
    dbg_print_kv('cookie_names', dbg_cookie_names($cookieJar));
    dbg_print_kv('csrf_token_after_report_get', $vars['csrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('xsrf_token_after_report_get', $vars['xsrf_token'] !== '' ? 'yes' : 'no');
    dbg_print_kv('body_preview', mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$reportGetResponse['body']) ?? ''), 0, 260, 'UTF-8'));

    dbg_step('STEP 4: POST report endpoint');
    $reportResponse = dbg_curl_request(
        $reportUrl,
        'POST',
        [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Referer' => $reportUrl,
            'X-CSRF-TOKEN' => $vars['csrf_token'],
            'X-XSRF-TOKEN' => $vars['xsrf_token'],
            'Accept' => '*/*',
            'X-Requested-With' => 'XMLHttpRequest',
        ],
        [
            '_token' => $vars['_token'],
            'from_date' => $vars['date_from'],
            'to_date' => $vars['date_to'],
        ],
        $cookieJar
    );

    dbg_print_kv('http_code', $reportResponse['http_code']);
    dbg_print_kv('redirect_count', $reportResponse['redirect_count']);
    dbg_print_kv('effective_url', $reportResponse['effective_url']);
    dbg_print_kv('location_headers', dbg_location_headers($reportResponse['headers']));
    dbg_print_kv('response_headers_count', count($reportResponse['headers']));
    dbg_print_kv('content_type_headers', dbg_header_values($reportResponse['headers'], 'Content-Type'));
    dbg_print_kv('content_disposition_headers', dbg_header_values($reportResponse['headers'], 'Content-Disposition'));
    dbg_print_kv('body_preview', mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$reportResponse['body']) ?? ''), 0, 400, 'UTF-8'));

    $downloadSaved = false;
    if ((int)$reportResponse['http_code'] === 200 && dbg_is_binary_download($reportResponse['headers'], (string)$reportResponse['body'])) {
        $filename = dbg_detect_download_filename($reportResponse['headers']);
        if ($filename === '') {
            $filename = 'all_packages_' . date('Ymd_His') . '.xlsx';
        }
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.xlsx';
        }

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        $targetPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFilename;
        $bytes = file_put_contents($targetPath, (string)$reportResponse['body']);
        if ($bytes !== false && $bytes > 0) {
            $downloadSaved = true;
            dbg_print_kv('download_saved', 'yes');
            dbg_print_kv('download_path', $targetPath);
            dbg_print_kv('download_bytes', $bytes);
        }
    }

    if (!$downloadSaved) {
        dbg_print_kv('download_saved', 'no');
        dbg_print_kv('download_hint', 'Ответ не распознан как файл; проверьте headers/body и redirect_to в логах.');
    }
    dbg_step('DONE');
    dbg_print_kv('final_status', 'ok');
    exit(0);
} catch (Throwable $e) {
    dbg_step('FAILED');
    dbg_print_kv('error', $e->getMessage());
    exit(1);
}
