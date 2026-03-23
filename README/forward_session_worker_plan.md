# План внедрения persistent session worker для web + Node

## Контекст

Цель: безопасно добавить работу с посылками в сторону форварда через web + Node, не ломая текущий одноразовый сценарный раннер.

Базовый MVP:

- один actor = один worker
- один worker = один browser + одна page + одна session
- jobs выполняются последовательно
- session persistent
- idle timeout
- context tracking

Позже:

- pooling
- shared account scheduler
- popup/label/print pipeline
- интеграция с APP OcrScanner

---

## Главный принцип

`www/scripts/test_connector_operations_browser.js` не переделываем сразу в session-worker.

Он остаётся:

- стабильным one-shot runner
- совместимым с текущими сценариями
- опорным источником общего browser-core

Новая persistent-session логика делается в отдельном worker рядом с ним.

---

## Статусы

- ✅ выполнено
- ⏳ в работе
- ⬜ не начато
- ⚠️ блокер / требует уточнения

---

## Чеклист этапов

### 1. Зафиксировать границы проекта
**Статус:** ✅ выполнено

Что фиксируем:

- текущий `test_connector_operations_browser.js` остаётся рабочим one-shot инструментом
- session-режим не внедряем в него напрямую
- новый функционал для форварда строим отдельно

Критерий завершения:

- команда согласовала, что старый runner не ломаем

---

### 2. Выделить общий browser-core
**Статус:** ✅ выполнено

Цель:

вынести общие browser-утилиты в отдельный модуль, чтобы не копировать большой файл целиком.

Предлагаемый файл:

`www/scripts/lib/connector_browser_core.js`

Что выносим:

- запуск браузера и fallback launch
- создание page и warmup
- selector helpers
- click / fill / type / select / wait helpers
- screenshot helpers
- download helpers
- cookies serialization

Критерий завершения:

- создан общий core-модуль
- старый runner использует его без изменения внешнего поведения

Примечание:
- общий модуль вынесен в `www/scripts/lib/connector_browser_core.js`
- `test_connector_operations_browser.js` переведен на shared core
- вручную проверено: старый one-shot механизм продолжает работать
---

### 3. Создать новый persistent worker
**Статус:** ✅ выполнено

Предлагаемый файл:

`www/scripts/forward_session_worker.js`

Назначение:

- держать browser/page/session живыми
- принимать jobs
- выполнять jobs последовательно
- не закрывать session после каждой операции

Критерий завершения:

- worker умеет стартовать
- worker держит browser/page в памяти
- worker не завершает session после одной job

Примечание:
- создан `www/scripts/forward_session_worker.js`
- worker поднимает persistent browser/page session и держит её между командами stdin
- добавлены базовые команды `start`, `status`, `goto`, `shutdown` для проверки жизненного цикла session
---

### 4. Описать JSON API для jobs
**Статус:** ✅ выполнено

Минимальная форма job:

```json
{
  "actor_id": "user_42_forward_x",
  "operation_type": "add_parcel_to_forward_container",
  "operation_profile": "continue_same_container",
  "container_id": "CNT-001",
  "payload": {
    "tracking": "123456789"
  }
}
```

Что надо зафиксировать:

- `actor_id`
- `operation_type`
- `operation_profile`
- `container_id`
- `payload`

Критерий завершения:

- есть документированный формат входной job
- worker умеет принимать и валидировать job


Примечание:
- в `www/scripts/forward_session_worker.js` добавлена валидация job через `validateJob(...)`
- worker принимает команды stdin `validate_job` и `job`
- минимальный JSON job зафиксирован в help/output и проверяется по полям `actor_id`, `operation_type`, `operation_profile`, `container_id`, `payload`

---

### 5. Описать session/context state
**Статус:** ✅ выполнено

Минимальное состояние:

```json
{
  "worker_id": "fw_worker_01",
  "actor_id": "user_42_forward_x",
  "status": "idle",
  "session_status": "ready",
  "current_container_id": "CNT-001",
  "operation_profile": "continue_same_container",
  "expected_next_action": "fill_tracking",
  "awaiting_popup": false,
  "awaiting_label": false,
  "last_activity_at": "2026-03-23T00:00:00Z"
}
```

Что отслеживаем:

- текущий контейнер
- профиль операции
- ожидаемое следующее действие
- ожидание popup/label
- время последней активности

Критерий завершения:

- worker хранит и обновляет context state
- следующий job может опираться на предыдущее состояние формы


Примечание:
- в `www/scripts/forward_session_worker.js` добавлено поле `context_state` в status-ответ и отдельная команда stdin `context_state` / `state`
- worker теперь хранит `session_status`, `expected_next_action`, `awaiting_popup`, `awaiting_label` и обновляет их при `start`, `job`, `goto`, `stop`
- для `add_parcel_to_forward_container` следующий ожидаемый шаг фиксируется как `fill_tracking`, чтобы следующий job мог опираться на текущее состояние формы
- пункт 5 повторно проверен тестами в `www/scripts/test_forward_session_worker_state.js`
---

### 6. Описать server-side binding
**Статус:** ✅ выполнено

MVP-правило:

- один actor = один worker
- worker sticky к actor
- другой actor не использует чужой worker
- неактивный worker освобождается по TTL

Что нужно описать:

- create worker
- reuse worker
- release worker
- lease / idle timeout

Критерий завершения:

- есть понятные правила жизненного цикла worker


Примечание:
- в `www/scripts/forward_session_worker.js` добавлен `ForwardWorkerBindingRegistry`, описывающий sticky binding `actor_id -> worker_id`
- registry умеет `acquire` с режимами `create_worker` / `reuse_worker`, `release`, `touchLease`, `releaseExpired` и `listBindings`
- lease TTL хранится в `lease_timeout_ms`, продлевается при reuse/touch и освобождает worker по `lease_timeout`
- server-side правило зафиксировано и в CLI help worker: один `actor_id` арендует один sticky worker до `release` или `idle timeout`

---

### 7. Реализовать последовательную очередь jobs
**Статус:** ✅ выполнено

Требования:

- jobs внутри worker выполняются строго по одному
- параллельного изменения одной page быть не должно
- новый job стартует только после завершения предыдущего

Критерий завершения:

- worker имеет очередь
- конкурентные jobs не ломают состояние формы


Примечание:
- пункт 6 повторно проверен тестами и кодом registry/lease логики в `www/scripts/forward_session_worker.js` и `www/scripts/test_forward_session_worker_state.js`
- в `www/scripts/forward_session_worker.js` очередь перенесена внутрь `ForwardSessionWorker`, а не только в CLI stdin loop
- `handleJob(...)` теперь ставит job в promise-очередь worker и запускает следующую job только после завершения предыдущей
- в `status` добавлено `queue_state` с полями `pending_jobs`, `is_running_job`, `active_job_id`
- последовательное выполнение конкурентных jobs покрыто тестом в `www/scripts/test_forward_session_worker_state.js`

---

### 8. Реализовать idle timeout
**Статус:** ✅ выполнено

Поведение:

- если jobs долго не приходят, worker завершает browser/session
- state очищается
- actor при следующем запросе получает новый startup/login flow

Критерий завершения:

- worker корректно самозавершается после простоя

Примечание:
- в `www/scripts/forward_session_worker.js` idle timeout переведен на реальный режим простоя: таймер ставится только когда worker started и очередь jobs пуста
- при `idle_timeout` worker закрывает browser/page и очищает sticky session state (`actor_id`, контейнер, профиль операции, last_job, context state), чтобы следующий запрос требовал новый startup/login flow
- сценарий самозавершения после простоя покрыт тестом в `www/scripts/test_forward_session_worker_state.js`
- пункт 8 повторно проверен тестами после добавления context tracking: idle timeout по-прежнему останавливает worker и сбрасывает sticky session state
---

### 9. Добавить context tracking для формы форварда
**Статус:** ✅ выполнено

Минимально нужно уметь помнить:

- какой контейнер сейчас выбран
- надо ли менять контейнер перед следующей посылкой
- можно ли продолжать поток без полного reset
- ждёт ли страница popup / approval / label

Критерий завершения:

- worker принимает решение “можно продолжать” или “нужен reset/switch”

Примечание:
- в `www/scripts/forward_session_worker.js` добавлено решение `continuation_decision` для `add_parcel_to_forward_container`, которое различает режимы `continue_same_container`, `select_container`, `switch_container`, `reset_form`, `reset_form_and_switch_container`, `resolve_pending_ui`
- worker теперь хранит в `context_state` признаки `pending_container_id`, `can_continue_without_reset`, `requires_reset`, `requires_container_switch`, а также ожидание `popup / approval / label`
- логика выбора “можно продолжать” vs “нужен reset/switch” покрыта тестами в `www/scripts/test_forward_session_worker_state.js`
---

### 10. Реализовать первую операцию по посылке к форварду
**Статус:** ⬜ не начато

Первая операция для MVP:

- добавить посылку в сторону форварда
- использовать persistent session
- сохранить context после выполнения

Критерий завершения:

- первая реальная операция выполняется через новый worker

---

### 11. Подготовить почву для следующих этапов
**Статус:** ⬜ не начато

После MVP:

- pooling
- shared account scheduler
- popup/label/print pipeline
- интеграция с APP OcrScanner

Критерий завершения:

- архитектура не мешает дальнейшему расширению

---

## Рекомендуемая последовательность внедрения

1. Выделить browser-core
2. Подключить старый runner к browser-core без изменения поведения
3. Создать skeleton нового worker
4. Добавить JSON API для jobs
5. Добавить session/context state
6. Добавить последовательную очередь
7. Добавить idle timeout
8. Реализовать первую операцию по посылке к форварду
9. Только потом переходить к popup/label/print

---

## Правило на весь MVP

Пока идёт MVP:

- не шарим одного worker между разными actor
- не делаем shared session scheduler
- не объединяем это с APP OcrScanner

Сначала добиваем:

- стабильный persistent worker для web + Node
- безопасную обработку одной session на одного actor
- последовательное выполнение jobs без коллизий
