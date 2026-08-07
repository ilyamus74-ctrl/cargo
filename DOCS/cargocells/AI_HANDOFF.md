# CargoCells — AI / инженерный handoff

**Назначение:** этот файл нужно читать в начале нового ChatGPT/Codex/LLM-сеанса перед изменением warehouse/forwarder/scanner логики.

## 1. Стартовый prompt для нового сеанса

Использовать примерно так:

> Работаем с `ilyamus74-ctrl/cargo`. Сначала прочитай `DOCS/cargocells/README.md`, затем `ARCHITECTURE.md`, `WAREHOUSE_FLOW.md`, `STATUS_MODEL.md`, `AUDIT_2026-08-07.md` и `ROADMAP.md`. Перед любым изменением проверь актуальный код `main`, потому что документация — baseline, а код мог измениться. Не делай широкий рефакторинг без отдельного согласования. Для статусов warehouse_item_out соблюдай единый transition contract и обновляй документацию/тесты вместе с кодом.

---

## 2. Что нужно знать за 2 минуты

CargoCells состоит из:

```text
Android OCRScanner APP
  -> WebView/Web UI
     -> CoreAPI
        -> Warehouse handlers
           -> DB
           -> Forwarder PHP MVP
              -> external forwarder
           -> report/reconcile
           -> outbound/flight/container
           -> label/print
```

APP получает scanner flow с сервера через `device-scan-config`. Backend остаётся владельцем warehouse business state.

---

## 3. Критичные файлы

### Routing

```text
www/core_api.php
```

### Warehouse

```text
www/api/warehouse/warehouse_item_in_actions.php
www/api/warehouse/warehouse_item_stock_actions.php
www/api/warehouse/warehouse_move.php
www/api/warehouse/warehouse_sync_actions.php
www/api/warehouse/warehouse_forwarder_sync_helpers.php
www/api/warehouse/warehouse_forwarder_registration_helpers.php
www/api/warehouse/warehouse_cells_actions.php
```

### Connectors

```text
www/api/connectors/connector_actions.php
www/api/connectors/connector_engine.php
www/api/connectors/subrunners/connector_modules.php
```

### Forwarder

```text
www/scripts/mvp/app/Forwarder/
www/scripts/mvp/app/Forwarder/forwarder_endpoints_contract.yaml
www/scripts/mvp/app/Forwarder/forwarder_php_migration_plan.md
```

### Background

```text
www/api/system/system_tasks_lib.php
www/scripts/cron/system_tasks_runner.php
DOCS/system_tasks_background.md
```

### Android

```text
APP/OCRScanner/src/main/java/com/example/ocrscannertest/MainActivity.kt
APP/OCRScanner/src/main/java/com/example/ocrscannertest/DeviceConfig.kt
APP/OCRScanner/src/main/java/com/example/ocrscannertest/SCANNER_STATE_NOTES.md
DOCS/native_flow_operation.md
DOCS/refactroing_flow_to_cotlin_RESUME.md
```

---

## 4. Критичный business contract

Нельзя трактовать эти значения одинаково:

```text
forwarder_registration_status
external report status
warehouse_item_out.status
```

Outbound happy path:

```text
for_sync
 -> half_sync
 -> confirmed_sync
 -> to_send
 -> sended
 -> success?  // terminal meaning ещё нужно формализовать
```

Shortcut:

```text
external report already Declared
 -> local to_send
```

Ключевой инвариант:

```text
confirmed_sync < to_send
```

`confirmed_sync` = регистрация/синхронизация подтверждена.  
`to_send` = готово к фактической outbound-операции.

---

## 5. Недавний важный исправленный дефект

В reconcile numeric rank раньше был:

```text
to_send=30
confirmed_sync=40
```

поэтому `confirmed_sync -> to_send` ошибочно блокировался как downgrade.

Baseline после исправления:

```text
for_sync=10
half_sync=20
error=20
confirmed_sync=25
to_send=30
sended=50
success=60
```

Не возвращать старый порядок.

Но numeric rank — временная реализация. Roadmap требует explicit transition matrix.

---

## 6. Как работает report mapping

Главный runtime mapping:

```text
connectors_addons.report_out_statuses_json
```

Он преобразует:

```text
external report status -> warehouse_item_out.status
```

`status_targets_json` — дополнительный/legacy routing.

Нельзя предполагать exact mapping ASER/CAMEX/COLIBRI без чтения production DB. Пример `Not declared -> confirmed_sync`, `Declared -> to_send` должен подтверждаться runtime config.

---

## 7. Как работает приёмка

`commit_item_in_batch`:

```text
warehouse_item_in
 -> warehouse_item_stock
 -> forwarder preregistration
 -> warehouse_item_stock.forwarder_registration_*
 -> audit
```

Успешная preregistration сама по себе не означает outbound readiness.

---

## 8. Как работает report import

`warehouse_forwarder_sync_helpers.php`:

```text
report row
 -> forwarder_report_items upsert
 -> warehouse_item_stock update/create
 -> position -> local cell mapping
 -> declared-to-out attempt
 -> audit
```

Если новая report-посылка уже Declared, helper может создать `warehouse_item_out:to_send`.

Если outbound row уже существует с другим status, helper её не переписывает; progression ожидается через reconcile.

---

## 9. Как работает outbound scan

Lookup:

```text
warehouse_item_out_lookup
```

Confirm:

```text
warehouse_item_out_confirm_send
```

Backend допускает confirm только из:

```text
to_send
sended
```

External side effect:

```text
run_add_package_to_container.php
```

Local result:

```text
sended + flight/container/shipment_cell
```

**Известный риск:** fast path может поставить local `sended` и только потом запустить external confirmation в background. См. `AUD-004`.

---

## 10. Как работать с кодом безопасно

Перед изменением:

1. fetch актуальный `main`;
2. найти все места, где используется изменяемый status/action/table/function;
3. сравнить с docs;
4. определить, изменение local, external или APP contract;
5. написать/обновить regression test;
6. сделать минимальный patch;
7. проверить syntax/static tests;
8. показать diff;
9. smoke test;
10. обновить docs, если контракт изменился.

---

## 11. Запреты для LLM-разработки

Без отдельного решения не делать:

- массовое переименование `sended` -> `sent` только ради красоты;
- удаление legacy Node flow до inventory активных connectors;
- изменение DB schema destructive SQL без dry-run/backup/migration plan;
- прямой `UPDATE warehouse_item_out.status` в новых местах;
- hard-code внешних ASER/CAMEX report statuses в warehouse core;
- перенос warehouse business rules в Android APP;
- изменение нескольких независимых слоёв одним большим commit;
- логирование passwords/tokens/cookies.

---

## 12. Формат хорошего изменения

Каждая задача должна отвечать на вопросы:

```text
Проблема:
Фактический текущий flow:
Ожидаемый contract:
Какие файлы меняем:
Какие таблицы/статусы затрагиваем:
Backward compatibility:
Тест до изменения:
Patch:
Тест после изменения:
Rollback:
Документация обновлена:
```

---

## 13. Приоритетные audit findings

Перед новыми большими feature нужно помнить:

```text
AUD-002  duplicated/stale state machine
AUD-003  reconcile starvation risk
AUD-004  local sended before external confirmation in fast path
AUD-005  undefined success semantics
AUD-006  error mixed into linear rank
AUD-007  duplicated forwarder registration flows
AUD-008  runtime DDL
AUD-009  APP P1 handler override
AUD-010  insecure TLS option
AUD-011  fake/local-only unenroll
```

Полный список: `AUDIT_2026-08-07.md`.

---

## 14. Ближайший порядок работ

```text
runtime production audit (read-only)
 -> regression tests
 -> one warehouse state machine
 -> reconcile v2
 -> outbound external-confirmation consistency
 -> registration service consolidation
 -> APP cleanup/hardening
 -> Forwarder adapters
```

Не перескакивать сразу к «перепишем Forwarder красиво»: сначала нужно закрепить business contract и защитить текущий рабочий складской pipeline.
