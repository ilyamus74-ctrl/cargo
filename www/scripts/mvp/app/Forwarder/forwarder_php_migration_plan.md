# Forwarder PHP Requests Migration Plan

Базовый пример host для всех примеров: `https://dev-backend.colibri.az` (домен может отличаться по окружениям).

## Цель
Перейти от работы через текущие раннеры к прямому PHP-клиенту по endpoint-контракту, чтобы быстрее и надежнее автоматизировать операции форвардера.

## Принципы реализации
1. Один общий HTTP-клиент с авторизацией (`/login`, CSRF token, cookie jar).
2. Один слой `ForwarderApiClient` (чистые запросы/ответы), выше — `ForwarderService` (бизнес-действия).
3. Для каждого действия фиксируем:
   - endpoint/method,
   - тип ответа (`html`/`json`),
   - что парсим из ответа,
   - критерий успеха.
4. HTML-flow (формы) постепенно заменяем на JSON endpoint’ы там, где это возможно.

---

## 1) Рейсы (flights)

### 1.1 Список рейсов
- **Статус:** ✅ Уже есть готовый flow (`run_flight_list`).
- **Действия:**
  - GET `/collector/flights`
  - GET `/collector/flights?page={n}`
- **Парсинг:** таблица рейсов, пагинация, поля рейса.
- **Следующий шаг миграции:** вынести в `FlightApi::list(page)`.

### 1.2 Добавление рейса
- **Статус:** ✅ Уже есть готовый flow (`run_add_flight`).
- **Действия:**
  - GET `/collector/flights` (получить hidden поля/токен)
  - POST `form_action` с `flight_number`, `awb`, `form_defaults`
- **Следующий шаг миграции:** `FlightApi::create(flightNumber, awb)`.

### 1.3 Удаление рейса
- **Статус:** ✅ Уже есть готовый flow (`run_delete_flight`).
- **Действия:**
  - поиск рейса формой,
  - удаление по ссылке **или** DELETE `/collector/flights/delete`.
- **Следующий шаг миграции:** `FlightApi::delete(id|search)`.

### 1.4 Редактирование рейса
- **Статус:** ✅ Добавлен flow `run_edit_flight` и endpoint-контракт.
- **Текущая реализация:**
  - POST `/collector/flights/update`
  - payload: `_token`, `id`, `carrier`, `flight_number`, `awb`, `departure`, `destination`, `flight_time`
  - если `_token` не передан через CLI, раннер автоматически пытается получить его c `/collector/flights`.
- **Следующий шаг миграции:** `FlightApi::update(id, payload)`.

### 1.5 Закрытие рейса
- **Статус:** ⏳ Не описано в контракте (требуется разведка).
- **План разведки:**
  - определить endpoint закрытия (кнопка close/complete/finalize),
  - описать переход статуса и ошибки (если есть незакрытые контейнеры).
- **Целевой метод:** `FlightApi::close(id)`.

---

## 2) Контейнеры (containers)

### 2.1 Список контейнеров
- **Статус:** ✅ Есть (`/collector/get-containers` по `flight_id`, `run_list_container_to_flight`).
- **Действия:**
  - GET `/collector/get-containers` (`flight_id`, `_token`)
  - GET `/collector/containers?search=1&flight={flight_id}`
- **CLI:** `php run_list_container_to_flight.php --base-url=... --login=... --password=... --flight-id=...`
- **Следующий шаг миграции:** вынести в `ContainerApi::listByFlight(flightId)`.

### 2.2 Принадлежность контейнера к рейсу
- **Статус:** ✅ Есть в текущем flow (контейнер добавляется к конкретному `flight`).
- **Проверка:** сравнение списков контейнеров до/после операции.

### 2.3 Добавление контейнера
- **Статус:** ✅ Уже есть (`run_add_container_to_flight`).
- **Действия:** POST `/collector/add-new-container` (`flight`, `_token`).
- **Следующий шаг миграции:** `ContainerApi::createForFlight(flightId)`.

### 2.4 Удаление контейнера
- **Статус:** ✅ Добавлен flow `run_del_container_from_flight`.
- **Текущая реализация:**
  - GET `/collector/containers` (получить CSRF token),
  - DELETE `/collector/containers/delete`
  - payload: `id`, `_token`
  - ожидаемый success-response: `case=success` (или `success=true`).
- **Следующий шаг миграции:** `ContainerApi::delete(containerId)`.

### 2.5 Свойства контейнера (посылки в контейнере)
- **Статус:** ⏳ Частично есть по report API, но нет полного CRUD.
- **План:**
  - определить endpoint получения состава контейнера,
  - описать поля посылок (track, статус, даты).
- **Целевой метод:** `ContainerApi::getPackages(containerId)`.

### 2.6 Добавление посылок в контейнер
- **Статус:** ⏳ Не описано (требуется разведка).
- **План:**
  - найти форму/endpoint add package to container,
  - зафиксировать обязательные поля и формат ответа.
- **Целевой метод:** `ContainerApi::addPackage(containerId, track)`.

### 2.7 Закрытие контейнера
- **Статус:** ⏳ Не описано (требуется разведка).
- **План:**
  - найти endpoint close/finalize container,
  - описать предусловия (пустой/непустой контейнер и т.д.).
- **Целевой метод:** `ContainerApi::close(containerId)`.

---

## 3) Репорты (XML)

### 3.1 Получение репортов
- **Статус:** ✅ Закрыто для текущих operational-сценариев (`run_report` + `run_report_single`); отдельный XML API `ReportApi::exportXml(filters)` остаётся как техдолг/улучшение.
- **Сейчас есть:**
  - POST `/api/check-position`
  - POST `/api/check-package`
  - POST `/collector/check-package` (single-check по `number`, без выгрузки файла)
- **Добавлено в PHP:**
  - CLI `run_report_single.php --track=...`
  - поиск совпадения по track сначала в `package`, затем в `client_packages[]` (большой payload ~56KB)
  - консольный вывод `package_summary` для быстрых smoke-проверок.
  - подтвержденный smoke-кейс: `status=ACCEPTED`, `http_status=200`, `internal_id=CBR859569` для `track=H1000844804054601044`.
  - endpoint(ы) выгрузки XML,
  - формат авторизации и фильтров,
  - XSD/структуру XML.
- **Целевой метод:** `ReportApi::exportXml(filters)`.

---

## 4) Сбор посылок (`/collector`) — добавление и редактирование

### 4.1 Добавление посылки
- **Статус:** ✅ Добавлен flow `run_add_package` и endpoint-контракт.
- **Текущая реализация:**
  - GET `/collector` (получить `form#form`, default payload и endpoint submit),
  - POST `/collector/add` (или action из формы),
  - payload поддерживает основные поля web-form: `number`, `client_name_surname`, `destination`, `gross_weight`, `currency`, `quantity`, `status_id` + доп. поля (`category`, `invoice`, `title`, `subCat`, и т.д.),
  - добавлены business-правила в раннере:
    - если `client_id` пустой или `0` → `status_id` принудительно `36`, `client_name_surname` обязателен;
    - если `client_id` задан (не `0`) → `status_id` принудительно `37`, `client_name_surname` опционален (форвардер может автоподставить корректное ФИО),
  - ожидаемый success-response: JSON с `case=success` и `internal_id`.
- **Целевой метод:** `PackageApi::create(payload)`.

### 4.2 Редактирование посылки
- **Статус:** ⏳ Не описано в текущем контракте.
- **План разведки:**
  - определить edit endpoint и формат update payload,
  - добавить optimistic/pessimistic проверки после сохранения.
- **Целевой метод:** `PackageApi::update(id, payload)`.

### 4.3 Поиск посылок (anonymous/show)
- **Статус:** ✅ Добавлен отдельный flow `run_search_forward`.
- **Endpoint:** `GET /collector/anonymous/show`
- **Фильтры:** `code`, `internal_id`, `client`, `seller`, `from_date`, `to_date`, `page`.
- **CLI пример:**
  ```bash
  php run_search_forward.php \
    --base-url=https://dev-backend.colibri.az \
    --login='w' \
    --password='S' \
    --code=151515 \
    --page=1
  ```
- **Подтвержденный пример результата:**
  - `status=FOUND`
  - `http_status=200`
  - `result.total=1`
  - `result.exact_match.internal_id=CBR859613`
  - `result.exact_match.client_name_surname=\"kon kon\"`

---

## Приоритизация (что делаем первым)

### Этап A (быстрый перенос на PHP-запросы)
1. Auth клиент + CSRF lifecycle.
2. Flights: list/create/delete.
3. Containers: listByFlight/createForFlight.
4. Reports JSON (`check-position`, `check-package`).

### Этап B (закрываем пробелы)
1. Flight update/close.
2. Container delete/close.
3. Package create/update в `/collector`.

### Этап C (репорты XML и стабилизация)
1. XML export endpoint’ы.
2. Ретраи, таймауты, идемпотентность.
3. Интеграционные smoke-сценарии.

---

## Definition of Done по каждому новому действию
- Добавлен endpoint в `forwarder_endpoints_contract.yaml`.
- Есть PHP-метод в API-клиенте.
- Есть пример запроса и пример ответа.
- Явно указано: `json` или `html` + какие поля парсим.
- Есть smoke-проверка (минимум 1 позитивный сценарий)
