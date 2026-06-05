<?php

declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../../../configs/connectDB.php';

const FORWARDER_BOT_ID = 9999;

function arg(array $argv, string $name, ?string $default = null): ?string {
    foreach ($argv as $v) {
        if (str_starts_with((string)$v, '--' . $name . '=')) {
            return trim((string)substr((string)$v, strlen($name) + 3));
        }
    }
    return $default;
}

function status_group(string $status): string {
    $s = strtolower(trim($status));
    if (in_array($s, ['not declared', 'unknown client'], true)) return 'new_packages';
    if (in_array($s, ['declared. duty paid', 'legal entity', 'declared'], true)) return 'declared_packages';
    if (in_array($s, [
        'on the way',
        'ready for carriage',
        'customs clearance started',
        'customs clearance',
        'customs clearance completed'
    ], true)) return 'shipped_packages';
    return 'unmapped';
}

function fetch_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array {
    $st = $db->prepare($sql);
    if (!$st) return null;
    if ($types !== '') $st->bind_param($types, ...$params);
    if (!$st->execute()) return null;
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

function exists_track(mysqli $db, string $table, string $track): bool {
    $sql = "SELECT id FROM {$table} WHERE tracking_no = ? OR tuid = ? LIMIT 1";
    return fetch_one($db, $sql, 'ss', [$track, $track]) !== null;
}

function log_audit(mysqli $db, string $eventType, string $entityType, ?int $entityId, array $extra, string $description): void {
    $sql = "INSERT INTO audit_logs (uid_created, user_id, event_type, entity_type, entity_id, description, extra_data) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    if (!$st) return;
    $uid = FORWARDER_BOT_ID;
    $userId = FORWARDER_BOT_ID;
    $extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $st->bind_param('iississ', $uid, $userId, $eventType, $entityType, $entityId, $description, $extraJson);
    $st->execute();
    $st->close();
}

function insert_incoming(mysqli $db, int $batchUid, string $track, ?string $storage, ?string $status): ?int {
    $tuid = $track;
    $trackingNo = $track;
    $uid = FORWARDER_BOT_ID;

    $sql = "INSERT INTO warehouse_item_in (batch_uid, uid_created, user_id, committed, tuid, tracking_no, receiver_address, addons_json) VALUES (?, ?, ?, 0, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    if (!$st) return null;
    $receiverAddress = $storage !== null && $storage !== '' ? ('FWD_STORAGE:' . $storage) : null;
    $addons = json_encode([
        'source' => 'forwarder_report_bot',
        'forwarder_status_raw' => $status,
        'forwarder_storage' => $storage,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $st->bind_param('iiissss', $batchUid, $uid, $uid, $tuid, $trackingNo, $receiverAddress, $addons);
    $ok = $st->execute();
    $id = $ok ? (int)$db->insert_id : null;
    $st->close();
    return $id;
}

function update_outgoing_shipped(mysqli $db, string $track, ?string $storage, ?string $flight, ?string $container, string $statusRaw): ?int {
    $sql = "UPDATE warehouse_item_out
            SET status='sended',
                status_message=?,
                status_updated_at=NOW(),
                shipment_cell=COALESCE(?, shipment_cell),
                shipped_flight_no=COALESCE(?, shipped_flight_no),
                shipped_container_name=COALESCE(?, shipped_container_name)
            WHERE (tracking_no = ? OR tuid = ?)
              AND status IN ('for_sync', 'to_send', 'half_sync', 'confirmed_sync', 'error')
              ";
    $st = $db->prepare($sql);
    if (!$st) return null;
    $statusMessage = 'Updated by forwarder report bot from status: ' . $statusRaw;
    $st->bind_param('ssssss', $statusMessage, $storage, $flight, $container, $track, $track);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();
    return $affected;
}


function safe_report_table(string $forwarderKey): string {
    $k = strtolower(trim($forwarderKey));
    if (!preg_match('/^[a-z0-9_]+$/', $k)) {
        throw new InvalidArgumentException('Invalid forwarder key');
    }
    return 'connector_report_' . $k;
}


/** @return array<int, string> */
function resolve_active_report_tables(mysqli $db): array {
    $activeIds = [];
    $res = $db->query("SELECT id FROM connectors WHERE is_active = 1 ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $activeIds[$id] = true;
            }
        }
    }
    if ($activeIds === []) {
        return [];
    }

    $tables = [];
    $rt = $db->query("SHOW TABLES LIKE 'connector_report_%'");
    if (!$rt) {
        return [];
    }

    while ($tr = $rt->fetch_array(MYSQLI_NUM)) {
        $table = (string)($tr[0] ?? '');
        if ($table === '') {
            continue;
        }
        $q = "SELECT connector_id FROM {$table} ORDER BY id DESC LIMIT 50";
        $sample = $db->query($q);
        if (!$sample) {
            continue;
        }
        $belongsToActive = false;
        while ($sr = $sample->fetch_assoc()) {
            $cid = (int)($sr['connector_id'] ?? 0);
            if ($cid > 0 && isset($activeIds[$cid])) {
                $belongsToActive = true;
                break;
            }
        }
        if ($belongsToActive) {
            $tables[] = $table;
        }
    }

    return array_values(array_unique($tables));
}

/** @return array<int, array<string, mixed>> */
/** @return array<int, array<string, mixed>> */
function load_latest_report_rows(mysqli $db, string $table, int $limit): array {
    if (!preg_match('/^connector_report_[a-z0-9_]+$/i', $table)) {
        return [];
    }

    $safeTable = '`' . str_replace('`', '``', $table) . '`';

    $columns = [];
    $colsRes = $db->query("SHOW COLUMNS FROM {$safeTable}");
    if ($colsRes) {
        while ($col = $colsRes->fetch_assoc()) {
            $field = strtolower(trim((string)($col['Field'] ?? '')));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
        $colsRes->free();
    }

    if (empty($columns['id']) || empty($columns['payload_json'])) {
        return [];
    }

    $sourceExpr = !empty($columns['source_file']) ? '`source_file`' : "''";
    $createdExpr = !empty($columns['created_at']) ? '`created_at`' : 'NULL';
    $lastSeenExpr = !empty($columns['last_seen_at']) ? '`last_seen_at`' : 'NULL';

    $orderParts = [];
    if (!empty($columns['last_seen_at'])) {
        $orderParts[] = '`last_seen_at` DESC';
    }
    if (!empty($columns['created_at'])) {
        $orderParts[] = '`created_at` DESC';
    }
    $orderParts[] = '`id` DESC';

    $sql = "SELECT
                id,
                payload_json,
                {$sourceExpr} AS source_file,
                {$createdExpr} AS created_at,
                {$lastSeenExpr} AS last_seen_at
            FROM {$safeTable}
            ORDER BY " . implode(', ', $orderParts) . "
            LIMIT " . max(1, $limit);

    $res = $db->query($sql);
    if (!$res) {
        return [];
    }

    $byTrack = [];

    while ($row = $res->fetch_assoc()) {
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }

        $track = trim((string)($payload['tracking_number'] ?? ''));
        $track = trim($track, "\" ");
        if ($track === '') {
            continue;
        }

        $status = trim((string)($payload['status'] ?? ''));
        $group = status_group($status);

        $payload['_meta'] = [
            'report_row_id' => (int)$row['id'],
            'source_file' => (string)($row['source_file'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'last_seen_at' => (string)($row['last_seen_at'] ?? ''),
            'table' => $table,
            'effective_reason' => $group === 'shipped_packages'
                ? 'historical_shipped_status_found'
                : 'latest_seen_status',
        ];

        /*
         * Главная логика:
         * если по треку хоть раз встретили shipped_packages,
         * то считаем посылку уже уехавшей и больше не заменяем её Declared/Not declared.
         */
        if ($group === 'shipped_packages') {
            $byTrack[$track] = $payload;
            continue;
        }

        if (!isset($byTrack[$track])) {
            $byTrack[$track] = $payload;
            continue;
        }

        $existingStatus = trim((string)($byTrack[$track]['status'] ?? ''));
        $existingGroup = status_group($existingStatus);

        if ($existingGroup === 'shipped_packages') {
            continue;
        }

        /*
         * Если shipped-статуса нет, оставляем первую строку по сортировке
         * last_seen_at DESC / created_at DESC / id DESC.
         */
    }

    $res->free();

    return array_values($byTrack);
}

$argv = $_SERVER['argv'] ?? [];
$track = arg($argv, 'track', getenv('FORWARDER_TRACK') ?: '') ?? '';
$status = arg($argv, 'status', getenv('FORWARDER_STATUS') ?: '') ?? '';
$storage = arg($argv, 'storage', null);
$flight = arg($argv, 'flight', null);
$containerName = arg($argv, 'container-name', null);
$dryRun = strtolower((string)(arg($argv, 'dry-run', '0') ?? '0'));
$forwarderKey = (string)(arg($argv, 'forwarder', '') ?? '');
$reportLimit = (int)(arg($argv, 'report-limit', '5000') ?? '5000');
$dry = in_array($dryRun, ['1', 'true', 'yes'], true);
$onlyOutgoingRaw = strtolower((string)(arg($argv, 'only-outgoing', '0') ?? '0'));
$onlyOutgoing = in_array($onlyOutgoingRaw, ['1', 'true', 'yes', 'on'], true);
$runId = (string)(arg($argv, 'run-id', '') ?? '');
$runId = $runId !== '' ? $runId : ('run-report-bot-' . date('YmdHis'));
$batchUid = (int)preg_replace('/\D+/', '', $runId);
if ($batchUid <= 0) {
    $batchUid = (int)(microtime(true) * 1000000);
}

$result = [
    'run_id' => $runId,
    'bot_actor_id' => FORWARDER_BOT_ID,
    'track' => $track,
    'forwarder_status_raw' => $status,
    'group' => status_group($status),
    'actions' => [],
    'dry_run' => $dry,
    'only_outgoing' => $onlyOutgoing,
];

$mode = (string)(arg($argv, 'mode', 'single') ?? 'single');
if ($mode !== 'report' && ($track === '' || $status === '')) {
    $result['status'] = 'ERROR';
    $result['message'] = 'Required args for single mode: --track=... --status=...';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$config = new ForwarderConfig();
if (!$config->isConfigured()) {
    $result['status'] = 'AUTH_SKIPPED';
    $result['message'] = 'Forwarder auth config missing, DB sync still can run from report payload';
}

global $dbcnx;
if (!($dbcnx instanceof mysqli)) {
    $result['status'] = 'ERROR';
    $result['message'] = 'DB connection is not available';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}


if ($mode === 'report') {
    $reportTables = [];
    if ($forwarderKey !== '') {
        try {
            $reportTables = [safe_report_table($forwarderKey)];
        } catch (Throwable $e) {
            $result['status'] = 'ERROR';
            $result['message'] = 'Invalid --forwarder key';
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
            exit(1);
        }
    } else {
        $reportTables = resolve_active_report_tables($dbcnx);
    }

    if ($reportTables === []) {
        $result['status'] = 'ERROR';
        $result['message'] = 'No active connector_report_* tables found for active connectors';
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(1);
    }

    $result['report_tables_selected'] = $reportTables;
    $stats = ['created_in' => 0, 'skipped_duplicates' => 0, 'updated_out' => 0, 'manual_review' => 0, 'errors' => 0, 'rows_total' => 0,  'skipped_incoming_disabled' => 0,];

    foreach ($reportTables as $table) {
        $rows = load_latest_report_rows($dbcnx, $table, $reportLimit);
        $result['report_tables'][$table] = count($rows);
        $stats['rows_total'] += count($rows);

        foreach ($rows as $payload) {
        $trackRow = trim((string)($payload['tracking_number'] ?? ''), "\" ");
        $statusRow = trim((string)($payload['status'] ?? ''));
        $storageRow = trim((string)($payload['storage'] ?? ''));
        $flightRow = null;
        $containerRow = null;
        if ($trackRow === '' || $statusRow === '') {
            $stats['errors']++;
            continue;
        }

        $groupRow = status_group($statusRow);
        $inExistsRow = exists_track($dbcnx, 'warehouse_item_in', $trackRow);
        $stockExistsRow = exists_track($dbcnx, 'warehouse_item_stock', $trackRow);
        $outExistsRow = exists_track($dbcnx, 'warehouse_item_out', $trackRow);

        if ($groupRow === 'new_packages' || $groupRow === 'declared_packages') {
           if ($onlyOutgoing) {
              $stats['skipped_incoming_disabled']++;
              continue;
            }
            if (!$inExistsRow && !$stockExistsRow && !$outExistsRow) {
                if (!$dry) {
                    $newId = insert_incoming($dbcnx, $batchUid, $trackRow, $storageRow !== '' ? $storageRow : null, $statusRow);
                    log_audit($dbcnx, 'forwarder.sync.in.created', 'warehouse_item_in', $newId, [
                        'source' => 'forwarder_report_bot', 'bot_id' => FORWARDER_BOT_ID, 'run_id' => $runId,
                        'tracking_number' => $trackRow, 'forwarder_status_raw' => $statusRow,
                        'forwarder_storage' => $storageRow, 'report_meta' => $payload['_meta'] ?? null,
                    ], 'Package created in warehouse_item_in by bot from connector_report');
                }
                $stats['created_in']++;
            } else {
                $stats['skipped_duplicates']++;
            }
        } elseif ($groupRow === 'shipped_packages') {
            $affected = $dry ? 0 : (int)update_outgoing_shipped($dbcnx, $trackRow, $storageRow !== '' ? $storageRow : null, $flightRow, $containerRow, $statusRow);
            if ($affected > 0) {
                if (!$dry) {
                    log_audit($dbcnx, 'forwarder.sync.out.status_changed', 'warehouse_item_out', null, [
                        'source' => 'forwarder_report_bot', 'bot_id' => FORWARDER_BOT_ID, 'run_id' => $runId,
                        'tracking_number' => $trackRow, 'forwarder_status_raw' => $statusRow, 'affected_rows' => $affected,
                        'report_meta' => $payload['_meta'] ?? null,
                    ], 'Updated warehouse_item_out status by bot from connector_report');
                }
                $stats['updated_out'] += $affected;
            } else {
                $stats['out_no_change'] = ($stats['out_no_change'] ?? 0) + 1;
            }
        } else {
            $stats['manual_review']++;
        }
        }
    }

    if (!$dry) {
        log_audit($dbcnx, 'forwarder.sync.run.summary', 'forwarder_report_run', null, [
            'source' => 'forwarder_report_bot',
            'bot_id' => FORWARDER_BOT_ID,
            'run_id' => $runId,
            'report_tables' => $reportTables,
            'stats' => $stats,
        ], 'Forwarder report bot run summary');
    }

    $result['status'] = 'OK';
    $result['mode'] = 'report';
    $result['stats'] = $stats;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$group = $result['group'];
$inExists = exists_track($dbcnx, 'warehouse_item_in', $track);
$stockExists = exists_track($dbcnx, 'warehouse_item_stock', $track);
$outExists = exists_track($dbcnx, 'warehouse_item_out', $track);
$result['exists'] = ['in' => $inExists, 'stock' => $stockExists, 'out' => $outExists];

if ($group === 'new_packages' || $group === 'declared_packages') {
    if (!$inExists && !$stockExists && !$outExists) {
        if ($dry) {
            $result['actions'][] = 'would_insert_warehouse_item_in';
        } else {
            $newId = insert_incoming($dbcnx, $batchUid, $track, $storage, $status);
            $result['actions'][] = $newId ? 'inserted_warehouse_item_in' : 'insert_failed';
            log_audit($dbcnx, 'forwarder.sync.in.created', 'warehouse_item_in', $newId, [
                'source' => 'forwarder_report_bot', 'bot_id' => FORWARDER_BOT_ID,
                'run_id' => $runId, 'tracking_number' => $track,
                'forwarder_status_raw' => $status, 'forwarder_storage' => $storage,
            ], 'Package created in warehouse_item_in by bot');
        }
    } else {
        $result['actions'][] = 'skipped_duplicate_exists';
    }
}

if ($group === 'shipped_packages') {
    if ($dry) {
        $result['actions'][] = 'would_update_warehouse_item_out_to_sended';
    } else {
        $affected = update_outgoing_shipped($dbcnx, $track, $storage, $flight, $containerName, $status);
        if ($affected > 0) {
            $result['actions'][] = 'updated_warehouse_item_out_to_sended';
            log_audit($dbcnx, 'forwarder.sync.out.status_changed', 'warehouse_item_out', null, [
                'source' => 'forwarder_report_bot', 'bot_id' => FORWARDER_BOT_ID,
                'run_id' => $runId, 'tracking_number' => $track,
                'forwarder_status_raw' => $status, 'status_old' => 'to_send', 'status_new' => 'sended',
                'forwarder_storage' => $storage, 'flight_no' => $flight, 'container_no' => $containerName,
                'affected_rows' => $affected,
            ], 'Updated warehouse_item_out status by bot');
        } else {
            $result['actions'][] = 'out_not_updated';
        }
    }
}

if ($group === 'unmapped') {
    $result['actions'][] = 'manual_review';
    if (!$dry) {
        log_audit($dbcnx, 'forwarder.sync.skipped', 'forwarder_report_row', null, [
            'source' => 'forwarder_report_bot', 'bot_id' => FORWARDER_BOT_ID,
            'run_id' => $runId, 'tracking_number' => $track, 'forwarder_status_raw' => $status,
        ], 'Unmapped forwarder status, manual review required');
    }
}

$result['status'] = 'OK';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
