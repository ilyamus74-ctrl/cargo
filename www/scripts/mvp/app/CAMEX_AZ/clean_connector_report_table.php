<?php

declare(strict_types=1);

/**
 * Очистка connector_report_* от дублей payload_json.
 *
 * Логика:
 * - уникальность = connector_id + SHA2(payload_json, 256)
 * - оставляем самую старую строку MIN(id)
 * - seen_count показывает, сколько раз такой payload встречался
 * - last_seen_at показывает последний created_at среди дублей
 * - last_source_file показывает последний source_file среди дублей
 * - после очистки ставим UNIQUE KEY, чтобы новые дубли больше не вставлялись
 *
 * Usage:
 *   php clean_connector_report_table.php --table=connector_report_colibri_az --dry-run=1
 *   php clean_connector_report_table.php --table=connector_report_colibri_az --execute=1
 */

// если файл лежит в www/scripts/mvp/app/Forwarder/
require_once  '/home/cells/web/configs/connectDB.php';

function arg_value(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        $arg = (string)$arg;
        if (str_starts_with($arg, '--' . $name . '=')) {
            return trim(substr($arg, strlen($name) + 3));
        }
    }

    return $default;
}

function bool_arg(array $argv, string $name, bool $default = false): bool
{
    $value = strtolower((string)arg_value($argv, $name, $default ? '1' : '0'));

    return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true);
}

function out(array $payload, int $code = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($code);
}

function safe_report_table(string $table): string
{
    $table = trim($table);

    if (!preg_match('/^connector_report_[a-zA-Z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Unsafe table name: ' . $table);
    }

    return $table;
}

function qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function table_exists(mysqli $db, string $table): bool
{
    $tableEsc = $db->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '{$tableEsc}'";

    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException('SHOW TABLES failed: ' . $db->error);
    }

    return $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool
{
    $columnEsc = $db->real_escape_string($column);
    $sql = 'SHOW COLUMNS FROM ' . qi($table) . " LIKE '{$columnEsc}'";

    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException('SHOW COLUMNS failed: ' . $db->error . '; SQL=' . $sql);
    }

    return $res->num_rows > 0;
}

function index_exists(mysqli $db, string $table, string $indexName): bool
{
    $indexEsc = $db->real_escape_string($indexName);
    $sql = 'SHOW INDEX FROM ' . qi($table) . " WHERE Key_name = '{$indexEsc}'";

    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException('SHOW INDEX failed: ' . $db->error . '; SQL=' . $sql);
    }

    return $res->num_rows > 0;
}

function scalar_int(mysqli $db, string $sql): int
{
    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException('Query failed: ' . $db->error . '; SQL=' . $sql);
    }

    $row = $res->fetch_row();

    return (int)($row[0] ?? 0);
}

function run_sql(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException('SQL failed: ' . $db->error . '; SQL=' . $sql);
    }
}

function ensure_report_columns(mysqli $db, string $table): array
{
    $changes = [];
    $qt = qi($table);

    foreach (['id', 'connector_id', 'payload_json', 'source_file', 'created_at'] as $required) {
        if (!column_exists($db, $table, $required)) {
            throw new RuntimeException("Required column missing: {$table}.{$required}");
        }
    }

    if (!column_exists($db, $table, 'payload_sha256')) {
        run_sql(
            $db,
            "ALTER TABLE {$qt}
             ADD COLUMN payload_sha256 CHAR(64)
             GENERATED ALWAYS AS (SHA2(payload_json, 256)) STORED
             AFTER payload_json"
        );
        $changes[] = 'added payload_sha256';
    }

    if (!column_exists($db, $table, 'seen_count')) {
        run_sql(
            $db,
            "ALTER TABLE {$qt}
             ADD COLUMN seen_count INT UNSIGNED NOT NULL DEFAULT 1
             AFTER payload_sha256"
        );
        $changes[] = 'added seen_count';
    }

    if (!column_exists($db, $table, 'last_seen_at')) {
        run_sql(
            $db,
            "ALTER TABLE {$qt}
             ADD COLUMN last_seen_at DATETIME NULL
             AFTER seen_count"
        );
        $changes[] = 'added last_seen_at';
    }

    if (!column_exists($db, $table, 'last_source_file')) {
        run_sql(
            $db,
            "ALTER TABLE {$qt}
             ADD COLUMN last_source_file VARCHAR(255) NULL
             AFTER last_seen_at"
        );
        $changes[] = 'added last_source_file';
    }

    if (!index_exists($db, $table, 'idx_report_payload_sha256')) {
        run_sql(
            $db,
            "ALTER TABLE {$qt}
             ADD INDEX idx_report_payload_sha256 (connector_id, payload_sha256)"
        );
        $changes[] = 'added idx_report_payload_sha256';
    }

    return $changes;
}

function update_keeper_metadata(mysqli $db, string $table): int
{
    $qt = qi($table);

    $sql = "
        UPDATE {$qt} keep_row
        JOIN (
            SELECT
                connector_id,
                payload_sha256,
                MIN(id) AS keep_id,
                COUNT(*) AS seen_count_new,
                MAX(created_at) AS last_seen_at_new,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(COALESCE(source_file, '') ORDER BY created_at DESC, id DESC SEPARATOR '\n'),
                    '\n',
                    1
                ) AS last_source_file_new
            FROM {$qt}
            WHERE payload_sha256 IS NOT NULL
              AND payload_sha256 <> ''
            GROUP BY connector_id, payload_sha256
            HAVING COUNT(*) > 1
        ) d ON d.keep_id = keep_row.id
        SET
            keep_row.seen_count = GREATEST(keep_row.seen_count, d.seen_count_new),
            keep_row.last_seen_at = d.last_seen_at_new,
            keep_row.last_source_file = NULLIF(d.last_source_file_new, '')
    ";

    if (!$db->query($sql)) {
        throw new RuntimeException('Keeper metadata update failed: ' . $db->error);
    }

    return max(0, (int)$db->affected_rows);
}

function create_clean_table(mysqli $db, string $table, string $cleanTable): void
{
    if (table_exists($db, $cleanTable)) {
        run_sql($db, 'DROP TABLE ' . qi($cleanTable));
    }

    run_sql(
        $db,
        'CREATE TABLE ' . qi($cleanTable) . ' LIKE ' . qi($table)
    );
}

function fill_clean_table(mysqli $db, string $table, string $cleanTable): int
{
    $qt = qi($table);
    $qc = qi($cleanTable);

    /**
     * payload_sha256 не вставляем, потому что это GENERATED column.
     */
    $sql = "
        INSERT INTO {$qc}
        (
            id,
            connector_id,
            period_from,
            period_to,
            payload_json,
            seen_count,
            last_seen_at,
            last_source_file,
            source_file,
            created_at
        )
        SELECT
            r.id,
            r.connector_id,
            r.period_from,
            r.period_to,
            r.payload_json,
            r.seen_count,
            r.last_seen_at,
            r.last_source_file,
            r.source_file,
            r.created_at
        FROM {$qt} r
        JOIN (
            SELECT
                connector_id,
                payload_sha256,
                MIN(id) AS keep_id
            FROM {$qt}
            WHERE payload_sha256 IS NOT NULL
              AND payload_sha256 <> ''
            GROUP BY connector_id, payload_sha256
        ) k ON k.keep_id = r.id
    ";

    if (!$db->query($sql)) {
        throw new RuntimeException('Clean table fill failed: ' . $db->error);
    }

    return max(0, (int)$db->affected_rows);
}

function count_duplicate_rows(mysqli $db, string $table): array
{
    $qt = qi($table);

    $sql = "
        SELECT
            COUNT(*) AS duplicate_groups,
            COALESCE(SUM(cnt - 1), 0) AS duplicate_rows
        FROM (
            SELECT COUNT(*) AS cnt
            FROM {$qt}
            GROUP BY connector_id, payload_sha256
            HAVING COUNT(*) > 1
        ) x
    ";

    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException('Duplicate count failed: ' . $db->error);
    }

    $row = $res->fetch_assoc() ?: [];

    return [
        'duplicate_groups' => (int)($row['duplicate_groups'] ?? 0),
        'duplicate_rows' => (int)($row['duplicate_rows'] ?? 0),
    ];
}

function add_unique_key(mysqli $db, string $table): bool
{
    if (index_exists($db, $table, 'uq_report_payload_sha256')) {
        return false;
    }

    run_sql(
        $db,
        'ALTER TABLE ' . qi($table) . '
         ADD UNIQUE KEY uq_report_payload_sha256 (connector_id, payload_sha256)'
    );

    return true;
}

$argv = $_SERVER['argv'] ?? [];

$table = safe_report_table((string)arg_value($argv, 'table', ''));
$dryRun = bool_arg($argv, 'dry-run', false);
$execute = bool_arg($argv, 'execute', false);

if (!$dryRun && !$execute) {
    out([
        'status' => 'ERROR',
        'message' => 'Use --dry-run=1 for check or --execute=1 for real cleanup',
        'example_dry_run' => 'php clean_connector_report_table.php --table=connector_report_colibri_az --dry-run=1',
        'example_execute' => 'php clean_connector_report_table.php --table=connector_report_colibri_az --execute=1',
    ], 1);
}

if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) {
    out([
        'status' => 'ERROR',
        'message' => 'DB connection $dbcnx is not available',
    ], 1);
}

try {
    if (!table_exists($dbcnx, $table)) {
        throw new RuntimeException('Table does not exist: ' . $table);
    }

    $schemaChanges = [];

    if (!$dryRun) {
        $schemaChanges = ensure_report_columns($dbcnx, $table);
    }

    $rowsBefore = scalar_int($dbcnx, 'SELECT COUNT(*) FROM ' . qi($table));

    /**
     * Если dry-run, но payload_sha256 ещё нет — считаем через SHA2(payload_json, 256).
     */
    if ($dryRun && !column_exists($dbcnx, $table, 'payload_sha256')) {
        $duplicatesSql = "
            SELECT
                COUNT(*) AS duplicate_groups,
                COALESCE(SUM(cnt - 1), 0) AS duplicate_rows
            FROM (
                SELECT COUNT(*) AS cnt
                FROM " . qi($table) . "
                GROUP BY connector_id, SHA2(payload_json, 256)
                HAVING COUNT(*) > 1
            ) x
        ";

        $res = $dbcnx->query($duplicatesSql);
        if (!$res) {
            throw new RuntimeException('Dry-run duplicate count failed: ' . $dbcnx->error);
        }

        $dupRow = $res->fetch_assoc() ?: [];
        $duplicatesBefore = [
            'duplicate_groups' => (int)($dupRow['duplicate_groups'] ?? 0),
            'duplicate_rows' => (int)($dupRow['duplicate_rows'] ?? 0),
        ];
    } else {
        $duplicatesBefore = count_duplicate_rows($dbcnx, $table);
    }

    if ($dryRun) {
        out([
            'status' => 'OK',
            'mode' => 'dry-run',
            'table' => $table,
            'rows_before' => $rowsBefore,
            'duplicates_before' => $duplicatesBefore,
            'message' => 'No changes made',
        ]);
    }

    $backupTable = $table . '_bak_' . date('YmdHis');
    $cleanTable = $table . '_clean_' . date('YmdHis');

    $metadataUpdated = update_keeper_metadata($dbcnx, $table);

    create_clean_table($dbcnx, $table, $cleanTable);
    $insertedCleanRows = fill_clean_table($dbcnx, $table, $cleanTable);

    $duplicatesAfterClean = count_duplicate_rows($dbcnx, $cleanTable);

    if ($duplicatesAfterClean['duplicate_rows'] > 0) {
        throw new RuntimeException('Clean table still has duplicates, aborting rename');
    }

    $uniqueAdded = add_unique_key($dbcnx, $cleanTable);

    run_sql(
        $dbcnx,
        'RENAME TABLE ' . qi($table) . ' TO ' . qi($backupTable) . ',
                      ' . qi($cleanTable) . ' TO ' . qi($table)
    );

    $rowsAfter = scalar_int($dbcnx, 'SELECT COUNT(*) FROM ' . qi($table));

    out([
        'status' => 'OK',
        'mode' => 'execute',
        'table' => $table,
        'backup_table' => $backupTable,
        'rows_before' => $rowsBefore,
        'rows_after' => $rowsAfter,
        'duplicates_before' => $duplicatesBefore,
        'duplicates_after' => count_duplicate_rows($dbcnx, $table),
        'metadata_updated_rows' => $metadataUpdated,
        'inserted_clean_rows' => $insertedCleanRows,
        'unique_key_added' => $uniqueAdded,
        'schema_changes' => $schemaChanges,
        'message' => 'Report table cleaned and swapped successfully',
    ]);
} catch (Throwable $e) {
    out([
        'status' => 'ERROR',
        'table' => $table,
        'message' => $e->getMessage(),
    ], 1);
}

