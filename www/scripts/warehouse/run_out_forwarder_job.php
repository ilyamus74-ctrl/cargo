<?php
declare(strict_types=1);

$action = '__cli__';
require_once __DIR__ . '/../../../configs/connectDB.php';
$user = ['id' => 0];
require_once __DIR__ . '/../../api/warehouse/warehouse_sync_actions.php';

function out_forwarder_job_args(array $argv): array
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

function out_forwarder_json(array $payload, int $code = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($code);
}

try {
    if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) { throw new RuntimeException('DB connection is not available'); }
    $dbcnx->set_charset('utf8mb4');
    warehouse_sync_ensure_out_table($dbcnx);
    warehouse_sync_ensure_out_forwarder_jobs_table($dbcnx);

    $args = out_forwarder_job_args($argv);
    $jobId = max(0, (int)($args['job-id'] ?? 0));
    $limit = max(1, min(50, (int)($args['limit'] ?? 10)));
    $where = $jobId > 0 ? 'id = ? AND status IN (\'queued\',\'error\') AND attempts < max_attempts' : 'status IN (\'queued\',\'error\') AND attempts < max_attempts ORDER BY created_at ASC LIMIT ' . $limit;
    $stmt = $jobId > 0 ? $dbcnx->prepare('SELECT * FROM warehouse_out_forwarder_jobs WHERE ' . $where . ' LIMIT 1') : $dbcnx->prepare('SELECT * FROM warehouse_out_forwarder_jobs WHERE ' . $where);
    if (!$stmt) { throw new RuntimeException('Failed to prepare job query: ' . $dbcnx->error); }
    if ($jobId > 0) { $stmt->bind_param('i', $jobId); }
    $stmt->execute();
    $rows = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();

    $done = 0; $errors = 0;
    foreach ($rows as $job) {
        $id = (int)$job['id'];
        $lock = $dbcnx->prepare("UPDATE warehouse_out_forwarder_jobs SET status='running', attempts=attempts+1, started_at=NOW(), last_error=NULL WHERE id=? AND status IN ('queued','error') AND attempts < max_attempts");
        $lock->bind_param('i', $id); $lock->execute(); $affected = $lock->affected_rows; $lock->close();
        if ($affected <= 0) { continue; }
        $payload = json_decode((string)($job['payload_json'] ?? ''), true);
        if (!is_array($payload)) { $payload = []; }
        try {
            $connectorId = (int)($payload['connector_id'] ?? $job['connector_id'] ?? 0);
            $connector = warehouse_sync_fetch_connector_by_id($dbcnx, $connectorId);
            if (!$connector) { throw new RuntimeException('Коннектор не найден'); }
            $baseUrl = trim((string)($connector['base_url'] ?? ''));
            $login = trim((string)($connector['auth_username'] ?? ''));
            $password = trim((string)($connector['auth_password'] ?? ''));
            if ($baseUrl === '' || $login === '' || $password === '') { throw new RuntimeException('Не настроены доступы к форварду'); }
            $sessionFile = dirname(__DIR__, 2) . '/storage/forwarder_sessions/connector_' . $connectorId . '.cookie';
            $tracking = trim((string)($payload['tracking_no'] ?? $job['tracking_no'] ?? ''));
            $position = trim((string)($payload['container_id'] ?? $job['container_id'] ?? ''));
            $result = warehouse_sync_exec_forwarder_cli_script('run_add_package_to_container.php', [
                'base-url'=>$baseUrl, 'login'=>$login, 'password'=>$password, 'session-file'=>$sessionFile,
                'track'=>$tracking, 'verify-number'=>$tracking, 'position'=>$position, 'verify-check-package'=>'1',
                'print-label'=>'0', 'print-mode'=>'none', 'return-label-html'=>'0', 'return-label-vars'=>'0',
                'print-label-retries'=>'0', 'print-label-retry-delay-ms'=>'0',
            ]);
            $status = strtolower(trim((string)($result['status'] ?? '')));
            if ($status !== 'ok') { throw new RuntimeException(trim((string)($result['message'] ?? 'Forwarder sync failed'))); }
            $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE);
            $upd = $dbcnx->prepare("UPDATE warehouse_out_forwarder_jobs SET status='done', result_json=?, finished_at=NOW(), last_error=NULL WHERE id=?");
            $upd->bind_param('si', $resultJson, $id); $upd->execute(); $upd->close();
            $outId = (int)($job['out_item_id'] ?? 0);
            $updOut = $dbcnx->prepare("UPDATE warehouse_item_out SET forwarder_sync_status='ok', forwarder_sync_message=NULL, forwarder_synced_at=NOW() WHERE id=? LIMIT 1");
            $updOut->bind_param('i', $outId); $updOut->execute(); $updOut->close();
            $done++;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $upd = $dbcnx->prepare("UPDATE warehouse_out_forwarder_jobs SET status='error', last_error=?, finished_at=NOW() WHERE id=?");
            $upd->bind_param('si', $msg, $id); $upd->execute(); $upd->close();
            $outId = (int)($job['out_item_id'] ?? 0);
            $updOut = $dbcnx->prepare("UPDATE warehouse_item_out SET forwarder_sync_status='error', forwarder_sync_message=? WHERE id=? LIMIT 1");
            $updOut->bind_param('si', $msg, $outId); $updOut->execute(); $updOut->close();
            $errors++;
        }
    }
    out_forwarder_json(['status'=>'ok','processed'=>count($rows),'done'=>$done,'errors'=>$errors]);
} catch (Throwable $e) {
    out_forwarder_json(['status'=>'error','message'=>$e->getMessage()], 1);
}