<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../../api/warehouse/warehouse_forwarder_client_helpers.php';

function connector_client_lookup_json_out(array $payload, int $exitCode = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

function connector_client_lookup_country_from_key(string $connectorKey): string
{
    $parts = array_values(array_filter(explode('_', strtolower(trim($connectorKey))), static fn($part) => $part !== ''));
    return strtoupper((string)($parts[count($parts) - 1] ?? ''));
}

$options = getopt('', ['forwarder::', 'country::', 'receiver-address::', 'connector-key::', 'client-id::']);
$forwarder = strtoupper(trim((string)($options['forwarder'] ?? '')));
$country = strtoupper(trim((string)($options['country'] ?? '')));
$connectorKey = strtolower(trim((string)($options['connector-key'] ?? '')));
$clientId = trim((string)($options['client-id'] ?? ''));
$receiverAddress = trim((string)($options['receiver-address'] ?? ''));

if ($clientId === '') {
    $clientId = warehouse_forwarder_extract_numeric_client_id($receiverAddress);
}
if ($clientId === '') {
    connector_client_lookup_json_out(['status' => 'ok', 'found' => false, 'reason' => 'no_client_id']);
}

require_once __DIR__ . '/../../../../../configs/connectDB.php';
if ($country === '' && $connectorKey !== '') {
    $country = connector_client_lookup_country_from_key($connectorKey);
}
if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) {
    connector_client_lookup_json_out(['status' => 'error', 'message' => 'DB connection $dbcnx is not available'], 2);
}
$dbcnx->set_charset('utf8mb4');

try {
    if ($connectorKey !== '') {
        if ($country === '') {
            connector_client_lookup_json_out(['status' => 'error', 'message' => '--country is required when connector key has no country suffix'], 2);
        }
        $stmt = $dbcnx->prepare(
            'SELECT connector_key, forwarder_name, country_code, client_id, client_name, client_email, client_address, updated_at
               FROM connector_clients
              WHERE connector_key = ?
                AND country_code = ?
                AND client_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Prepare lookup failed: ' . $dbcnx->error);
        }
        $stmt->bind_param('sss', $connectorKey, $country, $clientId);
    } else {
        if ($forwarder === '' || $country === '') {
            connector_client_lookup_json_out(['status' => 'error', 'message' => 'Use --connector-key or --forwarder and --country'], 2);
        }
        $stmt = $dbcnx->prepare(
            'SELECT connector_key, forwarder_name, country_code, client_id, client_name, client_email, client_address, updated_at
               FROM connector_clients
              WHERE forwarder_name = ?
                AND country_code = ?
                AND client_id = ?
              LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Prepare lookup failed: ' . $dbcnx->error);
        }
        $stmt->bind_param('sss', $forwarder, $country, $clientId);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        connector_client_lookup_json_out([
            'status' => 'ok',
            'found' => false,
            'source' => 'local_cache',
            'client_id' => $clientId,
        ]);
    }

    connector_client_lookup_json_out([
        'status' => 'ok',
        'found' => true,
        'source' => 'local_cache',
        'connector_key' => (string)$row['connector_key'],
        'forwarder_name' => (string)$row['forwarder_name'],
        'country_code' => (string)$row['country_code'],
        'client_id' => (string)$row['client_id'],
        'client_name' => (string)$row['client_name'],
        'client_email' => $row['client_email'],
        'client_address' => $row['client_address'],
    ]);
} catch (Throwable $e) {
    connector_client_lookup_json_out(['status' => 'error', 'message' => $e->getMessage()], 1);
}
