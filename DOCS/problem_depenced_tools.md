1) Ключевые зависимости (Tools Management)
1.1. Шаблоны и DOM‑узлы

    Основной экран, табы, поиск и device-scan-config находятся в cells_NA_API_tools_management.html. Там же задано переключение контекстов по модалкам с атрибутом data-tools-modal.

    Список результатов рендерится через cells_NA_API_tools_management_rows.html и содержит кнопки, открывающие модалки:

        tools_management_open_modal (общая форма),

        tools_management_open_user_modal (назначение пользователя),

        tools_management_open_cell_modal (назначение ячейки).

    Контент модалок:

        cells_NA_API_tools_management_modal.html — обе выборки (user + cell) и кнопка tools_management_save_move. Select’ы: #toolAssignedUser и #toolStorageCell.

        cells_NA_API_tools_management_user_modal.html — только #toolAssignedUser.

        cells_NA_API_tools_management_cell_modal.html — только #toolStorageCell.

1.2. CoreAPI (web‑логика)

    Клиентские обработчики tools_management_open_* ставят тип модалки через setToolsManagementModalType(...) и открывают #fullscreenModal. Это важно для контекста сканирования.

    Обработчик tools_management_save_move сохраняет и закрывает модалку, потом обновляет поиск.

    JS‑функции, которые вызываются из flow:

        setToolsUserFromQR → ищет нужный option в #toolAssignedUser по data-qr-token или id.

        setToolsCellFromQR → ищет ячейку в #toolStorageCell по тексту/значению.

        triggerToolsManagementSave → кликает tools_management_save_move.

        resetToolsUserSelection/resetToolsCellSelection — сброс выбора.

1.3. Android (MainActivity.kt)

    Получает device-scan-config из WebView и использует его для flow (scan/confirm/clear/reset).

    При QR‑скане вызывает web‑callback setToolsUserFromQR / setToolsCellFromQR (из device-scan-config).

2) Поток операций (что должно происходить)
2.1. Основной контекст tools_storage_move

    Скан QR/Barcode → fill_field пишет в #tools-storage-move-search.

    По input/change CoreAPI запускает поиск, заполняя таблицу результатов.

2.2. Контекст tools_user_modal

    Нажатие на пользователя в таблице → tools_management_open_user_modal.

    CoreAPI выставляет data-tools-modal="user" на #fullscreenModal и показывает HTML модалки.
    Это триггерит смену контекста на tools_user_modal в device-scan-config.

    QR‑скан → web_callback: setToolsUserFromQR → устанавливает #toolAssignedUser.

    confirm (VOL DOWN DOUBLE) → triggerToolsManagementSave → tools_management_save_move.

2.3. Контекст tools_cell_modal

Аналогично:

    Нажатие на ячейку → tools_management_open_cell_modal.

    CoreAPI выставляет data-tools-modal="cell" → контекст tools_cell_modal.

    QR‑скан → setToolsCellFromQR → устанавливает #toolStorageCell.

    confirm → triggerToolsManagementSave.

3) Где чаще всего ломается (по твоему кейсу)

Симптом: «сканер закрывается, поле не меняется».

На этом шаблоне критично совпадение контекста модалки и наличие select‑элемента на момент скана:

    Контекст модалки зависит от data-tools-modal.
    Если setToolsManagementModalType('user'/'cell') не отработал, то устройство останется в tools_storage_move и вызовет не тот callback. Это прямо завязано на обработчики tools_management_open_user_modal / tools_management_open_cell_modal.

    DOM‑элемент #toolAssignedUser / #toolStorageCell должен быть уже в модалке.
    setToolsUserFromQR/setToolsCellFromQR ждут select и делают несколько повторов (withSelectRetry). Если модалка еще не успела подгрузиться — установка не произойдёт.
