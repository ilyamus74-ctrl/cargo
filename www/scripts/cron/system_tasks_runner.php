#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../configs/secure.php';
require_once __DIR__ . '/../../api/system/system_tasks_lib.php';

system_tasks_ensure_tables($dbcnx);
system_tasks_seed_defaults($dbcnx);

$lockRes = $dbcnx->query("SELECT GET_LOCK('system_tasks_runner_lock', 0) AS l");
$locked = false;
if ($lockRes && ($row = $lockRes->fetch_assoc())) {
    $locked = (int)($row['l'] ?? 0) === 1;
    $lockRes->free();
}

if (!$locked) {
    fwrite(STDOUT, "[system_tasks_runner] lock busy, skip\n");
    exit(0);
}

try {
    $stats = system_tasks_run_due($dbcnx, 1);
    fwrite(STDOUT, "[system_tasks_runner] ran={$stats['ran']} errors={$stats['errors']}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[system_tasks_runner] error: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    $dbcnx->query("DO RELEASE_LOCK('system_tasks_runner_lock')");
}
