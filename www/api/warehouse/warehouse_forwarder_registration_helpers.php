<?php
declare(strict_types=1);

if (!function_exists('warehouse_forwarder_normalize_key')) {
    function warehouse_forwarder_normalize_key(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    }
}

if (!function_exists('warehouse_forwarder_table_exists')) {
    function warehouse_forwarder_table_exists(mysqli $dbcnx, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $safe = $dbcnx->real_escape_string($table);
        $res = $dbcnx->query("SHOW TABLES LIKE '{$safe}'");
        if (!($res instanceof mysqli_result)) {
            return $cache[$table] = false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $cache[$table] = $exists;
    }
}

if (!function_exists('warehouse_forwarder_column_exists')) {
    function warehouse_forwarder_column_exists(mysqli $dbcnx, string $table, string $column): bool
    {
        if (!warehouse_forwarder_table_exists($dbcnx, $table)) {
            return false;
        }
        $safeTable = str_replace('`', '``', $table);
        $safeColumn = $dbcnx->real_escape_string($column);
        $res = $dbcnx->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!($res instanceof mysqli_result)) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('warehouse_forwarder_ensure_stock_registration_columns')) {
    function warehouse_forwarder_ensure_stock_registration_columns(mysqli $dbcnx): void
    {
        $columns = [
            'forwarder_registered_at' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registered_at DATETIME NULL AFTER addons_json",
            'forwarder_registration_status' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_status VARCHAR(32) NULL AFTER forwarder_registered_at",
            'forwarder_registration_message' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_message VARCHAR(255) NULL AFTER forwarder_registration_status",
            'forwarder_registration_response_json' => "ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_registration_response_json LONGTEXT NULL AFTER forwarder_registration_message",
        ];
        foreach ($columns as $column => $alterSql) {
            if (!warehouse_forwarder_column_exists($dbcnx, 'warehouse_item_stock', $column)) {
                $dbcnx->query($alterSql);
            }
        }
    }
}

if (!function_exists('warehouse_forwarder_decode_json_array')) {
    function warehouse_forwarder_decode_json_array($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('warehouse_forwarder_resolve_connector')) {
    function warehouse_forwarder_resolve_connector(mysqli $dbcnx, string $forwarder, string $country): ?array
    {
        $forwarderNorm = warehouse_forwarder_normalize_key($forwarder);
        if ($forwarderNorm === '') {
            return null;
        }
        $countryNorm = strtoupper(trim($country));
        $rows = [];
        $systemTypeSelect = warehouse_forwarder_column_exists($dbcnx, 'connectors', 'system_type') ? ', system_type' : '';
        if ($res = $dbcnx->query("SELECT id, name, countries, auth_username, auth_password, base_url, is_active{$systemTypeSelect} FROM connectors WHERE is_active = 1 ORDER BY id DESC")) {
            while ($row = $res->fetch_assoc()) {
                if (warehouse_forwarder_normalize_key((string)($row['name'] ?? '')) === $forwarderNorm) {
                    $rows[] = $row;
                }
            }
            $res->free();
        }
        if (!$rows) {
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
}

if (!function_exists('warehouse_forwarder_required_registration_fields')) {
    function warehouse_forwarder_required_registration_fields(mysqli $dbcnx, string $forwarder): array
    {
        $default = ['tracking_no', 'receiver_country_code', 'weight_kg', 'receiver_name'];
        $forwarderNorm = warehouse_forwarder_normalize_key($forwarder);
        if ($forwarderNorm === '') {
            return $default;
        }
        $required = [];
        if ($res = $dbcnx->query("SELECT connector_name, addons_json FROM connectors_addons WHERE addons_json IS NOT NULL AND TRIM(addons_json) <> ''")) {
            while ($row = $res->fetch_assoc()) {
                $connectorNorm = warehouse_forwarder_normalize_key((string)($row['connector_name'] ?? ''));
                if ($connectorNorm !== $forwarderNorm && $connectorNorm !== 'DEV_' . $forwarderNorm && ('DEV_' . $connectorNorm) !== $forwarderNorm) {
                    continue;
                }
                $decoded = warehouse_forwarder_decode_json_array($row['addons_json'] ?? '');
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
        return $required ? array_values(array_unique($required)) : $default;
    }
}

if (!function_exists('warehouse_forwarder_extract_client_id')) {
    function warehouse_forwarder_extract_client_id(array $stockItem): int
    {
        $candidateKeys = ['client_id', 'client-id', 'receiver_client_id', 'customer_id'];
        foreach ($candidateKeys as $key) {
            $raw = trim((string)($stockItem[$key] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                return (int)$raw;
            }
        }
        $addons = warehouse_forwarder_decode_json_array((string)($stockItem['addons_json'] ?? ''));
        foreach ($candidateKeys as $key) {
            $raw = trim((string)($addons[$key] ?? ''));
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
}

if (!function_exists('warehouse_forwarder_mask_submitted_args_password')) {
    function warehouse_forwarder_mask_submitted_args_password(array $raw): array
    {
        foreach ($raw as $key => $value) {
            if (is_array($value)) {
                $raw[$key] = warehouse_forwarder_mask_submitted_args_password($value);
            }
        }
        if (isset($raw['submitted_args']) && is_array($raw['submitted_args']) && array_key_exists('password', $raw['submitted_args'])) {
            $raw['submitted_args']['password'] = '***';
        }
        return $raw;
    }
}

if (!function_exists('warehouse_forwarder_client_id_debug')) {
    function warehouse_forwarder_client_id_debug(array $stockItem, int $clientId): array
    {
        return [
            'client_id_source' => 'receiver_address',
            'client_id_source_value' => (string)($stockItem['receiver_address'] ?? ''),
            'receiver_address' => (string)($stockItem['receiver_address'] ?? ''),
            'client_id_extracted' => (string)$clientId,
        ];
    }
}

if (!function_exists('warehouse_forwarder_validate_registration_payload')) {
    function warehouse_forwarder_validate_registration_payload(array $stockItem, array $requiredFields): array
    {
        $addons = warehouse_forwarder_decode_json_array((string)($stockItem['addons_json'] ?? ''));
        $clientId = warehouse_forwarder_extract_client_id($stockItem);
        $missing = [];
        foreach ($requiredFields as $field) {
            $field = trim((string)$field);
            if ($field === '') {
                continue;
            }
            $value = array_key_exists($field, $stockItem) ? trim((string)$stockItem[$field]) : trim((string)($addons[$field] ?? ''));
            if ($field === 'addons_json') {
                if (!$addons) {
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
}

if (!function_exists('warehouse_forwarder_required_fields_value_map')) {
    function warehouse_forwarder_required_fields_value_map(array $stockItem, array $requiredFields): array
    {
        $addons = warehouse_forwarder_decode_json_array((string)($stockItem['addons_json'] ?? ''));
        $map = [];
        foreach ($requiredFields as $field) {
            $field = trim((string)$field);
            if ($field === '') {
                continue;
            }
            $map[$field] = array_key_exists($field, $stockItem) ? (string)$stockItem[$field] : (string)($addons[$field] ?? '');
        }
        return $map;
    }
}

if (!function_exists('warehouse_forwarder_build_registration_payload')) {
    function warehouse_forwarder_build_registration_payload(array $stockItem): array
    {
        $addons = warehouse_forwarder_decode_json_array((string)($stockItem['addons_json'] ?? ''));
        $clientId = warehouse_forwarder_extract_client_id($stockItem);
        $tracking = trim((string)($stockItem['tracking_no'] ?? '')) ?: trim((string)($stockItem['tuid'] ?? ''));
        $category = trim((string)($addons['category'] ?? $addons['sub_category'] ?? 'general'));
        $countryCode = strtoupper(trim((string)($stockItem['receiver_country_code'] ?? '')));
        $destination = trim((string)($addons['forwarder_destination'] ?? $addons['destination_city'] ?? $addons['destination'] ?? ''));
        if ($destination === '') {
            $destination = $countryCode === 'AZ' ? 'Baku' : $countryCode;
        }
        $subCat = trim((string)($addons['sub_cat_text'] ?? $addons['tariff_type_text'] ?? $addons['tariff_type_name'] ?? $addons['sub_category'] ?? $addons['tariff_type'] ?? '')) ?: $category;
        $title = trim((string)($addons['title_text'] ?? $addons['title_name'] ?? $addons['item_title'] ?? $addons['product_name'] ?? $addons['title'] ?? $category));
        if ($title === '' || ctype_digit($title)) {
            $title = $category;
        }
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
            'status-id' => $clientId > 0 ? '37' : '36',
            'category' => $category,
            'description' => '',
            'sub-cat' => $subCat,
            'invoice' => trim((string)($addons['invoice'] ?? '0')),
            'position' => 'PSB010',
            'tracking-internal-same' => trim((string)($addons['tracking_internal_same'] ?? '0')),
            'tariff-type-id' => trim((string)($addons['tariff_type_id'] ?? $addons['tariff_type'] ?? '')),
            'is-legal-entity' => trim((string)($addons['is_legal_entity'] ?? 'off')),
            'invoice-status' => trim((string)($addons['invoice_status'] ?? '1')),
            'title' => $title,
            'seller' => trim((string)($addons['seller'] ?? '')),
            'container-id' => trim((string)($addons['container_id'] ?? '')),
            'total-images' => trim((string)($addons['total_images'] ?? '0')),
            'length' => trim((string)($addons['length'] ?? '')),
            'height' => trim((string)($addons['height'] ?? '')),
            'width' => trim((string)($addons['width'] ?? '')),
        ];
    }
}

if (!function_exists('warehouse_forwarder_exec_cli_script')) {
    function warehouse_forwarder_exec_cli_script(string $scriptName, array $args): array
    {
        $scriptPath = dirname(__DIR__, 2) . '/scripts/mvp/app/Forwarder/' . ltrim($scriptName, '/');
        if (!is_file($scriptPath)) {
            throw new RuntimeException('Не найден скрипт форвардера: ' . $scriptPath);
        }
        $cmdParts = ['php', $scriptPath];
        foreach ($args as $key => $value) {
            $key = trim((string)$key);
            if ($key !== '') {
                $cmdParts[] = '--' . $key . '=' . (string)$value;
            }
        }
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
        $lines = [];
        $exitCode = 0;
        @exec($cmd, $lines, $exitCode);
        $output = trim(implode("\n", $lines));
        $parsed = json_decode($output, true);
        if (!is_array($parsed)) {
            $parsed = ['status' => $exitCode === 0 ? 'ok' : 'error', 'message' => $output !== '' ? $output : 'Unknown CLI response'];
        }
        $parsed['_meta'] = ['script' => $scriptName, 'exit_code' => $exitCode, 'raw_output' => $output];
        return $parsed;
    }
}


if (!function_exists('warehouse_forwarder_exec_php_cli_script')) {
    function warehouse_forwarder_exec_php_cli_script(string $scriptPath, array $args): array
    {
        $root = dirname(__DIR__, 3);
        $fullPath = $scriptPath;
        if ($fullPath === '' || $fullPath[0] !== '/') {
            $fullPath = $root . '/' . ltrim($scriptPath, '/');
        }
        if (!is_file($fullPath)) {
            throw new RuntimeException('Не найден скрипт форвардера: ' . $fullPath);
        }
        $cmdParts = ['php', $fullPath];
        foreach ($args as $key => $value) {
            $key = trim((string)$key);
            if ($key !== '') {
                $cmdParts[] = '--' . $key . '=' . (string)$value;
            }
        }
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts)) . ' 2>&1';
        $lines = [];
        $exitCode = 0;
        @exec($cmd, $lines, $exitCode);
        $output = trim(implode("\n", $lines));
        $parsed = json_decode($output, true);
        if (!is_array($parsed)) {
            $parsed = ['status' => $exitCode === 0 ? 'ok' : 'error', 'message' => $output !== '' ? $output : 'Unknown CLI response'];
        }
        $parsed['_meta'] = ['script' => $scriptPath, 'exit_code' => $exitCode, 'raw_output' => $output];
        return warehouse_forwarder_mask_submitted_args_password($parsed);
    }
}

if (!function_exists('warehouse_forwarder_is_camex_az_connector')) {
    function warehouse_forwarder_is_camex_az_connector(array $stockItem, array $connector): bool
    {
        $receiverCompanyNorm = warehouse_forwarder_normalize_key((string)($stockItem['receiver_company'] ?? ''));
        $receiverCountry = strtoupper(trim((string)($stockItem['receiver_country_code'] ?? '')));
        $systemTypeNorm = warehouse_forwarder_normalize_key((string)($connector['system_type'] ?? ''));
        return ($receiverCompanyNorm === 'CAMEX' && $receiverCountry === 'AZ') || $systemTypeNorm === 'CAMEX_AZ';
    }
}

if (!function_exists('warehouse_forwarder_stock_numeric_value')) {
    function warehouse_forwarder_stock_numeric_value(array $stockItem, string $key, string $default = ''): string
    {
        $raw = str_replace(',', '.', trim((string)($stockItem[$key] ?? '')));
        if ($raw === '' || !is_numeric($raw)) {
            return $default;
        }
        return (string)(float)$raw;
    }
}

if (!function_exists('warehouse_forwarder_build_camex_az_args')) {
    function warehouse_forwarder_build_camex_az_args(array $stockItem, array $connector, string $context, int $stockItemId): array
    {
        $addons = warehouse_forwarder_decode_json_array((string)($stockItem['addons_json'] ?? ''));
        $tracking = trim((string)($stockItem['tracking_no'] ?? '')) ?: trim((string)($stockItem['tuid'] ?? ''));
        $invoiceCurrency = trim((string)($addons['invoice_ccy'] ?? $addons['invoice_currency'] ?? 'EUR')) ?: 'EUR';
        $args = [
            'connector-id' => (string)(int)($connector['id'] ?? 0),
            'prepare-mode' => 'client',
            'client-id' => (string)warehouse_forwarder_extract_client_id($stockItem),
            'tracking' => $tracking,
            'weight' => warehouse_forwarder_stock_numeric_value($stockItem, 'weight_kg'),
            'length' => warehouse_forwarder_stock_numeric_value($stockItem, 'size_l_cm', '0'),
            'width' => warehouse_forwarder_stock_numeric_value($stockItem, 'size_w_cm', '0'),
            'height' => warehouse_forwarder_stock_numeric_value($stockItem, 'size_h_cm', '0'),
            'box-code' => 'BOX1',
            'invoice-price' => '',
            'invoice-currency' => $invoiceCurrency,
            'shop' => 'amazon.de',
            'item-count' => '',
            'package-type-id' => trim((string)($addons['camex_package_type_id'] ?? $addons['package_type_id'] ?? '')),
            'comment' => '',
            'debug-dir' => '/tmp/camex_az_warehouse_manual_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $context) . '_' . $stockItemId,
            'dry-run' => '0',
            'confirm-submit' => '1',
        ];
        $flightNo = trim((string)($addons['camex_flight_no'] ?? $addons['flight_no'] ?? $addons['reisi'] ?? ''));
        if ($flightNo !== '') {
            $args['flight-no'] = $flightNo;
        }
        return $args;
    }
}

if (!function_exists('warehouse_forwarder_validate_camex_az_args')) {
    function warehouse_forwarder_validate_camex_az_args(array $stockItem, array $args): array
    {
        $missing = [];
        $receiverCompanyNorm = warehouse_forwarder_normalize_key((string)($stockItem['receiver_company'] ?? ''));
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
        } elseif ($missing !== []) {
            $message = 'CAMEX_AZ: не заполнены обязательные поля [' . implode(', ', $missing) . ']';
        }
        return [
            'ok' => $missing === [],
            'message' => $message,
            'required_fields' => ['tracking', 'client_id', 'weight', 'package_type_id', 'receiver_country_code', 'receiver_company'],
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
}

if (!function_exists('warehouse_forwarder_camex_az_registered_order')) {
    function warehouse_forwarder_camex_az_registered_order(array $result): array
    {
        $registeredOrder = $result['verify']['registered_order'] ?? $result['registered_order'] ?? [];
        return is_array($registeredOrder) ? $registeredOrder : [];
    }
}

if (!function_exists('warehouse_forwarder_update_table_registration_state')) {
    function warehouse_forwarder_update_table_registration_state(mysqli $dbcnx, string $table, string $whereColumn, int $itemId, ?string $registeredAt, string $status, string $message, string $responseJson): void
    {
        if (!warehouse_forwarder_table_exists($dbcnx, $table) || !warehouse_forwarder_column_exists($dbcnx, $table, $whereColumn)) {
            return;
        }
        $sets = [];
        $types = '';
        $params = [];
        foreach ([
            'forwarder_registered_at' => $registeredAt,
            'forwarder_registration_status' => $status,
            'forwarder_registration_message' => $message,
            'forwarder_registration_response_json' => $responseJson,
        ] as $column => $value) {
            if (warehouse_forwarder_column_exists($dbcnx, $table, $column)) {
                $sets[] = "`{$column}` = ?";
                $types .= 's';
                $params[] = $value;
            }
        }
        if (!$sets) {
            return;
        }
        $safeTable = str_replace('`', '``', $table);
        $safeWhere = str_replace('`', '``', $whereColumn);
        $sql = "UPDATE `{$safeTable}` SET " . implode(', ', $sets) . " WHERE `{$safeWhere}` = ?";
        $types .= 'i';
        $params[] = $itemId;
        $stmt = $dbcnx->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('warehouse_forwarder_update_registration_state')) {
    function warehouse_forwarder_update_registration_state(mysqli $dbcnx, int $stockItemId, string $status, string $message, array $rawResponse): ?string
    {
        warehouse_forwarder_ensure_stock_registration_columns($dbcnx);
        $rawResponse = warehouse_forwarder_mask_submitted_args_password($rawResponse);
        $responseJson = json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $registeredAt = strtolower($status) === 'ok' ? date('Y-m-d H:i:s') : null;
        warehouse_forwarder_update_table_registration_state($dbcnx, 'warehouse_item_stock', 'id', $stockItemId, $registeredAt, $status, $message, $responseJson);
        warehouse_forwarder_update_table_registration_state($dbcnx, 'warehouse_item_out', 'stock_item_id', $stockItemId, $registeredAt, $status, $message, $responseJson);
        return $registeredAt;
    }
}

if (!function_exists('warehouse_forwarder_sync_audit_log')) {
    function warehouse_forwarder_sync_audit_log(mysqli $dbcnx, array $data): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS warehouse_sync_audit ("
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
            . " item_id BIGINT UNSIGNED NOT NULL,"
            . " tracking_no VARCHAR(255) NOT NULL DEFAULT '',"
            . " forwarder VARCHAR(120) NOT NULL DEFAULT '',"
            . " country_code VARCHAR(16) NOT NULL DEFAULT '',"
            . " status VARCHAR(20) NOT NULL DEFAULT 'error',"
            . " message TEXT NULL,"
            . " response_json LONGTEXT NULL,"
            . " created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,"
            . " created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
            . " PRIMARY KEY (id), KEY idx_item_created (item_id, created_at), KEY idx_status_created (status, created_at)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $dbcnx->query($sql);
        $stmt = $dbcnx->prepare("INSERT INTO warehouse_sync_audit (item_id, tracking_no, forwarder, country_code, status, message, response_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $itemId = (int)($data['item_id'] ?? 0);
            $trackingNo = (string)($data['tracking_no'] ?? '');
            $forwarder = (string)($data['forwarder'] ?? '');
            $countryCode = (string)($data['country_code'] ?? '');
            $status = (string)($data['status'] ?? 'error');
            $message = (string)($data['message'] ?? '');
            $responseJson = (string)($data['response_json'] ?? '');
            $decodedResponse = json_decode($responseJson, true);
            if (is_array($decodedResponse)) {
                $responseJson = json_encode(warehouse_forwarder_mask_submitted_args_password($decodedResponse), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $responseJson;
            }
            $createdBy = (int)($data['created_by'] ?? 0);
            $stmt->bind_param('issssssi', $itemId, $trackingNo, $forwarder, $countryCode, $status, $message, $responseJson, $createdBy);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('warehouse_forwarder_load_stock_item')) {
    function warehouse_forwarder_load_stock_item(mysqli $dbcnx, int $stockItemId): ?array
    {
        warehouse_forwarder_ensure_stock_registration_columns($dbcnx);
        $stmt = $dbcnx->prepare("SELECT * FROM warehouse_item_stock WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $stockItemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('warehouse_forwarder_audit_manual')) {
    function warehouse_forwarder_audit_manual(int $userId, int $stockItemId, array $stockItem, string $status, string $message, array $result): void
    {
        if (!function_exists('audit_log')) {
            return;
        }
        audit_log(
            $userId,
            'WAREHOUSE_STOCK_FORWARDER_REGISTER',
            'WAREHOUSE_STOCK',
            $stockItemId,
            'Ручная регистрация посылки у форварда',
            [
                'source' => 'manual_button',
                'stock_item_id' => $stockItemId,
                'tracking_no' => (string)($stockItem['tracking_no'] ?? $stockItem['tuid'] ?? ''),
                'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                'registration_status' => $status,
                'message' => $message,
                'response' => warehouse_forwarder_mask_submitted_args_password($result),
            ]
        );
    }
}

if (!function_exists('warehouse_forwarder_register_stock_item')) {
    function warehouse_forwarder_register_stock_item(
        mysqli $dbcnx,
        int $stockItemId,
        int $userId,
        string $context = 'manual',
        bool $force = false
    ): array
    {
        $stockItem = warehouse_forwarder_load_stock_item($dbcnx, $stockItemId);
        if (!$stockItem) {
            return ['status' => 'error', 'message' => 'Посылка не найдена', 'item_id' => $stockItemId];
        }

        $track = trim((string)($stockItem['tracking_no'] ?? '')) ?: trim((string)($stockItem['tuid'] ?? ''));
        $alreadyStatus = strtolower(trim((string)($stockItem['forwarder_registration_status'] ?? '')));
        if ($alreadyStatus === 'ok' && !$force) {
            $result = [
                'status' => 'ok',
                'message' => 'Посылка уже зарегистрирована у форварда',
                'item_id' => $stockItemId,
                'tracking_no' => $track,
                'forwarder_registration_status' => 'ok',
                'forwarder_registered_at' => $stockItem['forwarder_registered_at'] ?? null,
                'raw_response' => [],
            ];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'ok', $result['message'], $result);
            return $result;
        }

        $isCamexAzStock = warehouse_forwarder_normalize_key((string)($stockItem['receiver_company'] ?? '')) === 'CAMEX'
            && strtoupper(trim((string)($stockItem['receiver_country_code'] ?? ''))) === 'AZ';
        $requiredFields = $isCamexAzStock ? [] : warehouse_forwarder_required_registration_fields($dbcnx, (string)($stockItem['receiver_company'] ?? ''));
        $missingFields = $requiredFields === [] ? [] : warehouse_forwarder_validate_registration_payload($stockItem, $requiredFields);
        $payloadPreview = warehouse_forwarder_build_registration_payload($stockItem);
        if ($missingFields) {
            $message = 'Не заполнены обязательные поля для регистрации у форварда: ' . implode(', ', $missingFields);
            $raw = [
                'registration_status' => 'validation_error',
                'missing_fields' => $missingFields,
                'required_fields' => $requiredFields,
                'required_field_values' => warehouse_forwarder_required_fields_value_map($stockItem, $requiredFields),
                'forwarder_args_preview' => $payloadPreview,
                'context' => $context,
            ];
            warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'validation_error', $message, $raw);
            warehouse_forwarder_sync_audit_log($dbcnx, [
                'item_id' => $stockItemId,
                'tracking_no' => $track,
                'forwarder' => (string)($stockItem['receiver_company'] ?? ''),
                'country_code' => (string)($stockItem['receiver_country_code'] ?? ''),
                'status' => 'error',
                'message' => 'manual_forwarder_registration: ' . $message,
                'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                'created_by' => $userId,
            ]);
            $result = ['status' => 'validation_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'validation_error', 'raw_response' => $raw];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'validation_error', $message, $result);
            return $result;
        }

        $connector = warehouse_forwarder_resolve_connector($dbcnx, (string)($stockItem['receiver_company'] ?? ''), (string)($stockItem['receiver_country_code'] ?? ''));
        if (!$connector) {
            $message = 'Не найден активный коннектор форвардера';
            $raw = ['registration_status' => 'connector_error', 'receiver_company' => (string)($stockItem['receiver_company'] ?? ''), 'context' => $context];
            warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'connector_error', $message, $raw);
            warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
            $result = ['status' => 'connector_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'connector_error', 'raw_response' => $raw];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'connector_error', $message, $result);
            return $result;
        }

        $baseUrl = trim((string)($connector['base_url'] ?? ''));
        $login = trim((string)($connector['auth_username'] ?? ''));
        $password = trim((string)($connector['auth_password'] ?? ''));
        if ($baseUrl === '' || $login === '' || $password === '') {
            $message = 'В коннекторе не заполнены base_url/login/password';
            $raw = ['registration_status' => 'connector_error', 'connector_id' => (int)($connector['id'] ?? 0), 'context' => $context];
            warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'connector_error', $message, $raw);
            warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
            $result = ['status' => 'connector_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'connector_error', 'raw_response' => $raw];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'connector_error', $message, $result);
            return $result;
        }

        if (warehouse_forwarder_is_camex_az_connector($stockItem, $connector)) {
            $camexArgs = [];
            try {
                $camexArgs = warehouse_forwarder_build_camex_az_args($stockItem, $connector, $context, $stockItemId);
                $requestedFlightNo = trim((string)($camexArgs['flight-no'] ?? ''));
                $validation = warehouse_forwarder_validate_camex_az_args($stockItem, $camexArgs);
                if (empty($validation['ok'])) {
                    $message = (string)($validation['message'] ?? 'CAMEX_AZ: validation_error');
                    $raw = [
                        'registration_status' => 'validation_error',
                        'connector_id' => (int)($connector['id'] ?? 0),
                        'connector_name' => (string)($connector['name'] ?? ''),
                        'stock_item_id' => $stockItemId,
                        'submitted_args' => $camexArgs,
                        'payload' => $camexArgs,
                        'args_preview' => $camexArgs,
                        'requested' => ['flight_no' => $requestedFlightNo],
                        'resolved' => ['flight_no' => ''],
                        'missing_fields' => $validation['missing_fields'] ?? [],
                        'required_fields' => $validation['required_fields'] ?? [],
                        'required_field_values' => $validation['required_field_values'] ?? [],
                        'context' => $context,
                    ];
                    warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'validation_error', $message, $raw);
                    warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
                    $result = ['status' => 'validation_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'validation_error', 'raw_response' => $raw];
                    warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'validation_error', $message, $result);
                    return $result;
                }

                $raw = warehouse_forwarder_exec_php_cli_script('www/scripts/mvp/app/CAMEX_AZ/run_prepare_package.php', $camexArgs);
                $registeredOrder = warehouse_forwarder_camex_az_registered_order($raw);
                $submitted = !empty($raw['submitted']);
                $alreadyRegistered = !empty($raw['already_registered']);
                $verifyTrackingFound = !empty($raw['verify']['tracking_found']);
                $resolvedFlightNo = trim((string)($raw['selected']['flight_no'] ?? $raw['payload_preview']['reisi'] ?? ''));
                $resultStatus = strtolower(trim((string)($raw['status'] ?? '')));
                $response = warehouse_forwarder_mask_submitted_args_password([
                    'submitted_args' => $camexArgs,
                    'payload' => $camexArgs,
                    'args_preview' => $camexArgs,
                    'result' => $raw,
                    'registered_order' => $registeredOrder,
                    'requested' => ['flight_no' => $requestedFlightNo],
                    'resolved' => ['flight_no' => $resolvedFlightNo],
                    'selected' => ['flight_no' => $resolvedFlightNo],
                    'debug_html' => $raw['debug_html'] ?? ($raw['debug']['html'] ?? null),
                    'connector_id' => (int)($connector['id'] ?? 0),
                    'connector_name' => (string)($connector['name'] ?? ''),
                    'stock_item_id' => $stockItemId,
                    'context' => $context,
                ]);
                $status = ($resultStatus === 'ok' && (($submitted && $verifyTrackingFound) || $alreadyRegistered)) ? 'ok' : 'forwarder_error';
                $message = $status === 'ok'
                    ? ($alreadyRegistered ? 'Уже зарегистрировано у CAMEX_AZ (идемпотентный успех)' : 'Успешно зарегистрировано у CAMEX_AZ')
                    : trim((string)($raw['message'] ?? $raw['error'] ?? 'CAMEX_AZ: ошибка регистрации'));
                $registeredAt = warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, $status, $message, $response);
                warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => $status === 'ok' ? 'success' : 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
                $result = ['status' => $status, 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => $status, 'forwarder_registered_at' => $registeredAt, 'raw_response' => $response];
                warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, $status, $message, $result);
                return $result;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $raw = ['exception' => $message, 'submitted_args' => $camexArgs, 'context' => $context];
                warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $message, $raw);
                warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
                $result = ['status' => 'forwarder_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'forwarder_error', 'raw_response' => $raw];
                warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'forwarder_error', $message, $result);
                return $result;
            }
        }

        $payload = warehouse_forwarder_build_registration_payload($stockItem);
        $forwarderArgs = array_merge(['base-url' => $baseUrl, 'login' => $login, 'password' => $password], $payload);
        try {
            $raw = warehouse_forwarder_exec_cli_script('run_add_package.php', $forwarderArgs);
            $resultStatus = strtolower(trim((string)($raw['status'] ?? '')));
            $rawText = strtolower((string)(($raw['_meta']['raw_output'] ?? '') . ' ' . ($raw['message'] ?? '') . ' ' . ($raw['error'] ?? '')));
            $alreadyExists = strpos($rawText, 'already') !== false || strpos($rawText, 'exists') !== false || strpos($rawText, 'уже') !== false;
            $raw['submitted_args'] = $forwarderArgs;
            $raw['payload'] = $payload;
            $raw['client_id_debug'] = warehouse_forwarder_client_id_debug($stockItem, (int)($payload['client-id'] ?? 0));
            $raw['manual_registration'] = [
                'force' => $force,
                'previous_registration_status' => $alreadyStatus,
                'context' => $context,
            ];
            $raw = warehouse_forwarder_mask_submitted_args_password($raw);
            $status = ($resultStatus === 'ok' || $alreadyExists) ? 'ok' : 'forwarder_error';
            $message = $status === 'ok'
                ? ($alreadyExists ? 'Уже зарегистрировано у форварда (идемпотентный успех)' : 'Успешно зарегистрировано у форварда')
                : trim((string)($raw['message'] ?? $raw['error'] ?? 'Ошибка регистрации у форварда'));
            $registeredAt = warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, $status, $message, $raw);
            warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => $status === 'ok' ? 'success' : 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
            $result = ['status' => $status, 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => $status, 'forwarder_registered_at' => $registeredAt, 'raw_response' => $raw];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, $status, $message, $result);
            return $result;
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $raw = ['exception' => $message, 'context' => $context];
            warehouse_forwarder_update_registration_state($dbcnx, $stockItemId, 'forwarder_error', $message, $raw);
            warehouse_forwarder_sync_audit_log($dbcnx, ['item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder' => (string)($stockItem['receiver_company'] ?? ''), 'country_code' => (string)($stockItem['receiver_country_code'] ?? ''), 'status' => 'error', 'message' => 'manual_forwarder_registration: ' . $message, 'response_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'created_by' => $userId]);
            $result = ['status' => 'forwarder_error', 'message' => $message, 'item_id' => $stockItemId, 'tracking_no' => $track, 'forwarder_registration_status' => 'forwarder_error', 'raw_response' => $raw];
            warehouse_forwarder_audit_manual($userId, $stockItemId, $stockItem, 'forwarder_error', $message, $result);
            return $result;
        }
    }
}
