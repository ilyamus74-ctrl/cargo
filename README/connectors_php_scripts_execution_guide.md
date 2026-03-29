# Connectors PHP Scripts: зависимости и процесс выполнения

> Практическая инструкция для задач по операциям:
> - добавление контейнера (`add_container_to_flight_php`)
> - удаление контейнера (`delete_container_php`)
> - добавление рейса (`add_flight_php`)
> - удаление рейса (`delete_flight_php`)

---

## 1) Картина целиком: от кнопки до форвардера

1. Кнопка в шаблоне `templates/cells_NA_API_departures_rows.html` задаёт `data-operation` и runtime-параметры (`data-flight-id`, `data-container-id`, и т.д.).
2. Фронт (`www/js/core_api.js`) ловит клик `.js-departure-placeholder-action`, собирает `runtime_vars_json` и отправляет запрос в `core_api.php` c `action=test_connector_operations`.
3. `www/core_api.php` маршрутизирует `test_connector_operations` в `www/api/connectors/connector_actions.php`.
4. `connector_actions.php`:
   - загружает конфиг операций коннектора;
   - выбирает операцию по `test_operation`;
   - разворачивает placeholders в аргументах script-операции;
   - запускает PHP script из `www/scripts/mvp/app/Forwarder/*.php`.
5. Скрипт работает через `ForwarderSessionClient`, выполняет HTTP-вызовы к форвардеру, возвращает JSON.
6. Фронт получает ответ, при необходимости запускает refresh-операцию (`flight_list_php`) и обновляет список.

---

## 2) Файлы и зоны ответственности

### UI и клиент
- `templates/cells_NA_API_departures_rows.html`
  - Описывает кнопки и `data-*` payload.
- `www/js/core_api.js`
  - `buildFlightRuntimeVars()` формирует runtime vars.
  - `triggerPlaceholderOperation()` запускает `test_connector_operations`.
  - Для `_php` операций выставляет PHP entrypoint mode автоматически.

### API-роутинг и рантайм операций
- `www/core_api.php`
  - Маршрутизация action → handler.
- `www/api/connectors/connector_actions.php`
  - Шаблоны operation-конфигов (`connectors_operation_config_templates()`).
  - Разворачивание placeholders (`connectors_expand_script_arg_placeholders()`).
  - Запуск `kind=script` через interpreter/script_path.

### PHP скрипты форвардера
- `www/scripts/mvp/app/Forwarder/run_add_container_to_flight.php`
- `www/scripts/mvp/app/Forwarder/run_del_container_from_flight.php`
- `www/scripts/mvp/app/Forwarder/run_add_flight.php`
- `www/scripts/mvp/app/Forwarder/run_delete_flight.php`
- `www/scripts/mvp/app/Forwarder/sync_kernel.php`

---

## 3) Потоки выполнения по операциям

## 3.1 Добавление контейнера (`add_container_to_flight_php`)

### Минимально нужные runtime vars
- `flight_id`
- (обычно) `departure_id`, `destination_id`, `count`

### Operation config (источник)
- `connector_actions.php` → `add_container_to_flight_php`
- `script_path = run_add_container_to_flight.php`
- args:
  - `--flight-id={{flight_id}}`
  - `--departure-id={{departure_id}}`
  - `--destination-id={{destination_id}}`
  - `--count={{count}}`

### Что делает скрипт
1. Открывает `/collector/containers`, берёт csrf.
2. Делает submit add.
3. Делает verify (поиск/проверка изменений списка контейнеров).
4. Запускает `forwarder_sync_flight_containers_kernel()` для локальной БД.

---

## 3.2 Удаление контейнера (`delete_container_php`)

### Минимально нужные runtime vars
- `container_id`
- Желательно: `flight_id`
- Опционально: `container_name` (fallback-резолв id по имени)

### Operation config (источник)
- `connector_actions.php` → `delete_container_php`
- `script_path = run_del_container_from_flight.php`
- args:
  - `--flight-id={{flight_id}}`
  - `--container-id={{container_id}}`
  - `--container-name={{container_name}}`
  - `--connector-id={{connector_id}}`

### Что делает скрипт
1. Открывает `/collector/containers`, берёт csrf.
2. Если передан `container_name`, может уточнить `resolved_container_id` через `/collector/get-containers`.
3. Пробует delete:
   - `DELETE /collector/containers/delete`
   - fallback `POST /collector/containers/delete` + `_method=DELETE`
4. Делает post-probe через `/collector/get-containers`, проверяет, исчез ли контейнер.
5. При успехе запускает sync в БД.
6. Возвращает диагностику: `delete_attempts`, `resolved_container_id`, `container_still_present`, `sync_db_*`.

---

## 3.3 Добавление рейса (`add_flight_php`)

### Минимально нужные runtime vars
- `set_date`
- `add_flight` (AWB)

### Operation config (источник)
- `connector_actions.php` → `add_flight_php`
- `script_path = run_add_flight.php`

### Что делает скрипт
1. Открывает страницу рейсов, берёт csrf.
2. Делает submit создания рейса.
3. Возвращает статус операции и диагностические поля (method/path/http/message).

---

## 3.4 Удаление рейса (`delete_flight_php`)

### Минимально нужные runtime vars
- `flight_id` (или alias target/external id)

### Operation config (источник)
- `connector_actions.php` → `delete_flight_php`
- `script_path = run_delete_flight.php`

### Что делает скрипт
1. Находит цель удаления в HTML/доступных action.
2. Выполняет удаление (в зависимости от формы/ссылки/метода).
3. Возвращает JSON со статусом и диагностикой.

---

## 4) Критичные зависимости и «где чаще ломается»

## 4.1 UI → runtime vars
- Любой `data-*` в шаблоне должен быть отражён в `buildFlightRuntimeVars()`.
- Если переменная не попала в `runtime_vars_json`, script args не получат значение.

## 4.2 runtime vars → placeholders в args
- Если в operation args есть `{{...}}`, этот placeholder обязан поддерживаться в
  `connectors_expand_script_arg_placeholders()`.
- Типичный симптом пропуска placeholder: в script уходят literal-строки `{{container_id}}`.

## 4.3 Operation config целостность
- Для `kind=script` обязательно `config.script_path`.
- Если его нет в конкретной runtime операции коннектора — получите:
  `Для kind=script укажите operation.config.script_path`.

## 4.4 Refresh-процессы после операций
- После container-операций важен `flight_list_php` (актуализация UI + локальной БД).
- Неправильный refresh может маскировать фактический результат на форвардере.

---

## 5) Чеклист для следующих задач (обязательно)

1. **Шаблон кнопки**
   - Проверить `data-operation`, `data-*` параметры.
2. **JS runtime**
   - Убедиться, что `buildFlightRuntimeVars()` включает нужные ключи.
3. **Operation template**
   - Проверить `operation_id`, `kind`, `script_path`, `args`.
4. **Placeholder expansion**
   - Проверить, что все `{{...}}` из args поддержаны в `connectors_expand_script_arg_placeholders()`.
5. **Script args parsing**
   - Проверить, что `run_*.php` читает эти args (primary + aliases).
6. **Фактическая верификация**
   - Для add: контейнер/рейс появился.
   - Для delete: контейнер/рейс исчез.
7. **Sync состояние**
   - Проверить `sync_db_status`, `sync_db_message`.
8. **Диагностика**
   - Смотреть `script.args_expanded_masked`, `entrypoint_diagnostics`, `trace_log`, `graph_errors`.

---

## 6) Быстрые команды диагностики

```bash
# Синтаксис ключевых файлов
php -l www/api/connectors/connector_actions.php
php -l www/scripts/mvp/app/Forwarder/run_add_container_to_flight.php
php -l www/scripts/mvp/app/Forwarder/run_del_container_from_flight.php
php -l www/scripts/mvp/app/Forwarder/run_add_flight.php
php -l www/scripts/mvp/app/Forwarder/run_delete_flight.php
node --check www/js/core_api.js
```

```bash
# Найти все operation templates для PHP forwarder
rg -n "add_container_to_flight_php|delete_container_php|add_flight_php|delete_flight_php" \
  www/api/connectors/connector_actions.php
```

```bash
# Проверить, какие placeholders поддержаны
rg -n "connectors_expand_script_arg_placeholders|\\{\\{container_id\\}\\}|\\{\\{flight_id\\}\\}" \
  www/api/connectors/connector_actions.php
```

---

## 7) Рекомендуемый принцип изменений

- Сначала правим **контракт переменных** (Template → JS runtime vars → Operation args → Script parser).
- Потом уже правим бизнес-логику HTTP вызовов к форвардеру.
- Любую новую runtime переменную добавлять сразу в 4 места:
  1) `data-*` в кнопке (или другой источник),
  2) `buildFlightRuntimeVars()`,
  3) `args` в operation config,
  4) placeholder expansion + парсинг в script.
  