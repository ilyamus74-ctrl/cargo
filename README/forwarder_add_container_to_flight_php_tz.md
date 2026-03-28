# ТЗ: реализация операции `add_container_to_flight_php` на основе `run_add_flight.php`

## 1) Цель

Сделать стабильную script-операцию `add_container_to_flight_php` для Forwarder (Colibri), которая:

1. Ищет целевой рейс на странице `/collector/flights`.
2. Добавляет пустой контейнер в найденный рейс через штатный web-flow (с авторизованной PHP-сессией).
3. Возвращает унифицированный JSON-результат в формате остальных `run_*` скриптов.

Операция должна быть совместима с текущей инфраструктурой `core_api` и коннекторными `test_operation`.

---

## 2) Базовый референс и стиль реализации

Реализовать по паттерну `www/scripts/mvp/app/Forwarder/run_add_flight.php`:

- те же функции чтения CLI-аргументов (`--key=value`);
- тот же bootstrap + env setup;
- тот же стек (`ForwarderConfig`, `ForwarderHttpClient`, `SessionManager`, `LoginService`, `ForwarderSessionClient`);
- тот же подход к кодам выхода и финальному JSON.

Имя нового скрипта:  
`www/scripts/mvp/app/Forwarder/run_add_container_to_flight.php`.

---

## 3) Входные параметры (CLI)

Поддержать параметры (с алиасами):

- `--base-url` (`--base_url`)
- `--login`
- `--password`
- `--session-file` (`--session_file`)
- `--session-ttl-seconds` (`--session_ttl_seconds`)
- `--page-path` (`--page_path`) — по умолчанию `/collector/flights`

Целевые данные операции:

- `--flight-search` (`--flight_search_value`, `--flight`, `--flight_no`) — строка поиска рейса (например `HHN30032026`)
- `--target-flight-id` (`--target_flight_id`, `--flight_id`, `--external_id`) — id записи рейса (если известен)
- `--container-name` (`--container`, `--container_no`) — значение контейнера для добавления (если UI требует явное имя)

Правила обязательности:

- минимум один из параметров идентификации рейса обязателен: `flight-search` или `target-flight-id`;
- `container-name` обязателен, если форма/endpoint требует значение контейнера; иначе допускается автогенерация сервером.

---

## 4) Функциональные требования (пошаговый flow)

### Шаг 1. Инициализация и сессия

1. Нормализовать `base-url` как в `run_add_flight.php`.
2. Проставить ENV-переменные (`DEV_COLIBRI_*`, `FORWARDER_*`).
3. Создать клиента сессии и выполнить GET страницы рейсов (`page-path`).
4. Если запрос неуспешен — завершить с ошибкой.

### Шаг 2. Поиск целевого рейса

1. Распарсить HTML страницы и определить search form (action/method/default payload), аналогично подходу в `run_delete_flight.php`.
2. Отправить search-запрос с:
   - search field (определяется из формы),
   - значением `flight-search` (или `target-flight-id`, если search пуст),
   - `search=1` (если не пришел из defaults).
3. Из HTML результатов найти целевой row:
   - приоритет 1: строгое совпадение по `target-flight-id` (по row id / `select_row(id)` / первой колонке);
   - приоритет 2: совпадение по `flight-search` в тексте строки.
4. Если найдено несколько строк — выбрать по `target-flight-id`; если его нет, выбрать первую и зафиксировать предупреждение в `meta`.

### Шаг 3. Определение точки добавления контейнера

Нужно поддержать 2 варианта UI:

1. **Link/Button-driven**  
   В строке/деталях рейса есть ссылка/onclick/href на add container endpoint.
2. **Form-driven**  
   Кнопка открывает форму, где есть action/method + скрытые поля (`_token`, `flight_id` и т.п.).

Алгоритм:

1. Сначала попытаться извлечь явную ссылку/endpoint добавления контейнера.
2. Если явной ссылки нет — попытаться извлечь форму добавления контейнера.
3. Если форма недоступна в search HTML, выполнить дополнительный GET в endpoint деталей рейса (если он указан в `ondblclick`/`href`) и повторить парсинг там.

### Шаг 4. Формирование submit payload

Собрать payload из:

- default hidden/input полей формы (включая `_token`, если есть),
- идентификатора рейса (`id`/`flight_id`/`flight_record_id` — по фактической форме),
- контейнера (`container-name`, если требуется),
- служебных полей (`_method`, `search`, etc.) — только если они реально нужны форме.

### Шаг 5. Отправка запроса добавления контейнера

1. Использовать метод, полученный из формы/endpoint (`POST`/`GET`/`DELETE`/`PUT` и т.д.).
2. Запрос отправлять через `ForwarderSessionClient::requestWithSession(...)`.
3. Формат отправки — `application/x-www-form-urlencoded` (`asJson=false`), если не доказано, что endpoint JSON-only.
4. Успех: HTTP 2xx/3xx (как в текущих `run_*` скриптах).

### Шаг 6. (Опционально, но желательно) Пост-проверка

После submit выполнить повторный поиск рейса и проверить, что:

- контейнер появился в деталях рейса/таблице контейнеров, или
- сервер вернул явный success marker.

Если пост-проверка не удалась, но submit 2xx — вернуть `status=warning` (или `status=ok` + `verification=false`, если текущий контракт не поддерживает `warning`).

---

## 5) Контракт результата (stdout JSON)

Минимальный ответ:

```json
{
  "status": "ok|error",
  "message": "Container add submitted via PHP session client",
  "correlation_id": "run-add-container-...",
  "base_url": "https://...",
  "page_path": "/collector/flights",
  "search_path": "/collector/flights?...",
  "search_method": "GET|POST",
  "search_field": "name|search_values|...",
  "search_value": "HHN30032026",
  "target_flight_id": "2360",
  "resolved_flight_row_id": "2360",
  "submit_path": "/collector/...",
  "submit_method": "POST",
  "container_name": "NEW",
  "http_status": 200,
  "error": ""
}
```

Дополнительно (для диагностики):

- `search_response_status`
- `search_response_error`
- `matched_rows_count`
- `resolved_by` (`target_id|search_value|fallback_first_row`)
- `verify_status` (`passed|failed|skipped`)

---

## 6) Коды завершения процесса (exit code)

Рекомендуемый стандарт:

- `0` — операция успешна;
- `2` — невалидные/неполные входные аргументы;
- `3` — не заполнена конфигурация (`base-url/login/password`);
- `4` — ошибка загрузки основной страницы;
- `5` — ошибка парсинга search/form;
- `6` — рейс не найден;
- `7` — endpoint/форма добавления контейнера не найдены;
- `8` — ошибка отправки запроса добавления контейнера;
- `9` — submit успешен, но пост-проверка не подтверждена (если включена строгая валидация).

---

## 7) Обработка edge-cases

1. **Дубли рейсов с одинаковым `name`**  
   Обязательно поддержать точный выбор по `target-flight-id`.

2. **Отсутствие inline-кнопки в списке**  
   Обязательный fallback через детали рейса / форму.

3. **CSRF токен в `<meta>` и/или hidden input**  
   Поддержать оба источника.

4. **Разные имена полей в форме**  
   Не хардкодить одно имя; брать defaults + искать набор известных алиасов.

5. **Редиректы/протухшая сессия**  
   Полагаться на `requestWithSession` (relogin+retry уже встроен).

---

## 8) Нефункциональные требования

- PHP `strict_types=1`.
- Чистые функции-помощники для парсинга (`extract_search_form`, `extract_target_flight`, `extract_add_container_target`, ...).
- Без try/catch вокруг import/require.
- Детальные `fwrite(STDERR, "...")` сообщения на fail-path.
- Код должен быть читаемым и максимально похожим на текущий стиль `run_add_flight.php`/`run_delete_flight.php`.

---

## 9) Интеграция с `core_api`/операциями коннектора

После реализации скрипта добавить/обновить operation в конфиге коннектора:

- `operation_id`: `add_container_to_flight_php`
- `kind`: `script`
- `entrypoint`: путь к новому `run_add_container_to_flight.php`
- проброс аргументов:
  - `flight-search`
  - `target-flight-id`
  - `container-name`
  - auth/base/session параметры

UI-кнопка `data-operation="add_container_to_flight"` может иметь PHP-вариант/фолбэк на `add_container_to_flight_php` по аналогии с другими операциями.

---

## 10) План приемки (Acceptance Criteria)

Считать задачу выполненной, если:

1. Скрипт стабильно отрабатывает на реальном стенде `dev-backend.colibri.az` для кейса:
   - поиск рейса `HHN30032026`,
   - выбор записи по `target-flight-id` (например `2360`),
   - добавление контейнера.
2. Нет падения вида `... parse failed: add_container_target_not_found` в сценарии, где кнопка/ссылка отсутствует в строке таблицы.
3. В stdout возвращается валидный JSON с заполненными диагностическими полями.
4. Операция успешно запускается из `core_api` test_operation для соответствующего connector.
5. Для ошибочных сценариев возвращаются корректные exit code и человекочитаемые сообщения.
