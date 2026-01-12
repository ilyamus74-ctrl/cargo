<?php
declare(strict_types=1);

switch ($action) {

    case 'view_tools_stock':
    case 'tools_stock':
        $tools = fetch_tools_list($dbcnx);

        $smarty->assign('tools', $tools);
        $smarty->assign('current_tool', $tool);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_new_tool_stock':
            $emptyTool = [
            'id'              => '',
            'NameTool'        => '',
            'SerialNumber'    => '',
            'WarantyDays'     => 0,
            'PriceBuy'        => '',
            'DateBuy'         => '',
            'AddInSystem'     => date('Y-m-d'),
            'ResourceDays'    => 0,
            'ResourceEndDate' => '',
            'status'          => 'active',
            'notes'           => '',
            'img_path'       => [],
            'qr_codes'        => [],
        ];

        $smarty->assign('edit_tool', $emptyTool);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock_form.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'form_edit_tool_stock':
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'tool_id required',
            ];
            break;
        }

        $stmt = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ?');
        $stmt->bind_param('i', $toolId);
        $stmt->execute();
        $res = $stmt->get_result();
        $toolRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$toolRow) {
            $response = [
                'status'  => 'error',
                'message' => 'Инструмент не найден',
            ];
            break;
        }

        $smarty->assign('edit_tool', map_tool_to_template($toolRow));
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_tools_stock_form.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

case 'save_tool':
    $toolId        = (int)($_POST['tool_id'] ?? 0);
    $name          = trim($_POST['NameTool'] ?? '');
    $serialNumber  = trim($_POST['SerialNumber'] ?? '');
    $warrantyDays  = (int)($_POST['WarantyDays'] ?? 0);
    $priceBuyRaw   = $_POST['PriceBuy'] ?? '';
    $purchaseDate  = trim($_POST['DateBuy'] ?? '');
    $registeredAt  = trim($_POST['AddInSystem'] ?? '') ?: date('Y-m-d');
    $resourceDays  = (int)($_POST['ResourceDays'] ?? 0);
//    $resourceEnd   = trim($_POST['ResourceEndDate'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');
    $status        = !empty($_POST['status']) ? 'active' : 'inactive';
    $deleteFlag    = !empty($_POST['delete']);

    // Удаление инструмента
    if ($toolId > 0 && $deleteFlag) {

        $stmtOld = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ? LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $toolId);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            $oldTool = $resOld ? $resOld->fetch_assoc() : null;
            $stmtOld->close();
        } else {
            $oldTool = null;
        }

        $stmtDel = $dbcnx->prepare('DELETE FROM tool_resources WHERE id = ?');
        if ($stmtDel) {
            $stmtDel->bind_param('i', $toolId);
            $stmtDel->execute();
            $stmtDel->close();
        }
        $toolUid = $oldTool['uid'] ?? '';
        if ($toolUid !== '') {
            $toolDir = __DIR__ . '/img/tools_stock/' . $toolUid;
            remove_directory_recursive($toolDir);
        }

        audit_log(
            $user['id'] ?? null,
            'TOOL_DELETE',
            'TOOL',
            $toolId,
            'Инструмент удалён из профиля',
            $oldTool ?: []
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент удалён',
            'deleted' => true,
        ];
        break;
    }

    

    if ($name === '' || $serialNumber === '' || $purchaseDate === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Название, серийный номер и дата покупки обязательны',
        ];
        break;
    }

    $purchaseDateObj = DateTime::createFromFormat('Y-m-d', $purchaseDate);
    if (!$purchaseDateObj) {
        $purchaseDateObj = DateTime::createFromFormat('Y-m-d H:i:s', $purchaseDate);
    }

    if (!$purchaseDateObj) {
        try {
            $purchaseDateObj = new DateTime($purchaseDate);
        } catch (Exception $e) {
            $purchaseDateObj = null;
        }
    }

    if (!$purchaseDateObj) {
        $response = [
            'status'  => 'error',
            'message' => 'Неверный формат даты покупки',
        ];
        break;
    }

    $purchaseDate = $purchaseDateObj->format('Y-m-d');

    try {
        $registeredAt = (new DateTime($registeredAt))->format('Y-m-d');
    } catch (Exception $e) {
        $registeredAt = date('Y-m-d');
        }
   $priceBuy = ($priceBuyRaw === '' ? null : (float)$priceBuyRaw);

   $resourceEnd = null;
 
   if ($resourceDays > 0) {
    $resourceDays = max(0, (int)($_POST['ResourceDays'] ?? 0));

    $resourceEndObj = clone $purchaseDateObj;
        if ($resourceDays > 0) {
          $resourceEndObj->modify('+' . $resourceDays . ' days');
        }
    }
   $resourceEnd = $resourceEndObj->format('Y-m-d');
//print_r($resourceEnd);
    if ($toolId > 0) {
            $oldTool = null;
        $stmtOld = $dbcnx->prepare('SELECT * FROM tool_resources WHERE id = ? LIMIT 1');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $toolId);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            $oldTool = $resOld ? $resOld->fetch_assoc() : null;
            $stmtOld->close();
        }


        $deletePhotosRaw = $_POST['delete_photos'] ?? [];
        $deletePhotos    = [];

        if (is_array($deletePhotosRaw)) {
            foreach ($deletePhotosRaw as $photoPath) {
                $photoPath = trim((string)$photoPath);
                if ($photoPath !== '') {
                    $deletePhotos[] = $photoPath;
                }
            }
        }

        $photos = parse_tool_photos($oldTool['img_path'] ?? []);

        if ($deletePhotos) {
            $photos = array_values(array_filter($photos, static function ($path) use ($deletePhotos) {
                return !in_array($path, $deletePhotos, true);
            }));

            foreach ($deletePhotos as $photoPath) {
                if (!str_starts_with($photoPath, '/img/tools_stock/')) {
                    continue;
                }

                $absolutePath = __DIR__ . $photoPath;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        $photosJson = json_encode($photos, JSON_UNESCAPED_SLASHES);

$toolUid = (string)($oldTool['uid'] ?? '');
$qrPath  = null;

if ($toolUid !== '') {
    $qrImages = collect_tool_qr_images($toolUid, __DIR__ . '/img/tools_stock/' . $toolUid);
    if (!$qrImages) {
        $qrImages = generate_tool_qr_images($toolUid, get_subdomain_label());
    }
    $qrPath = json_encode($qrImages, JSON_UNESCAPED_SLASHES);
    //$qrPath = build_tool_qr_path_json($uid);
}
//$qrPath = build_tool_qr_path_json($uid);

        $newValues = [
            'name'              => $name,
            'serial_number'     => $serialNumber,
            'warranty_days'     => $warrantyDays,
            'price_buy'         => $priceBuy,
            'purchase_date'     => $purchaseDate,
            'registered_at'     => $registeredAt,
            'resource_days'     => $resourceDays,
            'operational_until' => $resourceEnd,
            'qr_path'           => $qrPath,
            'img_path'          => $photosJson,
            'notes'             => $notes,
            'status'            => $status,
        ];

        $sql = "UPDATE tool_resources
                   SET name = ?,
                       serial_number = ?,
                       warranty_days = ?,
                       price_buy = ?,
                       purchase_date = ?,
                       registered_at = ?,
                       resource_days = ?,
                       operational_until = ?,
                       qr_path = ?,
                       img_path = ?,
                       notes = ?,
                       status = ?,
                       updated_at = CURRENT_TIMESTAMP()
                 WHERE id = ?";

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (tool_resources update)');
        }

        $stmt->bind_param(
            'ssidssisssssi',
            $name,
            $serialNumber,
            $warrantyDays,
            $priceBuy,
            $purchaseDate,
            $registeredAt,
            $resourceDays,
            $resourceEnd,
            $qrPath,
            $photosJson,
            $notes,
            $status,
            $toolId
        );
        $stmt->execute();
        $stmt->close();

        $changes = [];
        if ($oldTool) {
            foreach ($newValues as $field => $value) {
                $oldValue = $oldTool[$field] ?? null;
                if ($oldValue != $value) {
                    $changes[$field] = ['old' => $oldValue, 'new' => $value];
                }
            }
        }

        audit_log(
            $user['id'] ?? null,
            'TOOL_UPDATE',
            'TOOL',
            $toolId,
            'Обновление инструмента',
            $changes ?: $newValues
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент обновлён',
            'tool_id' => $toolId,
        ];

    } else {
        $uid         = generate_uuid();
        $domainLabel = get_subdomain_label();
        $qrImages    = generate_tool_qr_images($uid, $domainLabel);
        if (!$qrImages) {
            $qrImages = collect_tool_qr_images($uid, __DIR__ . '/img/tools_stock/' . $uid);
        }
        $qrPath = json_encode($qrImages, JSON_UNESCAPED_SLASHES);
        //$qrPath = build_tool_qr_path_json($uid);

        $imgPatch = json_encode([], JSON_UNESCAPED_SLASHES);
        $location = 'warehouse';

        $newValues = [
            'name'              => $name,
            'serial_number'     => $serialNumber,
            'warranty_days'     => $warrantyDays,
            'price_buy'         => $priceBuy,
            'purchase_date'     => $purchaseDate,
            'registered_at'     => $registeredAt,
            'resource_days'     => $resourceDays,
            'operational_until' => $resourceEnd,
            'notes'             => $notes,
            'status'            => $status,
        ];


        $sql = "INSERT INTO tool_resources (
                    uid,
                    name,
                    serial_number,
                    price_buy,
                    warranty_days,
                    purchase_date,
                    registered_at,
                    location,
                    passport_service_months,
                    resource_days,
                    actual_usage_days,
                    operational_until,
                    status,
                    qr_path,
                    img_path,
                    notes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?, ?
                )";

        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare error (tool_resources insert)');
        }

        $stmt->bind_param(
            'sssdisssisssss',
            $uid,
            $name,
            $serialNumber,
            $priceBuy,
            $warrantyDays,
            $purchaseDate,
            $registeredAt,
            $location,
            $resourceDays,
            $resourceEnd,
            $status,
            $qrPath,
            $imgPatch,
            $notes
        );
        $stmt->execute();
        $newToolId = $stmt->insert_id;
        $stmt->close();


        audit_log(
            $user['id'] ?? null,
            'TOOL_CREATE',
            'TOOL',
            $newToolId,
            'Создание инструмента',
            $newValues
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Инструмент создан',
            'tool_id' => $newToolId,
        ];
    }

    break;


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
        $photoDir   = __DIR__ . '/img/tools_stock/' . $toolUid . '/photo';
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
    case 'view_devices':
        $devices = [];

        $sql = "SELECT id, device_uid, name, serial, model, app_version,
                       is_active, last_seen_at, last_ip,
                       created_at, activated_at
                  FROM devices
              ORDER BY created_at DESC";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $devices[] = $row;
            }
            $res->free();
        }

        $smarty->assign('devices', $devices);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_devices.html'); // сделаешь по аналогии с users
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'activate_device':
        // только админ, например
        auth_require_role('ADMIN');

        $deviceId  = (int)($_POST['device_id'] ?? 0);
        $isActive  = (int)($_POST['is_active'] ?? 0) ? 1 : 0;

        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        $sql = "UPDATE devices
                   SET is_active = ?,
                       activated_at = CASE
                           WHEN ? = 1 AND activated_at IS NULL
                           THEN CURRENT_TIMESTAMP(6)
                           ELSE activated_at
                       END
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("iii", $isActive, $isActive, $deviceId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            $isActive ? 'DEVICE_ACTIVATE' : 'DEVICE_DEACTIVATE',
            'DEVICE',
            $deviceId,
            $isActive ? 'Активация устройства' : 'Деактивация устройства'
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Статус устройства обновлён',
        ];
        break;


    case 'form_edit_device':
        // Можно ограничить ролью, если нужно
        // auth_require_role('ADMIN');

        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        $sql = "SELECT
                    id,
                    device_uid,
                    name,
                    serial,
                    model,
                    app_version,
                    device_token,
                    is_active,
                    last_seen_at,
                    last_ip,
                    created_at,
                    updated_at,
                    activated_at,
                    notes
                FROM devices
               WHERE id = ?
               LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare form_edit_device)',
            ];
            break;
        }
        $stmt->bind_param("i", $deviceId);
        $stmt->execute();
        $resD   = $stmt->get_result();
        $device = $resD->fetch_assoc();
        $stmt->close();

        if (!$device) {
            $response = [
                'status'  => 'error',
                'message' => 'Устройство не найдено',
            ];
            break;
        }

        // --- ДОБАВЛЯЕМ: последние 20 записей из audit_logs по этому устройству ---
        $logs = [];

        $sqlL = "SELECT
                    event_time,
                    user_id,
                    event_type,
                    entity_id,
                    ip_address,
                    user_agent,
                    description
                 FROM audit_logs
                 WHERE entity_type = 'DEVICE'
                   AND entity_id   = ?
                 ORDER BY event_time DESC
                 LIMIT 20";

        if ($stmtL = $dbcnx->prepare($sqlL)) {
            $stmtL->bind_param("i", $deviceId);
            $stmtL->execute();
            $resL = $stmtL->get_result();
            while ($row = $resL->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmtL->close();
        }

        // кладём как подмассив
        $device['logs'] = $logs;

        $smarty->assign('device',       $device);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_devices_profile.html');
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

 case 'save_device':
        auth_require_role('ADMIN');

        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }


        $deleteFlag = !empty($_POST['delete']);

        // Удаление устройства
        if ($deviceId > 0 && $deleteFlag) {

            $oldDevice = null;
            if ($stmtOld = $dbcnx->prepare('SELECT * FROM devices WHERE id = ? LIMIT 1')) {
                $stmtOld->bind_param('i', $deviceId);
                $stmtOld->execute();
                $resOld   = $stmtOld->get_result();
                $oldDevice = $resOld ? $resOld->fetch_assoc() : null;
                $stmtOld->close();
            }

            $stmtDel = $dbcnx->prepare('DELETE FROM devices WHERE id = ?');
            if ($stmtDel) {
                $stmtDel->bind_param('i', $deviceId);
                $stmtDel->execute();
                $stmtDel->close();
            }

            audit_log(
                $user['id'] ?? null,
                'DEVICE_DELETE',
                'DEVICE',
                $deviceId,
                'Устройство удалено из профиля',
                $oldDevice ?: []
            );

            $response = [
                'status'  => 'ok',
                'message' => 'Устройство удалено',
                'deleted' => true,
            ];
            break;
        }


        // Если не нужны редактируемые name/notes — можно вообще не трогать их.
        // Я оставляю поддержку, раз они уже были.
        $name  = trim($_POST['name']  ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Чекбокс статуса: если есть и не пустой — считаем активным
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE devices
                   SET notes      = ?,
                       is_active  = ?,
                       activated_at = CASE
                           WHEN ? = 1 AND activated_at IS NULL
                           THEN CURRENT_TIMESTAMP(6)
                           ELSE activated_at
                       END
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare save_device)',
            ];
            break;
        }

        $stmt->bind_param("siii", $notes, $isActive, $isActive, $deviceId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            'DEVICE_UPDATE',
            'DEVICE',
            $deviceId,
            'Изменение данных устройства',
            [
                'notes'     => $notes,
                'is_active' => $isActive,
            ]
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Устройство обновлено',
        ];
        break;

    // === Настройки ячеек склада ===
    case 'setting_cells':
        // если нужно ограничить только админами – раскомментируешь
        // if (!auth_has_role('ADMIN')) {
        //     $response = [
        //         'status'  => 'error',
        //         'message' => 'Недостаточно прав для просмотра ячеек',
        //     ];
        //     break;
        // }

        // если нужно что-то подтянуть из БД — потом сюда добавим SELECT
        // пример заготовки:
         $cells = [];
         if ($res = $dbcnx->query("SELECT id, code, qr_payload, description FROM cells ORDER BY code")) {
             while ($row = $res->fetch_assoc()) {
                 $cells[] = $row;
             }
             $res->free();
         }
        $smarty->assign('cells', $cells);

        // рендерим твой готовый шаблон с ячейками
        $smarty->assign('current_user', $user);
        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html'); // ПОДСТАВЬ имя своего шаблона
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

case 'add_new_cells':
        // auth_require_role('ADMIN'); // при желании

        $first = trim($_POST['first_code'] ?? '');
        $last  = trim($_POST['last_code'] ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if ($first === '' || $last === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Укажи первую и последнюю ячейку, например A10 и A99',
            ];
            break;
        }

        // Нормализуем в верхний регистр
        $first = strtoupper($first);
        $last  = strtoupper($last);

        // Парсим вида A10, B25 и т.п.
        $re = '/^([A-Z])(\d{1,4})$/u';

        if (!preg_match($re, $first, $m1) || !preg_match($re, $last, $m2)) {
            $response = [
                'status'  => 'error',
                'message' => 'Коды ячеек должны быть вида A10, A99 и т.п.',
            ];
            break;
        }

        $prefix1 = $m1[1];
        $n1      = (int)$m1[2];

        $prefix2 = $m2[1];
        $n2      = (int)$m2[2];

        if ($prefix1 !== $prefix2) {
            $response = [
                'status'  => 'error',
                'message' => 'Буквенная часть должна совпадать (например A10–A99, а не A10–B20)',
            ];
            break;
        }

        if ($n1 >= $n2) {
            $response = [
                'status'  => 'error',
                'message' => 'Первая ячейка должна быть меньше последней по номеру (например A10 и A99)',
            ];
            break;
        }

        $prefix = $prefix1;

        // Смотрим уже существующие коды в этом диапазоне, чтобы не делать дубли
        $fromCode = $prefix . $n1;
        $toCode   = $prefix . $n2;

        $stmt = $dbcnx->prepare(
            "SELECT code FROM cells WHERE code BETWEEN ? AND ?"
        );
        $stmt->bind_param("ss", $fromCode, $toCode);
        $stmt->execute();
        $res = $stmt->get_result();

        $existing = [];
        while ($row = $res->fetch_assoc()) {
            $existing[$row['code']] = true;
        }
        $stmt->close();

        // Папка для файлов
        $dir = __DIR__ . '/img/cells';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $created = 0;

        for ($i = $n1; $i <= $n2; $i++) {
            $code = $prefix . $i;        // A10, A11, ...

            if (isset($existing[$code])) {
                // Уже есть такая ячейка — пропускаем
                continue;
            }

            $qrPayload  = 'CELL:' . $code;
            $qrFileName = $code . '.png';                 // ИМЯ ФАЙЛА = КОД ЯЧЕЙКИ
            $fullPath   = $dir . '/' . $qrFileName;

            // Вставляем в БД: code, qr_payload, qr_file, description
            $stmtIns = $dbcnx->prepare(
                "INSERT INTO cells (code, qr_payload, qr_file, description)
                 VALUES (?, ?, ?, ?)"
            );
            $stmtIns->bind_param("ssss", $code, $qrPayload, $qrFileName, $desc);
            $stmtIns->execute();
            $stmtIns->close();

            // Генерируем PNG через qrencode
            $cmd = sprintf(
                'qrencode -o %s -s 4 %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($qrPayload)
            );
            $out = [];
            $rc  = 0;
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                error_log('qrencode error for cell ' . $code . ': ' . implode("\n", $out));
            }

            $created++;
        }

        // Перечитываем список ячеек
        $cells = [];
        $sql = "SELECT id, code, qr_payload, qr_file, description
                  FROM cells
              ORDER BY code";
        if ($res2 = $dbcnx->query($sql)) {
            while ($row = $res2->fetch_assoc()) {
                $cells[] = $row;
            }
            $res2->free();
        }

        $smarty->assign('cells', $cells);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html');
        $html = ob_get_clean();

        $response = [
            'status'  => 'ok',
            'message' => 'Создано новых ячеек: ' . $created,
            'html'    => $html,
        ];
        break;

    case 'delete_cell':
        $cellId = (int)($_POST['cell_id'] ?? 0);
        if ($cellId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Некорректный ID ячейки',
            ];
            break;
        }

        $stmt = $dbcnx->prepare(
            "SELECT id, code, qr_payload, qr_file, description
               FROM cells
              WHERE id = ?
              LIMIT 1"
        );
        $stmt->bind_param("i", $cellId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cell = $res->fetch_assoc();
        $stmt->close();

        if (!$cell) {
            $response = [
                'status'  => 'error',
                'message' => 'Ячейка не найдена',
            ];
            break;
        }

        // Удаляем файл, если есть
        $dir = __DIR__ . '/img/cells';
        if (!empty($cell['qr_file'])) {
            $path = $dir . '/' . $cell['qr_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        } else {
            // запасной вариант по коду
            $fallback = $dir . '/' . $cell['code'] . '.png';
            if (is_file($fallback)) {
                @unlink($fallback);
            }
        }

        // Удаляем из БД
        $stmt = $dbcnx->prepare("DELETE FROM cells WHERE id = ?");
        $stmt->bind_param("i", $cellId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            'CELL_DELETE',
            'CELL',
            $cellId,
            'Удаление ячейки склада',
            [
                'code'        => $cell['code'],
                'qr_payload'  => $cell['qr_payload'],
                'qr_file'     => $cell['qr_file'],
                'description' => $cell['description'],
            ]
        );

        // Перечитываем список ячеек
        $cells = [];
        $sql = "SELECT id, code, qr_payload, qr_file, description
                  FROM cells
              ORDER BY code";
        if ($res2 = $dbcnx->query($sql)) {
            while ($row = $res2->fetch_assoc()) {
                $cells[] = $row;
            }
            $res2->free();
        }

        $smarty->assign('cells', $cells);
        $smarty->assign('current_user', $user);

        ob_start();
        $smarty->display('cells_NA_API_warehouse_cells.html');
        $html = ob_get_clean();

        $response = [
            'status'  => 'ok',
            'message' => 'Ячейка удалена',
            'html'    => $html,
        ];
        break;

case 'warehouse_item_in':
case 'item_in':
    auth_require_login();
    $current = $user;
    $userId  = (int)$current['id'];

    $batches = [];

    if (auth_has_role('ADMIN')) {
        // Админ видит ВСЕ незавершённые партии + можем сразу знать, чей это приход
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count,
                wi.user_id,
                u.full_name        AS user_name
            FROM warehouse_item_in wi
            LEFT JOIN users u ON u.id = wi.user_id
            WHERE wi.committed = 0
            GROUP BY wi.batch_uid, wi.user_id
            ORDER BY started_at DESC
        ";
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $batches[] = $row;
            }
            $res->free();
        }
    } else {
        // Обычный пользователь — только свои партии
        $sql = "
            SELECT
                wi.batch_uid,
            MIN(wi.created_at) AS started_at,
            COUNT(*)           AS parcel_count
            FROM warehouse_item_in wi
            WHERE wi.committed = 0
              AND wi.user_id   = ?
            GROUP BY wi.batch_uid
            ORDER BY started_at DESC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('batches',      $batches);
    $smarty->assign('current_user', $current);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_in.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

case 'item_stock':
    auth_require_login();
    $current = $user;
    $userId  = (int)$current['id'];

    $batches = [];

    if (auth_has_role('ADMIN')) {
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count,
                wi.user_id,
                u.full_name        AS user_name
            FROM warehouse_item_stock wi
            LEFT JOIN users u ON u.id = wi.user_id
            GROUP BY wi.batch_uid, wi.user_id
            ORDER BY started_at DESC
        ";
        //
        if ($res = $dbcnx->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $batches[] = $row;
            }
            $res->free();
        }
    } else {
        $sql = "
            SELECT
                wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count
            FROM warehouse_item_stock wi
            WHERE wi.user_id = ?
            GROUP BY wi.batch_uid
            ORDER BY started_at DESC
        ";
//            GROUP BY wi.batch_uid

        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
    }

    $smarty->assign('batches',      $batches);
    $smarty->assign('current_user', $current);

    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_stock.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;


case 'open_item_in_batch':
    auth_require_login();
    $current = $user;
    $userId  = (int)$current['id'];

    $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
    if ($batchUid <= 0) {
        // новая партия
        $batchUid = (int)(microtime(true) * 1000000);
    }

    $items = [];

    if (auth_has_role('ADMIN')) {
        // Админ видит ВСЕ посылки в партии, кто бы их ни создавал
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $batchUid);
    } else {
        // Обычный юзер — только свои записи
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND user_id   = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("ii", $batchUid, $userId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $smarty->assign('batch_uid',    $batchUid);
    $smarty->assign('items',        $items);
    $smarty->assign('current_user', $current);

        $dest_country = [];
        $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
        $stmt = $dbcnx->prepare($sql);
        $stmt->execute();
        $res3 = $stmt->get_result();


        if ($res3 = $dbcnx->query($sql)) {
            while ($row = $res3->fetch_assoc()) {
                $dest_country[] = $row;
            }
            $res3->free();
        }
    $stmt->close();

    $smarty->assign('dest_country', $dest_country);
    $stand_devices = [];
    $sql = "SELECT device_uid, name, device_token
              FROM devices
             WHERE name LIKE 'stand\\_%'
             ORDER BY name ASC, device_uid ASC";
    if ($resStand = $dbcnx->query($sql)) {
        while ($row = $resStand->fetch_assoc()) {
            $stand_devices[] = $row;
        }
        $resStand->free();
    }
    $smarty->assign('stand_devices', $stand_devices);

    require_once __DIR__ . '/ocr_templates.php';
    require_once __DIR__ . '/ocr_dicts.php';


    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_in_batch.html');
    $html = ob_get_clean();

    $response = [
        'status' => 'ok',
        'html'   => $html,
    ];
    break;

case 'add_new_item_in':
    auth_require_login();
    $current = $user;

    // Кто сейчас залогинен — ОПЕРАТОР
    $operatorUserId = (int)$current['id'];

    // 1) партия
    $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
    if ($batchUid <= 0) {
        // новая партия — владелец = текущий пользователь
        $batchUid     = (int)(microtime(true) * 1000000); // uid_created
        $ownerUserId  = $operatorUserId;
    } else {
        // существующая партия — ищем её владельца
        $ownerUserId = $operatorUserId; // по умолчанию

        $stmtOwner = $dbcnx->prepare(
            "SELECT user_id
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0
              ORDER BY created_at ASC
              LIMIT 1"
        );
        if ($stmtOwner) {
            $stmtOwner->bind_param("i", $batchUid);
            $stmtOwner->execute();
            $resOwner = $stmtOwner->get_result();
            if ($rowOwner = $resOwner->fetch_assoc()) {
                $ownerUserId = (int)$rowOwner['user_id'];
            }
            $stmtOwner->close();
        }
    }

    // 2) поля из формы
    $tuid        = trim($_POST['tuid']        ?? '');
    $tracking    = trim($_POST['tracking_no'] ?? '');

    $carrierCode = trim($_POST['carrier_code'] ?? '');
    $carrierName = trim($_POST['carrier_name'] ?? '');

    $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
    // имя страны сейчас не приходит, можно оставить пустым
    $rcCountryName = '';

    $rcName        = trim($_POST['receiver_name']    ?? '');
    $rcCompany     = trim($_POST['receiver_company'] ?? '');
    $rcAddress     = trim($_POST['receiver_address'] ?? '');

    $sndName       = trim($_POST['sender_name']    ?? '');
    $sndCompany    = trim($_POST['sender_company'] ?? '');

    // вес и габариты: если пусто → 0
    $weightKg = $_POST['weight_kg'] ?? '';
    $sizeL    = $_POST['size_l_cm'] ?? '';
    $sizeW    = $_POST['size_w_cm'] ?? '';
    $sizeH    = $_POST['size_h_cm'] ?? '';

    $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
    $sizeL    = ($sizeL    === '' || $sizeL    === null) ? 0.0 : (float)$sizeL;
    $sizeW    = ($sizeW    === '' || $sizeW    === null) ? 0.0 : (float)$sizeW;
    $sizeH    = ($sizeH    === '' || $sizeH    === null) ? 0.0 : (float)$sizeH;

    // пока не используем
    $labelImage = null;
    $boxImage   = null;

    if ($tuid === '' || $tracking === '') {
        $response = [
            'status'  => 'error',
            'message' => 'Нужны хотя бы TUID и трек-номер',
        ];
        break;
    }

    $uidCreated = (int)(microtime(true) * 1000000);
    $deviceId   = 0; // для веба 0, для мобилки можно класть реальный device_id

    $sql = "INSERT INTO warehouse_item_in (
                batch_uid, uid_created, user_id, device_id, committed,
                tuid, tracking_no, carrier_code, carrier_name,
                receiver_country_code, receiver_country_name,
                receiver_name, receiver_company, receiver_address,
                sender_name, sender_company,
                weight_kg, size_l_cm, size_w_cm, size_h_cm,
                label_image, box_image
            ) VALUES (
                ?, ?, ?, ?, 0,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?
            )";

    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error: ' . $dbcnx->error,
        ];
        break;
    }

    // iiiisssssssssssddddss  = 21 параметр
    $stmt->bind_param(
        "iiiisssssssssssddddss",
        $batchUid,
        $uidCreated,
        $ownerUserId,    // владелец партии
        $deviceId,
        $tuid,
        $tracking,
        $carrierCode,
        $carrierName,
        $rcCountryCode,
        $rcCountryName,
        $rcName,
        $rcCompany,
        $rcAddress,
        $sndName,
        $sndCompany,
        $weightKg,
        $sizeL,
        $sizeW,
        $sizeH,
        $labelImage,
        $boxImage
    );
    $stmt->execute();
    $stmt->close();

    audit_log(
        $operatorUserId,                 // кто реально добавил
        'WAREHOUSE_IN_ADD_PARCEL',
        'WAREHOUSE_IN',
        $batchUid,                       // entity_id = batch_uid
        'Добавлена посылка в партию прихода',
        [
            'batch_uid'        => $batchUid,
            'owner_user_id'    => $ownerUserId,
            'operator_user_id' => $operatorUserId,
            'tuid'             => $tuid,
            'tracking_no'      => $tracking,
        ]
    );

    $response = [
        'status'    => 'ok',
        'message'   => 'Посылка добавлена',
        'batch_uid' => $batchUid,
    ];
    break;


case 'delete_item_in':
    auth_require_login();

    $current = $user;
    $userId  = (int)$current['id'];
    $isAdmin = auth_has_role('ADMIN');

    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'item_id не задан',
        ];
        break;
    }

    $stmtItem = $dbcnx->prepare(
        "SELECT id, batch_uid, user_id, committed\n           FROM warehouse_item_in\n          WHERE id = ?"
    );
    if (!$stmtItem) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare select item): ' . $dbcnx->error,
        ];
        break;
    }

    $stmtItem->bind_param("i", $itemId);
    $stmtItem->execute();
    $itemRow = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if (!$itemRow) {
        $response = [
            'status'  => 'error',
            'message' => 'Посылка не найдена',
        ];
        break;
    }

    $batchUid   = (int)($itemRow['batch_uid'] ?? 0);
    $itemUserId = (int)($itemRow['user_id']   ?? 0);
    $committed  = (int)($itemRow['committed'] ?? 0);

    if ($committed !== 0) {
        $response = [
            'status'  => 'error',
            'message' => 'Нельзя удалить завершённую посылку',
        ];
        break;
    }

    if (!$isAdmin && $itemUserId !== $userId) {
        $response = [
            'status'  => 'error',
            'message' => 'Недостаточно прав для удаления посылки',
        ];
        break;
    }

    if ($isAdmin) {
        $stmtDel = $dbcnx->prepare(
            "DELETE FROM warehouse_item_in\n             WHERE id = ?\n               AND committed = 0"
        );
        if ($stmtDel) {
            $stmtDel->bind_param("i", $itemId);
        }
    } else {
        $stmtDel = $dbcnx->prepare(
            "DELETE FROM warehouse_item_in\n             WHERE id = ?\n               AND user_id = ?\n               AND committed = 0"
        );
        if ($stmtDel) {
            $stmtDel->bind_param("ii", $itemId, $userId);
        }
    }

    if (!$stmtDel) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare delete): ' . $dbcnx->error,
        ];
        break;
    }

    $stmtDel->execute();
    $stmtDel->close();

    $items = [];

    if ($isAdmin) {
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("i", $batchUid);
    } else {
        $sql = "
            SELECT
                id,
                tuid,
                tracking_no,
                receiver_name,
                receiver_company,
                receiver_address,
                weight_kg,
                size_l_cm,
                size_w_cm,
                size_h_cm,
                created_at
            FROM warehouse_item_in
            WHERE batch_uid = ?
              AND user_id   = ?
              AND committed = 0
            ORDER BY created_at ASC
        ";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("ii", $batchUid, $userId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    $smarty->assign('batch_uid',    $batchUid);
    $smarty->assign('items',        $items);
    $smarty->assign('current_user', $current);

        $dest_country = [];
        $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
        $stmt = $dbcnx->prepare($sql);
        $stmt->execute();
        $res3 = $stmt->get_result();


        if ($res3 = $dbcnx->query($sql)) {
            while ($row = $res3->fetch_assoc()) {
                $dest_country[] = $row;
            }
            $res3->free();
        }
    $stmt->close();

    $smarty->assign('dest_country', $dest_country);

    require_once __DIR__ . '/ocr_templates.php';
    require_once __DIR__ . '/ocr_dicts.php';


    ob_start();
    $smarty->display('cells_NA_API_warehouse_item_in_batch.html');
    $html = ob_get_clean();

    $response = [
        'status'  => 'ok',
        'message' => 'Посылка удалена',
        'html'    => $html,
    ];
    break;



case 'commit_item_in_batch':
    auth_require_login();
    $current  = $user;
    $userId   = (int)$current['id'];
    $batchUid = (int)($_POST['batch_uid'] ?? 0);

    if ($batchUid <= 0) {
        $response = [
            'status'  => 'error',
            'message' => 'batch_uid не задан',
        ];
        break;
    }

    $isAdmin = auth_has_role('ADMIN');

    // 1) сколько незакоммиченных
    $stmt = $dbcnx->prepare(
        "SELECT COUNT(*) AS cnt
           FROM warehouse_item_in
          WHERE batch_uid = ?
            AND committed = 0"
    );
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare count): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cnt = (int)($res['cnt'] ?? 0);

    if ($cnt === 0) {
        $response = [
            'status'  => 'ok',
            'message' => 'Партия уже была завершена или пустая',
        ];
        break;
    }

    // 2) копируем в stock
    $sqlCopy = "INSERT INTO warehouse_item_stock (
                    batch_uid, uid_created, user_id, device_id, created_at,
                    tuid, tracking_no, carrier_code, carrier_name,
                    receiver_country_code, receiver_country_name,
                    receiver_name, receiver_company, receiver_address,
                    sender_name, sender_company,
                    weight_kg, size_l_cm, size_w_cm, size_h_cm,
                    label_image, box_image
                )
                SELECT
                    batch_uid, uid_created, user_id, device_id, created_at,
                    tuid, tracking_no, carrier_code, carrier_name,
                    receiver_country_code, receiver_country_name,
                    receiver_name, receiver_company, receiver_address,
                    sender_name, sender_company,
                    weight_kg, size_l_cm, size_w_cm, size_h_cm,
                    label_image, box_image
                  FROM warehouse_item_in
                 WHERE batch_uid = ?
                   AND committed = 0";

    $stmt = $dbcnx->prepare($sqlCopy);
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare copy): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $stmt->close();

    // 3) помечаем как committed
    $stmt = $dbcnx->prepare(
        "UPDATE warehouse_item_in
            SET committed = 1
          WHERE batch_uid = ?
            AND committed = 0"
    );
    if (!$stmt) {
        $response = [
            'status'  => 'error',
            'message' => 'DB error (prepare update): ' . $dbcnx->error,
        ];
        break;
    }

    $stmt->bind_param("i", $batchUid);
    $stmt->execute();
    $stmt->close();

    // 4) аудит
    audit_log(
        $userId,
        'WAREHOUSE_IN_COMMIT',
        'WAREHOUSE_IN',
        $batchUid,
        'Партия прихода переведена на склад',
        [
            'batch_uid'    => $batchUid,
            'committed_by' => $userId,
            'is_admin'     => $isAdmin,
            'items_count'  => $cnt,
        ]
    );

    $response = [
        'status'  => 'ok',
        'message' => 'Партия прихода завершена и перенесена на склад',
    ];
    break;


    // === 2) Заготовка под "Устройства" ===
/*    case 'view_devices':
        // потом сюда добавишь SELECT по устройствам и свой шаблон
        ob_start();
        $smarty->display('NiceAdmin/devices_list.html'); // создашь позже
        $html = ob_get_clean();

        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;
*/
    // сюда можешь добавить другие действия:
    // case 'set_lang': ... (то, что мы делали до этого)
    // case 'get_user_panel_html': ...
    // сюда добавишь остальные действия:
    // case 'save_user_settings':
    // case 'get_parcel_list':
    // case 'render_label_block':
    // и т.д.
    default:
        http_response_code(400);
        $response = [
            'status'  => 'error',
            'message' => 'Unknown action',
        ];
        break;
}