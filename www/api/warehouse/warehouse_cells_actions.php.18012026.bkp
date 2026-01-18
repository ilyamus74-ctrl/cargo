<?php
declare(strict_types=1);

/**
 * Обработчик действий с ячейками склада
 * Actions: setting_cells, add_new_cells, delete_cell, form_edit_cell, save_cell
 */

// Доступны: $action, $user, $dbcnx, $smarty

$response = ['status' => 'error', 'message' => 'Unknown warehouse cells action'];

switch ($action) {
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
         if ($res = $dbcnx->query("SELECT id, code, qr_payload, qr_file, description FROM cells ORDER BY code")) {
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
        $dir = __DIR__ . '/../../img/cells';
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
        // Удаляем файл, если есть
        $dir = __DIR__ . '/../../img/cells';
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

}
