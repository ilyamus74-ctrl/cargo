<?php

declare(strict_types=1);

function connectors_subrunners_registry(): array
{
    return [
        'flight_list_colibri' => 'connectors_subrunner_run_flight_list_colibri',
        'flight_list_dev_colibri' => 'connectors_subrunner_run_flight_list_colibri',
    ];
}

function connectors_subrunners_run(string $name, array $ctx, array $options = []): array
{
    $name = trim($name);
    $registry = connectors_subrunners_registry();
    $callable = $registry[$name] ?? null;

    if (!$callable || !function_exists($callable)) {
        throw new InvalidArgumentException('Subrunner не найден в реестре: ' . $name);
    }

    $result = $callable($ctx, $options);
    if (!is_array($result)) {
        throw new RuntimeException('Subrunner вернул некорректный результат: ' . $name);
    }

    return array_merge([
        'status' => 'ok',
        'message' => 'Subrunner выполнен',
        'metrics' => [
            'rows_extracted' => 0,
            'rows_written' => 0,
            'rows_skipped' => 0,
        ],
        'errors' => [],
    ], $result);
}

function connectors_subrunner_resolve_html(array $ctx, array $options): string
{
    $fromContext = trim((string)($ctx['browser']['final_html'] ?? ''));
    if ($fromContext !== '') {
        return $fromContext;
    }

    $finalHtmlPath = trim((string)($ctx['browser']['final_html_path'] ?? ''));
    if ($finalHtmlPath !== '' && is_file($finalHtmlPath)) {
        return (string)file_get_contents($finalHtmlPath);
    }

    $artifactsDir = trim((string)($ctx['browser']['artifacts_dir'] ?? ''));
    if ($artifactsDir !== '') {
        $candidate = rtrim($artifactsDir, '/\\') . DIRECTORY_SEPARATOR . 'final_page.html';
        if (is_file($candidate)) {
            return (string)file_get_contents($candidate);
        }
    }

    $explicit = trim((string)($options['html'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    throw new RuntimeException('Subrunner: HTML страницы не найден (ожидался final_page.html)');
}

function connectors_subrunner_run_flight_list_colibri(array $ctx, array $options): array
{
    $connector = isset($ctx['connector']) && is_array($ctx['connector']) ? $ctx['connector'] : [];
    $connectorId = (int)($ctx['connector_id'] ?? 0);
    if ($connectorId <= 0) {
        throw new InvalidArgumentException('Subrunner flight_list_colibri: некорректный connector_id');
    }

    $db = $GLOBALS['dbcnx'] ?? null;
    if (!($db instanceof mysqli)) {
        throw new RuntimeException('Subrunner flight_list_colibri: mysqli соединение недоступно');
    }

    $html = connectors_subrunner_resolve_html($ctx, $options);

    $tableSelector = trim((string)($options['table_selector'] ?? '#flights_table'));
    $timezone = trim((string)($options['timezone'] ?? 'UTC'));
    $writeMode = strtolower(trim((string)($options['write_mode'] ?? 'upsert')));
    if (!in_array($writeMode, ['upsert', 'insert'], true)) {
        $writeMode = 'upsert';
    }

    $rows = connectors_subrunner_extract_table_rows($html, $tableSelector);
    $tableName = connectors_subrunner_resolve_flight_table_name($connector, $options);
    connectors_subrunner_ensure_flight_table($db, $tableName);

    $written = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $rowIndex => $row) {
        try {
            $normalized = connectors_subrunner_normalize_flight_row($row, $timezone, $rowIndex + 1);
            if ($normalized === null) {
                $skipped++;
                continue;
            }

            if ($writeMode === 'insert') {
                connectors_subrunner_insert_flight_row($db, $tableName, $connectorId, $normalized);
            } else {
                connectors_subrunner_upsert_flight_row($db, $tableName, $connectorId, $normalized);
            }
            $written++;
        } catch (Throwable $e) {
            $errors[] = [
                'row' => $rowIndex + 1,
                'message' => $e->getMessage(),
            ];
        }
    }

    return [
        'status' => empty($errors) ? 'ok' : 'error',
        'message' => empty($errors)
            ? 'Flight-list Colibri: успешно обработано строк: ' . $written
            : 'Flight-list Colibri: завершено с ошибками',
        'metrics' => [
            'rows_extracted' => count($rows),
            'rows_written' => $written,
            'rows_skipped' => $skipped,
        ],
        'errors' => $errors,
        'meta' => [
            'table_name' => $tableName,
            'connector_id' => $connectorId,
            'table_selector' => $tableSelector,
            'timezone' => $timezone,
            'write_mode' => $writeMode,
            'connector_name' => (string)($connector['name'] ?? ''),
        ],
    ];
}

function connectors_subrunner_extract_table_rows(string $html, string $tableSelector): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tableXpath = connector_engine_css_to_xpath($tableSelector);
    $tables = $xpath->query($tableXpath);
    if (!$tables || $tables->length === 0) {
        throw new RuntimeException('Subrunner flight_list_colibri: таблица не найдена по селектору ' . $tableSelector);
    }

    $table = $tables->item(0);
    if (!$table) {
        return [];
    }

    $rows = [];
    foreach ($xpath->query('.//tr', $table) ?: [] as $tr) {
        $cols = [];
        $cellNodes = $xpath->query('./th|./td', $tr);
        if (!$cellNodes) {
            continue;
        }
        foreach ($cellNodes as $cell) {
            $cols[] = trim(preg_replace('/\s+/u', ' ', (string)$cell->textContent) ?? '');
        }

        if (!empty($cols)) {
            $rows[] = $cols;
        }
    }

    if (!empty($rows) && connectors_subrunner_row_looks_like_header($rows[0])) {
        array_shift($rows);
    }

    return $rows;
}

function connectors_subrunner_row_looks_like_header(array $row): bool
{
    $joined = mb_strtolower(implode(' ', $row));
    foreach (['flight', 'рейс', 'номер', 'дата'] as $marker) {
        if (mb_strpos($joined, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function connectors_subrunner_normalize_flight_row(array $row, string $timezone, int $rowNo): ?array
{
    $flightNo = trim((string)($row[0] ?? ''));
    $departureDateRaw = trim((string)($row[1] ?? ''));
    $route = trim((string)($row[2] ?? ''));
    $status = trim((string)($row[3] ?? ''));

    if ($flightNo === '' && $departureDateRaw === '' && $route === '' && $status === '') {
        return null;
    }

    if ($flightNo === '') {
        throw new RuntimeException('Пустой номер рейса');
    }

    $departureAt = null;
    if ($departureDateRaw !== '') {
        $departureAt = connectors_subrunner_parse_datetime($departureDateRaw, $timezone);
    }

    return [
        'flight_no' => $flightNo,
        'departure_at' => $departureAt,
        'departure_raw' => $departureDateRaw,
        'route' => $route,
        'status' => $status,
        'raw_json' => json_encode(['row_no' => $rowNo, 'cells' => $row], JSON_UNESCAPED_UNICODE),
    ];
}

function connectors_subrunner_parse_datetime(string $value, string $timezone): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $tz = new DateTimeZone($timezone ?: 'UTC');
    $formats = ['d.m.Y H:i', 'd.m.Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d H:i:s');
    }

    return null;
}


function connectors_subrunner_resolve_flight_table_name(array $connector, array $options): string
{
    $explicit = trim((string)($options['table_name'] ?? ''));
    if ($explicit !== '') {
        return connectors_subrunner_sanitize_table_name($explicit);
    }

    $connectorCode = trim((string)($options['connector_code'] ?? ''));
    if ($connectorCode === '') {
        $connectorCode = trim((string)($connector['name'] ?? ''));
    }

    if ($connectorCode === '') {
        $connectorCode = 'unknown';
    }

    $connectorCode = strtolower($connectorCode);
    $connectorCode = preg_replace('/[^a-z0-9]+/', '_', $connectorCode) ?? $connectorCode;
    $connectorCode = trim($connectorCode, '_');
    if ($connectorCode == '') {
        $connectorCode = 'unknown';
    }

    return 'connector_' . $connectorCode . '_operation_flight_list';
}

function connectors_subrunner_sanitize_table_name(string $tableName): string
{
    $tableName = strtolower(trim($tableName));
    $tableName = preg_replace('/[^a-z0-9_]+/', '_', $tableName) ?? $tableName;
    $tableName = trim($tableName, '_');

    if ($tableName === '') {
        throw new InvalidArgumentException('Subrunner flight_list_colibri: table_name пустой после нормализации');
    }

    return $tableName;
}

function connectors_subrunner_ensure_flight_table(mysqli $db, string $tableName): void
{
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = "
        CREATE TABLE IF NOT EXISTS {$safeTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            connector_id INT UNSIGNED NOT NULL,
            flight_no VARCHAR(128) NOT NULL,
            departure_at DATETIME NULL,
            departure_raw VARCHAR(128) NOT NULL DEFAULT '',
            route VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(128) NOT NULL DEFAULT '',
            raw_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_connector_flight_departure (connector_id, flight_no, departure_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$db->query($sql)) {
        throw new RuntimeException('Не удалось создать таблицу flight_list: ' . $db->error);
    }
}

function connectors_subrunner_upsert_flight_row(mysqli $db, string $tableName, int $connectorId, array $row): void
{
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = "
        INSERT INTO {$safeTable}
            (connector_id, flight_no, departure_at, departure_raw, route, status, raw_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            departure_raw = VALUES(departure_raw),
            route = VALUES(route),
            status = VALUES(status),
            raw_json = VALUES(raw_json)
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (upsert flight row): ' . $db->error);
    }

    $departureAt = $row['departure_at'] ?? null;
    $stmt->bind_param(
        'issssss',
        $connectorId,
        $row['flight_no'],
        $departureAt,
        $row['departure_raw'],
        $row['route'],
        $row['status'],
        $row['raw_json']
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (upsert flight row): ' . $err);
    }

    $stmt->close();
}

function connectors_subrunner_insert_flight_row(mysqli $db, string $tableName, int $connectorId, array $row): void
{
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $sql = "
        INSERT INTO {$safeTable}
            (connector_id, flight_no, departure_at, departure_raw, route, status, raw_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('DB prepare error (insert flight row): ' . $db->error);
    }

    $departureAt = $row['departure_at'] ?? null;
    $stmt->bind_param(
        'issssss',
        $connectorId,
        $row['flight_no'],
        $departureAt,
        $row['departure_raw'],
        $row['route'],
        $row['status'],
        $row['raw_json']
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('DB execute error (insert flight row): ' . $err);
    }

    $stmt->close();
}