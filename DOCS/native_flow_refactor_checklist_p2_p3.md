# Native flow refactor checklist — пункты 2 и 3 (операции и маппинг)

Дата: 2026-02-04

## 2) Стандартные операции “движка” (минимальный набор)

### 2.1. Операции flow-движка (FlowOp)
Поддерживаемые операции, которые парсятся из `device-scan-config` и выполняются в APP:
- `open_scanner(mode)` — открыть сканер в режиме `barcode/qr/ocr`.
- `web(name)` — вызвать веб-операцию (JS-функцию) либо встроенный хендлер в APP.
- `set_step(step_id)` — сменить активный шаг.
- `noop` — не выполнять действия.
- `web_if(cond, then, else)` — условная операция (например, `stand_selected`).

Основание: перечисление типов `FlowOp` и парсер операций `parseFlowOp`, плюс обработчик `executeFlowOps` в APP. Эти операции уже поддерживаются движком и используются в текущих flow. 

### 2.2. Обработка результата скана (ScanAction)
Минимальные action-типы, используемые в `device-scan-config` и обрабатываемые приложением:
- `fill_field` — проставить значение в поля по `field_id/field_ids` или `field_name/field_names` (с генерацией `input/change`).
- `api_check` — выполнить API-проверку (`/api/qr_check.php`) и применить результат (например, селект ячейки).
- `web_callback` — вызвать JS-функцию с результатом скана (callback в `window`).

Основание: ветки обработки `fill_field/api_check/web_callback` в `handleScanResult` и `handleWarehouseMoveScanResult`.

## 3) Маппинг “web‑действий” на нативные операции

### 3.1. Warehouse Move (`task_id: warehouse_move`)
| Web-действие | Нативная операция (DOM) | Примечание |
| --- | --- | --- |
| `openMoveModal` | клик по `.js-core-link[data-core-action="warehouse_move_open_modal"]` внутри `#warehouse-move-results-tbody` | В JS-реализации запускается CoreAPI call; при нативном исполнении допустимо эмулировать клик по core-action элементу. |
| `clear_search` | `set_input_value #warehouse-move-search = ''` + `input` event | Очистка строки поиска. |
| `reset_form` | `clear_search` + очистка `#warehouse-move-results-tbody` | Сбрасывается таблица результатов. |
| `setCellFromQR` | `set_select_value #cellId` по коду ячейки (из QR) + `change/input` | В JS ищется опция по тексту (обрезается префикс `CELL:`). |
| `triggerSaveButton` | `click button.js-core-link[data-core-action="warehouse_move_save_cell"]` | Триггер сохранения. |
| `confirmBatchMove` | Проверить `#warehouse-move-batch-cell` и `#warehouse-move-batch-total == 1`, затем использовать `data-item-id` из строки и вызвать `warehouse_move_batch_assign` | В JS-реализации идёт проверка DOM + CoreAPI запрос. |

### 3.2. Tools Management (`task_id: tools_management`)
| Web-действие | Нативная операция (DOM) | Примечание |
| --- | --- | --- |
| `clearToolsStorageMoveSearch` | `set_input_value #tools-storage-move-search = ''` + `input/change` | В JS дополнительно вызывает `CoreAPI.toolsManagement.fetchResults('')`. |
| `resetToolsUserSelection` | `set_select_value #toolAssignedUser = ''` + `change/input` | Сброс выбранного пользователя. |
| `resetToolsCellSelection` | `set_select_value #toolStorageCell = ''` + `change/input` | Сброс выбранной ячейки. |
| `setToolsUserFromQR` | выбрать опцию `#toolAssignedUser` по `data-qr-token` (или по `value` при числовом токене), затем `change/input` | В JS парсер извлекает токен из QR (`USER:`, `token=` и т.п.). |
| `setToolsCellFromQR` | выбрать опцию `#toolStorageCell` по нормализованному коду ячейки + `change/input` | В JS выделяется код из QR и нормализуется. |
| `triggerToolsManagementSave` | `click button.js-core-link[data-core-action="tools_management_save_move"]` | Сохранение перемещения. |

### 3.3. Warehouse Item In Batch (`task_id: warehouse_in`)
Эти web-действия уже имеют нативные обработчики в APP и напрямую мапятся на операции WebView:
- `clear_tracking` → очистка `#tuid` и `#trackingNo` (input/change).
- `clear_except_track` → очистка всех полей формы, кроме `tuid/trackingNo`.
- `clear_all` → полный сброс формы + дополнительные селекты (ячейки).
- `clear_measurements` → очистка `#weightKg`, `#sizeL`, `#sizeW`, `#sizeH`.
- `measure_request` → вызов `window.requestStandMeasurement()`.
- `add_new_item` → клик по `button[data-core-action="add_new_item_in"]` и очистка формы (если есть `tuid/trackingNo`).

Основание: прямые вызовы `clearTrackingAndTuidInWebView`, `clearParcelFormExceptTrack`, `clearAllInWebView`, `clearMeasurementsInWebView`, `requestStandMeasurementInWebView`, `prepareFormForNextScanInWebView` в APP.

## Отметка по чеклисту
- [x] Пункт 2 (минимальный набор операций) — определён по поддерживаемым `FlowOp` и `ScanAction`.
- [x] Пункт 3 (маппинг web-действий) — составлен для `warehouse_move`, `tools_management`, `warehouse_in`.