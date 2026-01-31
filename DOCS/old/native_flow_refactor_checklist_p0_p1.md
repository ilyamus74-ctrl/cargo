# Native flow refactor checklist — пункты 0 и 1 (аудит)

Дата: 2026-02-04

## 0) Термины (зафиксированы)
- **JS-flow**: сценарий выполняется в браузере через JS, читает `device-scan-config` и управляет DOM.
- **Native-flow**: сценарий читает приложение (APP) и выполняет стандартные операции (fill field, open scanner, set step и т.д.).
- **Гибрид**: UI остаётся в вебе, а flow исполняется в APP.

## 1) Подготовка — аудит текущей схемы

### 1.1. Страницы/шаблоны, где используется `device-scan-config`
- `templates/cells_NA_API_warehouse_move.html` — экран перемещения (сканнер + пакетное перемещение).
- `templates/cells_NA_API_tools_management.html` — модуль управления инструментами.
- `templates/cells_NA_API_warehouse_item_in_batch.html` — контент модалки партии приёмки.

### 1.2. JS-функции, вызываемые из flow (web/web_callback)

#### Warehouse Move (`task_id: warehouse_move`)
**web**:
- `openMoveModal`
- `clear_search`
- `reset_form`
- `triggerSaveButton`
- `confirmBatchMove`

**web_callback**:
- `setCellFromQR`

#### Tools Management (`task_id: tools_management`)
**web**:
- `clearToolsStorageMoveSearch`
- `triggerToolsManagementSave`
- `resetToolsUserSelection`
- `resetToolsCellSelection`

**web_callback**:
- `setToolsUserFromQR`
- `setToolsCellFromQR`

#### Warehouse Item In Batch (`task_id: warehouse_in`)
**web**:
- `clear_tracking`
- `clear_all`
- `clear_except_track`
- `measure_request`
- `clear_measurements`
- `add_new_item`

### 1.3. Критичные DOM-элементы и селекторы для flow

#### Warehouse Move (`templates/cells_NA_API_warehouse_move.html`)
- Вкладки/контексты:
  - `#warehouse-move-scanner-tab`
  - `#warehouse-move-batch-tab`
  - `#fullscreenModal` (контекст модалки)
- Поля/элементы ввода:
  - `#warehouse-move-search`
  - `#warehouse-move-batch-search`
  - `#warehouse-move-batch-cell`
  - `#cellId` (select в модалке для установки ячейки через `setCellFromQR`)
- Кнопки/действия:
  - `button[data-core-action="warehouse_move_save_cell"]`
  - `button[data-core-action="warehouse_move_batch_assign"]`
- Таблицы/результаты (используются в `confirmBatchMove`):
  - `#warehouse-move-results-tbody`
  - `#warehouse-move-batch-results-tbody`
  - `#warehouse-move-batch-total`
  - `.js-warehouse-move-batch-action`
  - `[data-item-id]`

#### Tools Management (`templates/cells_NA_API_tools_management.html`)
- Вкладки/контексты:
  - `#tools-storage-move-tab`
  - `#fullscreenModal[data-tools-modal="user"]`
  - `#fullscreenModal[data-tools-modal="cell"]`
- Поле ввода:
  - `#tools-storage-move-search`

#### Warehouse Item In Batch (`templates/cells_NA_API_warehouse_item_in_batch.html`)
- Формы/поля, которые заполняются из flow:
  - `#tuid`
  - `#trackingNo`
  - `#senderName`
- Контейнер формы:
  - `#item-in-modal-form`

## Отметка по чеклисту
- [x] Пункт 0 (термины) — зафиксирован.
- [x] Пункт 1 (аудит) — выполнен: страницы, JS-функции и DOM-элементы перечислены.
