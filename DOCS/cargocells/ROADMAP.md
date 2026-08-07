# CargoCells — дорожная карта стабилизации и развития

**Baseline:** 2026-08-07

Цель roadmap — не «переписать проект красиво», а получить систему, которую можно безопасно развивать небольшими изменениями и быстро диагностировать вместе с LLM.

## Принцип порядка работ

```text
зафиксировать контракт
    -> измерить production
        -> покрыть критичный flow тестами
            -> убрать расхождения state machine
                -> стабилизировать external side effects
                    -> только потом архитектурный refactor
```

---

# Phase 0 — Documentation baseline

**Статус:** DONE v1

Сделано:

- архитектурная карта;
- warehouse lifecycle;
- status model;
- Forwarder MVP map;
- code audit;
- AI handoff contract.

Definition of Done:

- новый инженер/LLM может за 10–15 минут понять основные entrypoints;
- смысл `confirmed_sync` и `to_send` больше не определяется догадкой;
- найденные риски имеют ID и приоритет.

---

# Phase 1 — Production runtime inventory без изменения данных

**Приоритет:** немедленно после baseline docs.

## 1.1 Connector inventory

Снять read-only snapshot:

```text
connectors
connectors_addons
operations_json metadata
```

Нужны:

- connector id/name/country/active;
- report table naming;
- `report_out_statuses_json`;
- `status_targets_json`;
- operation IDs/kinds;
- не выводить passwords/tokens/cookies.

## 1.2 Status inventory

Посчитать:

```text
warehouse_item_out GROUP BY status
oldest/newest status_updated_at
stock without out
out without stock
duplicate tracking/tuid
```

## 1.3 Reconcile inventory

Проверить, не зацикливается ли worker на одних и тех же старых `LIMIT 200` rows.

## 1.4 Background inventory

Проверить:

- active system tasks;
- last run/result;
- cron;
- batch backlog.

## 1.5 Report status inventory

Собрать реальные external statuses по каждому connector/country и сравнить с mapping.

**DoD:** `AUDIT_2026-08-07.md` дополнен runtime section с фактами и SQL output summary.

---

# Phase 2 — Regression safety net

До изменения state machine создать минимальный набор автоматических тестов.

## 2.1 Status resolver tests

Обязательные кейсы:

```text
Not declared -> confirmed_sync
confirmed_sync + Declared -> to_send
to_send + old Not declared -> stays to_send
unknown external status -> no automatic to_send
error mapping -> explicit expected behavior
```

## 2.2 Forwarder import fixtures

Fixture report rows:

- новая Declared посылка;
- новая Not declared;
- существующая stock item;
- существующая outbound item;
- изменившаяся position/cell;
- duplicate report.

## 2.3 Outbound API tests

```text
confirmed_sync -> confirm denied
to_send -> confirm allowed
missing container -> denied
external failure -> expected local state
repeat scan -> idempotent result
```

## 2.4 APP contract smoke

Минимум:

- `device-scan-config` parse;
- active_context;
- hardware button mapping;
- warehouse-item-out scan field;
- browser fallback.

**DoD:** hotfix типа старой rank-ошибки невозможно слить без красного теста.

---

# Phase 3 — Единая warehouse outbound state machine

## 3.1 Ввести constants/enum-like contract

Например:

```text
WarehouseOutStatus
WarehouseOutTransitionPolicy
```

Не обязательно использовать PHP enum, если версия PHP/совместимость мешает. Важно одно место истины.

## 3.2 Explicit transition matrix

Убрать numeric rank как business decision.

Transition service должен получать:

```text
item_id
from
to
source
reason
external_status
correlation_id
```

## 3.3 Перевести на него

- reconcile;
- report import;
- manual sync;
- outbound confirm;
- будущий finalization.

## 3.4 Исправить `process_definition.states`

Он должен генерироваться из той же state machine, а не содержать отдельный hard-code.

**DoD:** runtime, helper/UI и tests используют один transition contract.

---

# Phase 4 — Reconcile v2

## 4.1 Fair scheduling

Убрать starvation из `ORDER BY status_updated_at ASC LIMIT N`.

Варианты:

- `last_reconciled_at`;
- cursor by id/time;
- очередь ожидающих reconcile;
- status-specific workers.

## 4.2 Отделить terminal/ready states

Reconcile не должен бесконечно перечитывать `success`, если в этом нет business need.

Для `to_send` также определить: есть ли причины повторно проверять внешний report или readiness уже фиксируется.

## 4.3 Error classification

```text
retryable
permanent
external_stale
validation
configuration
```

## 4.4 Metrics

Каждый run:

```text
selected
changed
noop
not_found
unknown_status
retryable_error
permanent_error
downgrade_blocked
age_of_oldest_pending
```

**DoD:** ни одна старая noop row не может бесконечно блокировать новые записи.

---

# Phase 5 — Outbound transaction / external confirmation

Это самый важный consistency refactor после state machine.

## 5.1 Убрать fire-and-forget без контроля

Текущий `exec(... &)` должен быть заменён managed job/outbox.

## 5.2 Выбрать модель

Предпочтительно:

```text
to_send
 -> external_pending
 -> external_confirmed
 -> sended
```

Либо оставить `warehouse_item_out.status` проще и добавить:

```text
external_send_status
external_send_attempts
external_send_last_error
external_send_confirmed_at
```

## 5.3 Idempotency

Key минимум:

```text
connector_id + tracking + flight + container + operation
```

## 5.4 Printing

Отделить:

```text
external container confirmation
local state transition
label render
label print
```

И явно решить порядок/compensation для каждого сбоя.

**DoD:** нельзя получить «локально sended, у forwarder не добавлено» без видимого pending/error state и retry job.

---

# Phase 6 — Registration service consolidation

Объединить повторяющуюся регистрацию из:

```text
commit_item_in_batch
warehouse_sync_item
stock manual registration
```

в единый service.

Service responsibilities:

- resolve connector/adapter;
- validate required fields;
- build normalized DTO;
- execute create/find;
- idempotency;
- verify;
- classify errors;
- persist registration state;
- audit.

Entry handlers должны отличаться только source/context.

---

# Phase 7 — APP stabilization

## 7.1 Hardware button ownership

Убрать конфликтующий безусловный P1 override.

## 7.2 Разделить `MainActivity.kt`

Целевая декомпозиция:

```text
ScannerController
HardwareKeyRouter
WebViewBridge
FlowEngine
EnrollmentClient
OcrController
AppSettings
```

## 7.3 TLS

`allowInsecureSsl` только debug/dev build.

## 7.4 Real unenroll

Реальный server revoke.

## 7.5 Formalize `device-scan-config` schema

Версионировать:

```json
{
  "schema_version": 1,
  "task_id": "...",
  "contexts": {},
  "buttons": {},
  "flow": {},
  "api": {},
  "ui": {}
}
```

**DoD:** server-driven flow остаётся главным, APP не содержит page-specific special case без documented reason.

---

# Phase 8 — Forwarder Core + adapters

Только после стабилизации domain state.

## 8.1 Зафиксировать operation codes

Например:

```text
package.create
package.lookup
package.report.single
package.add_to_container
flight.list
flight.create
flight.update
flight.close
container.list
container.create
container.delete
container.sync
report.all_packages
```

## 8.2 Adapter interface

```text
ForwarderAdapterInterface
```

Adapters: ASER/COLIBRI-like, CAMEX_AZ, остальные по мере необходимости.

## 8.3 Thin runners

`run_*.php` остаются CLI compatibility entrypoints, но вызывают service/adapter.

## 8.4 Legacy browser path

После inventory пометить deprecated и удалить только когда нет активного connector, зависящего от него.

---

# Phase 9 — DB migrations и schema governance

## 9.1 Versioned migrations

Создание/изменение таблиц больше не выполняется во время обычного пользовательского request.

## 9.2 Constraints/indexes

Проверить:

- unique identity strategy tracking/TUID;
- indexes report lookup;
- status/reconcile indexes;
- foreign-key-like consistency даже если реальные FK не используются.

## 9.3 Data repair scripts

Каждый repair:

```text
dry-run -> report -> backup/snapshot -> apply -> verification
```

---

# Phase 10 — Observability и эксплуатационная документация

## Correlation ID

Один ID проходит:

```text
APP request
CoreAPI
warehouse audit
forwarder runner
system task
print job
```

## Parcel timeline

В UI должна быть одна история:

```text
accepted
stock created
forwarder registered
report state changed
outbound ready
container selected
external add confirmed
label printed
sent/finalized
```

## Operational dashboard

Минимум:

- pending sync;
- reconcile backlog;
- unknown report statuses;
- external send errors;
- print errors;
- mismatched container counts/weight.

---

# Рабочий порядок ближайших задач

Не начинать Phase 8 «красивый Forwarder refactor» сейчас. Ближайшая последовательность:

```text
1. Runtime audit
2. Regression tests
3. State machine single source of truth
4. Reconcile starvation fix
5. Outbound external-confirmation consistency
6. Registration service
7. APP cleanup/hardening
8. Forwarder adapters
```

Так мы сначала защищаем работающий складской pipeline, а уже потом уменьшаем технический долг.
