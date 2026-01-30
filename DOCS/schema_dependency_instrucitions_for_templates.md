Ниже — схема зависимостей и последовательность операций для двух шаблонов, без кода, с привязкой к файлам.
1) Warehouse Move (cells_NA_API_warehouse_move.html)
1.1. Какие файлы участвуют и за что отвечают

Шаблоны (UI и DOM‑структура):

    cells_NA_API_warehouse_move.html — основной экран с вкладками “Сканнер перемещение” и “Пакетное перемещение”, скрытые блоки для OCR‑шаблонов и ключевой JSON device-scan-config (контексты, flow, кнопки и правила переключения контекста).

    cells_NA_API_warehouse_move_rows.html — отрисовка строк таблицы результатов; кнопка “Посылка” с data-core-action="warehouse_move_open_modal" для открытия модалки на запись в ячейку.

    cells_NA_API_warehouse_move_batch_rows.html — строки для пакетного режима; есть кнопки warehouse_move_batch_assign и warehouse_move_open_modal с data-item-id.

    cells_NA_API_warehouse_move_modal.html — контент модалки: select#cellId (куда кладется ячейка из QR), кнопка сохранения с data-core-action="warehouse_move_save_cell".

CoreAPI (web слой, обработка модалок/запросов):

    core_api.js (это тот самый файл CoreAPI) — универсальная обработка кликов .js-core-link, сбор FormData, запрос /core_api.php, и обработчики действий (warehouse_move_open_modal, warehouse_move_save_cell, warehouse_move_batch_assign).

    Внутри pageInits.warehouse_move определены функции, которые вызывает flow (openMoveModal, setCellFromQR, triggerSaveButton, confirmBatchMove, clear_search, reset_form). Это мост между flow и DOM.

Android‑приложение (MainActivity.kt):

    MainActivity.kt (OCRScanner) получает JSON device-scan-config и OCR‑данные из DOM через JS‑инъекцию и DeviceApp.onMainContext, реагирует на скан и события громкости, и исполняет flow‑операции (open_scanner, web, set_step, noop).

    Обработка результатов скана: fill_field, api_check, web_callback (вызов JS‑функции в WebView).

1.2. Ключевые зависимости и связи
A. Шаблон ↔ CoreAPI (модальные окна)

    Кнопка с data-core-action="warehouse_move_open_modal" (в списке результатов) → CoreAPI.events.handleClick строит FormData и вызывает /core_api.php.

    Обработчик warehouse_move_open_modal показывает HTML в #fullscreenModal.

    В модалке есть select#cellId и кнопка warehouse_move_save_cell — они используются JS‑flow/сканером для установки ячейки и сохранения.

B. Шаблон ↔ Android (device-scan-config)

    device-scan-config содержит:

        contexts: scanner, scanner_modal, batch

        context_switch: переключение контекста при активной вкладке и при показе/скрытии модалки

        flow для каждого контекста, где шаги описывают, что делать по событиям scan/confirm/clear/reset.

    MainActivity читает этот JSON из DOM (через JS‑инъекцию и DeviceApp.onMainContext).

C. Flow ↔ Web‑функции

    В flow для scan_cell_in_modal у QR‑скана указан web_callback: setCellFromQR. Значит после скана QR‑кода Android вызовет JS‑функцию window.setCellFromQR(...) в странице.

    setCellFromQR и triggerSaveButton реализованы в pageInits.warehouse_move и работают через DOM (#cellId, кнопка warehouse_move_save_cell).

1.3. Последовательность операций (scanner → modal → save)
Контекст scanner (основная вкладка)

    Скан штрихкода → flow шаг scan_parcel: open_scanner(barcode); результат скана пишет в #warehouse-move-search (action fill_field).

    confirm (VOL DOWN DOUBLE) → web: openMoveModal, открывает модалку по первому результату таблицы.

Контекст scanner_modal

    После открытия модалки активируется контекст scanner_modal (в context_switch.modals).

    Скан QR в шаге scan_cell_in_modal → web_callback: setCellFromQR, который ставит select#cellId.

    confirm → triggerSaveButton (клик по warehouse_move_save_cell) → CoreAPI вызывает /core_api.php и закрывает модалку в обработчике.

Контекст batch

    В пакетном режиме flow сначала сканит QR ячейки (scan_cell), затем штрихкод посылки (scan_parcel). По confirm вызывается confirmBatchMove, который собирает item_id и cell_id и отправляет warehouse_move_batch_assign.

2) Warehouse Item In (cells_NA_API_warehouse_item_in.html)
2.1. Какие файлы участвуют

Шаблоны:

    cells_NA_API_warehouse_item_in.html — основной экран со списком партий, кнопкой “Начать новую партию”, и #fullscreenModal для деталей партии.

    cells_NA_API_warehouse_item_in_batch.html — контент модалки партии: форма #item-in-modal-form, таблица посылок и device-scan-config для flow приемки (barcode → ocr → measure → submit).

CoreAPI (web слой):

    Обработчик open_item_in_batch открывает модалку, add_new_item_in перерисовывает модалку после добавления, commit_item_in_batch закрывает модалку и перезагружает список.

Android (MainActivity.kt):

    Получает JSON device-scan-config и запускает flow (в т.ч. web‑операции clear_tracking, clear_all, add_new_item и т. д.).

2.2. Последовательность операций (batch modal → flow)

    Пользователь нажимает “Начать новую партию” (open_item_in_batch) или “Открыть” существующую партию. CoreAPI открывает #fullscreenModal и вставляет HTML партии (batch).

    Внутри модалки cells_NA_API_warehouse_item_in_batch.html лежит device-scan-config с flow:

        barcode: open_scanner(barcode) → fill_field в tuid/trackingNo/senderName

        ocr: open_scanner(ocr) → следующий шаг measure

        measure: web: measure_request → следующий шаг submit

        submit: web: add_new_item и возврат на barcode

    Android, получив flow, обрабатывает кнопки громкости:

        scan/confirm/clear/reset → executeFlowOps, где web‑операции преобразуются в функции изменения формы в WebView (очистки, запрос измерений, подготовка новой посылки).

    Кнопка “Добавить посылку” — это обычный data-core-action="add_new_item_in" (web‑действие), CoreAPI обновляет контент модалки, сохраняя контекст партии.

3) Общая схема зависимостей (коротко)

UI (templates) → задают DOM‑IDs (#cellId, #item-in-modal-form), создают device-scan-config, и дают кнопки с data-core-action.
CoreAPI (web) → ловит клики js-core-link, вызывает /core_api.php, открывает/закрывает модалки и вставляет HTML.
MainActivity (Android) → читает device-scan-config из DOM, запускает flow, открывает сканер, и по результату вызывает JS‑callback или меняет поля формы напрямую в WebView.

Ключевой мост для QR‑скана ячейки в Warehouse Move — это web_callback: setCellFromQR + select#cellId в модалке + triggerSaveButton на confirm.

