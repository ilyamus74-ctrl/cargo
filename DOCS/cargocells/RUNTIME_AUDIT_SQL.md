# CargoCells — runtime audit SQL (read-only)

**Назначение:** собрать фактическое состояние production без изменения данных.  
**Все запросы ниже:** `SELECT` / `SHOW`, без `UPDATE/DELETE/ALTER`.

> Выполнять в **MySQL/MariaDB-консоли production CargoCells** под пользователем с read-only правами, если такой доступ есть. Не публиковать passwords/tokens/cookies из таблицы `connectors`.

---

## 0. Контекст БД

```sql
SELECT NOW() AS db_now, @@hostname AS db_host, @@version AS db_version, DATABASE() AS db_name;
```

---

## 1. Connectors без секретов

```sql
SELECT
    id,
    name,
    countries,
    base_url,
    auth_type,
    is_active,
    is_test_connector,
    ssl_ignore,
    last_sync_at,
    last_success_at,
    LEFT(COALESCE(last_error, ''), 300) AS last_error
FROM connectors
ORDER BY id;
```

### Status mappings

```sql
SELECT
    c.id AS connector_id,
    c.name,
    c.countries,
    ca.status_targets_json,
    ca.report_out_statuses_json
FROM connectors c
LEFT JOIN connectors_addons ca ON ca.connector_id = c.id
ORDER BY c.id;
```

### Operation metadata без credentials

```sql
SELECT
    id,
    name,
    countries,
    JSON_VALID(operations_json) AS operations_json_valid,
    CHAR_LENGTH(COALESCE(operations_json, '')) AS operations_json_bytes
FROM connectors
ORDER BY id;
```

Если нужно увидеть `operations_json`, перед передачей результата убедиться, что внутри нет секретов.

---

## 2. Distribution `warehouse_item_out.status`

```sql
SELECT
    LOWER(TRIM(COALESCE(status, ''))) AS status,
    COUNT(*) AS cnt,
    MIN(COALESCE(status_updated_at, created_at)) AS oldest,
    MAX(COALESCE(status_updated_at, created_at)) AS newest
FROM warehouse_item_out
GROUP BY LOWER(TRIM(COALESCE(status, '')))
ORDER BY cnt DESC, status;
```

### Возраст backlog

```sql
SELECT
    LOWER(TRIM(status)) AS status,
    COUNT(*) AS cnt,
    TIMESTAMPDIFF(HOUR, MIN(COALESCE(status_updated_at, created_at)), NOW()) AS oldest_age_hours
FROM warehouse_item_out
WHERE LOWER(TRIM(status)) IN ('for_sync','half_sync','confirmed_sync','error')
GROUP BY LOWER(TRIM(status));
```

---

## 3. Первые строки, которые сейчас получит reconcile

```sql
SELECT
    id,
    stock_item_id,
    tracking_no,
    receiver_company,
    receiver_country_code,
    status,
    status_message,
    status_updated_at,
    created_at
FROM warehouse_item_out
WHERE status IN ('for_sync', 'confirmed_sync', 'half_sync', 'error', 'success', 'to_send')
ORDER BY status_updated_at ASC, id ASC
LIMIT 200;
```

Сохранить список `id`. Через 1–2 reconcile-run повторить запрос. Если почти весь список остаётся тем же и новые pending rows не заходят в окно — подтверждён `AUD-003`.

---

## 4. Stock без outbound row

```sql
SELECT COUNT(*) AS stock_without_out
FROM warehouse_item_stock wi
LEFT JOIN warehouse_item_out wo ON wo.stock_item_id = wi.id
WHERE wo.stock_item_id IS NULL;
```

Разбивка по forwarder:

```sql
SELECT
    UPPER(TRIM(COALESCE(wi.receiver_company, ''))) AS forwarder,
    UPPER(TRIM(COALESCE(wi.receiver_country_code, ''))) AS country,
    COUNT(*) AS cnt
FROM warehouse_item_stock wi
LEFT JOIN warehouse_item_out wo ON wo.stock_item_id = wi.id
WHERE wo.stock_item_id IS NULL
GROUP BY
    UPPER(TRIM(COALESCE(wi.receiver_company, ''))),
    UPPER(TRIM(COALESCE(wi.receiver_country_code, '')))
ORDER BY cnt DESC;
```

---

## 5. Outbound row без stock

```sql
SELECT COUNT(*) AS out_without_stock
FROM warehouse_item_out wo
LEFT JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
WHERE wi.id IS NULL;
```

Примеры:

```sql
SELECT
    wo.id,
    wo.stock_item_id,
    wo.tracking_no,
    wo.tuid,
    wo.status,
    wo.receiver_company,
    wo.receiver_country_code
FROM warehouse_item_out wo
LEFT JOIN warehouse_item_stock wi ON wi.id = wo.stock_item_id
WHERE wi.id IS NULL
ORDER BY wo.id DESC
LIMIT 100;
```

---

## 6. Duplicate tracking/TUID

### Stock tracking duplicates

```sql
SELECT
    UPPER(TRIM(tracking_no)) AS tracking_no,
    COUNT(*) AS cnt,
    GROUP_CONCAT(id ORDER BY id DESC) AS stock_ids
FROM warehouse_item_stock
WHERE TRIM(COALESCE(tracking_no, '')) <> ''
GROUP BY UPPER(TRIM(tracking_no))
HAVING COUNT(*) > 1
ORDER BY cnt DESC, tracking_no
LIMIT 200;
```

### Outbound tracking duplicates

```sql
SELECT
    UPPER(TRIM(tracking_no)) AS tracking_no,
    COUNT(*) AS cnt,
    GROUP_CONCAT(CONCAT(id, ':', status) ORDER BY id DESC) AS out_rows
FROM warehouse_item_out
WHERE TRIM(COALESCE(tracking_no, '')) <> ''
GROUP BY UPPER(TRIM(tracking_no))
HAVING COUNT(*) > 1
ORDER BY cnt DESC, tracking_no
LIMIT 200;
```

### TUID duplicates

```sql
SELECT
    UPPER(TRIM(tuid)) AS tuid,
    COUNT(*) AS cnt,
    GROUP_CONCAT(id ORDER BY id DESC) AS stock_ids
FROM warehouse_item_stock
WHERE TRIM(COALESCE(tuid, '')) <> ''
GROUP BY UPPER(TRIM(tuid))
HAVING COUNT(*) > 1
ORDER BY cnt DESC, tuid
LIMIT 200;
```

---

## 7. Outbound anomalies

### `sended` без контейнера/рейса

```sql
SELECT
    id,
    stock_item_id,
    tracking_no,
    status,
    shipped_flight_no,
    shipped_container_name,
    shipment_cell,
    status_updated_at
FROM warehouse_item_out
WHERE LOWER(TRIM(status)) = 'sended'
  AND (
      TRIM(COALESCE(shipped_container_name, '')) = ''
      OR TRIM(COALESCE(shipped_flight_no, '')) = ''
  )
ORDER BY status_updated_at DESC
LIMIT 200;
```

### `to_send` без forwarder/country

```sql
SELECT
    id,
    stock_item_id,
    tracking_no,
    receiver_company,
    receiver_country_code,
    status_message
FROM warehouse_item_out
WHERE LOWER(TRIM(status)) = 'to_send'
  AND (
      TRIM(COALESCE(receiver_company, '')) = ''
      OR TRIM(COALESCE(receiver_country_code, '')) = ''
  )
ORDER BY id DESC;
```

### label payload distribution

```sql
SELECT
    LOWER(TRIM(COALESCE(status, ''))) AS out_status,
    LOWER(TRIM(COALESCE(label_payload_status, ''))) AS label_status,
    COUNT(*) AS cnt
FROM warehouse_item_out
GROUP BY
    LOWER(TRIM(COALESCE(status, ''))),
    LOWER(TRIM(COALESCE(label_payload_status, '')))
ORDER BY out_status, cnt DESC;
```

---

## 8. Forwarder registration state

```sql
SELECT
    LOWER(TRIM(COALESCE(forwarder_registration_status, ''))) AS registration_status,
    UPPER(TRIM(COALESCE(receiver_company, ''))) AS forwarder,
    UPPER(TRIM(COALESCE(receiver_country_code, ''))) AS country,
    COUNT(*) AS cnt,
    MIN(forwarder_registered_at) AS oldest,
    MAX(forwarder_registered_at) AS newest
FROM warehouse_item_stock
GROUP BY
    LOWER(TRIM(COALESCE(forwarder_registration_status, ''))),
    UPPER(TRIM(COALESCE(receiver_company, ''))),
    UPPER(TRIM(COALESCE(receiver_country_code, '')))
ORDER BY cnt DESC;
```

Последние ошибки без sensitive response payload:

```sql
SELECT
    id,
    tracking_no,
    receiver_company,
    receiver_country_code,
    forwarder_registration_status,
    LEFT(COALESCE(forwarder_registration_message, ''), 500) AS message,
    forwarder_registered_at
FROM warehouse_item_stock
WHERE LOWER(TRIM(COALESCE(forwarder_registration_status, ''))) IN (
    'validation_error', 'connector_error', 'forwarder_error'
)
ORDER BY COALESCE(forwarder_registered_at, created_at) DESC
LIMIT 200;
```

---

## 9. System tasks

```sql
SELECT *
FROM system_tasks
ORDER BY id;
```

Последние runs:

```sql
SELECT *
FROM system_task_runs
ORDER BY id DESC
LIMIT 200;
```

Batch jobs:

```sql
SELECT
    status,
    COUNT(*) AS cnt,
    MIN(created_at) AS oldest,
    MAX(created_at) AS newest
FROM warehouse_sync_batch_jobs
GROUP BY status
ORDER BY cnt DESC;
```

Batch items:

```sql
SELECT
    status,
    COUNT(*) AS cnt
FROM warehouse_sync_batch_job_items
GROUP BY status
ORDER BY cnt DESC;
```

---

## 10. Audit / sync trace density

```sql
SELECT
    status,
    COUNT(*) AS cnt,
    MIN(created_at) AS oldest,
    MAX(created_at) AS newest
FROM warehouse_sync_audit
GROUP BY status
ORDER BY cnt DESC;
```

```sql
SELECT
    stage,
    decision,
    COUNT(*) AS cnt,
    MIN(created_at) AS oldest,
    MAX(created_at) AS newest
FROM warehouse_sync_trace
GROUP BY stage, decision
ORDER BY cnt DESC;
```

---

## 11. Indexes ключевых таблиц

```sql
SHOW INDEX FROM warehouse_item_stock;
SHOW INDEX FROM warehouse_item_out;
SHOW INDEX FROM warehouse_sync_audit;
SHOW INDEX FROM warehouse_sync_trace;
SHOW INDEX FROM system_tasks;
SHOW INDEX FROM warehouse_sync_batch_jobs;
SHOW INDEX FROM warehouse_sync_batch_job_items;
```

Для каждой active `connector_report_*` таблицы отдельно:

```sql
SHOW INDEX FROM connector_report_aser_az;
```

Имя заменить на фактическое. Нужен индекс по нормализованному tracking column; reliance только на `payload_json LIKE` — нежелательно.

---

## 12. Реальные report statuses

Для конкретной report table сначала посмотреть columns:

```sql
SHOW COLUMNS FROM connector_report_aser_az;
```

Если есть `status` и `tracking_no`:

```sql
SELECT
    TRIM(status) AS report_status,
    COUNT(*) AS cnt
FROM connector_report_aser_az
GROUP BY TRIM(status)
ORDER BY cnt DESC;
```

Список нужно сравнить с `connectors_addons.report_out_statuses_json`.

Целевой результат audit:

```text
external status seen in DB
 -> mapping exists?
 -> expected local state
 -> number of rows
 -> unknown/unmapped count
```

---

## 13. Диагностика одной посылки

Заменить `TRACK_HERE`:

```sql
SELECT *
FROM warehouse_item_stock
WHERE UPPER(TRIM(tracking_no)) = UPPER('TRACK_HERE')
   OR UPPER(TRIM(tuid)) = UPPER('TRACK_HERE')
ORDER BY id DESC;
```

```sql
SELECT *
FROM warehouse_item_out
WHERE UPPER(TRIM(tracking_no)) = UPPER('TRACK_HERE')
   OR UPPER(TRIM(tuid)) = UPPER('TRACK_HERE')
ORDER BY id DESC;
```

```sql
SELECT
    id,
    item_id,
    tracking_no,
    forwarder,
    country_code,
    status,
    message,
    created_at
FROM warehouse_sync_audit
WHERE UPPER(TRIM(tracking_no)) = UPPER('TRACK_HERE')
ORDER BY id;
```

```sql
SELECT
    id,
    item_id,
    connector_id,
    job_id,
    stage,
    policy_code,
    decision,
    reason,
    created_at
FROM warehouse_sync_trace
WHERE item_id IN (
    SELECT id
    FROM warehouse_item_stock
    WHERE UPPER(TRIM(tracking_no)) = UPPER('TRACK_HERE')
       OR UPPER(TRIM(tuid)) = UPPER('TRACK_HERE')
)
ORDER BY id;
```

После этого отдельно смотреть строку в соответствующей `connector_report_*` table.

---

## 14. Что прислать для Audit v2

Достаточно выводов разделов:

```text
0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12
```

Secrets из `connectors` не нужны. `auth_password`, `auth_token`, `auth_cookies` не выводить.
