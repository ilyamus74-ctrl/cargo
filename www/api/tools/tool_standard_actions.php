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

    $toolsBaseDir = dirname(__DIR__, 2) . '/img/tools_stock';

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
            $toolDir = $toolsBaseDir . '/' . $toolUid;
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

                $absolutePath = dirname(__DIR__, 2) . $photoPath;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        $photosJson = json_encode($photos, JSON_UNESCAPED_SLASHES);

$toolUid = (string)($oldTool['uid'] ?? '');
$qrPath  = null;

if ($toolUid !== '') {
    $qrImages = collect_tool_qr_images($toolUid, $toolsBaseDir . '/' . $toolUid);
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
            $qrImages = collect_tool_qr_images($uid, $toolsBaseDir . '/' . $uid);
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
}