# План переезда с `steps_browser` (Node) на PHP для connector-операций форвардеров

> Версия: 2026-03-29  
> Цель: безопасно перевести операции форвардеров на PHP entrypoint без регрессий для разных коннекторов.

---

## 1. Ключевая позиция (мнение)

**Не делать “big-bang” миграцию всех коннекторов сразу.**  
Правильная стратегия: **двойной рантайм + feature flags + поэтапный rollout по каждому форвардеру/операции**.

Почему:
- у коннекторов разные HTML-формы, методы submit и правила авторизации;
- одинаковый operation id может иметь разные “подводные камни” у разных форвардеров;
- без пер-коннекторного rollout сложно быстро локализовать регрессии.

---

## 2. Что мигрируем в первую очередь

Приоритет операций (от безопасных к рискованным):

1. `flight_list_php` (read-only, лучший кандидат для старта)
2. `add_container_to_flight_php`
3. `delete_container_php`
4. `add_flight_php`
5. `edit_flight` (php script)
6. `delete_flight_php` (часто самая чувствительная операция из-за вариантов delete-механики)

---

## 3. Архитектурный принцип: dual-runtime

Для каждого operation id держим:
- Node-ветку (`steps_browser`/legacy),
- PHP-ветку (`kind=script`, `interpreter=php`),
- и явный механизм выбора entrypoint.

Рекомендуемый порядок выбора:
1. Явный `entrypoint_mode` из запроса (если задан),
2. правило по `operation_id` (например `_php`),
3. connector-level policy (feature flag / rollout group),
4. fallback по диагностируемой причине (с обязательной трассировкой).

---

## 4. Матрица миграции по коннекторам

Для каждого `connector_id` вести таблицу:

- `connector_id`
- `forwarder_name`
- `country/region`
- операции-кандидаты
- current runtime (`node`/`php`)
- target runtime (`php`)
- rollout stage (`canary`, `10%`, `50%`, `100%`)
- last error rate
- known quirks (csrf, form method, custom selectors, delete semantics)

Рекомендация: хранить матрицу в отдельной техтаблице или в конфиге, но не в шаблонах UI.

---

## 5. Пошаговый rollout

## Шаг 0. Инвентаризация
- Собрать фактические operation-конфиги всех коннекторов.
- Нормализовать placeholders и обязательные runtime vars.
- Проверить, что для `kind=script` всегда заполнен `config.script_path`.

## Шаг 1. Контракт переменных
- Единый контракт: Template/UI → `buildFlightRuntimeVars()` → operation args → script parser.
- Проверить coverage placeholders в `connectors_expand_script_arg_placeholders()`.
- Запретить merge operation-args с неподдерживаемыми `{{...}}` placeholders (pre-flight validation).

## Шаг 2. Read-only сначала
- Перевести `flight_list` на `flight_list_php` для canary-коннекторов.
- Сверять Node vs PHP результаты по:
  - количеству рейсов,
  - ключевым полям статуса/контейнеров,
  - времени ответа.

## Шаг 3. Write-операции контейнеров
- Включить `add_container_to_flight_php`, затем `delete_container_php`.
- Для delete обязательно:
  - retry strategy (`DELETE` + `POST _method=DELETE`),
  - verify post-state (контейнер исчез у форвардера),
  - синхронизация БД + строгий `sync_db_status`.

## Шаг 4. Write-операции рейсов
- `add_flight_php`, `edit_flight`, `delete_flight_php` включать отдельно и постепенно.
- Для delete_flight вести отдельные адаптеры (link-delete vs API delete), если форвардеры различаются.

## Шаг 5. Декомиссия Node
- Только после стабильности по метрикам:
  - ошибка < целевого порога,
  - отсутствие high-severity инцидентов N дней,
  - полное покрытие smoke/regression сценариев.

---

## 6. Наблюдаемость и диагностика (must-have)

На каждом запуске операции сохранять:
- `run_id`, `connector_id`, `operation_id`, `entrypoint_mode`, `resolved_entrypoint_operation`,
- `script.args_expanded_masked`,
- `entrypoint_diagnostics`,
- `trace_log`, `graph_errors`,
- итоговые `sync_db_*` поля.

Для write-операций:
- факт изменения у форвардера (post-state verify),
- факт изменения локальной БД.

---

## 7. Правила безопасности rollout

- **Canary list**: сначала 1-2 “прозрачных” коннектора.
- Авто-откат на Node при превышении error budget.
- Нельзя включать `100%` без промежуточных стадий.
- Любая деградация delete/add операций блокирует следующий этап rollout.

---

## 8. Что обязательно править при новых задачах

1. `templates/*` (если меняются `data-*` параметры кнопок)
2. `www/js/core_api.js` (`buildFlightRuntimeVars`, `triggerPlaceholderOperation`)
3. `www/api/connectors/connector_actions.php`
   - operation template,
   - placeholder expansion,
   - runtime validation.
4. `www/scripts/mvp/app/Forwarder/run_*.php`
   - parse args,
   - HTTP flow,
   - verify результата,
   - diagnostics JSON.
5. `sync_kernel.php` / DB sync логика (если write-операция меняет state).

---

## 9. Технический backlog после старта миграции

- Pre-flight validator для operation-конфигов:
  - `kind=script => script_path required`,
  - все placeholders из args должны поддерживаться в expansion map.
- Автотест “placeholder completeness” для script args.
- Golden tests: сравнение Node vs PHP результатов для `flight_list`.
- Набор smoke сценариев per connector для add/delete container/flight.

---

## 10. Критерии готовности к финальному отключению Node

- Все целевые операции на PHP для всех активных коннекторов.
- Нет блокирующих расхождений Node vs PHP по read-model.
- Write-потоки (add/delete) подтверждают post-state у форвардера и в локальной БД.
- 2–4 недели стабильной эксплуатации без критических инцидентов.
