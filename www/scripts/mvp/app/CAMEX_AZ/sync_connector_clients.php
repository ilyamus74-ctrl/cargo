<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../../../configs/connectDB.php';
require_once __DIR__ . '/../../../../api/warehouse/warehouse_forwarder_client_helpers.php';

function connector_clients_json_out(array $payload, int $exitCode = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

function connector_clients_is_safe_report_table(string $table): bool
{
    return preg_match('/^connector_report_[a-z0-9_]+$/i', $table) === 1;
}

function connector_clients_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function connector_clients_meta_from_table(string $table): array
{
    $suffix = substr($table, strlen('connector_report_'));
    $parts = array_values(array_filter(explode('_', $suffix), static fn($part) => $part !== ''));
    $forwarder = strtoupper((string)($parts[0] ?? ''));
    $country = strtoupper((string)($parts[count($parts) - 1] ?? ''));

    return [
        'connector_key' => strtolower($suffix),
        'forwarder_name' => $forwarder,
        'country_code' => $country,
    ];
}

function connector_clients_load_all_report_tables(mysqli $db): array
{
    $tables = [];
    $res = $db->query("SHOW TABLES LIKE 'connector\\_report\\_%'");
    if (!$res) {
        throw new RuntimeException('SHOW TABLES failed: ' . $db->error);
    }
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $table = (string)($row[0] ?? '');
        if (connector_clients_is_safe_report_table($table)) {
            $tables[] = $table;
        }
    }
    $res->free();
    sort($tables);
    return $tables;
}

function connector_clients_normalize_datetime($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return $value;
}

function connector_clients_sync_table(mysqli $db, string $table, int $limit, bool $dryRun): array
{
    if (!connector_clients_is_safe_report_table($table)) {
        throw new InvalidArgumentException('Unsafe report table name: ' . $table);
    }

    $meta = connector_clients_meta_from_table($table);
    if ($meta['connector_key'] === '' || $meta['forwarder_name'] === '' || $meta['country_code'] === '') {
        throw new InvalidArgumentException('Cannot derive connector metadata from table: ' . $table);
    }

    $processed = 0;
    $upserted = 0;
    $skipped = 0;
    $errors = [];

    $sql = 'SELECT id, payload_json, created_at, last_seen_at FROM ' . connector_clients_quote_identifier($table) . ' ORDER BY id DESC LIMIT ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare report select failed for ' . $table . ': ' . $db->error);
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $upsertStmt = null;
    if (!$dryRun) {
        $upsertSql = "INSERT INTO connector_clients (
                connector_key,
                forwarder_name,
                country_code,
                client_id,
                client_name,
                client_email,
                client_address,
                first_seen_at,
                last_seen_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                forwarder_name = VALUES(forwarder_name),
                client_name = VALUES(client_name),
                client_email = COALESCE(VALUES(client_email), client_email),
                client_address = COALESCE(VALUES(client_address), client_address),
                last_seen_at = GREATEST(COALESCE(last_seen_at, VALUES(last_seen_at)), VALUES(last_seen_at)),
                updated_at = CURRENT_TIMESTAMP";
        $upsertStmt = $db->prepare($upsertSql);
        if (!$upsertStmt) {
            $stmt->close();
            throw new RuntimeException('Prepare connector_clients upsert failed: ' . $db->error);
        }
    }

    while ($row = $res->fetch_assoc()) {
        $processed++;
        $payloadJson = (string)($row['payload_json'] ?? '');
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            $skipped++;
            continue;
        }

        $clientId = trim((string)($payload['client_id'] ?? ''));
        $clientName = trim((string)($payload['client_name'] ?? ''));
        if ($clientId === '' || $clientName === '') {
            $skipped++;
            continue;
        }

        $clientEmail = trim((string)($payload['email'] ?? $payload['client_email'] ?? ''));
        $clientAddress = trim((string)($payload['address'] ?? $payload['client_address'] ?? ''));
        $clientEmailParam = $clientEmail !== '' ? $clientEmail : null;
        $clientAddressParam = $clientAddress !== '' ? $clientAddress : null;
        $firstSeenAt = connector_clients_normalize_datetime($row['created_at'] ?? null);
        $lastSeenAt = connector_clients_normalize_datetime($row['last_seen_at'] ?? null) ?? $firstSeenAt;

        if ($dryRun) {
            $upserted++;
            continue;
        }

        $upsertStmt->bind_param(
            'sssssssss',
            $meta['connector_key'],
            $meta['forwarder_name'],
            $meta['country_code'],
            $clientId,
            $clientName,
            $clientEmailParam,
            $clientAddressParam,
            $firstSeenAt,
            $lastSeenAt
        );
        if (!$upsertStmt->execute()) {
            $errors[] = 'row id=' . (string)($row['id'] ?? '') . ': ' . $upsertStmt->error;
            $skipped++;
            continue;
        }
        $upserted++;
    }

    if ($upsertStmt) {
        $upsertStmt->close();
    }
    $stmt->close();

    return [
        'processed' => $processed,
        'upserted' => $upserted,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

$options = getopt('', ['table:', 'all', 'limit::', 'dry-run::']);
$table = trim((string)($options['table'] ?? ''));
$all = array_key_exists('all', $options);
$limit = (int)($options['limit'] ?? 50000);
$dryRun = array_key_exists('dry-run', $options) && (string)($options['dry-run'] ?? '1') !== '0';

if ($limit <= 0) {
    $limit = 50000;
}
if ($table === '' && !$all) {
    connector_clients_json_out(['status' => 'error', 'message' => 'Use --table=connector_report_... or --all'], 2);
}
if ($table !== '' && !connector_clients_is_safe_report_table($table)) {
    connector_clients_json_out(['status' => 'error', 'message' => 'Unsafe report table name: ' . $table], 2);
}
if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) {
    connector_clients_json_out(['status' => 'error', 'message' => 'DB connection $dbcnx is not available'], 2);
}
$dbcnx->set_charset('utf8mb4');

try {
    $tables = $table !== '' ? [$table] : connector_clients_load_all_report_tables($dbcnx);
    $summary = [
        'status' => 'ok',
        'processed' => 0,
        'upserted' => 0,
        'skipped' => 0,
        'tables' => $tables,
    ];
    if ($dryRun) {
        $summary['dry_run'] = true;
    }
    $errors = [];

    foreach ($tables as $currentTable) {
        $stats = connector_clients_sync_table($dbcnx, $currentTable, $limit, $dryRun);
        $summary['processed'] += $stats['processed'];
        $summary['upserted'] += $stats['upserted'];
        $summary['skipped'] += $stats['skipped'];
        foreach ($stats['errors'] as $error) {
            $errors[] = $currentTable . ': ' . $error;
        }
    }

    if ($errors !== []) {
        $summary['errors'] = $errors;
    }
    connector_clients_json_out($summary, $errors === [] ? 0 : 1);
} catch (Throwable $e) {
    connector_clients_json_out(['status' => 'error', 'message' => $e->getMessage()], 1);
}
