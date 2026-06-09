<?php
declare(strict_types=1);
/**
 * Обработчик действий с приходом товаров на склад
 * Actions: warehouse_item_in, item_in, open_item_in_batch, add_new_item_in, 
 *          delete_item_in, commit_item_in_batch
 */
// Доступны: $action, $user, $dbcnx, $smarty
$response = ['status' => 'error', 'message' => 'Unknown warehouse item in action'];

require_once __DIR__ . '/warehouse_forwarder_client_helpers.php';



function warehouse_item_in_try_camex_az_remote_client_lookup(mysqli $dbcnx, string $clientId, string $receiverAddress): array
{
    if ($clientId === '') {
        return [
            'status' => 'ok',
            'ok' => true,
            'found' => false,
            'source' => 'remote_camex',
            'client_id' => $clientId,
            'message' => 'remote lookup skipped/failed',
        ];
    }

    try {
        $camexDir = dirname(__DIR__, 2) . '/scripts/mvp/app/CAMEX_AZ';
        require_once $camexDir . '/bootstrap.php';

        $repoRoot = dirname(__DIR__, 3);
        $args = [
            'connector-id' => '3',
            'timeout' => '5',
        ];

        $connectorRow = \App\Forwarder\Config\ConnectorConfigRepository::loadRow($args, $repoRoot);
        $overrides = \App\Forwarder\Config\ConnectorConfigRepository::buildForwarderOverrides($connectorRow, $args);
        $config = new \App\Forwarder\Config\ForwarderConfig($overrides);
        if ($config->baseUrl() === '' || $config->webLogin() === '' || $config->webPassword() === '') {
            return [
                'status' => 'ok',
                'ok' => true,
                'found' => false,
                'source' => 'remote_camex',
                'client_id' => $clientId,
                'message' => 'remote lookup skipped/failed',
            ];
        }
        if ($config->httpAuthEnabled() && ($config->httpAuthLogin() === '' || $config->httpAuthPassword() === '')) {
            return [
                'status' => 'ok',
                'ok' => true,
                'found' => false,
                'source' => 'remote_camex',
                'client_id' => $clientId,
                'message' => 'remote lookup skipped/failed',
            ];
        }

        $logger = new \App\Forwarder\Logging\ForwarderLogger('warehouse-camex-client-' . date('YmdHis'));
        $sessionClient = new \App\Forwarder\Http\CamexSessionClient(
            $config,
            new \App\Forwarder\Http\ForwarderHttpClient($config),
            new \App\Forwarder\Http\SessionManager(),
            $logger
        );
        $service = new \App\Forwarder\Services\ClientLookupService($repoRoot, $sessionClient, $dbcnx);
        $result = $service->lookup([
            'connector_id' => 3,
            'client_id' => $clientId,
            'receiver_address' => $receiverAddress,
            'write_cache' => true,
            'dry_run' => false,
            'debug_dir' => '',
        ]);

        if (($result['status'] ?? '') !== 'ok') {
            return [
                'status' => 'ok',
                'ok' => true,
                'found' => false,
                'source' => 'remote_camex',
                'client_id' => $clientId,
                'message' => 'remote lookup skipped/failed',
            ];
        }

        return [
            'status' => 'ok',
            'ok' => true,
            'found' => true,
            'source' => 'remote_camex',
            'cached' => true,
            'connector_key' => (string)($result['connector_key'] ?? 'camex_az'),
            'forwarder_name' => (string)($result['forwarder_name'] ?? 'CAMEX'),
            'country_code' => (string)($result['country_code'] ?? 'AZ'),
            'client_id' => (string)($result['client_id'] ?? $clientId),
            'client_name' => (string)($result['client_name'] ?? ''),
            'client_email' => $result['client_email'] ?? null,
            'client_address' => $result['client_address'] ?? null,
        ];
    } catch (Throwable $e) {
        error_log('[warehouse_lookup_forwarder_client][remote_camex] ' . $e->getMessage());
        return [
            'status' => 'ok',
            'ok' => true,
            'found' => false,
            'source' => 'remote_camex',
            'client_id' => $clientId,
            'message' => 'remote lookup skipped/failed',
        ];
    }
}

function warehouse_item_in_ensure_addons_columns(mysqli $dbcnx): void
{
    $checkIn = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_in LIKE 'addons_json'");
    if ($checkIn instanceof mysqli_result) {
        $existsIn = $checkIn->num_rows > 0;
        $checkIn->free();
        if (!$existsIn) {
            $dbcnx->query("ALTER TABLE warehouse_item_in ADD COLUMN addons_json LONGTEXT NULL AFTER box_image");
        }
    }

    $checkStock = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE 'addons_json'");
    if ($checkStock instanceof mysqli_result) {
        $existsStock = $checkStock->num_rows > 0;
        $checkStock->free();
        if (!$existsStock) {
            $dbcnx->query("ALTER TABLE warehouse_item_stock ADD COLUMN addons_json LONGTEXT NULL AFTER box_image");
        }
    }
}


function warehouse_item_in_ensure_cell_columns(mysqli $dbcnx): void
{
    $checkIn = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_in LIKE 'cell_id'");
    if ($checkIn instanceof mysqli_result) {
        $existsIn = $checkIn->num_rows > 0;
        $checkIn->free();
        if (!$existsIn) {
            $dbcnx->query("ALTER TABLE warehouse_item_in ADD COLUMN cell_id INT NULL AFTER receiver_address");
        }
    }

    $checkStock = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE 'cell_id'");
    if ($checkStock instanceof mysqli_result) {
        $existsStock = $checkStock->num_rows > 0;
        $checkStock->free();
        if (!$existsStock) {
            $dbcnx->query("ALTER TABLE warehouse_item_stock ADD COLUMN cell_id INT NULL AFTER receiver_address");
        }
    }
}

function warehouse_item_in_load_cells(mysqli $dbcnx): array
{
    $cells = [];
    $sql = "SELECT id, code FROM cells ORDER BY code ASC";
    if ($resCells = $dbcnx->query($sql)) {
        while ($row = $resCells->fetch_assoc()) {
            $cells[] = $row;
        }
        $resCells->free();
    }

    return $cells;
}

function warehouse_item_in_resolve_cell_id(mysqli $dbcnx, string $rawCellId, string $fallbackCellCode = ''): ?int
{
    $cellExistsById = static function (int $cellId) use ($dbcnx): bool {
        if ($cellId <= 0) {
            return false;
        }
        $stmt = $dbcnx->prepare("SELECT id FROM cells WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $cellId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return isset($row['id']);
    };

    $rawCellId = trim($rawCellId);
    if ($rawCellId !== '') {
        $numeric = (int)$rawCellId;
        if ($numeric > 0) {
            return $numeric;
        }
    }

    $candidateCode = strtoupper(trim($fallbackCellCode));
    if ($candidateCode === '' && $rawCellId !== '') {
        $candidateCode = strtoupper($rawCellId);
    }
    if ($candidateCode === '') {
        return null;
    }

    if (preg_match('/^[0-9]+$/', $candidateCode) === 1) {
        $parsedId = (int)$candidateCode;
        return $cellExistsById($parsedId) ? $parsedId : null;
    }
    if (preg_match('/^C([0-9]+)$/', $candidateCode, $m) === 1) {
        $fromCode = (int)$m[1];
        if ($fromCode > 0 && $cellExistsById($fromCode)) {
            return $fromCode;
        }
    }

    $stmt = $dbcnx->prepare("SELECT id FROM cells WHERE UPPER(code) = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $candidateCode);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $cellId = (int)($row['id'] ?? 0);
    return $cellId > 0 ? $cellId : null;
}

function warehouse_item_in_load_addons_map(mysqli $dbcnx): array
{
    $addonsMap = [];
    $addonsRawMap = [];

    $sql = "SELECT connector_name, addons_json
              FROM connectors_addons
             WHERE addons_json IS NOT NULL
               AND TRIM(addons_json) <> ''
               AND TRIM(addons_json) <> '{}'
               AND TRIM(addons_json) <> '[]'";
    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $connector = strtoupper(trim((string)($row['connector_name'] ?? '')));
            $rawAddonsJson = trim((string)($row['addons_json'] ?? ''));
            if ($connector === '' || $rawAddonsJson === '') {
                continue;
            }

            $decoded = json_decode($rawAddonsJson, true);
            if (!is_array($decoded)) {
                continue;
            }

            $extra = $decoded['extra'] ?? [];
            if (!is_array($extra) || empty($extra)) {
                continue;
            }

            $addonsMap[$connector] = $extra;
            $addonsRawMap[$connector] = $decoded;
        }
        $res->free();
    }

    return [$addonsMap, $addonsRawMap];
}


function warehouse_item_in_normalize_key(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?? '';
    return trim($value, '_');
}

function warehouse_item_in_ensure_forwarder_registration_columns(mysqli $dbcnx): void
{
    $columns = [
        'forwarder_registered_at' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registered_at DATETIME NULL AFTER addons_json",
        'forwarder_registration_status' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_status VARCHAR(32) NULL AFTER forwarder_registered_at",
        'forwarder_registration_message' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_message TEXT NULL AFTER forwarder_registration_status",
        'forwarder_registration_response_json' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_response_json LONGTEXT NULL AFTER forwarder_registration_message",
    ];

    foreach ($columns as $columnName => $alterSql) {
        $res = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE '" . $dbcnx->real_escape_string($columnName) . "'");
        if ($res instanceof mysqli_result) {
            $exists = $res->num_rows > 0;
            $res->free();
            if (!$exists) {
                $dbcnx->query($alterSql);
            }
        }
    }

    $messageColumn = $dbcnx->query("SHOW COLUMNS FROM warehouse_item_stock LIKE 'forwarder_registration_message'");
    if ($messageColumn instanceof mysqli_result) {
        $messageInfo = $messageColumn->fetch_assoc();
        $messageColumn->free();
        $messageType = strtolower((string)($messageInfo['Type'] ?? ''));
        if ($messageType !== '' && $messageType !== 'text') {
            $dbcnx->query("ALTER TABLE warehouse_item_stock MODIFY COLUMN forwarder_registration_message TEXT NULL");
        }
    }
}

function warehouse_item_in_parse_cli_json_output(string $output): ?array
{
    $output = trim($output);
    if ($output === '') {
        return null;
    }

    $parsed = json_decode($output, true);
    if (is_array($parsed)) {
        return $parsed;
    }

    $lines = preg_split('/\R/', $output) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim((string)$lines[$i]);
        if ($line === '' || substr($line, 0, 1) !== '{') {
            continue;
        }
        $parsed = json_decode($line, true);
        if (is_array($parsed)) {
            return $parsed;
        }
    }

    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $parsed = json_decode(substr($output, $jsonStart), true);
        if (is_array($parsed)) {
            return $parsed;
        }
    }

    return null;
}

function warehouse_item_in_mask_cli_output(string $output, array $args): string
{
    foreach ($args as $key => $value) {
        $keyNorm = strtolower((string)$key);
        if (strpos($keyNorm, 'pass') === false && strpos($keyNorm, 'token') === false && strpos($keyNorm, 'secret') === false) {
            continue;
        }
        $value = (string)$value;
        if ($value !== '') {
            $output = str_replace($value, '***', $output);
        }
    }
    return $output;
}

function warehouse_item_in_exec_php_cli_script(string $scriptPath, array $args): array
{
    $scriptPath = trim($scriptPath);
    if ($scriptPath === '') {
        throw new RuntimeException('Не указан PHP CLI script path');
    }
    if ($scriptPath[0] !== '/') {
        $scriptPath = dirname(__DIR__, 3) . '/' . ltrim($scriptPath, '/');
    }
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Не найден PHP CLI скрипт: ' . $scriptPath);
    }

    $cmdParts = ['php', $scriptPath];
    foreach ($args as $key => $value) {
        $normalizedKey = trim((string)$key);
        if ($normalizedKey === '') {
            continue;
        }
        $cmdParts[] = '--' . $normalizedKey . '=' . (string)$value;
    }

    $cmd = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
    $lines = [];
    $exitCode = 0;
    @exec($cmd, $lines, $exitCode);
    $output = warehouse_item_in_mask_cli_output(trim((string)implode("\n", $lines)), $args);
    $parsed = warehouse_item_in_parse_cli_json_output($output);
    if (!is_array($parsed)) {
        $parsed = [
            'status' => $exitCode === 0 ? 'ok' : 'error',
            'message' => $output !== '' ? $output : 'Unknown CLI response',
        ];
    }
    $parsed['_meta'] = [
        'script' => $scriptPath,
        'exit_code' => $exitCode,
        'raw_output' => $output,
    ];

    return warehouse_item_in_mask_submitted_args_password($parsed);
}

function warehouse_item_in_exec_forwarder_cli_script(string $scriptName, array $args): array
{
    return warehouse_item_in_exec_php_cli_script(dirname(__DIR__, 2) . '/scripts/mvp/app/Forwarder/' . ltrim($scriptName, '/'), $args);
}

function warehouse_item_in_resolve_forwarder_connector(mysqli $dbcnx, string $forwarder, string $country): ?array
{
    $forwarderNorm = warehouse_item_in_normalize_key($forwarder);
    if ($forwarderNorm === '') {
        return null;
    }

    $countryNorm = strtoupper(trim($country));
    $selectColumns = ['id', 'name', 'countries', 'auth_username', 'auth_password', 'base_url', 'is_active'];
    if ($resColumns = $dbcnx->query("SHOW COLUMNS FROM connectors LIKE 'system_type'")) {
        $hasSystemType = $resColumns->num_rows > 0;
        $resColumns->free();
        if ($hasSystemType) {
            $selectColumns[] = 'system_type';
        }
    }

    $rows = [];
    if ($resAll = $dbcnx->query('SELECT ' . implode(', ', $selectColumns) . " FROM connectors WHERE is_active = 1 ORDER BY id DESC")) {
        while ($row = $resAll->fetch_assoc()) {
            $nameNorm = warehouse_item_in_normalize_key((string)($row['name'] ?? ''));
            if ($nameNorm === '') {
                continue;
            }
            if ($nameNorm === $forwarderNorm) {
                $rows[] = $row;
            }
        }
        $resAll->free();
    }

    if (empty($rows)) {
        return null;
    }

    if ($countryNorm !== '') {
        foreach ($rows as $row) {
            $countries = strtoupper(trim((string)($row['countries'] ?? '')));
            if ($countries === '') {
                continue;
            }
            $parts = array_map('trim', explode(',', $countries));
            if (in_array($countryNorm, $parts, true)) {
                return $row;
            }
        }
    }
    return $countryNorm === '' ? ($rows[0] ?? null) : null;
}

function warehouse_item_in_decode_json_array($raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function warehouse_item_in_required_registration_fields(mysqli $dbcnx, string $forwarder): array
{
    $default = [
        'tracking_no',
        'receiver_country_code',
        'weight_kg',
        'receiver_name',
    ];

    $forwarderNorm = warehouse_item_in_normalize_key($forwarder);
    if ($forwarderNorm === '') {
        return $default;
    }

    $required = [];
    if ($res = $dbcnx->query("SELECT connector_name, addons_json FROM connectors_addons WHERE addons_json IS NOT NULL AND TRIM(addons_json) <> ''")) {
        while ($row = $res->fetch_assoc()) {
            $connectorNorm = warehouse_item_in_normalize_key((string)($row['connector_name'] ?? ''));
            if ($connectorNorm !== $forwarderNorm && $connectorNorm !== 'DEV_' . $forwarderNorm && ('DEV_' . $connectorNorm) !== $forwarderNorm) {
                continue;
            }
            $decoded = warehouse_item_in_decode_json_array($row['addons_json'] ?? '');
            $rawFields = $decoded['required_registration_fields'] ?? [];
            if (is_array($rawFields)) {
                foreach ($rawFields as $field) {
                    $field = trim((string)$field);
                    if ($field !== '') {
                        $required[] = $field;
                    }
                }
            }
        }
        $res->free();
    }

    if (empty($required)) {
        return $default;
    }

    return array_values(array_unique($required));
}

function warehouse_item_in_is_camex_az_connector(array $stockItem, array $connector): bool
{
    $receiverCompanyNorm = warehouse_item_in_normalize_key((string)($stockItem['receiver_company'] ?? ''));
    $receiverCountry = strtoupper(trim((string)($stockItem['receiver_country_code'] ?? '')));
    if ($receiverCompanyNorm === 'CAMEX' && $receiverCountry === 'AZ') {
        return true;
    }

    $systemTypeNorm = warehouse_item_in_normalize_key((string)($connector['system_type'] ?? ''));
    if ($systemTypeNorm === 'CAMEX_AZ') {
        return true;
    }

    return false;
}

function warehouse_item_in_stock_numeric_value(array $stockItem, string $key, string $default = ''): string
{
    $raw = str_replace(',', '.', trim((string)($stockItem[$key] ?? '')));
    if ($raw === '' || !is_numeric($raw)) {
        return $default;
    }
    return (string)(float)$raw;
}

function warehouse_item_in_build_camex_az_args(array $stockItem, array $connector, $batchUid, int $stockItemId): array
{
    $batchUid = (string)$batchUid;
    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    $tracking = trim((string)($stockItem['tracking_no'] ?? ''));
    if ($tracking === '') {
        $tracking = trim((string)($stockItem['tuid'] ?? ''));
    }

    $invoiceCurrency = trim((string)($addons['invoice_ccy'] ?? $addons['invoice_currency'] ?? 'EUR'));
    if ($invoiceCurrency === '') {
        $invoiceCurrency = 'EUR';
    }

    $args = [
        'connector-id' => (string)(int)($connector['id'] ?? 0),
        'prepare-mode' => 'client',
        'client-id' => (string)warehouse_item_in_extract_client_id($stockItem),
        'tracking' => $tracking,
        'weight' => warehouse_item_in_stock_numeric_value($stockItem, 'weight_kg'),
        'length' => warehouse_item_in_stock_numeric_value($stockItem, 'size_l_cm', '0'),
        'width' => warehouse_item_in_stock_numeric_value($stockItem, 'size_w_cm', '0'),
        'height' => warehouse_item_in_stock_numeric_value($stockItem, 'size_h_cm', '0'),
        'box-code' => 'BOX1',
        'invoice-price' => '',
        'invoice-currency' => $invoiceCurrency,
        'shop' => 'amazon.de',
        'item-count' => '',
        'package-type-id' => trim((string)($addons['camex_package_type_id'] ?? $addons['package_type_id'] ?? '')),
        'comment' => '',
        'debug-dir' => '/tmp/camex_az_warehouse_commit_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $batchUid) . '_' . $stockItemId,
        'dry-run' => '0',
        'confirm-submit' => '1',
    ];

    $flightNo = trim((string)($addons['camex_flight_no'] ?? $addons['flight_no'] ?? $addons['reisi'] ?? ''));
    if ($flightNo !== '') {
        $args['flight-no'] = $flightNo;
    }

    return $args;
}


function warehouse_item_in_resolve_camex_az_flight_no(array $stockItem, array $connector, array $camexArgs): array
{
    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    foreach (['camex_flight_no', 'flight_no', 'reisi'] as $key) {
        $value = trim((string)($addons[$key] ?? ''));
        if ($value !== '') {
            return [
                'flight_no' => $value,
                'source' => 'addons_json.' . $key,
                'result' => null,
                'args_preview' => $camexArgs,
            ];
        }
    }

    $dryRunArgs = $camexArgs;
    unset($dryRunArgs['flight-no']);
    $dryRunArgs['dry-run'] = '1';
    $dryRunArgs['confirm-submit'] = '0';
    $dryRunArgs['debug-dir'] = '';

    $result = warehouse_item_in_exec_php_cli_script('www/scripts/mvp/app/CAMEX_AZ/run_prepare_package.php', $dryRunArgs);
    $flightNo = trim((string)($result['selected']['flight_no'] ?? ''));
    if ($flightNo === '') {
        $flightNo = trim((string)($result['payload_preview']['reisi'] ?? ''));
    }

    return [
        'flight_no' => $flightNo,
        'source' => $flightNo !== '' ? 'run_prepare_package.dry_run' : 'not_found',
        'result' => $result,
        'args_preview' => $dryRunArgs,
    ];
}

function warehouse_item_in_validate_camex_az_args(array $stockItem, array $args): array
{
    $missing = [];
    $requiredFields = [
        'tracking',
        'client_id',
        'weight',
        'package_type_id',
        'receiver_country_code',
        'receiver_company',
    ];

    $receiverCompanyNorm = warehouse_item_in_normalize_key((string)($stockItem['receiver_company'] ?? ''));
    $receiverCountry = strtoupper(trim((string)($stockItem['receiver_country_code'] ?? '')));
    $clientId = (int)($args['client-id'] ?? 0);
    $weight = str_replace(',', '.', trim((string)($args['weight'] ?? '')));
    $packageTypeId = trim((string)($args['package-type-id'] ?? ''));

    if (trim((string)($args['tracking'] ?? '')) === '') {
        $missing[] = 'tracking';
    }
    if ($clientId <= 0) {
        $missing[] = 'client_id';
    }
    if ($weight === '' || !is_numeric($weight) || (float)$weight <= 0) {
        $missing[] = 'weight';
    }
    if ($packageTypeId === '' || $packageTypeId === '0') {
        $missing[] = 'package_type_id';
    }
    if ($receiverCountry !== 'AZ') {
        $missing[] = 'receiver_country_code';
    }
    if ($receiverCompanyNorm !== 'CAMEX') {
        $missing[] = 'receiver_company';
    }

    $message = '';
    if (in_array('client_id', $missing, true)) {
        $message = 'CAMEX_AZ: не найден client_id';
    } elseif (in_array('package_type_id', $missing, true)) {
        $message = 'CAMEX_AZ: не выбран Package Type';
    } elseif (!empty($missing)) {
        $message = 'CAMEX_AZ: не заполнены обязательные поля [' . implode(', ', $missing) . ']';
    }

    return [
        'ok' => empty($missing),
        'message' => $message,
        'required_fields' => $requiredFields,
        'missing_fields' => array_values(array_unique($missing)),
        'required_field_values' => [
            'tracking' => (string)($args['tracking'] ?? ''),
            'client_id' => (string)$clientId,
            'weight' => (string)($args['weight'] ?? ''),
            'flight_no' => (string)($args['flight-no'] ?? ''),
            'package_type_id' => (string)($args['package-type-id'] ?? ''),
            'receiver_country_code' => $receiverCountry,
            'receiver_company' => (string)($stockItem['receiver_company'] ?? ''),
        ],
    ];
}

function warehouse_item_in_camex_az_registered_order(array $result): array
{
    $registeredOrder = $result['verify']['registered_order'] ?? $result['registered_order'] ?? [];
    return is_array($registeredOrder) ? $registeredOrder : [];
}

function warehouse_item_in_validate_registration_payload(array $stockItem, array $requiredFields): array
{
    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    $clientId = warehouse_item_in_extract_client_id($stockItem);
    $missing = [];
    foreach ($requiredFields as $field) {
        $field = trim((string)$field);
        if ($field === '') {
            continue;
        }
        $value = '';
        if (array_key_exists($field, $stockItem)) {
            $value = trim((string)$stockItem[$field]);
        } elseif (array_key_exists($field, $addons)) {
            $value = trim((string)$addons[$field]);
        }

        if ($field === 'addons_json') {
            if (empty($addons)) {
                $missing[] = $field;
            }
            continue;
        }
        if ($field === 'receiver_name' && $clientId > 0) {
            continue;
        }
        if ($value === '') {
            $missing[] = $field;
        }
    }

    return array_values(array_unique($missing));
}

function warehouse_item_in_extract_client_id(array $stockItem): int
{
    $candidateKeys = ['client_id', 'client-id', 'receiver_client_id', 'customer_id'];
    foreach ($candidateKeys as $key) {
        if (!array_key_exists($key, $stockItem)) {
            continue;
        }
        $raw = trim((string)$stockItem[$key]);
        if ($raw !== '' && ctype_digit($raw)) {
            return (int)$raw;
        }
    }

    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    foreach ($candidateKeys as $key) {
        if (!array_key_exists($key, $addons)) {
            continue;
        }
        $raw = trim((string)$addons[$key]);
        if ($raw !== '' && ctype_digit($raw)) {
            return (int)$raw;
        }
    }

    $receiverAddress = trim((string)($stockItem['receiver_address'] ?? ''));
    if ($receiverAddress !== '') {
        $normalized = strtoupper($receiverAddress);
        if (preg_match('/^C([0-9]+)$/', $normalized, $m) === 1) {
            return (int)$m[1];
        }
        if (ctype_digit($normalized)) {
            return (int)$normalized;
        }
        if (preg_match('/^[A-Z]{1,8}([0-9]+)$/', $normalized, $m) === 1) {
            return (int)$m[1];
        }
    }
    return 0;
}

function warehouse_item_in_mask_submitted_args_password(array $raw): array
{
    foreach ($raw as $key => $value) {
        if (is_array($value)) {
            $raw[$key] = warehouse_item_in_mask_submitted_args_password($value);
        }
    }
    if (isset($raw['submitted_args']) && is_array($raw['submitted_args']) && array_key_exists('password', $raw['submitted_args'])) {
        $raw['submitted_args']['password'] = '***';
    }
    return $raw;
}

function warehouse_item_in_client_id_debug(array $stockItem, int $clientId): array
{
    return [
        'client_id_source' => 'receiver_address',
        'client_id_source_value' => (string)($stockItem['receiver_address'] ?? ''),
        'receiver_address' => (string)($stockItem['receiver_address'] ?? ''),
        'client_id_extracted' => (string)$clientId,
    ];
}

function warehouse_item_in_status_id_by_client_id(int $clientId): int
{
    return $clientId > 0 ? 37 : 36;
}

function warehouse_item_in_required_fields_value_map(array $stockItem, array $requiredFields): array
{
    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    $map = [];
    foreach ($requiredFields as $field) {
        $field = trim((string)$field);
        if ($field === '') {
            continue;
        }
        if (array_key_exists($field, $stockItem)) {
            $map[$field] = (string)$stockItem[$field];
            continue;
        }
        if (array_key_exists($field, $addons)) {
            $map[$field] = (string)$addons[$field];
            continue;
        }
        $map[$field] = '';
    }
    return $map;
}

function warehouse_item_in_build_registration_payload(array $stockItem): array
{
    $addons = warehouse_item_in_decode_json_array((string)($stockItem['addons_json'] ?? ''));
    $clientId = warehouse_item_in_extract_client_id($stockItem);
    $tracking = trim((string)($stockItem['tracking_no'] ?? ''));
    if ($tracking === '') {
        $tracking = trim((string)($stockItem['tuid'] ?? ''));
    }
    $category = trim((string)($addons['category'] ?? $addons['sub_category'] ?? 'general'));
    $countryCode = strtoupper(trim((string)($stockItem['receiver_country_code'] ?? '')));
    $destination = trim((string)($addons['forwarder_destination'] ?? $addons['destination_city'] ?? $addons['destination'] ?? ''));
    if ($destination === '') {
        $destination = $countryCode === 'AZ' ? 'Baku' : $countryCode;
    }
    $subCat = trim((string)($addons['sub_cat_text'] ?? $addons['tariff_type_text'] ?? $addons['tariff_type_name'] ?? $addons['sub_category'] ?? $addons['tariff_type'] ?? ''));
    $title = trim((string)($addons['title_text'] ?? $addons['title_name'] ?? $addons['item_title'] ?? $addons['product_name'] ?? $addons['title'] ?? $category));
    if ($subCat === '') {
        $subCat = $category;
    }
    if ($title === '' || ctype_digit($title)) {
        $title = $category;
    }
    $tariffTypeId = trim((string)($addons['tariff_type_id'] ?? $addons['tariff_type'] ?? ''));
    $invoiceStatus = trim((string)($addons['invoice_status'] ?? '1'));
    $trackingInternalSame = trim((string)($addons['tracking_internal_same'] ?? '0'));
    $position = 'PSB010';
    $grossWeight = trim((string)($addons['gross_weight'] ?? ''));
    if ($grossWeight === '') {
        $weightRaw = str_replace(',', '.', trim((string)($stockItem['weight_kg'] ?? '')));
        if ($weightRaw !== '' && is_numeric($weightRaw)) {
            $grossWeight = number_format((float)$weightRaw, 3, '.', '');
        }
    }
    if ($grossWeight === '' || !is_numeric($grossWeight) || (float)$grossWeight <= 0) {
        $grossWeight = '1';
    }
    return [
        'track' => $tracking,
        'destination' => $destination,
        'weight' => trim((string)($stockItem['weight_kg'] ?? '')),
        'gross-weight' => $grossWeight,
        'currency' => trim((string)($addons['currency'] ?? 'USD')),
        'quantity' => trim((string)($addons['quantity'] ?? '1')),
        'client-name-surname' => trim((string)($stockItem['receiver_name'] ?? '')),
        'client-id' => (string)$clientId,
        'status-id' => (string)warehouse_item_in_status_id_by_client_id($clientId),
        'category' => $category,
        'description' => '',
        'sub-cat' => $subCat,
        'invoice' => trim((string)($addons['invoice'] ?? '0')),
        'position' => $position,
        'tracking-internal-same' => $trackingInternalSame,
        'tariff-type-id' => $tariffTypeId,
        'is-legal-entity' => trim((string)($addons['is_legal_entity'] ?? 'off')),
        'invoice-status' => $invoiceStatus,
        'title' => $title,
        'seller' => trim((string)($addons['seller'] ?? '')),
        'container-id' => trim((string)($addons['container_id'] ?? '')),
        'total-images' => trim((string)($addons['total_images'] ?? '0')),
        'length' => trim((string)($addons['length'] ?? '')),
        'height' => trim((string)($addons['height'] ?? '')),
        'width' => trim((string)($addons['width'] ?? '')),

    ];
}

function warehouse_item_in_update_registration_state(mysqli $dbcnx, int $stockItemId, string $status, string $message, array $rawResponse): void
{
    $rawResponse = warehouse_item_in_mask_submitted_args_password($rawResponse);
    $responseJson = json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $registeredAt = strtolower($status) === 'ok' ? date('Y-m-d H:i:s') : null;
    $stmt = $dbcnx->prepare(
        "UPDATE warehouse_item_stock
            SET forwarder_registered_at = ?,
                forwarder_registration_status = ?,
                forwarder_registration_message = ?,
                forwarder_registration_response_json = ?
          WHERE id = ?
          LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('ssssi', $registeredAt, $status, $message, $responseJson, $stockItemId);
        $stmt->execute();
        $stmt->close();
    }
}

function warehouse_item_in_ensure_sync_audit_table(mysqli $dbcnx): void
{
    $sql = "CREATE TABLE IF NOT EXISTS warehouse_sync_audit (
"
        . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
        . " item_id BIGINT UNSIGNED NOT NULL,
"
        . " tracking_no VARCHAR(255) NOT NULL DEFAULT '',
"
        . " forwarder VARCHAR(120) NOT NULL DEFAULT '',
"
        . " country_code VARCHAR(16) NOT NULL DEFAULT '',
"
        . " status VARCHAR(20) NOT NULL DEFAULT 'error',
"
        . " message TEXT NULL,
"
        . " response_json LONGTEXT NULL,
"
        . " created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
"
        . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
"
        . " PRIMARY KEY (id),
"
        . " KEY idx_item_created (item_id, created_at),
"
        . " KEY idx_status_created (status, created_at)
"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $dbcnx->query($sql);
}

function warehouse_item_in_sync_audit_log(mysqli $dbcnx, array $entry): void
{
    warehouse_item_in_ensure_sync_audit_table($dbcnx);

    $sql = 'INSERT INTO warehouse_sync_audit (item_id, tracking_no, forwarder, country_code, status, message, response_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return;
    }

    $itemId = (int)($entry['item_id'] ?? 0);
    $tracking = trim((string)($entry['tracking_no'] ?? ''));
    $forwarder = trim((string)($entry['forwarder'] ?? ''));
    $country = trim((string)($entry['country_code'] ?? ''));
    $status = trim((string)($entry['status'] ?? 'error'));
    $message = trim((string)($entry['message'] ?? ''));
    $responseJson = trim((string)($entry['response_json'] ?? ''));
    $decodedResponse = json_decode($responseJson, true);
    if (is_array($decodedResponse)) {
        $responseJson = json_encode(warehouse_item_in_mask_submitted_args_password($decodedResponse), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $responseJson;
    }
    $createdBy = (int)($entry['created_by'] ?? 0);

    $stmt->bind_param('issssssi', $itemId, $tracking, $forwarder, $country, $status, $message, $responseJson, $createdBy);
    $stmt->execute();
    $stmt->close();
}

function warehouse_item_in_normalize_image_json(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $clean = [];
    foreach ($decoded as $path) {
        if (!is_string($path)) {
            continue;
        }
        $path = trim($path);
        if ($path === '') {
            continue;
        }
        $clean[] = $path;
    }

    if (empty($clean)) {
        return null;
    }

    $encoded = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);
    return $encoded !== false ? $encoded : null;
}

function warehouse_item_in_decode_image_paths(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $path) {
        if (!is_string($path)) {
            continue;
        }
        $path = trim($path);
        if ($path === '') {
            continue;
        }
        $result[] = $path;
    }

    return array_values(array_unique($result));
}

function warehouse_item_in_ensure_photo_dir(string $absDir): bool
{
    if (is_dir($absDir)) {
        return true;
    }
    return @mkdir($absDir, 0775, true);
}


function warehouse_item_in_move_images_to_stock_dir(?string $raw): ?string
{
    $paths = warehouse_item_in_decode_image_paths($raw);
    if (empty($paths)) {
        return null;
    }

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $result = [];
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }

        if (strpos($path, '/img/warehouse_item_in/') !== 0 || $docRoot === '') {
            $result[] = $path;
            continue;
        }

        $targetPath = '/img/warehouse_item_stock/' . ltrim(substr($path, strlen('/img/warehouse_item_in/')), '/');
        $sourceAbs = $docRoot . $path;
        $targetAbs = $docRoot . $targetPath;
        $targetDir = dirname($targetAbs);

        if (!warehouse_item_in_ensure_photo_dir($targetDir)) {
            $result[] = $path;
            continue;
        }

        $moved = false;
        if (is_file($sourceAbs)) {
            if (@rename($sourceAbs, $targetAbs)) {
                $moved = true;
            } elseif (@copy($sourceAbs, $targetAbs)) {
                @unlink($sourceAbs);
                $moved = true;
            }
        }

        $result[] = $moved ? $targetPath : $path;
    }

    return warehouse_item_in_normalize_image_json(json_encode($result, JSON_UNESCAPED_UNICODE) ?: '');
}


function warehouse_item_in_find_or_create_draft(mysqli $dbcnx, int $batchUid, int $ownerUserId, int $deviceId, array $fields): int
{
    $tuid = trim((string)($fields['tuid'] ?? ''));
    $tracking = trim((string)($fields['tracking_no'] ?? ''));

    $stmtFind = $dbcnx->prepare(
        "SELECT id
           FROM warehouse_item_in
          WHERE batch_uid = ?
            AND committed = 0
            AND tuid = ?
            AND tracking_no = ?
          ORDER BY id DESC
          LIMIT 1"
    );
    if ($stmtFind) {
        $stmtFind->bind_param('iss', $batchUid, $tuid, $tracking);
        $stmtFind->execute();
        $row = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
    }

    $uidCreated = (int)(microtime(true) * 1000000);
    $sql = "INSERT INTO warehouse_item_in (
                batch_uid, uid_created, user_id, device_id, committed,
                tuid, tracking_no, carrier_code, carrier_name,
                receiver_country_code, receiver_country_name,
                receiver_name, receiver_company, receiver_address,
                sender_name, sender_company,
                weight_kg, size_l_cm, size_w_cm, size_h_cm,
                label_image, box_image, addons_json
            ) VALUES (
                ?, ?, ?, ?, 0,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )";

    $stmtInsert = $dbcnx->prepare($sql);
    if (!$stmtInsert) {
        return 0;
    }

    $stmtInsert->bind_param(
        'iiiisssssssssssddddsss',
        $batchUid,
        $uidCreated,
        $ownerUserId,
        $deviceId,
        $fields['tuid'],
        $fields['tracking_no'],
        $fields['carrier_code'],
        $fields['carrier_name'],
        $fields['receiver_country_code'],
        $fields['receiver_country_name'],
        $fields['receiver_name'],
        $fields['receiver_company'],
        $fields['receiver_address'],
        $fields['sender_name'],
        $fields['sender_company'],
        $fields['weight_kg'],
        $fields['size_l_cm'],
        $fields['size_w_cm'],
        $fields['size_h_cm'],
        $fields['label_image'],
        $fields['box_image'],
        $fields['addons_json']
    );
    $stmtInsert->execute();
    $newId = (int)$stmtInsert->insert_id;
    $stmtInsert->close();
    return $newId;
}

function findWarehouseDuplicate(mysqli $dbcnx, string $carrierName, string $tuid, string $tracking, int $excludeItemInId = 0): array
{
    $carrierName = trim($carrierName);
    $tuid = trim($tuid);
    $tracking = trim($tracking);
    if ($carrierName === '' || ($tuid === '' && $tracking === '')) {
        return ['duplicate' => false, 'source' => null];
    }
    $searchTracking = $tracking !== '' ? $tracking : $tuid;
    $checks = [
        'warehouse_item_in',
        'warehouse_item_stock',
    ];
    foreach ($checks as $table) {
        $sql = "SELECT id FROM {$table} WHERE carrier_name = ? AND (tuid = ? OR tracking_no = ?)";
        if ($table === 'warehouse_item_in' && $excludeItemInId > 0) {
            $sql .= " AND id <> " . (int)$excludeItemInId;
        }
        $sql .= " LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("sss", $carrierName, $tuid, $searchTracking);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return ['duplicate' => true, 'source' => $table];
        }
    }
    return ['duplicate' => false, 'source' => null];
}
switch ($action) {
    case 'warehouse_item_in':
    case 'item_in':
        auth_require_login();
        $current = $user;
        $userId  = (int)$current['id'];
        $batches = [];
        $canViewAll = auth_has_permission('warehouse.in.view_all') || auth_has_role('ADMIN');
        if ($canViewAll) {
            // Админ видит ВСЕ незавершённые партии + можем сразу знать, чей это приход
            $sql = "
                SELECT
                    wi.batch_uid,
                    MIN(wi.created_at) AS started_at,
                    COUNT(*)           AS parcel_count,
                    wi.user_id,
                    u.full_name        AS user_name
                FROM warehouse_item_in wi
                LEFT JOIN users u ON u.id = wi.user_id
                WHERE wi.committed = 0
                GROUP BY wi.batch_uid, wi.user_id
                ORDER BY started_at DESC
            ";
            if ($res = $dbcnx->query($sql)) {
                while ($row = $res->fetch_assoc()) {
                    $batches[] = $row;
                }
                $res->free();
            }
        } else {
            // Обычный пользователь — только свои партии
            $sql = "
                SELECT
                    wi.batch_uid,
                MIN(wi.created_at) AS started_at,
                COUNT(*)           AS parcel_count
                FROM warehouse_item_in wi
                WHERE wi.committed = 0
                  AND wi.user_id   = ?
                GROUP BY wi.batch_uid
                ORDER BY started_at DESC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $batches[] = $row;
            }
            $stmt->close();
        }
        $smarty->assign('batches',      $batches);
        $smarty->assign('current_user', $current);
        ob_start();
        $smarty->display('cells_NA_API_warehouse_item_in.html');
        $html = ob_get_clean();
        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'open_item_in_batch':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        warehouse_item_in_ensure_cell_columns($dbcnx);
        warehouse_item_in_ensure_forwarder_registration_columns($dbcnx);
        $current = $user;
        $userId  = (int)$current['id'];
        $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
        if ($batchUid <= 0) {
            // новая партия
            $batchUid = (int)(microtime(true) * 1000000);
        }
        $items = [];

        $canViewAll = auth_has_permission('warehouse.in.view_all') || auth_has_role('ADMIN');
        if ($canViewAll) {
            // Админ видит ВСЕ посылки в партии, кто бы их ни создавал
            $sql = "
                SELECT
                    id,
                    tuid,
                    tracking_no,
                    receiver_name,
                    receiver_company,
                    receiver_address,
                    weight_kg,
                    size_l_cm,
                    size_w_cm,
                    size_h_cm,
                    created_at
                FROM warehouse_item_in
                WHERE batch_uid = ?
                  AND committed = 0
                ORDER BY created_at ASC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param("i", $batchUid);
        } else {
            // Обычный юзер — только свои записи
            $sql = "
                SELECT
                    id,
                    tuid,
                    tracking_no,
                    receiver_name,
                    receiver_company,
                    receiver_address,
                    weight_kg,
                    size_l_cm,
                    size_w_cm,
                    size_h_cm,
                    created_at
                FROM warehouse_item_in
                WHERE batch_uid = ?
                  AND user_id   = ?
                  AND committed = 0
                ORDER BY created_at ASC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param("ii", $batchUid, $userId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        $smarty->assign('batch_uid',    $batchUid);
        $smarty->assign('items',        $items);
        $smarty->assign('current_user', $current);
            $dest_country = [];
            $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
            $stmt = $dbcnx->prepare($sql);
            $stmt->execute();
            $res3 = $stmt->get_result();
            if ($res3 = $dbcnx->query($sql)) {
                while ($row = $res3->fetch_assoc()) {
                    $dest_country[] = $row;
                }
                $res3->free();
            }
        $stmt->close();
        $smarty->assign('dest_country', $dest_country);
        $stand_devices = [];
        $sql = "SELECT device_uid, name, device_token
                  FROM devices
                 WHERE name LIKE 'stand\\_%'
                 ORDER BY name ASC, device_uid ASC";
        if ($resStand = $dbcnx->query($sql)) {
            while ($row = $resStand->fetch_assoc()) {
                $stand_devices[] = $row;
            }
            $resStand->free();
        }
        $smarty->assign('stand_devices', $stand_devices);
        $smarty->assign('cells', warehouse_item_in_load_cells($dbcnx));
        [$addonsMap, $addonsRawMap] = warehouse_item_in_load_addons_map($dbcnx);
        $smarty->assign('addons_map', $addonsMap);
        $smarty->assign('addons_raw_map', $addonsRawMap);
        require_once __DIR__ . '/../../ocr_templates.php';
        require_once __DIR__ . '/../../ocr_dicts.php';
        ob_start();
        $smarty->display('cells_NA_API_warehouse_item_in_batch.html');
        $html = ob_get_clean();
        $response = [
            'status' => 'ok',
            'html'   => $html,
        ];
        break;

    case 'add_new_item_in':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        warehouse_item_in_ensure_cell_columns($dbcnx);
        $current = $user;
        // Кто сейчас залогинен — ОПЕРАТОР
        $operatorUserId = (int)$current['id'];
        // 1) партия
        $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
        if ($batchUid <= 0) {
            // новая партия — владелец = текущий пользователь
            $batchUid     = (int)(microtime(true) * 1000000); // uid_created
            $ownerUserId  = $operatorUserId;
        } else {
            // существующая партия — ищем её владельца
            $ownerUserId = $operatorUserId; // по умолчанию
            $stmtOwner = $dbcnx->prepare(
                "SELECT user_id
                   FROM warehouse_item_in
                  WHERE batch_uid = ?
                    AND committed = 0
                  ORDER BY created_at ASC
                  LIMIT 1"
            );
            if ($stmtOwner) {
                $stmtOwner->bind_param("i", $batchUid);
                $stmtOwner->execute();
                $resOwner = $stmtOwner->get_result();
                if ($rowOwner = $resOwner->fetch_assoc()) {
                    $ownerUserId = (int)$rowOwner['user_id'];
                }
                $stmtOwner->close();
            }
        }
        // 2) поля из формы
        $tuid        = trim($_POST['tuid']        ?? '');
        $tracking    = trim($_POST['tracking_no'] ?? '');
        $carrierName = trim($_POST['carrier_name'] ?? '');
        $carrierCode = $carrierName !== '' ? $carrierName : trim((string)($_POST['carrier_code'] ?? ''));
        $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
        // имя страны сейчас не приходит, можно оставить пустым
        $rcCountryName = '';
        $rcName        = trim($_POST['receiver_name']    ?? '');
        $rcCompany     = trim($_POST['receiver_company'] ?? '');
        $rcAddress     = trim($_POST['receiver_address'] ?? '');
        $cellIdRaw     = trim((string)($_POST['cell_id'] ?? ''));
        $cellId        = warehouse_item_in_resolve_cell_id($dbcnx, $cellIdRaw, $rcAddress);
        $sndName       = trim($_POST['sender_name']    ?? '');
        $sndCompany    = trim($_POST['sender_company'] ?? '');
        // вес и габариты: если пусто → 0
        $weightKg = $_POST['weight_kg'] ?? '';
        $sizeL    = $_POST['size_l_cm'] ?? '';
        $sizeW    = $_POST['size_w_cm'] ?? '';
        $sizeH    = $_POST['size_h_cm'] ?? '';
        $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
        $sizeL    = ($sizeL    === '' || $sizeL    === null) ? 0.0 : (float)$sizeL;
        $sizeW    = ($sizeW    === '' || $sizeW    === null) ? 0.0 : (float)$sizeW;
        $sizeH    = ($sizeH    === '' || $sizeH    === null) ? 0.0 : (float)$sizeH;
        $addonsJsonRaw = trim((string)($_POST['addons_json'] ?? ''));
        $labelImage = trim((string)($_POST['label_image'] ?? ''));
        $boxImage   = trim((string)($_POST['box_image'] ?? ''));

        $addonsJson = $addonsJsonRaw !== '' ? $addonsJsonRaw : null;
        $labelImage = $labelImage !== '' ? $labelImage : null;
        $boxImage   = $boxImage !== '' ? $boxImage : null;
        $labelImage = warehouse_item_in_move_images_to_stock_dir($labelImage);
        $boxImage   = warehouse_item_in_move_images_to_stock_dir($boxImage);
        if ($tuid === '' || $tracking === '') {
            $response = [
                'status'  => 'error',
                'message' => 'Нужны хотя бы TUID и трек-номер',
            ];
            break;
        }
        $itemId = (int)($_POST['item_id'] ?? 0);
        $duplicateCheck = findWarehouseDuplicate($dbcnx, $carrierName, $tuid, $tracking, $itemId);
        if ($duplicateCheck['duplicate']) {
            $response = [
                'status'  => 'error',
                'message' => 'Такая посылка уже есть на складе',
            ];
            break;
        }
        $uidCreated = (int)(microtime(true) * 1000000);
        $deviceId   = 0; // для веба 0, для мобилки можно класть реальный device_id

        $itemId = (int)($_POST['item_id'] ?? 0);
        $cellIdForDb = $cellId ?? 0;
        if ($itemId > 0) {
            $stmt = $dbcnx->prepare(
                "UPDATE warehouse_item_in
                    SET tuid = ?,
                        tracking_no = ?,
                        carrier_code = ?,
                        carrier_name = ?,
                        receiver_country_code = ?,
                        receiver_country_name = ?,
                        receiver_name = ?,
                        receiver_company = ?,
                        receiver_address = ?,
                        cell_id = NULLIF(?, 0),
                        sender_name = ?,
                        sender_company = ?,
                        weight_kg = ?,
                        size_l_cm = ?,
                        size_w_cm = ?,
                        size_h_cm = ?,
                        label_image = ?,
                        box_image = ?,
                        addons_json = ?
                  WHERE id = ?
                    AND batch_uid = ?
                    AND committed = 0
                  LIMIT 1"
            );
            if (!$stmt) {
                $response = [
                    'status'  => 'error',
                    'message' => 'DB error: ' . $dbcnx->error,
                ];
                break;
            }
            $stmt->bind_param(
                'sssssssssissddddsssii',
                $tuid,
                $tracking,
                $carrierCode,
                $carrierName,
                $rcCountryCode,
                $rcCountryName,
                $rcName,
                $rcCompany,
                $rcAddress,
                $cellIdForDb,
                $sndName,
                $sndCompany,
                $weightKg,
                $sizeL,
                $sizeW,
                $sizeH,
                $labelImage,
                $boxImage,
                $addonsJson,
                $itemId,
                $batchUid
            );
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected < 0) {
                $response = [
                    'status'  => 'error',
                    'message' => 'Не удалось обновить посылку',
                ];
                break;
            }
        } else {
            $sql = "INSERT INTO warehouse_item_in (
                        batch_uid, uid_created, user_id, device_id, committed,
                        tuid, tracking_no, carrier_code, carrier_name,
                        receiver_country_code, receiver_country_name,
                        receiver_name, receiver_company, receiver_address, cell_id,
                        sender_name, sender_company,
                        weight_kg, size_l_cm, size_w_cm, size_h_cm,
                        label_image, box_image, addons_json
                    ) VALUES (
                        ?, ?, ?, ?, 0,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, NULLIF(?, 0),
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?
                    )";
            $stmt = $dbcnx->prepare($sql);
            if (!$stmt) {
                $response = [
                    'status'  => 'error',
                    'message' => 'DB error: ' . $dbcnx->error,
                ];
                break;
            }
            $stmt->bind_param(
                "iiiisssssssssissddddsss",
                $batchUid,
                $uidCreated,
                $ownerUserId,
                $deviceId,
                $tuid,
                $tracking,
                $carrierCode,
                $carrierName,
                $rcCountryCode,
                $rcCountryName,
                $rcName,
                $rcCompany,
                $rcAddress,
                $cellIdForDb,
                $sndName,
                $sndCompany,
                $weightKg,
                $sizeL,
                $sizeW,
                $sizeH,
                $labelImage,
                $boxImage,
                $addonsJson
            );
            $stmt->execute();
            $itemId = (int)$stmt->insert_id;
            $stmt->close();
        }

        audit_log(
            $operatorUserId,                 // кто реально добавил
            'WAREHOUSE_IN_ADD_PARCEL',
            'WAREHOUSE_IN',
            $batchUid,                       // entity_id = batch_uid
            'Добавлена посылка в партию прихода',
            [
                'batch_uid'        => $batchUid,
                'owner_user_id'    => $ownerUserId,
                'operator_user_id' => $operatorUserId,
                'tuid'             => $tuid,
                'tracking_no'      => $tracking,
                'addons_json'      => $addonsJson,
            ]
        );
        $response = [
            'status'    => 'ok',
            'message'   => 'Посылка добавлена',
            'batch_uid' => $batchUid,
        ];
        break;

    case 'save_item_in_draft':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        warehouse_item_in_ensure_cell_columns($dbcnx);
        $current = $user;
        $operatorUserId = (int)$current['id'];

        $batchUid = isset($_POST['batch_uid']) ? (int)$_POST['batch_uid'] : 0;
        if ($batchUid <= 0) {
            $batchUid = (int)(microtime(true) * 1000000);
        }

        $ownerUserId = $operatorUserId;
        $stmtOwner = $dbcnx->prepare(
            "SELECT user_id
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0
              ORDER BY created_at ASC
              LIMIT 1"
        );
        if ($stmtOwner) {
            $stmtOwner->bind_param('i', $batchUid);
            $stmtOwner->execute();
            $resOwner = $stmtOwner->get_result();
            if ($rowOwner = $resOwner->fetch_assoc()) {
                $ownerUserId = (int)$rowOwner['user_id'];
            }
            $stmtOwner->close();
        }

        $tuid        = trim($_POST['tuid'] ?? '');
        $tracking    = trim($_POST['tracking_no'] ?? '');
        $carrierName = trim($_POST['carrier_name'] ?? '');
        $carrierCode = $carrierName !== '' ? $carrierName : trim((string)($_POST['carrier_code'] ?? ''));
        $rcCountryCode = trim($_POST['receiver_country_code'] ?? '');
        $rcCountryName = '';
        $rcName        = trim($_POST['receiver_name'] ?? '');
        $rcCompany     = trim($_POST['receiver_company'] ?? '');
        $rcAddress     = trim($_POST['receiver_address'] ?? '');
        $sndName       = trim($_POST['sender_name'] ?? '');
        $sndCompany    = trim($_POST['sender_company'] ?? '');

        $weightKg = $_POST['weight_kg'] ?? '';
        $sizeL    = $_POST['size_l_cm'] ?? '';
        $sizeW    = $_POST['size_w_cm'] ?? '';
        $sizeH    = $_POST['size_h_cm'] ?? '';
        $weightKg = ($weightKg === '' || $weightKg === null) ? 0.0 : (float)$weightKg;
        $sizeL    = ($sizeL    === '' || $sizeL    === null) ? 0.0 : (float)$sizeL;
        $sizeW    = ($sizeW    === '' || $sizeW    === null) ? 0.0 : (float)$sizeW;
        $sizeH    = ($sizeH    === '' || $sizeH    === null) ? 0.0 : (float)$sizeH;

        if ($tuid === '' || $tracking === '') {
            $response = ['status' => 'error', 'message' => 'Нужны TUID и трек-номер'];
            break;
        }

        $deviceId = 0;
        $fields = [
            'tuid' => $tuid,
            'tracking_no' => $tracking,
            'carrier_code' => $carrierCode,
            'carrier_name' => $carrierName,
            'receiver_country_code' => $rcCountryCode,
            'receiver_country_name' => $rcCountryName,
            'receiver_name' => $rcName,
            'receiver_company' => $rcCompany,
            'receiver_address' => $rcAddress,
            'sender_name' => $sndName,
            'sender_company' => $sndCompany,
            'weight_kg' => $weightKg,
            'size_l_cm' => $sizeL,
            'size_w_cm' => $sizeW,
            'size_h_cm' => $sizeH,
            'label_image' => null,
            'box_image' => null,
            'addons_json' => null,
        ];

        $draftId = warehouse_item_in_find_or_create_draft($dbcnx, $batchUid, $ownerUserId, $deviceId, $fields);
        if ($draftId <= 0) {
            $response = ['status' => 'error', 'message' => 'Не удалось создать черновик'];
            break;
        }

        $response = [
            'status' => 'ok',
            'message' => 'Черновик создан',
            'batch_uid' => $batchUid,
            'item_id' => $draftId,
        ];
        break;

    case 'clear_item_in_draft':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            $response = ['status' => 'ok', 'message' => 'Форма очищена'];
            break;
        }
        $stmtRead = $dbcnx->prepare("SELECT label_image, box_image FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if ($stmtRead) {
            $stmtRead->bind_param('i', $itemId);
            $stmtRead->execute();
            $row = $stmtRead->get_result()->fetch_assoc();
            $stmtRead->close();

            $paths = array_merge(
                warehouse_item_in_decode_image_paths((string)($row['label_image'] ?? '')),
                warehouse_item_in_decode_image_paths((string)($row['box_image'] ?? ''))
            );
            $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            foreach ($paths as $path) {
                if (strpos($path, '/img/warehouse_item_in/') !== 0 || $docRoot === '') {
                    continue;
                }
                $abs = $docRoot . $path;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }

        $stmt = $dbcnx->prepare("DELETE FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->close();
        $response = ['status' => 'ok', 'message' => 'Черновик удалён'];
        break;

    case 'upload_item_in_photo':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
        if ($itemId <= 0) {
            $response = ['status' => 'error', 'message' => 'Сначала получите измерения'];
            break;
        }
        if (!in_array($photoType, ['label', 'box'], true)) {
            $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
            break;
        }
        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            $response = ['status' => 'error', 'message' => 'Файл не передан'];
            break;
        }

        $stmt = $dbcnx->prepare("SELECT id, uid_created, label_image, box_image FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            $response = ['status' => 'error', 'message' => 'Черновик не найден'];
            break;
        }

        $upload = $_FILES['photo'];
        $errorCode = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $response = ['status' => 'error', 'message' => 'Ошибка загрузки файла'];
            break;
        }
        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $response = ['status' => 'error', 'message' => 'Некорректный временный файл'];
            break;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            $response = ['status' => 'error', 'message' => 'Разрешены только JPEG/PNG/WEBP'];
            break;
        }

        $uidCreated = trim((string)($item['uid_created'] ?? ''));
        if ($uidCreated === '') {
            $uidCreated = (string)$itemId;
        }
        $baseRelDir = 'img/warehouse_item_in/' . $uidCreated;
        $baseAbsDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $baseRelDir;
        if (!warehouse_item_in_ensure_photo_dir($baseAbsDir)) {
            $response = ['status' => 'error', 'message' => 'Не удалось создать каталог для фото'];
            break;
        }

        $fileName = date('Ymd_His') . '_' . $photoType . '.' . $allowed[$mime];
        $destAbs = $baseAbsDir . '/' . $fileName;
        $publicPath = '/' . $baseRelDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $destAbs)) {
            $response = ['status' => 'error', 'message' => 'Не удалось сохранить файл'];
            break;
        }

        $field = $photoType === 'label' ? 'label_image' : 'box_image';
        $oldJson = (string)($item[$field] ?? '');
        $oldPaths = warehouse_item_in_decode_image_paths($oldJson);
        $oldPaths[] = $publicPath;
        $json = warehouse_item_in_normalize_image_json(json_encode($oldPaths, JSON_UNESCAPED_UNICODE) ?: '');
        if ($json === null) {
            @unlink($destAbs);
            $response = ['status' => 'error', 'message' => 'Ошибка сериализации пути'];
            break;
        }

        $sql = "UPDATE warehouse_item_in SET {$field} = ? WHERE id = ? AND committed = 0 LIMIT 1";
        $stmt = $dbcnx->prepare($sql);
        if (!$stmt) {
            @unlink($destAbs);
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('si', $json, $itemId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'status' => 'ok',
            'message' => 'Фото загружено',
            'photo_type' => $photoType,
            'path' => $publicPath,
            'json' => $json,
        ];
        break;

    case 'delete_item_in_photo':
        auth_require_login();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $photoType = strtolower(trim((string)($_POST['photo_type'] ?? '')));
        if ($itemId <= 0) {
            $response = ['status' => 'error', 'message' => 'Некорректный item_id'];
            break;
        }
        if (!in_array($photoType, ['label', 'box'], true)) {
            $response = ['status' => 'error', 'message' => 'Некорректный тип фото'];
            break;
        }

        $field = $photoType === 'label' ? 'label_image' : 'box_image';
        $stmt = $dbcnx->prepare("SELECT id, {$field} FROM warehouse_item_in WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            $response = ['status' => 'error', 'message' => 'Черновик не найден'];
            break;
        }

        $oldPaths = warehouse_item_in_decode_image_paths((string)($item[$field] ?? ''));
        $stmt = $dbcnx->prepare("UPDATE warehouse_item_in SET {$field} = NULL WHERE id = ? AND committed = 0 LIMIT 1");
        if (!$stmt) {
            $response = ['status' => 'error', 'message' => 'DB error: ' . $dbcnx->error];
            break;
        }
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $stmt->close();

        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        foreach ($oldPaths as $path) {
            if (strpos($path, '/img/warehouse_item_in/') !== 0 || $docRoot === '') {
                continue;
            }
            $abs = $docRoot . $path;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        $response = ['status' => 'ok', 'message' => 'Фото удалено', 'json' => ''];
        break;

    case 'warehouse_lookup_forwarder_client':
        auth_require_login();
        $receiverCompany = strtoupper(trim((string)($_POST['receiver_company'] ?? '')));
        $receiverCountryCode = strtoupper(trim((string)($_POST['receiver_country_code'] ?? '')));
        $receiverAddress = trim((string)($_POST['receiver_address'] ?? ''));

        if ($receiverCountryCode === 'AZERBAIJAN' || $receiverCountryCode === 'AZE' || $receiverCountryCode === 'AZB') {
            $receiverCountryCode = 'AZ';
        }

        $clientId = warehouse_forwarder_extract_numeric_client_id($receiverAddress);
        if ($clientId === '') {
            $response = [
                'status' => 'ok',
                'found' => false,
                'reason' => 'no_client_id',
            ];
            break;
        }

        if ($receiverCompany === '' || $receiverCountryCode === '') {
            $response = [
                'status' => 'ok',
                'found' => false,
                'source' => 'local_cache',
                'client_id' => $clientId,
                'reason' => 'missing_forwarder_or_country',
            ];
            break;
        }

        $stmt = $dbcnx->prepare(
            "SELECT connector_key, forwarder_name, country_code, client_id, client_name, client_email, client_address, updated_at
               FROM connector_clients
              WHERE forwarder_name = ?
                AND country_code = ?
                AND client_id = ?
              LIMIT 1"
        );
        if (!$stmt) {
            $response = [
                'status' => 'ok',
                'found' => false,
                'source' => 'local_cache',
                'client_id' => $clientId,
                'reason' => 'lookup_unavailable',
            ];
            break;
        }

        $stmt->bind_param('sss', $receiverCompany, $receiverCountryCode, $clientId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {

            if ($receiverCompany === 'CAMEX' && $receiverCountryCode === 'AZ') {
                $response = warehouse_item_in_try_camex_az_remote_client_lookup($dbcnx, $clientId, $receiverAddress);
                break;
            }

            $response = [
                'status' => 'ok',
                'found' => false,
                'source' => 'local_cache',
                'client_id' => $clientId,
            ];
            break;
        }

        $response = [
            'status' => 'ok',
            'found' => true,
            'source' => 'local_cache',
            'connector_key' => (string)($row['connector_key'] ?? ''),
            'forwarder_name' => (string)($row['forwarder_name'] ?? ''),
            'country_code' => (string)($row['country_code'] ?? ''),
            'client_id' => (string)($row['client_id'] ?? ''),
            'client_name' => (string)($row['client_name'] ?? ''),
            'client_email' => $row['client_email'] ?? null,
            'client_address' => $row['client_address'] ?? null,
        ];
        break;
    case 'check_item_in_duplicate':
        auth_require_login();
        $tuid        = trim($_POST['tuid']        ?? '');
        $tracking    = trim($_POST['tracking_no'] ?? '');
        $carrierName = trim($_POST['carrier_name'] ?? '');
        $itemId = (int)($_POST['item_id'] ?? 0);
        $duplicateCheck = findWarehouseDuplicate($dbcnx, $carrierName, $tuid, $tracking, $itemId);
        $response = [
            'status'    => 'ok',
            'duplicate' => $duplicateCheck['duplicate'],
            'source'    => $duplicateCheck['source'],
        ];
    case 'delete_item_in':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        warehouse_item_in_ensure_cell_columns($dbcnx);
        $current = $user;
        $userId  = (int)$current['id'];
        $isAdmin = auth_has_permission('warehouse.in.manage_all') || auth_has_role('ADMIN');
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'item_id не задан',
            ];
            break;
        }
        $stmtItem = $dbcnx->prepare(
            "SELECT id, batch_uid, user_id, committed\n           FROM warehouse_item_in\n          WHERE id = ?"
        );
        if (!$stmtItem) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare select item): ' . $dbcnx->error,
            ];
            break;
        }
        $stmtItem->bind_param("i", $itemId);
        $stmtItem->execute();
        $itemRow = $stmtItem->get_result()->fetch_assoc();
        $stmtItem->close();
        if (!$itemRow) {
            $response = [
                'status'  => 'error',
                'message' => 'Посылка не найдена',
            ];
            break;
        }
        $batchUid   = (int)($itemRow['batch_uid'] ?? 0);
        $itemUserId = (int)($itemRow['user_id']   ?? 0);
        $committed  = (int)($itemRow['committed'] ?? 0);
        if ($committed !== 0) {
            $response = [
                'status'  => 'error',
                'message' => 'Нельзя удалить завершённую посылку',
            ];
            break;
        }
        if (!$isAdmin && $itemUserId !== $userId) {
            $response = [
                'status'  => 'error',
                'message' => 'Недостаточно прав для удаления посылки',
            ];
            break;
        }
        if ($isAdmin) {
            $stmtDel = $dbcnx->prepare(
                "DELETE FROM warehouse_item_in\n             WHERE id = ?\n               AND committed = 0"
            );
            if ($stmtDel) {
                $stmtDel->bind_param("i", $itemId);
            }
        } else {
            $stmtDel = $dbcnx->prepare(
                "DELETE FROM warehouse_item_in\n             WHERE id = ?\n               AND user_id = ?\n               AND committed = 0"
            );
            if ($stmtDel) {
                $stmtDel->bind_param("ii", $itemId, $userId);
            }
        }
        if (!$stmtDel) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare delete): ' . $dbcnx->error,
            ];
            break;
        }
        $stmtDel->execute();
        $stmtDel->close();
        $items = [];
        audit_log(
            $userId,
            'WAREHOUSE_IN_DELETE_PARCEL',
            'WAREHOUSE_IN',
            $itemId,
            'Удалена посылка из партии прихода',
            [
                'batch_uid'    => $batchUid,
                'item_id'      => $itemId,
                'item_user_id' => $itemUserId,
                'deleted_by'   => $userId,
                'is_admin'     => $isAdmin,
            ]
        );
        if ($isAdmin) {
            $sql = "
                SELECT
                    id,
                    tuid,
                    tracking_no,
                    receiver_name,
                    receiver_company,
                    receiver_address,
                    weight_kg,
                    size_l_cm,
                    size_w_cm,
                    size_h_cm,
                    created_at
                FROM warehouse_item_in
                WHERE batch_uid = ?
                  AND committed = 0
                ORDER BY created_at ASC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param("i", $batchUid);
        } else {
            $sql = "
                SELECT
                    id,
                    tuid,
                    tracking_no,
                    receiver_name,
                    receiver_company,
                    receiver_address,
                    weight_kg,
                    size_l_cm,
                    size_w_cm,
                    size_h_cm,
                    created_at
                FROM warehouse_item_in
                WHERE batch_uid = ?
                  AND user_id   = ?
                  AND committed = 0
                ORDER BY created_at ASC
            ";
            $stmt = $dbcnx->prepare($sql);
            $stmt->bind_param("ii", $batchUid, $userId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        $smarty->assign('batch_uid',    $batchUid);
        $smarty->assign('items',        $items);
        $smarty->assign('current_user', $current);
            $dest_country = [];
            $sql = "SELECT `id`,`code_iso2`,`code_iso3`,`name_en`,`name_local` FROM `dest_countries` WHERE `is_active` = '1' ORDER BY `id`";
            $stmt = $dbcnx->prepare($sql);
            $stmt->execute();
            $res3 = $stmt->get_result();
            if ($res3 = $dbcnx->query($sql)) {
                while ($row = $res3->fetch_assoc()) {
                    $dest_country[] = $row;
                }
                $res3->free();
            }
        $stmt->close();
        $smarty->assign('dest_country', $dest_country);
        $stand_devices = [];
        $sql = "SELECT device_uid, name, device_token
                  FROM devices
                 WHERE name LIKE 'stand\\_%'
                 ORDER BY name ASC, device_uid ASC";
        if ($resStand = $dbcnx->query($sql)) {
            while ($row = $resStand->fetch_assoc()) {
                $stand_devices[] = $row;
            }
            $resStand->free();
        }
        $smarty->assign('stand_devices', $stand_devices);
        $smarty->assign('cells', warehouse_item_in_load_cells($dbcnx));

        [$addonsMap, $addonsRawMap] = warehouse_item_in_load_addons_map($dbcnx);
        $smarty->assign('addons_map', $addonsMap);
        $smarty->assign('addons_raw_map', $addonsRawMap);
        require_once __DIR__ . '/../../ocr_templates.php';
        require_once __DIR__ . '/../../ocr_dicts.php';
        ob_start();
        $smarty->display('cells_NA_API_warehouse_item_in_batch.html');
        $html = ob_get_clean();
        $response = [
            'status'  => 'ok',
            'message' => 'Посылка удалена',
            'html'    => $html,
        ];
        break;
    case 'commit_item_in_batch':
        auth_require_login();
        warehouse_item_in_ensure_addons_columns($dbcnx);
        warehouse_item_in_ensure_cell_columns($dbcnx);
        warehouse_item_in_ensure_forwarder_registration_columns($dbcnx);
        $current  = $user;
        $userId   = (int)$current['id'];
        $batchUid = (int)($_POST['batch_uid'] ?? 0);
        if ($batchUid <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'batch_uid не задан',
            ];
            break;
        }

        $isAdmin = auth_has_permission('warehouse.in.manage_all') || auth_has_role('ADMIN');
        // 1) сколько незакоммиченных
        $stmt = $dbcnx->prepare(
            "SELECT COUNT(*) AS cnt
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0"
        );
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare count): ' . $dbcnx->error,
            ];
            break;
        }
        $stmt->bind_param("i", $batchUid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cnt = (int)($res['cnt'] ?? 0);
        if ($cnt === 0) {
            $response = [
                'status'  => 'ok',
                'message' => 'Партия уже была завершена или пустая',
            ];
            break;
        }

        $pendingRows = [];
        $stmtRows = $dbcnx->prepare(
            "SELECT id, uid_created, tuid, tracking_no, receiver_company, receiver_country_code, receiver_name, receiver_address, weight_kg, addons_json, cell_id
               FROM warehouse_item_in
              WHERE batch_uid = ?
                AND committed = 0
              ORDER BY id ASC"
        );
        if ($stmtRows) {
            $stmtRows->bind_param("i", $batchUid);
            $stmtRows->execute();
            $resRows = $stmtRows->get_result();
            while ($resRows && ($row = $resRows->fetch_assoc())) {
                $pendingRows[] = $row;
            }
            $stmtRows->close();
        }
        // 2) копируем в stock
        $sqlCopy = "INSERT INTO warehouse_item_stock (
                        batch_uid, uid_created, user_id, device_id, created_at,
                        tuid, tracking_no, carrier_code, carrier_name,
                        receiver_country_code, receiver_country_name,
                        receiver_name, receiver_company, receiver_address, cell_id,
                        sender_name, sender_company,
                        weight_kg, size_l_cm, size_w_cm, size_h_cm,
                        label_image, box_image, addons_json
                    )
                    SELECT
                        batch_uid, uid_created, user_id, device_id, created_at,
                        tuid, tracking_no, carrier_code, carrier_name,
                        receiver_country_code, receiver_country_name,
                        receiver_name, receiver_company, receiver_address, cell_id,
                        sender_name, sender_company,
                        weight_kg, size_l_cm, size_w_cm, size_h_cm,
                        label_image, box_image, addons_json
                      FROM warehouse_item_in
                     WHERE batch_uid = ?
                       AND committed = 0";
        $stmt = $dbcnx->prepare($sqlCopy);
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare copy): ' . $dbcnx->error,
            ];
            break;
        }
        $stmt->bind_param("i", $batchUid);
        $stmt->execute();
        $stmt->close();

        // 3) помечаем как committed
        $stmt = $dbcnx->prepare(
            "UPDATE warehouse_item_in
                SET committed = 1
              WHERE batch_uid = ?
                AND committed = 0"
        );
        if (!$stmt) {
            $response = [
                'status'  => 'error',
                'message' => 'DB error (prepare update): ' . $dbcnx->error,
            ];
            break;
        }
        $stmt->bind_param("i", $batchUid);
        $stmt->execute();
        $stmt->close();

        $registrationSummary = [
            'registered' => 0,
            'validation_skipped' => 0,
            'integration_errors' => 0,
            'details' => [],
        ];

        foreach ($pendingRows as $row) {
            $stockItemId = 0;
            $rowTrackingNo = trim((string)($row['tracking_no'] ?? ''));
            $rowTuid = trim((string)($row['tuid'] ?? ''));
            $stmtStock = $dbcnx->prepare(
                "SELECT id, tuid, tracking_no, receiver_company, receiver_country_code, receiver_name, receiver_address, weight_kg, addons_json, cell_id, forwarder_registration_status
                   FROM warehouse_item_stock
                  WHERE batch_uid = ?
                    AND uid_created = ?
                    AND COALESCE(tracking_no, '') = ?
                    AND COALESCE(tuid, '') = ?
                  ORDER BY id DESC
                  LIMIT 1"
            );
            if ($stmtStock) {
                $uidCreated = (int)($row['uid_created'] ?? 0);
                $stmtStock->bind_param('iiss', $batchUid, $uidCreated, $rowTrackingNo, $rowTuid);
                $stmtStock->execute();
                $resStock = $stmtStock->get_result();
                $stockItem = $resStock ? $resStock->fetch_assoc() : null;
                $stmtStock->close();
                if (!$stockItem) {
                    warehouse_item_in_sync_audit_log($dbcnx, [
                        'item_id' => 0,
                        'tracking_no' => (string)($rowTrackingNo !== '' ? $rowTrackingNo : $rowTuid),
                        'forwarder' => (string)($row['receiver_company'] ?? ''),
                        'country_code' => (string)($row['receiver_country_code'] ?? ''),
                        'status' => 'error',
                        'message' => 'commit_item_in_batch: Не найдена запись в warehouse_item_stock после commit',
                        'response_json' => json_encode(['batch_uid' => $batchUid, 'uid_created' => $uidCreated], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                        'created_by' => $userId,
                    ]);
                    $registrationSummary['integration_errors']++;
                    $registrationSummary['details'][] = [
                        'track' => (string)($rowTrackingNo !== '' ? $rowTrackingNo : $rowTuid),
                        'status' => 'integration_error',
                        'message' => 'Не найдена запись в warehouse_item_stock после commit',
                    ];
                    continue;
                }
                $stockItemId = (int)($stockItem['id'] ?? 0);
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => (string)(($stockItem['tracking_no'] ?? '') !== '' ? $stockItem['tracking_no'] : ($rowTrackingNo !== '' ? $rowTrackingNo : $rowTuid)),
                    'forwarder' => (string)($stockItem['receiver_company'] ?? $row['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? $row['receiver_country_code'] ?? ''),
                    'status' => 'success',
                    'message' => 'commit_item_in_batch: Запись перенесена в warehouse_item_stock',
                    'response_json' => json_encode([
                        'batch_uid' => $batchUid,
                        'warehouse_item_stock_id' => $stockItemId,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);
            } else {
                continue;
            }

            $track = trim((string)($stockItem['tracking_no'] ?? $stockItem['tuid'] ?? ''));
            $resolvedCellId = warehouse_item_in_resolve_cell_id(
                $dbcnx,
                (string)($stockItem['cell_id'] ?? ''),
                (string)($stockItem['receiver_address'] ?? '')
            );
            if ($resolvedCellId !== null && (int)($stockItem['cell_id'] ?? 0) !== $resolvedCellId) {
                $stmtFixCell = $dbcnx->prepare(
                    "UPDATE warehouse_item_stock
                        SET cell_id = ?
                      WHERE id = ?
                      LIMIT 1"
                );
                if ($stmtFixCell) {
                    $stmtFixCell->bind_param('ii', $resolvedCellId, $stockItemId);
                    $stmtFixCell->execute();
                    $stmtFixCell->close();
                    $stockItem['cell_id'] = $resolvedCellId;
                }
            }
            $alreadyStatus = strtolower(trim((string)($stockItem['forwarder_registration_status'] ?? '')));
            if ($alreadyStatus === 'ok') {
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => $track,
                    'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                    'status' => 'success',
                    'message' => 'commit_item_in_batch: Предрегистрация уже была выполнена ранее',
                    'response_json' => json_encode([
                        'registration_status' => 'ok',
                        'source' => 'warehouse_item_stock.forwarder_registration_status',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);
                $registrationSummary['registered']++;
                $registrationSummary['details'][] = [
                    'track' => $track,
                    'status' => 'ok',
                    'message' => 'Предрегистрация уже была выполнена ранее',
                ];
                continue;
            }

            $isCamexAzByReceiver = warehouse_item_in_normalize_key((string)($stockItem['receiver_company'] ?? '')) === 'CAMEX'
                && strtoupper(trim((string)($stockItem['receiver_country_code'] ?? ''))) === 'AZ';
            $requiredFields = warehouse_item_in_required_registration_fields($dbcnx, (string)($stockItem['receiver_company'] ?? ''));
            $missingFields = $isCamexAzByReceiver ? [] : warehouse_item_in_validate_registration_payload($stockItem, $requiredFields);
            if (!empty($missingFields)) {
                $message = 'Пропущено: не заполнены поля [' . implode(', ', $missingFields) . ']';
                $requiredFieldValues = warehouse_item_in_required_fields_value_map($stockItem, $requiredFields);
                $payloadPreview = warehouse_item_in_build_registration_payload($stockItem);
                $forwarderArgsPreview = [
                    'track' => $payloadPreview['track'] ?? '',
                    'destination' => $payloadPreview['destination'] ?? '',
                    'weight' => $payloadPreview['weight'] ?? '',
                    'gross-weight' => $payloadPreview['gross-weight'] ?? '',
                    'currency' => $payloadPreview['currency'] ?? '',
                    'quantity' => $payloadPreview['quantity'] ?? '',
                    'client-name-surname' => $payloadPreview['client-name-surname'] ?? '',
                    'client-id' => $payloadPreview['client-id'] ?? '',
                    'status-id' => $payloadPreview['status-id'] ?? '',
                    'category' => $payloadPreview['category'] ?? '',
                    'invoice' => $payloadPreview['invoice'] ?? '',
                    'position' => $payloadPreview['position'] ?? '',
                    'tracking-internal-same' => $payloadPreview['tracking-internal-same'] ?? '',
                    'tariff-type-id' => $payloadPreview['tariff-type-id'] ?? '',
                    'is-legal-entity' => $payloadPreview['is-legal-entity'] ?? '',
                    'invoice-status' => $payloadPreview['invoice-status'] ?? '',
                    'title' => $payloadPreview['title'] ?? '',
                    'seller' => $payloadPreview['seller'] ?? '',
                    'container-id' => $payloadPreview['container-id'] ?? '',
                    'total-images' => $payloadPreview['total-images'] ?? '',
                    'length' => $payloadPreview['length'] ?? '',
                    'height' => $payloadPreview['height'] ?? '',
                    'width' => $payloadPreview['width'] ?? '',
                    'description' => $payloadPreview['description'] ?? '',
                    'sub-cat' => $payloadPreview['sub-cat'] ?? '',
                ];
                warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'validation_error', $message, [
                    'missing_fields' => $missingFields,
                    'required_fields' => $requiredFields,
                    'required_field_values' => $requiredFieldValues,
                    'forwarder_args_preview' => $forwarderArgsPreview,
                ]);
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => $track,
                    'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                    'status' => 'error',
                    'message' => 'commit_item_in_batch: ' . $message,
                    'response_json' => json_encode([
                        'registration_status' => 'validation_error',
                        'missing_fields' => $missingFields,
                        'required_fields' => $requiredFields,
                        'required_field_values' => $requiredFieldValues,
                        'forwarder_args_preview' => $forwarderArgsPreview,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);
                $registrationSummary['validation_skipped']++;
                $registrationSummary['details'][] = [
                    'track' => $track,
                    'status' => 'validation_error',
                    'message' => $message,
                    'required_fields' => $requiredFields,
                    'required_field_values' => $requiredFieldValues,
                    'forwarder_args_preview' => $forwarderArgsPreview,
                ];
                continue;
            }

            $connector = warehouse_item_in_resolve_forwarder_connector(
                $dbcnx,
                (string)($stockItem['receiver_company'] ?? ''),
                (string)($stockItem['receiver_country_code'] ?? '')
            );
            if (!$connector) {
                $message = 'Не найден активный коннектор форвардера';
                warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'connector_error', $message, []);
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => $track,
                    'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                    'status' => 'error',
                    'message' => 'commit_item_in_batch: ' . $message,
                    'response_json' => json_encode([
                        'registration_status' => 'connector_error',
                        'receiver_company' => (string)($stockItem['receiver_company'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);
                $registrationSummary['integration_errors']++;
                $registrationSummary['details'][] = [
                    'track' => $track,
                    'status' => 'connector_error',
                    'message' => $message,
                ];
                continue;
            }

            if (warehouse_item_in_is_camex_az_connector($stockItem, $connector)) {
                $camexArgs = [];
                try {
                    $camexArgs = warehouse_item_in_build_camex_az_args($stockItem, $connector, (string)$batchUid, $stockItemId);
                    $requestedFlightNo = trim((string)($camexArgs['flight-no'] ?? ''));
                    $flightResolution = [
                        'flight_no' => $requestedFlightNo,
                        'source' => $requestedFlightNo !== '' ? 'request' : 'camex_form',
                        'result' => null,
                        'args_preview' => $camexArgs,
                    ];
                    $camexValidation = warehouse_item_in_validate_camex_az_args($stockItem, $camexArgs);
                    if (empty($camexValidation['ok'])) {
                        $message = (string)($camexValidation['message'] ?? 'CAMEX_AZ: validation_error');
                        $response = [
                            'registration_status' => 'validation_error',
                            'connector_id' => (int)($connector['id'] ?? 0),
                            'connector_name' => (string)($connector['name'] ?? ''),
                            'stock_item_id' => $stockItemId,
                            'batch_uid' => $batchUid,
                            'submitted_args' => $camexArgs,
                            'payload' => $camexArgs,
                            'args_preview' => $camexArgs,
                            'flight_resolution' => $flightResolution,
                            'missing_fields' => $camexValidation['missing_fields'] ?? [],
                            'required_fields' => $camexValidation['required_fields'] ?? [],
                            'required_field_values' => $camexValidation['required_field_values'] ?? [],
                        ];
                        warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'validation_error', $message, $response);
                        warehouse_item_in_sync_audit_log($dbcnx, [
                            'item_id' => $stockItemId,
                            'tracking_no' => $track,
                            'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                            'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                            'status' => 'error',
                            'message' => 'commit_item_in_batch: ' . $message,
                            'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                            'created_by' => $userId,
                        ]);
                        $registrationSummary['validation_skipped']++;
                        $registrationSummary['details'][] = [
                            'track' => $track,
                            'status' => 'validation_error',
                            'message' => $message,
                            'required_fields' => $camexValidation['required_fields'] ?? [],
                            'required_field_values' => $camexValidation['required_field_values'] ?? [],
                        ];
                        continue;
                    }

                    $result = warehouse_item_in_exec_php_cli_script('www/scripts/mvp/app/CAMEX_AZ/run_prepare_package.php', $camexArgs);
                    $registeredOrder = warehouse_item_in_camex_az_registered_order($result);
                    $submitted = !empty($result['submitted']);
                    $alreadyRegistered = !empty($result['already_registered']);
                    $verifyTrackingFound = !empty($result['verify']['tracking_found']);
                    $resolvedFlightNo = trim((string)($result['selected']['flight_no'] ?? $result['payload_preview']['reisi'] ?? ''));
                    $resultStatus = strtolower(trim((string)($result['status'] ?? '')));
                    $response = [
                        'submitted_args' => $camexArgs,
                        'payload' => $camexArgs,
                        'args_preview' => $camexArgs,
                        'result' => $result,
                        'registered_order' => $registeredOrder,
                        'flight_resolution' => $flightResolution,
                        'requested' => ['flight_no' => $requestedFlightNo],
                        'resolved' => ['flight_no' => $resolvedFlightNo],
                        'selected' => ['flight_no' => $resolvedFlightNo],
                        'debug_html' => $result['debug_html'] ?? ($result['debug']['html'] ?? null),
                        'connector_id' => (int)($connector['id'] ?? 0),
                        'connector_name' => (string)($connector['name'] ?? ''),
                        'stock_item_id' => $stockItemId,
                        'batch_uid' => $batchUid,
                    ];
                    $response = warehouse_item_in_mask_submitted_args_password($response);
                    if ($resultStatus === 'ok' && (($submitted && $verifyTrackingFound) || $alreadyRegistered)) {
                        $okMessage = $alreadyRegistered
                            ? 'Уже зарегистрировано у CAMEX_AZ (идемпотентный успех)'
                            : 'Успешно зарегистрировано у CAMEX_AZ';
                        warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'ok', $okMessage, $response);
                        warehouse_item_in_sync_audit_log($dbcnx, [
                            'item_id' => $stockItemId,
                            'tracking_no' => $track,
                            'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                            'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                            'status' => 'success',
                            'message' => 'commit_item_in_batch: ' . $okMessage,
                            'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                            'created_by' => $userId,
                        ]);
                        $registrationSummary['registered']++;
                        $registrationSummary['details'][] = [
                            'track' => $track,
                            'status' => 'ok',
                            'message' => $okMessage,
                            'submitted' => $submitted,
                            'already_registered' => $alreadyRegistered,
                            'registered_order' => $registeredOrder,
                        ];
                    } else {
                        $err = trim((string)($result['message'] ?? $result['error'] ?? 'CAMEX_AZ: ошибка регистрации'));
                        warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $err, $response);
                        warehouse_item_in_sync_audit_log($dbcnx, [
                            'item_id' => $stockItemId,
                            'tracking_no' => $track,
                            'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                            'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                            'status' => 'error',
                            'message' => 'commit_item_in_batch: ' . $err,
                            'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                            'created_by' => $userId,
                        ]);
                        $registrationSummary['integration_errors']++;
                        $registrationSummary['details'][] = [
                            'track' => $track,
                            'status' => 'forwarder_error',
                            'message' => $err,
                        ];
                    }
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                    $response = [
                        'exception' => $err,
                        'submitted_args' => $camexArgs,
                        'payload' => $camexArgs,
                        'connector_id' => (int)($connector['id'] ?? 0),
                        'connector_name' => (string)($connector['name'] ?? ''),
                        'stock_item_id' => $stockItemId,
                        'batch_uid' => $batchUid,
                    ];
                    warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $err, $response);
                    warehouse_item_in_sync_audit_log($dbcnx, [
                        'item_id' => $stockItemId,
                        'tracking_no' => $track,
                        'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                        'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                        'status' => 'error',
                        'message' => 'commit_item_in_batch: ' . $err,
                        'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                        'created_by' => $userId,
                    ]);
                    $registrationSummary['integration_errors']++;
                    $registrationSummary['details'][] = [
                        'track' => $track,
                        'status' => 'forwarder_error',
                        'message' => $err,
                    ];
                }
                continue;
            }

            $baseUrl = trim((string)($connector['base_url'] ?? ''));
            $login = trim((string)($connector['auth_username'] ?? ''));
            $password = trim((string)($connector['auth_password'] ?? ''));
            if ($baseUrl === '' || $login === '' || $password === '') {
                $message = 'В коннекторе не заполнены base_url/login/password';
                warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'connector_error', $message, $connector);
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => $track,
                    'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                    'status' => 'error',
                    'message' => 'commit_item_in_batch: ' . $message,
                    'response_json' => json_encode([
                        'registration_status' => 'connector_error',
                        'connector_id' => (int)($connector['id'] ?? 0),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);
                $registrationSummary['integration_errors']++;
                $registrationSummary['details'][] = [
                    'track' => $track,
                    'status' => 'connector_error',
                    'message' => $message,
                ];
                continue;
            }

            $payload = warehouse_item_in_build_registration_payload($stockItem);
            try {
                $forwarderArgs = [
                    'base-url' => $baseUrl,
                    'login' => $login,
                    'password' => $password,
                    'track' => $payload['track'],
                    'destination' => $payload['destination'],
                    'weight' => $payload['weight'],
                    'gross-weight' => $payload['gross-weight'],
                    'currency' => $payload['currency'],
                    'quantity' => $payload['quantity'],
                    'client-name-surname' => $payload['client-name-surname'],
                    'client-id' => $payload['client-id'],
                    'status-id' => $payload['status-id'],
                    'category' => $payload['category'],
                    'invoice' => $payload['invoice'],
                    'position' => $payload['position'],
                    'tracking-internal-same' => $payload['tracking-internal-same'],
                    'tariff-type-id' => $payload['tariff-type-id'],
                    'is-legal-entity' => $payload['is-legal-entity'],
                    'invoice-status' => $payload['invoice-status'],
                    'title' => $payload['title'],
                    'seller' => $payload['seller'],
                    'container-id' => $payload['container-id'],
                    'total-images' => $payload['total-images'],
                    'length' => $payload['length'],
                    'height' => $payload['height'],
                    'width' => $payload['width'],
                    'description' => $payload['description'],
                    'sub-cat' => $payload['sub-cat'],
                ];
                $result = warehouse_item_in_exec_forwarder_cli_script('run_add_package.php', $forwarderArgs);
                $resultStatus = strtolower(trim((string)($result['status'] ?? '')));
                $rawText = strtolower((string)(($result['_meta']['raw_output'] ?? '') . ' ' . ($result['message'] ?? '') . ' ' . ($result['error'] ?? '')));
                $alreadyExists = strpos($rawText, 'already') !== false || strpos($rawText, 'exists') !== false || strpos($rawText, 'уже') !== false;
                if ($resultStatus === 'ok' || $alreadyExists) {
                    $okMessage = $alreadyExists ? 'Уже зарегистрировано у форварда (идемпотентный успех)' : 'Успешно зарегистрировано у форварда';
                    $result['submitted_args'] = $forwarderArgs;
                    $result['payload'] = $payload;
                    $result['client_id_debug'] = warehouse_item_in_client_id_debug($stockItem, (int)($payload['client-id'] ?? 0));
                    $result = warehouse_item_in_mask_submitted_args_password($result);
                    warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'ok', $okMessage, $result);
                    warehouse_item_in_sync_audit_log($dbcnx, [
                        'item_id' => $stockItemId,
                        'tracking_no' => $track,
                        'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                        'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                        'status' => 'success',
                        'message' => 'commit_item_in_batch: ' . $okMessage,
                        'response_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                        'created_by' => $userId,
                    ]);
                    $registrationSummary['registered']++;
                    $registrationSummary['details'][] = [
                        'track' => $track,
                        'status' => 'ok',
                        'message' => $okMessage,
                    ];
                } else {
                    $err = trim((string)($result['message'] ?? $result['error'] ?? 'Ошибка предрегистрации'));
                    $result['submitted_args'] = $forwarderArgs;
                    $result['payload'] = $payload;
                    $result['client_id_debug'] = warehouse_item_in_client_id_debug($stockItem, (int)($payload['client-id'] ?? 0));
                    $result = warehouse_item_in_mask_submitted_args_password($result);
                    warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $err, $result);
                    warehouse_item_in_sync_audit_log($dbcnx, [
                        'item_id' => $stockItemId,
                        'tracking_no' => $track,
                        'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                        'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                        'status' => 'error',
                        'message' => 'commit_item_in_batch: ' . $err,
                        'response_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                        'created_by' => $userId,
                    ]);
                    $registrationSummary['integration_errors']++;
                    $registrationSummary['details'][] = [
                        'track' => $track,
                        'status' => 'forwarder_error',
                        'message' => $err,
                    ];
                }
            } catch (Throwable $e) {
                $err = $e->getMessage();
                warehouse_item_in_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $err, ['exception' => $err]);
                warehouse_item_in_sync_audit_log($dbcnx, [
                    'item_id' => $stockItemId,
                    'tracking_no' => $track,
                    'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                    'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                    'status' => 'error',
                    'message' => 'commit_item_in_batch: ' . $err,
                    'response_json' => json_encode(['exception' => $err], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                    'created_by' => $userId,
                ]);                $registrationSummary['integration_errors']++;
                $registrationSummary['details'][] = [
                    'track' => $track,
                    'status' => 'forwarder_error',
                    'message' => $err,
                ];
            }
        }
        // 4) аудит
        audit_log(
            $userId,
            'WAREHOUSE_IN_COMMIT',
            'WAREHOUSE_IN',
            $batchUid,
            'Партия прихода переведена на склад',
            [
                'batch_uid'    => $batchUid,
                'committed_by' => $userId,
                'is_admin'     => $isAdmin,
                'items_count'  => $cnt,
                'registration_summary' => $registrationSummary,
            ]
        );
        $response = [
            'status'  => 'ok',
            'message' => 'Партия прихода завершена и перенесена на склад. Регистрация у форварда: успешно ' . (int)$registrationSummary['registered'] . ', пропущено ' . (int)$registrationSummary['validation_skipped'] . ', ошибок ' . (int)$registrationSummary['integration_errors'] . '.',
            'moved_to_stock' => $cnt,
            'registration_summary' => $registrationSummary,
        ];
        break;
}
