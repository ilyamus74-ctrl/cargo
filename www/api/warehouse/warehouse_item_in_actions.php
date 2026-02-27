<?php
declare(strict_types=1);
/**
 * Обработчик действий с приходом товаров на склад
 * Actions: warehouse_item_in, item_in, open_item_in_batch, add_new_item_in, 
 *          delete_item_in, commit_item_in_batch
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse item in action'];


function warehouse_item_in_ensure_addons_columns(mysqli $dbcnx): void
{
    $checkIn = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_in LIKE 'addons_json'");
    if ($checkIn instanceof mysqli_result) {
        $existsIn = $checkIn->num_rows > 0;
        $checkIn->free();
        if (!$existsIn) {
            $dbcnx->query("ALTER TABLE warehouse_item_in ADD COLUMN addons_json LONGTEXT NULL AFTER box_image");
        }
    }

    $checkStock = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE 'addons_json'");
    if ($checkStock instanceof mysqli_result) {
        $existsStock = $checkStock->num_rows > 0;
        $checkStock->free();
        if (!$existsStock) {
            $dbcnx->query("ALTER TABLE warehouse_item_stock ADD COLUMN addons_json LONGTEXT NULL AFTER box_image");
        }
    }
}

function warehouse_item_in_load_addons_map(mysqli $dbcnx): array
{
    $addonsMap = [];
    $addonsRawMap = [];

    $sql = "SELECT connector_name, addons_json
              FROM connectors_addons
             WHERE addons_json IS NOT NULL
               AND TRIM(addons_json) <> ''
               AND TRIM(addons_json) <> '{}'
               AND TRIM(addons_json) <> '[]'";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $connector = strtoupper(trim((string)($row['connector_name'] ?? '')));
            $rawAddonsJson = trim((string)($row['addons_json'] ?? ''));
            if ($connector === '' || $rawAddonsJson === '') {
                continue;
            }

            $decoded = json_decode($rawAddonsJson, true);
            if (!is_array($decoded)) {
                continue;
            }

            $extra = $decoded['extra'] ?? [];
            if (!is_array($extra) || empty($extra)) {
                continue;
            }

            $addonsMap[$connector] = $extra;
            $addonsRawMap[$connector] = $decoded;
        }
        $res->free();
    }

    return [$addonsMap, $addonsRawMap];
}

function warehouse_item_in_normalize_image_json(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $clean = [];
    foreach ($decoded as $path) {
        if (!is_string($path)) {
            continue;
        }
        $path = trim($path);
        if ($path === '') {
            continue;
        }
        $clean[] = $path;
    }

    if (empty($clean)) {
        return null;
    }

    $encoded = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);
    return $encoded !== false ? $encoded : null;
}

function warehouse_item_in_decode_image_paths(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $path) {
        if (!is_string($path)) {
            continue;
        }
        $path = trim($path);
        if ($path === '') {
            continue;
        }
        $result[] = $path;
    }

    return array_values(array_unique($result));
}

function warehouse_item_in_ensure_photo_dir(string $absDir): bool
{
    if (is_dir($absDir)) {
        return true;
    }
    return @mkdir($absDir, 0775, true);
}

function warehouse_item_in_find_or_create_draft(mysqli $dbcnx, int $batchUid, int $ownerUserId, int $deviceId, array $fields): int
{
    $tuid = trim((string)($fields['tuid'] ?? ''));
    $tracking = trim((string)($fields['tracking_no'] ?? ''));

    $stmtFind = $dbcnx->prepare(
        "SELECT id
           FROM warehouse_item_in
          WHERE batch_uid = ?
            AND committed = 0
            AND tuid = ?
            AND tracking_no = ?
          ORDER BY id DESC
          LIMIT 1"
    );
    if ($stmtFind) {
        $stmtFind->bind_param('iss', $batchUid, $tuid, $tracking);
        $stmtFind->execute();
        $row = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
    }

    $uidCreated = (int)(microtime(true) * 1000000);
    $sql = "INSERT INTO warehouse_item_in (
                batch_uid, uid_created, user_id, device_id, committed,
                tuid, tracking_no, carrier_code, carrier_name,
                receiver_country_code, receiver_country_name,
                receiver_name, receiver_company, receiver_address,
                sender_name, sender_company,
                weight_kg, size_l_cm, size_w_cm, size_h_cm,
                label_image, box_image, addons_json
            ) VALUES (
                ?, ?, ?, ?, 0,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )";

    $stmtInsert = $dbcnx->prepare($sql);
    if (!$stmtInsert) {
        return 0;
    }

    $stmtInsert->bind_param(
        'iiiisssssssssssddddsss',
        $batchUid,
        $uidCreated,
        $ownerUserId,
        $deviceId,
        $fields['tuid'],
        $fields['tracking_no'],
        $fields['carrier_code'],
        $fields['carrier_name'],
        $fields['receiver_country_code'],
        $fields['receiver_country_name'],
        $fields['receiver_name'],
        $fields['receiver_company'],
        $fields['receiver_address'],
        $fields['sender_name'],
        $fields['sender_company'],
        $fields['weight_kg'],
        $fields['size_l_cm'],
        $fields['size_w_cm'],
        $fields['size_h_cm'],
        $fields['label_image'],
        $fields['box_image'],
        $fields['addons_json']
    );
    $stmtInsert->execute();
    $newId = (int)$stmtInsert->insert_id;
    $stmtInsert->close();
    return $newId;
}

function findWarehouseDuplicate(mysqli $dbcnx, string $carrierName, string $tuid, string $tracking, int $excludeItemInId = 0): array
{
    $carrierName = trim($carrierName);
    $tuid = trim($tuid);
    $tracking = trim($tracking);
    if ($carrierName === '' || ($tuid === '' && $tracking === '')) {
        return ['duplicate' => false, 'source' => null];
    }
    $searchTracking = $tracking !== '' ? $tracking : $tuid;
    $checks = [
        'warehouse_item_in',
        'warehouse_item_stock',
    ];
    foreach ($checks as $table) {
        $sql = "SELECT id FROM {$table} WHERE carrier_name = ? AND (tuid = ? OR tracking_no = ?)";
        if ($table === 'warehouse_item_in' && $excludeItemInId > 0) {
            $sql .= " AND id <> " . (int)$excludeItemInId;
        }
        $sql .= " LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("sss", $carrierName, $tuid, $searchTracking);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return ['duplicate' => true, 'source' => $table];
        }
    }
    return ['duplicate' => false, 'source' => null];
}
switch ($action) {
    case 'warehouse_item_in':
    case 'item_in':
        auth_require_login();
        $current = $user;
        $userId  = (int)$current['id'];
        $batches = [];
        $canViewAll = auth_has_permission('warehouse.in.view_all') || auth_has_role('ADMIN');
        if ($canViewAll) {
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

    case 'open_item_in_batch':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        $current = $user;
        $userId  = (int)$current['id'];
        $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
        if ($batchUid <= 0) {
            // новая партия
            $batchUid = (int)(microtime(true) * 1000000);
        }
        $items = [];

        $canViewAll = auth_has_permission('warehouse.in.view_all') || auth_has_role('ADMIN');
        if ($canViewAll) {
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
        [$addonsMap, $addonsRawMap] = warehouse_item_in_load_addons_map($dbcnx);
        $smarty->assign('addons_map', $addonsMap);
        $smarty->assign('addons_raw_map', $addonsRawMap);
        require_once __DIR__ . '/../../ocr_templates.php';
        require_once __DIR__ . '/../../ocr_dicts.php';
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
        warehouse_item_in_ensure_addons_columns($dbcnx);
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
        $addonsJsonRaw = trim((string)($_POST['addons_json'] ?? ''));
        $labelImage = trim((string)($_POST['label_image'] ?? ''));
        $boxImage   = trim((string)($_POST['box_image'] ?? ''));

        $addonsJson = $addonsJsonRaw !== '' ? $addonsJsonRaw : null;
        $labelImage = $labelImage !== '' ? $labelImage : null;
        $boxImage   = $boxImage !== '' ? $boxImage : null;
        if ($tuid === '' || $tracking === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Нужны хотя бы TUID и трек-номер',
            ];
            break;
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $duplicateCheck = findWarehouseDuplicate($dbcnx, $carrierName, $tuid, $tracking, $itemId);
        if ($duplicateCheck['duplicate']) {
            $response = [
                'status'  => 'error',
                'message' => 'Такая посылка уже есть на складе',
            ];
            break;
        }
        $uidCreated = (int)(microtime(true) * 1000000);
        $deviceId   = 0; // для веба 0, для мобилки можно класть реальный device_id

        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $dbcnx->prepare(
                "UPDATE warehouse_item_in
                    SET tuid = ?,
                        tracking_no = ?,
                        carrier_code = ?,
                        carrier_name = ?,
                        receiver_country_code = ?,
                        receiver_country_name = ?,
                        receiver_name = ?,
                        receiver_company = ?,
                        receiver_address = ?,
                        sender_name = ?,
                        sender_company = ?,
                        weight_kg = ?,
                        size_l_cm = ?,
                        size_w_cm = ?,
                        size_h_cm = ?,
                        label_image = ?,
                        box_image = ?,
                        addons_json = ?
                  WHERE id = ?
                    AND batch_uid = ?
                    AND committed = 0
                  LIMIT 1"
            );
            if (!$stmt) {
                $response = [
                    'status'  => 'error',
                    'message' => 'DB error: ' . $dbcnx->error,
                ];
                break;
            }
            $stmt->bind_param(
                'sssssssssssddddsssii',
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
                $boxImage,
                $addonsJson,
                $itemId,
                $batchUid
            );
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected < 0) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Не удалось обновить посылку',
                ];
                break;
            }
        } else {
            $sql = "INSERT INTO warehouse_item_in (
                        batch_uid, uid_created, user_id, device_id, committed,
                        tuid, tracking_no, carrier_code, carrier_name,
                        receiver_country_code, receiver_country_name,
                        receiver_name, receiver_company, receiver_address,
                        sender_name, sender_company,
                        weight_kg, size_l_cm, size_w_cm, size_h_cm,
                        label_image, box_image, addons_json
                    ) VALUES (
                        ?, ?, ?, ?, 0,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?
                    )";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status'  => 'error',
                    'message' => 'DB error: ' . $dbcnx->error,
                ];
                break;
            }
            $stmt->bind_param(
                "iiiisssssssssssddddsss",
                $batchUid,
                $uidCreated,
                $ownerUserId,
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
                $boxImage,
                $addonsJson
            );
            $stmt->execute();
            $itemId = (int)$stmt->insert_id;
            $stmt->close();
        }

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
                'addons_json'      => $addonsJson,
            ]
        );
        $response = [
            'status'    => 'ok',
            'message'   => 'Посылка добавлена',
            'batch_uid' => $batchUid,
        ];
        break;

    case 'save_item_in_draft':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        $current = $user;
        $operatorUserId = (int)$current['id'];

        $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
        if ($batchUid <= 0) {
            $batchUid = (int)(microtime(true) * 1000000);
        }

        $ownerUserId = $operatorUserId;
        $stmtOwner = $dbcnx->prepare(
            "SELECT user_id
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0
              ORDER BY created_at ASC
              LIMIT 1"
        );
        if ($stmtOwner) {
            $stmtOwner->bind_param('i', $batchUid);
            $stmtOwner->execute();
            $resOwner = $stmtOwner->get_result();
            if ($rowOwner = $resOwner->fetch_assoc()) {
                $ownerUserId = (int)$rowOwner['user_id'];
            }
            $stmtOwner->close();
        }

        $tuid        = trim($_POST['tuid'] ?? '');
        $tracking    = trim($_POST['tracking_no'] ?? '');
        $carrierCode = trim($_POST['carrier_code'] ?? '');
        $carrierName = trim($_POST['carrier_name'] ?? '');
        $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
        $rcCountryName = '';
        $rcName        = trim($_POST['receiver_name'] ?? '');
        $rcCompany     = trim($_POST['receiver_company'] ?? '');
        $rcAddress     = trim($_POST['receiver_address'] ?? '');
        $sndName       = trim($_POST['sender_name'] ?? '');
        $sndCompany    = trim($_POST['sender_company'] ?? '');

        $weightKg = $_POST['weight_kg'] ?? '';
        $sizeL    = $_POST['size_l_cm'] ?? '';
        $sizeW    = $_POST['size_w_cm'] ?? '';
        $sizeH    = $_POST['size_h_cm'] ?? '';
        $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
        $sizeL    = ($sizeL    === '' || $sizeL    === null) ? 0.0 : (float)$sizeL;
        $sizeW    = ($sizeW    === '' || $sizeW    === null) ? 0.0 : (float)$sizeW;
        $sizeH    = ($sizeH    === '' || $sizeH    === null) ? 0.0 : (float)$sizeH;

        if ($tuid === '' || $tracking === '') {
            $response = ['status' => 'error', 'message' => 'Нужны TUID и трек-номер'];
            break;
        }

        $deviceId = 0;
        $fields = [
            'tuid' => $tuid,
            'tracking_no' => $tracking,
            'carrier_code' => $carrierCode,
            'carrier_name' => $carrierName,
            'receiver_country_code' => $rcCountryCode,
            'receiver_country_name' => $rcCountryName,
            'receiver_name' => $rcName,
            'receiver_company' => $rcCompany,
            'receiver_address' => $rcAddress,
            'sender_name' => $sndName,
            'sender_company' => $sndCompany,
            'weight_kg' => $weightKg,
            'size_l_cm' => $sizeL,
            'size_w_cm' => $sizeW,
            'size_h_cm' => $sizeH,
            'label_image' => null,
            'box_image' => null,
            'addons_json' => null,
        ];

        $draftId = warehouse_item_in_find_or_create_draft($dbcnx, $batchUid, $ownerUserId, $deviceId, $fields);
        if ($draftId <= 0) {
            $response = ['status' => 'error', 'message' => 'Не удалось создать черновик'];
            break;
        }

        $response = [
            'status' => 'ok',
            'message' => 'Черновик создан',
            'batch_uid' => $batchUid,
            'item_id' => $draftId,
        ];
        break;

    case 'clear_item_in_draft':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            $response = ['status' => 'ok', 'message' => 'Форма очищена'];
            break;
        }
        $stmtRead = $dbcnx->prepare("SELECT label_image, box_image FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if ($stmtRead) {
            $stmtRead->bind_param('i', $itemId);
            $stmtRead->execute();
            $row = $stmtRead->get_result()->fetch_assoc();
            $stmtRead->close();

            $paths = array_merge(
                warehouse_item_in_decode_image_paths((string)($row['label_image'] ?? '')),
                warehouse_item_in_decode_image_paths((string)($row['box_image'] ?? ''))
            );
            $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            foreach ($paths as $path) {
                if (strpos($path, '/img/warehouse_item_in/') !== 0 || $docRoot === '') {
                    continue;
                }
                $abs = $docRoot . $path;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }

        $stmt = $dbcnx->prepare("DELETE FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->close();
        $response = ['status' => 'ok', 'message' => 'Черновик удалён'];
        break;

    case 'upload_item_in_photo':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
        if ($itemId <= 0) {
            $response = ['status' => 'error', 'message' => 'Сначала получите измерения'];
            break;
        }
        if (!in_array($photoType, ['label', 'box'], true)) {
            $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
            break;
        }
        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            $response = ['status' => 'error', 'message' => 'Файл не передан'];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT id, uid_created, label_image, box_image FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            $response = ['status' => 'error', 'message' => 'Черновик не найден'];
            break;
        }

        $upload = $_FILES['photo'];
        $errorCode = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $response = ['status' => 'error', 'message' => 'Ошибка загрузки файла'];
            break;
        }
        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $response = ['status' => 'error', 'message' => 'Некорректный временный файл'];
            break;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            $response = ['status' => 'error', 'message' => 'Разрешены только JPEG/PNG/WEBP'];
            break;
        }

        $uidCreated = trim((string)($item['uid_created'] ?? ''));
        if ($uidCreated === '') {
            $uidCreated = (string)$itemId;
        }
        $baseRelDir = 'img/warehouse_item_in/' . $uidCreated;
        $baseAbsDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $baseRelDir;
        if (!warehouse_item_in_ensure_photo_dir($baseAbsDir)) {
            $response = ['status' => 'error', 'message' => 'Не удалось создать каталог для фото'];
            break;
        }

        $fileName = date('Ymd_His') . '_' . $photoType . '.' . $allowed[$mime];
        $destAbs = $baseAbsDir . '/' . $fileName;
        $publicPath = '/' . $baseRelDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $destAbs)) {
            $response = ['status' => 'error', 'message' => 'Не удалось сохранить файл'];
            break;
        }

        $field = $photoType === 'label' ? 'label_image' : 'box_image';
        $oldJson = (string)($item[$field] ?? '');
        $oldPaths = warehouse_item_in_decode_image_paths($oldJson);
        $oldPaths[] = $publicPath;
        $json = warehouse_item_in_normalize_image_json(json_encode($oldPaths, JSON_UNESCAPED_UNICODE) ?: '');
        if ($json === null) {
            @unlink($destAbs);
            $response = ['status' => 'error', 'message' => 'Ошибка сериализации пути'];
            break;
        }

        $sql = "UPDATE warehouse_item_in SET {$field} = ? WHERE id = ? AND committed = 0 LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            @unlink($destAbs);
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('si', $json, $itemId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'status' => 'ok',
            'message' => 'Фото загружено',
            'photo_type' => $photoType,
            'path' => $publicPath,
            'json' => $json,
        ];
        break;

    case 'delete_item_in_photo':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
        if ($itemId <= 0) {
            $response = ['status' => 'error', 'message' => 'Некорректный item_id'];
            break;
        }
        if (!in_array($photoType, ['label', 'box'], true)) {
            $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
            break;
        }

        $field = $photoType === 'label' ? 'label_image' : 'box_image';
        $stmt = $dbcnx->prepare("SELECT id, {$field} FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            $response = ['status' => 'error', 'message' => 'Черновик не найден'];
            break;
        }

        $oldPaths = warehouse_item_in_decode_image_paths((string)($item[$field] ?? ''));
        $stmt = $dbcnx->prepare("UPDATE warehouse_item_in SET {$field} = NULL WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->close();

        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        foreach ($oldPaths as $path) {
            if (strpos($path, '/img/warehouse_item_in/') !== 0 || $docRoot === '') {
                continue;
            }
            $abs = $docRoot . $path;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        $response = ['status' => 'ok', 'message' => 'Фото удалено', 'json' => ''];
        break;
    case 'check_item_in_duplicate':
        auth_require_login();
        $tuid        = trim($_POST['tuid']        ?? '');
        $tracking    = trim($_POST['tracking_no'] ?? '');
        $carrierName = trim($_POST['carrier_name'] ?? '');
        $itemId = (int)($_POST['item_id'] ?? 0);
        $duplicateCheck = findWarehouseDuplicate($dbcnx, $carrierName, $tuid, $tracking, $itemId);
        $response = [
            'status'    => 'ok',
            'duplicate' => $duplicateCheck['duplicate'],
            'source'    => $duplicateCheck['source'],
        ];
    case 'delete_item_in':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        $current = $user;
        $userId  = (int)$current['id'];
        $isAdmin = auth_has_permission('warehouse.in.manage_all') || auth_has_role('ADMIN');
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
        audit_log(
            $userId,
            'WAREHOUSE_IN_DELETE_PARCEL',
            'WAREHOUSE_IN',
            $itemId,
            'Удалена посылка из партии прихода',
            [
                'batch_uid'    => $batchUid,
                'item_id'      => $itemId,
                'item_user_id' => $itemUserId,
                'deleted_by'   => $userId,
                'is_admin'     => $isAdmin,
            ]
        );
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

        [$addonsMap, $addonsRawMap] = warehouse_item_in_load_addons_map($dbcnx);
        $smarty->assign('addons_map', $addonsMap);
        $smarty->assign('addons_raw_map', $addonsRawMap);
        require_once __DIR__ . '/../../ocr_templates.php';
        require_once __DIR__ . '/../../ocr_dicts.php';
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
        warehouse_item_in_ensure_addons_columns($dbcnx);
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

        $isAdmin = auth_has_permission('warehouse.in.manage_all') || auth_has_role('ADMIN');
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
                        label_image, box_image, addons_json
                    )
                    SELECT
                        batch_uid, uid_created, user_id, device_id, created_at,
                        tuid, tracking_no, carrier_code, carrier_name,
                        receiver_country_code, receiver_country_name,
                        receiver_name, receiver_company, receiver_address,
                        sender_name, sender_company,
                        weight_kg, size_l_cm, size_w_cm, size_h_cm,
                        label_image, box_image, addons_json
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
}
