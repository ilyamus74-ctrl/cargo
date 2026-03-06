<?php
declare(strict_types=1);

function system_tasks_ensure_tables(mysqli $dbcnx): void
{
    $dbcnx->query("CREATE TABLE IF NOT EXISTS system_tasks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(64) NOT NULL UNIQUE,
        name VARCHAR(191) NOT NULL,
        description TEXT NULL,
        endpoint_action VARCHAR(128) NOT NULL,
        payload_json LONGTEXT NULL,
        interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        last_run_at DATETIME NULL,
        next_run_at DATETIME NULL,
        last_status VARCHAR(16) NULL,
        last_message TEXT NULL,
        is_running TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $dbcnx->query("CREATE TABLE IF NOT EXISTS system_task_runs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        task_id INT UNSIGNED NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        status VARCHAR(16) NOT NULL,
        message TEXT NULL,
        context_json LONGTEXT NULL,
        KEY idx_task_started (task_id, started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $dbcnx->query("CREATE TABLE IF NOT EXISTS warehouse_sync_batch_jobs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        forwarder VARCHAR(64) NULL,
        requested_by INT UNSIGNED NULL,
        total_items INT UNSIGNED NOT NULL DEFAULT 0,
        processed_items INT UNSIGNED NOT NULL DEFAULT 0,
        ok_items INT UNSIGNED NOT NULL DEFAULT 0,
        fail_items INT UNSIGNED NOT NULL DEFAULT 0,
        message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $dbcnx->query("CREATE TABLE IF NOT EXISTS warehouse_sync_batch_job_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        job_id BIGINT UNSIGNED NOT NULL,
        item_id INT UNSIGNED NOT NULL,
        connector_id INT UNSIGNED NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        message TEXT NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_job_item (job_id, item_id),
        KEY idx_job_status (job_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function system_tasks_seed_defaults(mysqli $dbcnx): void
{
    $defaults = [
        [
            'code' => 'operation_1_hourly',
            'name' => 'Операция #1 (раз в час)',
            'description' => 'Шаблон системной задачи. Можно заменить endpoint_action на реальный.',
            'endpoint_action' => 'operation_1',
            'interval_minutes' => 60,
        ],
        [
            'code' => 'warehouse_sync_out_backfill_hourly',
            'name' => 'Backfill warehouse_item_out (раз в час)',
            'description' => 'Заполняет/обновляет warehouse_item_out из warehouse_item_stock.',
            'endpoint_action' => 'warehouse_sync_out_backfill',
            'interval_minutes' => 60,
        ],
        [
            'code' => 'connectors_report_operation_1_hourly',
            'name' => 'Коннекторы: Операция #1 (обновление репортов, раз в час)',
            'description' => 'Для активных коннекторов с включенной операцией #1 скачивает и импортирует репорты.',
            'endpoint_action' => 'connectors_report_operation_1',
            'interval_minutes' => 60,
        ],
        [
            'code' => 'warehouse_sync_batch_worker',
            'name' => 'Обработчик пакетной синхронизации посылок',
            'description' => 'Берет queued/running batch jobs и обрабатывает их в фоне.',
            'endpoint_action' => 'warehouse_sync_batch_worker',
            'interval_minutes' => 1,
        ],
    ];

    $sql = "INSERT INTO system_tasks (code, name, description, endpoint_action, interval_minutes, is_enabled, next_run_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                endpoint_action = VALUES(endpoint_action),
                interval_minutes = VALUES(interval_minutes)";

    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('system_tasks seed prepare failed');
    }

    foreach ($defaults as $task) {
        $stmt->bind_param(
            'ssssi',
            $task['code'],
            $task['name'],
            $task['description'],
            $task['endpoint_action'],
            $task['interval_minutes']
        );
        $stmt->execute();
    }
    $stmt->close();
}

function system_tasks_run_due(mysqli $dbcnx, int $systemUserId = 0): array
{
    system_tasks_ensure_tables($dbcnx);

    $ran = 0;
    $errors = 0;

    $res = $dbcnx->query("SELECT * FROM system_tasks
                         WHERE is_enabled = 1
                           AND is_running = 0
                           AND (next_run_at IS NULL OR next_run_at <= NOW())
                         ORDER BY next_run_at IS NULL DESC, next_run_at ASC, id ASC
                         LIMIT 20");

    if (!$res) {
        throw new RuntimeException('Failed to fetch due tasks: ' . $dbcnx->error);
    }

    while ($task = $res->fetch_assoc()) {
        $taskId = (int)$task['id'];
        $lockStmt = $dbcnx->prepare("UPDATE system_tasks SET is_running = 1 WHERE id = ? AND is_running = 0");
        if (!$lockStmt) {
            continue;
        }
        $lockStmt->bind_param('i', $taskId);
        $lockStmt->execute();
        $locked = $lockStmt->affected_rows > 0;
        $lockStmt->close();
        if (!$locked) {
            continue;
        }

        $ran += 1;
        $runStatus = 'ok';
        $runMessage = 'done';
        $context = [];

        try {
            $result = system_tasks_execute($dbcnx, $task, $systemUserId);
            $runStatus = ($result['status'] ?? 'ok') === 'ok' ? 'ok' : 'error';
            $runMessage = (string)($result['message'] ?? 'done');
            $context = is_array($result['context'] ?? null) ? $result['context'] : [];
        } catch (Throwable $e) {
            $runStatus = 'error';
            $runMessage = $e->getMessage();
            $errors += 1;
        }

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        $runStmt = $dbcnx->prepare("INSERT INTO system_task_runs (task_id, status, message, context_json, finished_at)
                                    VALUES (?, ?, ?, ?, NOW())");
        if ($runStmt) {
            $runStmt->bind_param('isss', $taskId, $runStatus, $runMessage, $contextJson);
            $runStmt->execute();
            $runStmt->close();
        }

        $interval = max(1, (int)($task['interval_minutes'] ?? 60));
        $upd = $dbcnx->prepare("UPDATE system_tasks
                               SET is_running = 0,
                                   last_run_at = NOW(),
                                   next_run_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                                   last_status = ?,
                                   last_message = ?
                               WHERE id = ?");
        if ($upd) {
            $upd->bind_param('issi', $interval, $runStatus, $runMessage, $taskId);
            $upd->execute();
            $upd->close();
        }
    }

    $res->free();

    return ['ran' => $ran, 'errors' => $errors];
}

function system_tasks_execute(mysqli $dbcnx, array $task, int $systemUserId = 0): array
{
    $action = trim((string)($task['endpoint_action'] ?? ''));
    if ($action === '') {
        return ['status' => 'error', 'message' => 'Empty endpoint_action'];
    }

    if ($action === 'operation_1') {
        return [
            'status' => 'ok',
            'message' => 'operation_1 placeholder executed',
            'context' => ['task' => 'operation_1'],
        ];
    }

    if ($action === 'warehouse_sync_batch_worker') {
        return system_tasks_run_warehouse_sync_batch_worker($dbcnx, $systemUserId);
    }

    if ($action === 'warehouse_sync_out_backfill') {
        return system_tasks_run_warehouse_sync_out_backfill($dbcnx, $task);
    }

    if ($action === 'connectors_report_operation_1') {
        return system_tasks_run_connectors_report_operation_1($dbcnx, $task);
    }

    return [
        'status' => 'error',
        'message' => 'Unknown endpoint_action: ' . $action,
    ];
}

function system_tasks_run_warehouse_sync_out_backfill(mysqli $dbcnx, array $task): array
{
    $payloadRaw = (string)($task['payload_json'] ?? '');
    $payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
    if (!is_array($payload)) {
        $payload = [];
    }

    $limit = max(1, min(10000, (int)($payload['limit'] ?? 500)));

    if (!function_exists('warehouse_sync_out_backfill_from_stock')) {
        $action = '__system_task_bootstrap__';
        $user = ['id' => 1, 'role' => 'ADMIN'];
        $response = ['status' => 'ok'];
        require_once __DIR__ . '/../warehouse/warehouse_sync_actions.php';
    }

    if (!function_exists('warehouse_sync_out_backfill_from_stock')) {
        return ['status' => 'error', 'message' => 'warehouse_sync_out_backfill_from_stock not available'];
    }

    $result = warehouse_sync_out_backfill_from_stock($dbcnx, $limit);

    return [
        'status' => 'ok',
        'message' => 'warehouse_sync_out_backfill done',
        'context' => [
            'limit' => $limit,
            'processed' => (int)($result['processed'] ?? 0),
            'inserted' => (int)($result['inserted'] ?? 0),
            'updated' => (int)($result['updated'] ?? 0),
        ],
    ];
}

function system_tasks_run_connectors_report_operation_1(mysqli $dbcnx, array $task): array
{
    if (!function_exists('connectors_decode_operations')) {
        $action = '__system_task_bootstrap__';
        $user = ['id' => 1, 'role' => 'ADMIN'];
        $response = ['status' => 'ok'];
        require_once __DIR__ . '/../connectors/connector_actions.php';
    }

    $requiredFns = [
        'connectors_decode_operations',
        'connectors_normalize_report_table_name',
        'connectors_ensure_report_table',
        'connectors_download_report_file',
        'connectors_import_csv_into_report_table',
        'connectors_import_xlsx_into_report_table',
    ];
    foreach ($requiredFns as $fn) {
        if (!function_exists($fn)) {
            return ['status' => 'error', 'message' => "{$fn} not available"];
        }
    }

    $processed = 0;
    $ok = 0;
    $fail = 0;
    $details = [];

    $res = $dbcnx->query("SELECT * FROM connectors WHERE is_active = 1 ORDER BY id ASC");
    if (!$res) {
        return ['status' => 'error', 'message' => 'Failed to fetch connectors'];
    }

    while ($connector = $res->fetch_assoc()) {
        $connectorId = (int)($connector['id'] ?? 0);
        if ($connectorId <= 0) {
            continue;
        }

        $operations = connectors_decode_operations($connector);
        $reportCfg = (array)($operations['report'] ?? []);
        if (empty($reportCfg['enabled'])) {
            continue;
        }

        $processed += 1;

        try {
            $targetTable = connectors_normalize_report_table_name((string)($reportCfg['target_table'] ?? ''));
            connectors_ensure_report_table($dbcnx, $targetTable);

            $downloadInfo = connectors_download_report_file($connector, $reportCfg, null, null);
            $fieldMapping = isset($reportCfg['field_mapping']) && is_array($reportCfg['field_mapping']) ? $reportCfg['field_mapping'] : [];
            $fileExt = strtolower(trim((string)($downloadInfo['file_extension'] ?? '')));

            $importedRows = 0;
            if ($fileExt === 'csv') {
                $importedRows = connectors_import_csv_into_report_table(
                    $dbcnx,
                    $targetTable,
                    (string)$downloadInfo['file_path'],
                    $connectorId,
                    null,
                    null,
                    $fieldMapping
                );
            } elseif ($fileExt === 'xlsx') {
                $importedRows = connectors_import_xlsx_into_report_table(
                    $dbcnx,
                    $targetTable,
                    (string)$downloadInfo['file_path'],
                    $connectorId,
                    null,
                    null,
                    $fieldMapping
                );
            }

            $ok += 1;
            $details[] = [
                'connector_id' => $connectorId,
                'status' => 'ok',
                'target_table' => $targetTable,
                'file_extension' => $fileExt,
                'imported_rows' => $importedRows,
            ];
        } catch (Throwable $e) {
            $fail += 1;
            $details[] = [
                'connector_id' => $connectorId,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    $res->free();

    return [
        'status' => $fail > 0 ? 'error' : 'ok',
        'message' => "connectors_report_operation_1 done: processed={$processed}, ok={$ok}, fail={$fail}",
        'context' => [
            'processed' => $processed,
            'ok' => $ok,
            'fail' => $fail,
            'details' => $details,
        ],
    ];
}

function system_tasks_run_warehouse_sync_batch_worker(mysqli $dbcnx, int $systemUserId = 0): array
{
    $jobRes = $dbcnx->query("SELECT * FROM warehouse_sync_batch_jobs
                            WHERE status IN ('queued','running')
                            ORDER BY FIELD(status, 'running', 'queued'), created_at ASC
                            LIMIT 1");
    if (!$jobRes) {
        return ['status' => 'error', 'message' => 'Failed to fetch batch jobs'];
    }

    $job = $jobRes->fetch_assoc();
    $jobRes->free();

    if (!$job) {
        return ['status' => 'ok', 'message' => 'No queued batch jobs', 'context' => ['processed' => 0]];
    }

    $jobId = (int)$job['id'];
    if ((string)$job['status'] === 'queued') {
        $stmt = $dbcnx->prepare("UPDATE warehouse_sync_batch_jobs SET status = 'running', started_at = NOW() WHERE id = ? AND status = 'queued'");
        if ($stmt) {
            $stmt->bind_param('i', $jobId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $itemsRes = $dbcnx->query("SELECT * FROM warehouse_sync_batch_job_items
                              WHERE job_id = {$jobId} AND status = 'pending'
                              ORDER BY id ASC
                              LIMIT 20");
    if (!$itemsRes) {
        return ['status' => 'error', 'message' => 'Failed to fetch batch items'];
    }

    $processed = 0;
    $ok = 0;
    $fail = 0;

    while ($item = $itemsRes->fetch_assoc()) {
        $processed += 1;
        $itemId = (int)$item['item_id'];
        $connectorId = (int)($item['connector_id'] ?? 0);
        $jobItemId = (int)$item['id'];

        $mark = $dbcnx->prepare("UPDATE warehouse_sync_batch_job_items
                                 SET status = 'running', attempts = attempts + 1, started_at = NOW()
                                 WHERE id = ? AND status = 'pending'");
        if ($mark) {
            $mark->bind_param('i', $jobItemId);
            $mark->execute();
            $changed = $mark->affected_rows > 0;
            $mark->close();
            if (!$changed) {
                continue;
            }
        }

        $_SESSION['user'] = ['id' => $systemUserId > 0 ? $systemUserId : 1, 'role' => 'ADMIN'];
        $_POST = [
            'action' => 'warehouse_sync_item',
            'item_id' => (string)$itemId,
            'connector_id' => (string)$connectorId,
        ];
        $action = 'warehouse_sync_item';
        $user = $_SESSION['user'];
        $response = ['status' => 'error', 'message' => 'Unknown'];

        require __DIR__ . '/../warehouse/warehouse_sync_actions.php';

        $isOk = (string)($response['status'] ?? '') === 'ok';
        $msg = (string)($response['message'] ?? '');

        if ($isOk) {
            $ok += 1;
            $upd = $dbcnx->prepare("UPDATE warehouse_sync_batch_job_items SET status = 'ok', message = ?, finished_at = NOW() WHERE id = ?");
        } else {
            $fail += 1;
            $upd = $dbcnx->prepare("UPDATE warehouse_sync_batch_job_items SET status = 'error', message = ?, finished_at = NOW() WHERE id = ?");
        }
        if ($upd) {
            $upd->bind_param('si', $msg, $jobItemId);
            $upd->execute();
            $upd->close();
        }
    }
    $itemsRes->free();

    $dbcnx->query("UPDATE warehouse_sync_batch_jobs j
                  JOIN (
                    SELECT job_id,
                           SUM(status IN ('ok','error')) AS processed,
                           SUM(status = 'ok') AS ok_count,
                           SUM(status = 'error') AS fail_count,
                           SUM(status = 'pending') AS pending_count
                    FROM warehouse_sync_batch_job_items
                    WHERE job_id = {$jobId}
                    GROUP BY job_id
                  ) s ON s.job_id = j.id
                  SET j.processed_items = s.processed,
                      j.ok_items = s.ok_count,
                      j.fail_items = s.fail_count,
                      j.status = IF(s.pending_count = 0, 'done', 'running'),
                      j.finished_at = IF(s.pending_count = 0, NOW(), j.finished_at),
                      j.message = CONCAT('processed=', s.processed, ', ok=', s.ok_count, ', fail=', s.fail_count)
                  WHERE j.id = {$jobId}");

    return [
        'status' => 'ok',
        'message' => 'Batch worker executed',
        'context' => ['job_id' => $jobId, 'processed' => $processed, 'ok' => $ok, 'fail' => $fail],
    ];
}
