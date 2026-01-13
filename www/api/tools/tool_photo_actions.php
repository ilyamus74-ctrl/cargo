<?php
declare(strict_types=1);

/**
 * Обработчик действий с фотографиями инструментов
 * Actions: upload_tool_photo
 */

// Доступны: $action, $user, $dbcnx, $smarty

$response = ['status' => 'error', 'message' => 'Unknown tool photo action'];

switch ($action) {
    case 'upload_tool_photo':
        $toolId = (int)($_POST['tool_id'] ?? 0);

        if ($toolId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'tool_id required',
            ];
            break;
        }

        if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $response = [
                'status'  => 'error',
                'message' => 'Файл изображения не получен',
            ];
            break;
        }

        $stmtTool = $dbcnx->prepare('SELECT id, uid, img_path FROM tool_resources WHERE id = ? LIMIT 1');
        if (!$stmtTool) {
            throw new RuntimeException('DB prepare error (upload_tool_photo select)');
        }

        $stmtTool->bind_param('i', $toolId);
        $stmtTool->execute();
        $resTool = $stmtTool->get_result();
        $toolRow = $resTool ? $resTool->fetch_assoc() : null;
        $stmtTool->close();

        if (!$toolRow || empty($toolRow['uid'])) {
            $response = [
                'status'  => 'error',
                'message' => 'Инструмент не найден',
            ];
            break;
        }

        $tmpName = $_FILES['photo']['tmp_name'];
        $fileInfo = @getimagesize($tmpName);
        if (!$fileInfo || ($fileInfo[2] ?? 0) === IMAGETYPE_UNKNOWN) {
            $response = [
                'status'  => 'error',
                'message' => 'Файл не является изображением',
            ];
            break;
        }

        $imgData = file_get_contents($tmpName);
        $srcImage = @imagecreatefromstring($imgData);

        if (!$srcImage) {
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось прочитать изображение',
            ];
            break;
        }

        $srcWidth  = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        $maxSide   = 1600;

        $scale = ($srcWidth > $maxSide || $srcHeight > $maxSide)
            ? min($maxSide / max($srcWidth, 1), $maxSide / max($srcHeight, 1))
            : 1;

        $dstWidth  = (int)max(1, round($srcWidth * $scale));
        $dstHeight = (int)max(1, round($srcHeight * $scale));

        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        $toolUid    = $toolRow['uid'];
        $photoDir   = dirname(__DIR__, 2) . '/img/tools_stock/' . $toolUid . '/photo';
        $photoName  = 'photo_' . date('Ymd_His') . '.jpg';
        $photoPath  = $photoDir . '/' . $photoName;
        $publicPath = sprintf('/img/tools_stock/%s/photo/%s', $toolUid, $photoName);

        if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
            $response = [
                'status'  => 'error',
                'message' => 'Не удалось создать каталог для фото',
            ];
            imagedestroy($dstImage);
            imagedestroy($srcImage);
            break;
        }

        imagejpeg($dstImage, $photoPath, 90);

        imagedestroy($dstImage);
        imagedestroy($srcImage);

        $photos     = parse_tool_photos($toolRow['img_path'] ?? null);
        $photos[]   = $publicPath;
        $photos     = array_values(array_unique($photos));
        $photosJson = json_encode($photos, JSON_UNESCAPED_SLASHES);

        $stmtUpdate = $dbcnx->prepare('UPDATE tool_resources SET img_path = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('DB prepare error (upload_tool_photo update)');
        }

        $stmtUpdate->bind_param('si', $photosJson, $toolId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        $response = [
            'status'  => 'ok',
            'message' => 'Фото добавлено',
            'photos'  => $photos,
        ];

        break;
}