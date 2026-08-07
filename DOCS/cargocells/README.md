# CargoCells — инженерная документация

**Статус:** baseline v1  
**Дата:** 2026-08-07  
**Ветка:** `main`

Этот каталог — точка входа для дальнейшей разработки CargoCells человеком и LLM. Его задача — зафиксировать не только «что делает код», но и **где находится ответственность, какие статусы что означают, где проходят границы между APP/Web/CoreAPI/Forwarder и какие технические долги нельзя забыть при следующих изменениях**.

## Область текущего аудита

Baseline v1 покрывает рабочий контур:

`Android scanner APP -> WebView/Web -> CoreAPI -> Warehouse -> Forwarder MVP -> report/import/reconcile -> outbound -> container/flight -> label/print`.

Аудит выполнен по коду репозитория. Он **не заменяет runtime-аудит production DB**, фактических значений `connectors`, `connectors_addons`, cron/system tasks, очередей и внешних ответов форвардеров. Runtime-аудит выделен отдельным этапом roadmap.

## Читать в таком порядке

1. [`ARCHITECTURE.md`](ARCHITECTURE.md) — слои системы и ответственность модулей.
2. [`WAREHOUSE_FLOW.md`](WAREHOUSE_FLOW.md) — жизненный цикл посылки от приёмки до отгрузки.
3. [`STATUS_MODEL.md`](STATUS_MODEL.md) — локальные статусы, внешние статусы и разрешённые переходы.
4. [`FORWARDER_MVP.md`](FORWARDER_MVP.md) — PHP Forwarder MVP, runners, отчёты, рейсы и контейнеры.
5. [`AUDIT_2026-08-07.md`](AUDIT_2026-08-07.md) — найденные риски и несогласованности.
6. [`RUNTIME_AUDIT_SQL.md`](RUNTIME_AUDIT_SQL.md) — безопасный read-only сбор фактов с production DB для Audit v2.
7. [`ROADMAP.md`](ROADMAP.md) — порядок дальнейших работ без опасного «рефакторинга всего сразу».
8. [`AI_HANDOFF.md`](AI_HANDOFF.md) — краткий контракт для нового чата/LLM-сеанса.

## Главные исходные файлы

| Область | Основные файлы |
|---|---|
| HTTP/API routing | `www/core_api.php` |
| Приёмка | `www/api/warehouse/warehouse_item_in_actions.php` |
| Склад/карточка | `www/api/warehouse/warehouse_item_stock_actions.php` |
| Синхронизация и отгрузка | `www/api/warehouse/warehouse_sync_actions.php` |
| Импорт данных форварда | `www/api/warehouse/warehouse_forwarder_sync_helpers.php` |
| Коннекторы/config | `www/api/connectors/connector_actions.php` |
| Background jobs | `www/api/system/system_tasks_lib.php`, `www/scripts/cron/system_tasks_runner.php` |
| Forwarder MVP | `www/scripts/mvp/app/Forwarder/` |
| Endpoint contract | `www/scripts/mvp/app/Forwarder/forwarder_endpoints_contract.yaml` |
| Android scanner APP | `APP/OCRScanner/` |
| Native flow notes | `DOCS/native_flow_operation.md`, `DOCS/refactroing_flow_to_cotlin_RESUME.md` |

## Правила документации

1. **Код и production-конфигурация — фактическая реальность.** Если документация расходится с текущим кодом, сначала фиксируем расхождение, затем обновляем код/документ осознанно.
2. Любое изменение `warehouse_item_out.status` или его переходов требует обновить `STATUS_MODEL.md` и тесты переходов.
3. Любой новый внешний статус форвардера должен проходить через явный connector mapping; нельзя размазывать строки статусов по разным PHP-файлам.
4. Android APP не должен становиться вторым warehouse-backend. Его задача — scanner/UI bridge и выполнение серверного flow; бизнес-состояние хранится и проверяется на сервере.
5. Новый форвардер должен добавляться через контракт/adapter, а не копированием всей папки `Forwarder`.
6. Изменение production flow делается маленькими проверяемыми шагами: **наблюдение -> контракт -> тест -> изменение -> smoke test -> документация**.

## Текущий зафиксированный инвариант outbound

`confirmed_sync` означает, что регистрация/синхронизация подтверждена, но посылка ещё не обязана быть готова к отгрузке. `to_send` означает, что она готова к outbound-операциям. Поэтому:

```text
confirmed_sync < to_send < sended
```

Временный числовой rank в коде должен соответствовать этому порядку. В долгосрочной архитектуре числовой rank планируется заменить на явную таблицу разрешённых переходов.
