# Native flow refactor checklist — пункт 4 (движок в APP)

Дата: 2026-02-04

## 4) Движок в APP (чтение `device-scan-config`)

### 4.1. Чтение `device-scan-config` из WebView
- В WebView инжектится `INSTALL_MAIN_OBSERVER_JS`: скрипт читает `#device-scan-config` (и вспомогательные блоки `ocr-templates`, `ocr-templates-destcountry`, `ocr-dicts`) и пушит JSON через `DeviceApp.onMainContext`.
- `DeviceBridge.onMainContext` принимает payload, чистит значения от `null/undefined` и прокидывает `taskJson` в `onContextUpdated`, где обновляется `taskConfig` и синхронизируется активный flow.
- Обновления контекста инициируются через `MutationObserver` по `#main`/`body`, чтобы перечитывать JSON при смене DOM.

### 4.2. Парсер и структура state machine
- `parseScanTaskConfig` собирает `ScanTaskConfig`: `task_id`, `default_mode`, `modes`, `contexts`, `active_context`, `buttons`, `flow`, `api`, `ui`.
- `parseFlowConfig` + `parseFlowOp` парсят flow (`start`, `steps`, `next_on_scan`, `on_action`, `barcode/qr` действия) и операции `FlowOp` (`open_scanner`, `web`, `set_step`, `noop`, `web_if`).
- Поддерживается flow на уровне контекста (`contexts.<key>.flow`) и глобальный flow (`flow` в корне).

### 4.3. Обработка скан-результатов и переходы
- При скане Barcode/QR определяется активный контекст, выбирается `ScanAction` из шага (или из контекста как fallback), выполняется `handleWarehouseMoveScanResult` либо `fillBarcodeUsingTemplate`.
- После обработки скана применяется `next_on_scan` (через `setFlowStep`), что синхронизирует `currentFlowStep` и состояние `warehouseScanStep` для `warehouse_in`.
- OCR-скан завершает заполнение формы, затем запускает `next_on_scan` из context/global flow (или стандартный переход `MEASURE` для `warehouse_in` при отсутствии flow).

### 4.4. Кнопки/события и `on_action`
- `buttons` из конфигурации мапят события (vol-keys) на `on_action` в flow.
- `dispatchContextFlowAction` предпочтительно исполняет контекстный flow, иначе делегирует в `dispatchButtonAction`.
- `executeFlowActionsInContext` выполняет `FlowOp` в контексте: сканер, web-операции, смена шага, `web_if`.

## Отметка по чеклисту
- [x] Пункт 4 (движок в APP) — описан: чтение JSON, парсер, state machine, обработка сканов и кнопок.
