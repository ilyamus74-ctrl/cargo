# `run_report.php` и операция `report/php_report`: какие параметры обязательны

## 1) Можно ли запускать `www/scripts/mvp/app/Forwarder/run_report.php` без настроек «Операция report»?

Да, можно, потому что это **два разных механизма**:

- `www/scripts/mvp/app/Forwarder/run_report.php` — отдельный CLI-скрипт форвардера для проверки контейнера/трек-номера.
- «Операция report» (`kind=php_report`) — это конфиг в `operations_json/operations_v3_json`, который использует рантайм коннекторов для скачивания и импорта отчёта.

Поэтому `run_report.php` не читает `steps`, `field_mapping`, `curl_config`, `target_table` из JSON операции report.

---

## 2) Ключи для `run_report.php` (Forwarder CLI)

### CLI аргументы

Поддерживаются:

- `--track=<TRACK>`
- `--container=<CONTAINER>`

Также можно передать позиционно:

- `argv[1]` как `track`
- `argv[2]` как `container`

### ENV переменные

#### Обязательные для реального scan-запроса

- `FORWARDER_BASE_URL` (или алиас `DEV_COLIBRI_BASE_URL`)
- `FORWARDER_LOGIN` (или алиас `DEV_COLIBRI_LOGIN`)
- `FORWARDER_PASSWORD` (или алиас `DEV_COLIBRI_PASSWORD`)

Если этих трёх нет:

- без `track/container` скрипт вернёт `AUTH_SKIPPED` (preflight);
- с `track/container` завершится ошибкой (exit code `3`).

#### Дополнительные (не обязательные)

- `FORWARDER_TRACK`
- `FORWARDER_CONTAINER`
- `FORWARDER_FLOW_ENABLED` (по умолчанию включён)
- `FORWARDER_SESSION_FILE`
- `FORWARDER_SESSION_TTL_SECONDS`
- `FORWARDER_IDEMPOTENCY_FILE`
- `FORWARDER_IDEMPOTENCY_TTL_SECONDS`

---

## 3) Ключи для JSON-конфига «Операция report» (`kind=php_report`)

Ниже перечисление полей, которые используются в рантайме report-операции.

### Базовые поля операции

- `kind`: должно быть `"php_report"`
- `config`: объект настроек report

### Ключи в `config`

- `download_mode`: `"curl"` или `"browser"`
- `file_extension`: например `"xlsx"`
- `target_table`: имя таблицы импорта
- `field_mapping`: объект маппинга `column_in_db -> column_in_file`
- `log_steps`: `0/1` (лог шагов)
- `steps`: массив шагов (используется для `download_mode=browser`)
- `curl_config`: объект cURL-настроек (используется для `download_mode=curl`)

### Минимальные требования runnable-валидации

Чтобы операция считалась runnable:

- обязательно `download_mode`
- если `download_mode="curl"`, обязательно `curl_config.url`

### Что реально обязательно для импорта в БД

- `target_table` (иначе берётся fallback `connector_report_temp`)
- `field_mapping` (если пусто, файл можно скачать, но импорт обычно пропускается)

### Структура `curl_config`

Основные ключи:

- `url` *(обязательно для режима curl)*
- `method` (`GET`/`POST`, по умолчанию `GET`)
- `headers` (объект заголовков)
- `body` (объект/строка тела)
- `expected_content_types` (опционально)
- `success` (опционально)
- `login` (опционально, если нужен логин до скачивания)

### Структура `curl_config.login` (если используется)

- `csrf_url` (опционально)
- `csrf_method` (опционально)
- `url`
- `method`
- `headers`
- `body`
- `success`
- `csrf_preflight` (опционально)

---

## 4) Ваш пример JSON steps

Ваш пример с:

- `download_mode = curl`
- `file_extension = xlsx`
- `target_table = connector_report_dev_colibri_az`
- заполненными `field_mapping` и `curl_config`

подходит для `kind=php_report` и закрывает минимальные требования runnable-проверки (`download_mode` + `curl_config.url`).

---

## 5) Ответы на частые вопросы (по вашему кейсу)

### Откуда скрипт знает, что писать именно в `connector_report_dev_colibri_az`?

Источник — `config.target_table` текущей операции `php_report`.

1. При выполнении `php_report` рантайм читает `target_table` из `operation.config`.
2. Если поле пустое, подставляет fallback `connector_report_temp`.
3. Импорт вызывает `connectors_ensure_report_table(...)` и затем `connectors_import_*_into_report_table(...)` уже с этим именем таблицы.

То есть имя таблицы берётся **не из файла отчёта** и не «угадывается», а приходит из `config.target_table` операции.

### `steps` реально сейчас используется?

Да, но только если `download_mode = "browser"`.

- В режиме `browser` рантайм берёт `steps` из `config.steps` и добавляет к ним login-steps из scenario (если есть).
- Если итоговый список шагов пуст, операция падает с ошибкой валидации (`нужно заполнить report_steps_json или browser_login_steps`).
- В режиме `curl` поле `steps` не участвует в скачивании.

### Если удалить ваш JSON, скрипт перестанет работать?

Зависит от того, **что именно удалить**:

1. Если удалить только `steps`, но оставить `download_mode = "curl"` и валидный `curl_config.url` — будет работать (для curl `steps` не нужны).
2. Если удалить `steps` при `download_mode = "browser"` и при этом нет `browser_login_steps` в scenario — не будет работать (ошибка про пустые шаги).
3. Если удалить `target_table` — скачивание возможно, но таблица станет fallback `connector_report_temp`.
4. Если удалить `field_mapping` (или оставить пустым) — файл может скачаться, но импорт в БД будет пропущен.
5. Если удалить `curl_config.url` при `download_mode = "curl"` — операция не runnable и завершится ошибкой конфигурации.
