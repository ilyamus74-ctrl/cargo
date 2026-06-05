
## Общие принципы
- Источник сценариев остаётся в вебе: `device-scan-config` лежит в HTML и читается приложением.
- В APP сценарий исполняется нативно (WebView операции, сканер, шаги flow).
- В браузере без APP всё продолжает работать через JS‑fallback (если он включён).

## Что делает APP
- Читает `device-scan-config` и исполняет flow нативным движком (скан, шаги, web‑операции).
- Для `warehouse_move`, `tools_management`, `warehouse_item_in` выполняет стандартные операции (open scanner, fill/select, click, set step) без вызова JS‑хуков.
- При необходимости вызывает web‑операции через WebView, но основные действия выполняются нативно.

## Что делает Web (браузер без APP)
- Остаётся источником сценариев (`device-scan-config`).
- Использует JS‑fallback из `core_api.js` для критичных операций (`openMoveModal`, `setCellFromQR`, `triggerSaveButton`, `confirmBatchMove`, tools‑операции).
- Страницы должны работать без APP как раньше (JS‑flow остаётся).

## CoreAPI
- `CoreAPI` остаётся основным AJAX‑клиентом для всех операций (и в WEB, и при вызовах из APP WebView).

## Точки контроля
- В APP: проверяем корректность шагов flow и кликов веб‑кнопок.
- В WEB: проверяем, что JS‑fallback выполняет те же операции без APP.
