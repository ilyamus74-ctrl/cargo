<?php
declare(strict_types=1);

$action = '__cli__';
require_once __DIR__ . '/../../../configs/connectDB.php';
$user = ['id' => 0];
require_once __DIR__ . '/../../api/warehouse/warehouse_sync_actions.php';

function out_forwarder_label_prepare_args(array $argv): array
{
    $args = [];
    foreach ($argv as $arg) {
        if (!is_string($arg) || !str_starts_with($arg, '--')) { continue; }
        $eq = strpos($arg, '=');
        if ($eq === false) { $args[substr($arg, 2)] = '1'; continue; }
        $args[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
    }
    return $args;
}

function out_forwarder_label_prepare_json(array $payload, int $code = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($code);
}

try {
    if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) { throw new RuntimeException('DB connection is not available'); }
    $dbcnx->set_charset('utf8mb4');
    warehouse_sync_ensure_out_table($dbcnx);

    $args = out_forwarder_label_prepare_args($argv);
    $limit = max(1, min(50, (int)($args['limit'] ?? 10)));
    $maxAttempts = max(1, min(20, (int)($args['max-attempts'] ?? 5)));

    $sql = "SELECT wo.* FROM warehouse_item_out wo
            WHERE wo.status = 'to_send'
              AND (wo.forwarder_label_status IS NULL OR wo.forwarder_label_status IN ('queued','preparing','error'))
              AND COALESCE(wo.forwarder_label_message, '') NOT LIKE CONCAT('attempts>=', ?, '%')
            ORDER BY COALESCE(wo.forwarder_label_prepared_at, wo.status_updated_at, wo.created_at) ASC, wo.id ASC
            LIMIT " . $limit;
    $attemptMarker = (string)$maxAttempts;
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Failed to prepare item query: ' . $dbcnx->error); }
    $stmt->bind_param('s', $attemptMarker);
    $stmt->execute();
    $rows = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    $done = 0; $errors = 0; $skipped = 0;
    foreach ($rows as $item) {
        $outId = (int)$item['id'];
        $lock = $dbcnx->prepare("UPDATE warehouse_item_out SET forwarder_label_status='preparing', forwarder_label_message=NULL WHERE id=? AND status='to_send' AND (forwarder_label_status IS NULL OR forwarder_label_status IN ('queued','error'))");
        $lock->bind_param('i', $outId); $lock->execute(); $affected = $lock->affected_rows; $lock->close();
        if ($affected <= 0) { $skipped++; continue; }

        try {
            $connector = warehouse_sync_resolve_permitted_connector($dbcnx, $item, 0);
            $connectorId = (int)($connector['id'] ?? 0);
            $baseUrl = trim((string)($connector['base_url'] ?? ''));
            $login = trim((string)($connector['auth_username'] ?? ''));
            $password = trim((string)($connector['auth_password'] ?? ''));
            if ($connectorId <= 0 || $baseUrl === '' || $login === '' || $password === '') { throw new RuntimeException('Не настроены доступы к форварду'); }
            $tracking = trim((string)($item['tracking_no'] ?: ($item['tuid'] ?? '')));
            $position = trim((string)($item['shipment_cell'] ?: ($item['shipped_container_name'] ?? '')));
            if ($tracking === '' || $position === '') { throw new RuntimeException('Не хватает tracking/container position для подготовки лейбла'); }
            $sessionFile = dirname(__DIR__, 2) . '/storage/forwarder_sessions/connector_' . $connectorId . '.cookie';
            $result = warehouse_sync_exec_forwarder_cli_script('run_add_package_to_container.php', [
                'base-url'=>$baseUrl, 'login'=>$login, 'password'=>$password, 'session-file'=>$sessionFile,
                'track'=>$tracking, 'verify-number'=>$tracking, 'position'=>$position, 'verify-check-package'=>'1',
                'print-label'=>'0', 'print-mode'=>'none', 'return-label-html'=>'0', 'return-label-vars'=>'1',
                'print-label-retries'=>'0', 'print-label-retry-delay-ms'=>'0',
            ]);
            if (strtolower(trim((string)($result['status'] ?? ''))) !== 'ok') { throw new RuntimeException(trim((string)($result['message'] ?? 'Forwarder label prepare failed'))); }
            $labelVars = (array)($result['label_vars'] ?? []);
            if (!$labelVars) { throw new RuntimeException('Форвард не вернул label_vars'); }
            if (warehouse_sync_label_vars_have_test_values($labelVars)) { throw new RuntimeException('В label_vars обнаружены тестовые данные'); }
            $missing = warehouse_sync_label_vars_require_ready_fields($labelVars);
            if ($missing) { throw new RuntimeException('Нет обязательных данных label_vars: ' . implode(', ', $missing)); }
            $waybill = trim((string)($labelVars['{{internal_id}}'] ?? $labelVars['internal_id'] ?? $labelVars['{{barcode}}'] ?? $labelVars['barcode'] ?? ''));
            $json = json_encode($labelVars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $upd = $dbcnx->prepare("UPDATE warehouse_item_out SET forwarder_label_status='ready', forwarder_label_message=NULL, forwarder_label_vars_json=?, forwarder_waybill_code=?, forwarder_label_prepared_at=NOW(), forwarder_sync_status='ok', forwarder_sync_message=NULL, forwarder_synced_at=NOW() WHERE id=? LIMIT 1");
            $upd->bind_param('ssi', $json, $waybill, $outId); $upd->execute(); $upd->close();
            $done++;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $upd = $dbcnx->prepare("UPDATE warehouse_item_out SET forwarder_label_status='error', forwarder_label_message=?, forwarder_label_prepared_at=NOW() WHERE id=? LIMIT 1");
            $upd->bind_param('si', $msg, $outId); $upd->execute(); $upd->close();
            $errors++;
        }
    }
    out_forwarder_label_prepare_json(['status'=>'ok','processed'=>count($rows),'ready'=>$done,'errors'=>$errors,'skipped'=>$skipped]);
} catch (Throwable $e) {
    out_forwarder_label_prepare_json(['status'=>'error','message'=>$e->getMessage()], 1);
}
