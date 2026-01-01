<?php

declare(strict_types=1);

function fetch_users_list(mysqli $dbcnx): array
{
    $users = [];

    $sql = "SELECT id,
                   username,
                   full_name,
                   email,
                   is_active,
                   created_at,
                   last_login_at,
                   login_count
              FROM users
          ORDER BY id";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        $res->free();
    }

    return $users;
}

function build_tool_qr_path_json(string $uid): string
{
    $dir = __DIR__ . '/../img/tools_stock/' . $uid;

    // сначала пробуем собрать то, что уже лежит
    $qr = collect_tool_qr_images($uid, $dir);

    // если пусто — генерим и собираем заново (чтобы попал и qr_raw.png)
    if (!$qr) {
        generate_tool_qr_images($uid, get_subdomain_label());
        $qr = collect_tool_qr_images($uid, $dir);
    }

    if (!$qr) {
        return '{}';
    }

    return json_encode($qr, JSON_UNESCAPED_SLASHES) ?: '{}';
}

/**
 * @param mixed $raw
 */
function parse_tool_photos($raw): array
{
    if (is_array($raw)) {
        return array_values(array_filter(array_map('strval', $raw)));
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded)));
    }

    return [trim($raw)];
}

function fetch_tools_list(mysqli $dbcnx): array
{
    $tools = [];

    $sql = "SELECT id,
                   name,
                   serial_number,
                   registered_at,
                   status
              FROM tool_resources
          ORDER BY id";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $tools[] = $row;
        }
        $res->free();
    }

    return $tools;
}

function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function cm_to_px(float $cm, int $dpi = 300): int
{
    return (int)round($cm * $dpi / 2.54);
}

function get_subdomain_label(): string
{
    $host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $parts = array_filter(explode('.', $host));

    return strtoupper($parts[0] ?? '');
}

function remove_directory_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            remove_directory_recursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function collect_tool_qr_images(string $uid, string $baseDir): array
{
    $result = [];

    foreach (glob($baseDir . '/qr_*.png') as $filePath) {
        $fileName = basename($filePath);

        if (preg_match('/^qr_([^\\.]+)\\.png$/', $fileName, $m)) {
            $suffix        = $m[1];
            $result[$suffix] = sprintf('/img/tools_stock/%s/%s', $uid, $fileName);
        }
    }

    ksort($result);

    return $result;
}

function generate_tool_qr_images(string $uid, string $labelText): array
{
    $baseDir = __DIR__ . '/../img/tools_stock/' . $uid;
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
        error_log("QR: cannot create dir {$baseDir}");
        return [];
    }

    $qrPayload = $uid;
    $rawPath   = $baseDir . '/qr_raw.png';

    $cmd = sprintf(
        'qrencode -o %s -s 8 -l M %s 2>/dev/null',
        escapeshellarg($rawPath),
        escapeshellarg($qrPayload)
    );
    exec($cmd, $out, $ret);
    if ($ret !== 0 || !is_file($rawPath)) {
        error_log("QR: qrencode failed for tool {$uid}, rc={$ret}");
        return [];
    }

    $rawImage = imagecreatefrompng($rawPath);
    if (!$rawImage) {
        error_log("QR: cannot open raw QR for tool {$uid}");
        return [];
    }

    $qrWidth     = imagesx($rawImage);
    $qrHeight    = imagesy($rawImage);
    $labelHeight = 36;
    $canvas      = imagecreatetruecolor($qrWidth, $qrHeight + $labelHeight);

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 0, 0, 0);

    imagefill($canvas, 0, 0, $white);
    imagecopy($canvas, $rawImage, 0, 0, 0, 0, $qrWidth, $qrHeight);

    $font      = 5;
    $text      = $labelText ?: 'QR';
    $textWidth = imagefontwidth($font) * strlen($text);
    $textX     = max(0, (int)(($qrWidth - $textWidth) / 2));
    $textY     = $qrHeight + (int)(($labelHeight - imagefontheight($font)) / 2);
    imagestring($canvas, $font, $textX, $textY, $text, $black);

    $sizesCm = [
        '5x5' => 5.0,
        '3x3' => 3.0,
        '2x2' => 2.0,
    ];

    $result = [];

    foreach ($sizesCm as $suffix => $cm) {
        $targetSize = cm_to_px($cm);
        $resized    = imagecreatetruecolor($targetSize, $targetSize);

        imagefill($resized, 0, 0, $white);
        imagecopyresampled(
            $resized,
            $canvas,
            0,
            0,
            0,
            0,
            $targetSize,
            $targetSize,
            imagesx($canvas),
            imagesy($canvas)
        );

        $fileName = sprintf('qr_%s.png', $suffix);
        $filePath = $baseDir . '/' . $fileName;

        imagepng($resized, $filePath);
        imagedestroy($resized);

        $result[$suffix] = sprintf('/img/tools_stock/%s/%s', $uid, $fileName);
    }

    imagedestroy($canvas);
    imagedestroy($rawImage);

    if (!$result) {
        $result = collect_tool_qr_images($uid, $baseDir);
    }

    return $result;
}

function map_tool_to_template(array $tool): array
{
    //    $qrPathsRaw = $tool['qr_path'] ?? '';
    $qrPathsRaw = $tool['qr_path'] ?? $tool['qr_patch'] ?? '';
    $qrCodes    = [];
    $toolUid    = $tool['uid'] ?? '';

    if ($qrPathsRaw !== '') {
        $decoded = json_decode((string)$qrPathsRaw, true);

        if (is_array($decoded)) {
            foreach ($decoded as $size => $path) {
                if (is_string($path)) {
                    $qrCodes[] = [
                        'size' => (string)$size,
                        'path' => $path,
                    ];
                }
            }
        } elseif (is_string($qrPathsRaw)) {
            $qrCodes[] = [
                'size' => 'default',
                'path' => (string)$qrPathsRaw,
            ];
        }
    }


    if (!$qrCodes && $toolUid !== '') {
        $qrCodes = collect_tool_qr_images($toolUid, __DIR__ . '/../img/tools_stock/' . $toolUid);

        $qrCodes = array_map(
            static function ($path, $size): array {
                return [
                    'size' => (string)$size,
                    'path' => $path,
                ];
            },
            $qrCodes,
            array_keys($qrCodes)
        );
    }

    $formatDate = static function ($value): string {
        if (!$value) {
            return '';
        }

        try {
            $dt = new DateTime($value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    };


    $photos = parse_tool_photos($tool['img_path'] ?? $tool['img_patch'] ?? null);
    return [
        'id'              => $tool['id'] ?? '',
        'NameTool'        => $tool['name'] ?? '',
        'SerialNumber'    => $tool['serial_number'] ?? '',
        'WarantyDays'     => $tool['warranty_days'] ?? 0,
        'PriceBuy'        => $tool['price_buy'] ?? '',
        'DateBuy'         => $formatDate($tool['purchase_date'] ?? ''),
        'AddInSystem'     => $formatDate($tool['registered_at'] ?? ''),
        'ResourceDays'    => $tool['resource_days'] ?? 0,
        'ResourceEndDate' => $formatDate($tool['operational_until'] ?? ''),
        'status'          => $tool['status'] ?? 'active',
        'notes'           => $tool['notes'] ?? '',
        'qr_codes'        => $qrCodes,
        'img_path'        => $photos,
    ];
}

/**
 * Генерирует/пересоздаёт QR-картинку для одного пользователя.
 *
 * - Удаляет все старые файлы вида {id}_qr*.png
 * - Если нет токена в базе — генерирует новый, сохраняет в users.qr_login_token
 * - Создаёт PNG через qrencode
 *
 * Возвращает имя файла (без пути) или null при ошибке.
 */
function ensure_user_qr_image(array $userRow): ?string
{
    global $dbcnx;

    $userId = (int)$userRow['id'];
    $token  = trim((string)($userRow['qr_login_token'] ?? ''));

    // Каталог с картинками
    $qrDir = __DIR__ . '/../img/users/qr';
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0775, true) && !is_dir($qrDir)) {
            error_log("QR: cannot create dir $qrDir");
            return null;
        }
    }

    // Чистим старые файлы для этого пользователя
    foreach (glob($qrDir . '/' . $userId . '_qr*.png') as $old) {
        @unlink($old);
    }

    // Если в БД нет токена — генерим
    if ($token === '') {
        $token = bin2hex(random_bytes(16)); // 32 hex-символа

        $stmt = $dbcnx->prepare("UPDATE users SET qr_login_token = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $token, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Имя файла строго по твоей схеме: {id}_qr{token}.png
    $fileName = $userId . '_qr' . $token . '.png';
    $filePath = $qrDir . '/' . $fileName;

    // Что кодируем в QR — тут можно хоть чистый токен.
    // Потом твой APP по скану отправит этот токен на сервер.
    $payload = $token;

    // qrencode должен быть в PATH. Можно проверить: `which qrencode`
    $cmd = sprintf(
        'qrencode -o %s -s 6 -l M %s 2>/dev/null',
        escapeshellarg($filePath),
        escapeshellarg($payload)
    );
    exec($cmd, $out, $ret);

    if ($ret !== 0 || !is_file($filePath)) {
        error_log("QR: qrencode failed for user $userId, rc=$ret");
        return null;
    }

    return $fileName;
}