<?php
declare(strict_types=1);

/**
 * Обработчик действий с ячейками склада
 * Actions: setting_cells, add_new_cells, delete_cell, form_edit_cell, save_cell
 */

// Доступны: $action, $user, $dbcnx, $smarty
require_once __DIR__ . '/warehouse_forwarder_sync_helpers.php';

$response = ['status' => 'error', 'message' => 'Unknown warehouse cells action'];
warehouse_forwarder_ensure_sync_tables($dbcnx);

switch ($action) {
    case 'sync_forwarder_positions':
        $connectorId = (int)($_POST['connector_id'] ?? 0);
        if ($connectorId <= 0) { $response = ['status'=>'error','message'=>'connector_id обязателен']; break; }
        $response = ['status'=>'ok','diagnostics'=>warehouse_forwarder_sync_positions($dbcnx, $connectorId)];
        break;

    case 'form_cell_forwarder_mappings':
        warehouse_forwarder_ensure_sync_tables($dbcnx);
        $cellId = (int)($_POST['cell_id'] ?? 0);
        $stmt = $dbcnx->prepare('SELECT id, code FROM cells WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $cellId); $stmt->execute(); $cell = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$cell) { $response = ['status'=>'error','message'=>'Ячейка не найдена']; break; }
        $connectorCountrySelect = warehouse_forwarder_column_exists($dbcnx, 'connectors', 'country_code') ? ', country_code' : ", '' AS country_code";
        $connectors=[]; if($r=$dbcnx->query('SELECT id,name' . $connectorCountrySelect . ' FROM connectors ORDER BY name')){while($x=$r->fetch_assoc())$connectors[]=$x;$r->free();}
        $positions=[]; if($r=$dbcnx->query('SELECT connector_id,position_code,position_label FROM forwarder_positions WHERE is_active=1 ORDER BY connector_id, position_code')){while($x=$r->fetch_assoc())$positions[]=$x;$r->free();}
        $mappings=[]; $stmt=$dbcnx->prepare('SELECT m.*, c.name AS connector_name FROM warehouse_cell_forwarder_map m LEFT JOIN connectors c ON c.id=m.connector_id WHERE m.cell_id=? ORDER BY c.name,m.forwarder_position_code'); $stmt->bind_param('i',$cellId); $stmt->execute(); $rr=$stmt->get_result(); while($x=$rr->fetch_assoc())$mappings[]=$x; $stmt->close();
        $smarty->assign('cell',$cell); $smarty->assign('connectors',$connectors); $smarty->assign('forwarder_positions',$positions); $smarty->assign('mappings',$mappings);
        ob_start(); $smarty->display('cells_NA_API_warehouse_cell_forwarder_mappings.html'); $html=ob_get_clean();
        $response=['status'=>'ok','html'=>$html];
        break;

    case 'save_cell_forwarder_mapping':
        warehouse_forwarder_ensure_sync_tables($dbcnx);
        $cellId=(int)($_POST['cell_id']??0); $connectorId=(int)($_POST['connector_id']??0); $code=warehouse_forwarder_norm_code((string)($_POST['forwarder_position_code']??''));
        $country=strtoupper(trim((string)($_POST['country_code']??''))); $active=!empty($_POST['is_active'])?1:0; $comment=trim((string)($_POST['comment']??''));
        if($cellId<=0||$connectorId<=0||$code===''){ $response=['status'=>'error','message'=>'Заполните ячейку, коннектор и позицию форварда']; break; }
        $stmt=$dbcnx->prepare('INSERT INTO warehouse_cell_forwarder_map (connector_id,forwarder_position_code,cell_id,country_code,is_active,comment) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE cell_id=VALUES(cell_id), country_code=VALUES(country_code), is_active=VALUES(is_active), comment=VALUES(comment)');
        $stmt->bind_param('isisis',$connectorId,$code,$cellId,$country,$active,$comment); $stmt->execute(); $stmt->close();
        $response=['status'=>'ok','message'=>'Связь сохранена'];
        break;

    case 'delete_cell_forwarder_mapping':
        warehouse_forwarder_ensure_sync_tables($dbcnx);
        $mappingId=(int)($_POST['mapping_id']??0); $stmt=$dbcnx->prepare('DELETE FROM warehouse_cell_forwarder_map WHERE id=? LIMIT 1'); $stmt->bind_param('i',$mappingId); $stmt->execute(); $stmt->close();
        $response=['status'=>'ok','message'=>'Связь удалена'];
        break;

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
         if ($res = $dbcnx->query("SELECT c.id, c.code, c.qr_payload, c.qr_file, c.description, GROUP_CONCAT(CONCAT(COALESCE(conn.name, CONCAT('Connector #', m.connector_id)), ': ', m.forwarder_position_code) ORDER BY conn.name SEPARATOR '||') AS forwarder_mappings FROM cells c LEFT JOIN warehouse_cell_forwarder_map m ON m.cell_id = c.id AND m.is_active = 1 LEFT JOIN connectors conn ON conn.id = m.connector_id GROUP BY c.id, c.code, c.qr_payload, c.qr_file, c.description ORDER BY c.code")) {
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

    case 'form_edit_cell':
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
        $smarty->assign('edit_cell', $cell);
        ob_start();
        $smarty->display('cells_NA_API_warehouse_cell_form.html');
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
        //$re = '/^([A-Z])(\d{1,4})$/u';
        // Парсим вида A10, B25, MAN10 и т.п.
        $re = '/^([A-Z]{1,3})(\d{1,4})$/u';
        if (!preg_match($re, $first, $m1) || !preg_match($re, $last, $m2)) {
            $response = [
                'status'  => 'error',
                'message' => 'Коды ячеек должны быть вида A10, A99 , PI1 - PI99 , ZAL1 - ZAL99 и т.п.',
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
                'message' => 'Буквенная часть должна совпадать (например A10–A99, PI1 - PI99, ZAL1 - ZAL99, а не A10–B20)',
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
        $dir = __DIR__ .  '/../../img/cells';
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
            $qrPayload  = 'CELL: ' . $code;
            
            // ==========================================
            // Генерируем SVG
            // ==========================================
            $svgFileName = $code . '.svg';
            $svgPath     = $dir . '/' . $svgFileName;
            $cmdSvg = sprintf(
                'qrencode -o %s -t SVG -s 8 -l H %s 2>&1',
                escapeshellarg($svgPath),
                escapeshellarg($qrPayload)
            );
            exec($cmdSvg, $outSvg, $rcSvg);
            if ($rcSvg !== 0) {
                error_log('qrencode SVG error for cell ' . $code .  ': ' . implode("\n", $outSvg));
            }
            
            // ==========================================
            // Генерируем PNG в хорошем качестве
            // ==========================================
            $pngFileName = $code . '.png';
            $pngPath     = $dir . '/' . $pngFileName;
            $cmdPng = sprintf(
                'qrencode -o %s -t PNG -s 10 -l H %s 2>&1',
                escapeshellarg($pngPath),
                escapeshellarg($qrPayload)
            );
            exec($cmdPng, $outPng, $rcPng);
            if ($rcPng !== 0) {
                error_log('qrencode PNG error for cell ' . $code . ': ' . implode("\n", $outPng));
            }
            
            // Вставляем в БД:  code, qr_payload, qr_file (PNG), description
            // qr_file оставляем PNG для обратной совместимости
            $stmtIns = $dbcnx->prepare(
                "INSERT INTO cells (code, qr_payload, qr_file, description)
                 VALUES (?, ?, ?, ? )"
            );
            $stmtIns->bind_param("ssss", $code, $qrPayload, $pngFileName, $desc);
            $stmtIns->execute();
            $stmtIns->close();
            
            $created++;
        }
        // Перечитываем список ячеек
        $cells = [];
        $sql = "SELECT c.id, c.code, c.qr_payload, c.qr_file, c.description, GROUP_CONCAT(CONCAT(COALESCE(conn.name, CONCAT('Connector #', m.connector_id)), ': ', m.forwarder_position_code) ORDER BY conn.name SEPARATOR '||') AS forwarder_mappings
                  FROM cells c
                  LEFT JOIN warehouse_cell_forwarder_map m ON m.cell_id = c.id AND m.is_active = 1
                  LEFT JOIN connectors conn ON conn.id = m.connector_id
                 GROUP BY c.id, c.code, c.qr_payload, c.qr_file, c.description
                 ORDER BY c.code";
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
    case 'save_cell':
        $cellId = (int)($_POST['cell_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($cellId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Некорректный ID ячейки',
            ];
            break;
        }
        $stmt = $dbcnx->prepare(
            "SELECT id, code, qr_payload, description
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
        $stmt = $dbcnx->prepare(
            "UPDATE cells
                SET description = ?
              WHERE id = ?"
        );
        $stmt->bind_param("si", $description, $cellId);
        $stmt->execute();
        $stmt->close();
        audit_log(
            $user['id'] ?? null,
            'CELL_UPDATE',
            'CELL',
            $cellId,
            'Обновление описания ячейки',
            [
                'code'               => $cell['code'],
                'description_before' => $cell['description'],
                'description_after'  => $description,
            ]
        );
        $response = [
            'status'  => 'ok',
            'message' => 'Описание ячейки обновлено',
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


        $stmt = $dbcnx->prepare(
            "SELECT COUNT(*) AS total
               FROM warehouse_item_stock
              WHERE cell_id = ?"
        );
        $stmt->bind_param("i", $cellId);
        $stmt->execute();
        $res = $stmt->get_result();
        $usage = $res->fetch_assoc();
        $stmt->close();
        $totalUsage = (int)($usage['total'] ?? 0);
        if ($totalUsage > 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Нельзя удалить ячейку: есть посылки, привязанные к этой ячейке.',
            ];
            break;
        }
        // Удаляем файлы (PNG и SVG), если есть
        $dir = __DIR__ . '/../../img/cells';
        $code = $cell['code'];
        
        // Удаляем PNG
        if (! empty($cell['qr_file'])) {
            $pngPath = $dir . '/' . $cell['qr_file'];
            if (is_file($pngPath)) {
                @unlink($pngPath);
            }
        } else {
            // запасной вариант по коду
            $fallbackPng = $dir . '/' .  $code . '.png';
            if (is_file($fallbackPng)) {
                @unlink($fallbackPng);
            }
        }
        
        // Удаляем SVG
        $svgPath = $dir . '/' . $code . '. svg';
        if (is_file($svgPath)) {
            @unlink($svgPath);
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
        $sql = "SELECT c.id, c.code, c.qr_payload, c.qr_file, c.description, GROUP_CONCAT(CONCAT(COALESCE(conn.name, CONCAT('Connector #', m.connector_id)), ': ', m.forwarder_position_code) ORDER BY conn.name SEPARATOR '||') AS forwarder_mappings
                  FROM cells c
                  LEFT JOIN warehouse_cell_forwarder_map m ON m.cell_id = c.id AND m.is_active = 1
                  LEFT JOIN connectors conn ON conn.id = m.connector_id
                 GROUP BY c.id, c.code, c.qr_payload, c.qr_file, c.description
                 ORDER BY c.code";
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

}
