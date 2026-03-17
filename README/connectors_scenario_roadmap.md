
ДОРОБОТКА
ниже отмеченно

    *   - Сделанно/готово
    **  - Сделанно частично
    *** - Не сделанно

От тебя мй дорогой друг CODEX прошу после задания давать статус если он выполнен или нет
 
ниже план

План доработки (UI + backend + совместимость)

* - 0) Цели релиза (зафиксированы)
    Перейти от фиксированных report/submission/track_and_label_info к динамическому списку операций.
    Добавить в модель операции:
        module
        action
        kind
        display_name
    Сохранить backward compatibility со старым operations_json.
    Подготовить основу для рейсовых сценариев (flights).
Текущая база для этого уже есть: операции/зависимости/entrypoint/политики/план выполнения
    Статус фиксации целей: выполнено (release baseline принят).
    Критерии контроля на релиз:
        1) Новый контракт описывает динамический массив operations.
        2) У каждой операции есть display_name/module/action/kind.
        3) Старые payload открываются через миграцию old -> v3 (read-compat).
        4) В roadmap есть явный этап по flights-шаблонам и execution.

* - 1) Новый JSON-контракт операций (v3)
* - 1.1. Структура

Сделать основной формат: 
{
  "schema_version": 3,
  "operations": [
    {
      "operation_id": "report_sync",
      "display_name": "Синхронизация отчета",
      "module": "warehouse",
      "action": "warehouse_sync_item",
      "kind": "api_call",
      "enabled": 1,
      "entrypoint": 1,
      "on_dependency_fail": "stop",
      "run_after": [],
      "run_with": [],
      "run_finally": [],
      "config": {}
    }
  ]
}


Правила структуры:

    schema_version — обязательно, фиксировано = 3.
    operations — обязательно, массив операций (минимум 1 для исполняемого сценария).
    operation_id — обязательно, уникально в рамках operations.
    display_name — обязательно, человекочитаемое имя операции для UI/логов.
    module/kind/action — системная привязка операции к исполнителю.
    run_after/run_with/run_finally — всегда массивы operation_id (допускается пустой массив).
    config — всегда JSON-объект (допускается пустой объект {}).

* - 1.2. Справочники

    module: warehouse | connectors | devices | tools | users | system | generic
    kind: api_call | browser_steps | script | noop
    Правило: если модуль не выбран, то module = generic, kind = browser_steps

    Нормализация при сохранении:
        module = trim(lowercase(module));
        kind = trim(lowercase(kind));
        если module пустой/null => module = generic;
        если module = generic и kind пустой/null => kind = browser_steps.
    Валидация:
        module должен входить в справочник module.
        kind должен входить в справочник kind.
        при module != generic и kind = api_call поле action обязательно.
    Статус: выполнено.
    

* - 1.3. Почему так

Роутер уже модульный (action -> api/...), это естественно ложится в контракт v3:
    module/action/kind становятся явной связкой, которая повторяет фактическую маршрутизацию в core_api.
    Это снижает риск рассинхронизации между UI payload и backend-исполнением.

    Что сделано в коде:
        добавлена валидация action через реестр action -> module, извлекаемый из core_api.php;
        для kind=api_call + module!=generic теперь проверяется, что action существует в роутере и относится к тому же module;
        ошибки валидации отдаются до сохранения operations_json, чтобы не сохранять неконсистентный контракт.
    Статус: выполнено.

* - 2) UI: вкладки как динамический список + “+”
* - 2.1. Новый UX

Вместо жестких вкладок #1/#2/#3:
    слева/сверху список вкладок операций;
    последняя вкладка-кнопка + “Добавить операцию”;
    при добавлении создается новая операция с дефолтом:
        display_name = "Новая операция"
        operation_id = "op_<n>"
        module = "generic"
        kind = "browser_steps"
        enabled = 0, entrypoint = 0
        зависимости пустые.
Сейчас вкладки захардкожены — это как раз меняем

Реализация в коде:
    - шаблон формы операций переведен на динамический рендер вкладок из `operations_v3_json`;
    - добавлена вкладка-кнопка `+ Добавить операцию`;
    - добавление создает default-операцию (`display_name="Новая операция"`, `operation_id="op_<n>"`, `module="generic"`, `kind="browser_steps"`, `enabled=0`, `entrypoint=0`, пустые зависимости);
    - перед сохранением выполняется клиентская проверка (уникальность operation_id, валидный JSON для run_*/config, ссылки только на существующие operation_id).
Статус: выполнено.

* - 2.2. Карточка операции (единая форма)

Поля:
    Основные: display_name, operation_id, enabled, entrypoint, on_dependency_fail
    Связь с системой: module, kind, action
    Зависимости: run_after, run_with, run_finally (JSON-массив operation_id)
    config (JSON объект) — универсальное поле для параметров.
Реализация в коде:
    - карточка операции в модалке собрана в единую форму с секциями: "Основные", "Связь с системой", "Зависимости", "Параметры";
    - сохранены все обязательные поля контракта v3: `display_name`, `operation_id`, `enabled`, `entrypoint`, `on_dependency_fail`, `module`, `kind`, `action`, `run_after`, `run_with`, `run_finally`, `config`;
    - подписи для зависимостей явно фиксируют ожидаемый формат: JSON-массив `operation_id`.
Статус: выполнено.

* - 2.3. UI-валидации

    operation_id уникален (на клиенте до отправки).
    module != generic + kind = api_call => action обязателен.
    generic + пустой module => автозаполнение module=generic, kind=browser_steps.
    run_* должны содержать существующие operation_id.
Реализация в коде:
    - клиентская сборка payload проверяет уникальность `operation_id` до отправки формы;
    - при `module != generic` и `kind=api_call` `action` обязателен, иначе сохранение блокируется;
    - если `module` пустой, UI принудительно нормализует операцию к `module=generic`, `kind=browser_steps`;
    - `run_after/run_with/run_finally` валидируются как JSON-массивы и дополнительно проверяются на ссылки только на существующие `operation_id`.
Статус: выполнено.

* - 3) Backend: универсальный parser/save/validate
* - 3.1. Новый путь обработки формы

Заменить сборку payload “по 3 фиксированным операциям” на цикл по массиву операций.
Сейчас сборка фиксирована и завязана на поля report_*, submission_*, track_and_label_info_*.

Статус: выполнено (legacy-путь сохранен, но backend-сборка переведена на универсальный цикл по массиву описаний операций).

* - 3.2. Валидация

Расширить текущую runtime-валидацию:

    уникальность operation_id;
    корректность ссылок в зависимостях;
    контроль циклов (у вас уже есть граф и топосорт);
    проверка module/kind/action по правилам.

Граф исполнения уже реализован, можно переиспользовать.


Реализация в коде:
    - добавлена явная проверка дублей `operation_id` на уровне исходного `operations_v3_json` с указанием индексов конфликтующих операций;
    - runtime-валидация ссылок в `run_after/run_with/run_finally` оставлена обязательной (существование ссылки, запрет self-link, запрет ссылок на disabled операции);
    - контроль циклов переведен на существующий topological sort (`connectors_topological_sort_operations`) через переиспользование графа зависимостей;
    - проверка `module/kind/action` выполняется для каждой операции по справочникам и registry роутера `core_api.php` до сохранения.
Статус: выполнено.


* - 3.3. Совместимость

    На чтении:
        если старый формат (report/submission/...) — мигрировать в v3 в памяти;
    На сохранении:
        писать v3;
    Временный fallback:
        если нужен старый тест-раннер, строить “compat view” для report/submission
Реализация в коде:
    - миграция old -> v3 выполняется при чтении `operations_json` в памяти (без немедленной записи в БД);
    - сохранение `save_connector_operations` всегда сериализует и пишет payload формата v3;
    - для legacy test-runner добавлен compat view: из v3-пакета в памяти собираются `report/submission/track_and_label_info` для существующих тестовых процедур.
Статус: выполнено.

* - 4) Реестр действий по модулям (для dropdown action)

Сделать backend endpoint (или встроенный payload в форму), который отдает:
{
  "warehouse": ["warehouse_sync_item", "warehouse_sync_reports", ...],
  "connectors": ["test_connector_operations", ...],
  ...
}
Источник данных можно взять из существующего $routes в core_api.php (группировка по пути handler).

UI:

    при выборе module фильтрует список action;
    при module=generic поле action не обязательно.

Реализация в коде:
    - добавлен internal endpoint `get_module_actions_registry` в `core_api.php`, который строит registry из `$routes` и группирует actions по module (по пути handler `api/<module>/...`);
    - в UI конструктора операций поле `module` переведено на `select`, поле `action` переведено на `select` и динамически фильтруется при смене module;
    - для `module=generic` `action` можно оставить пустым (и это явно подсвечено в форме).
Статус: выполнено.
    
* - 5) Исполнение операций по kind

Минимальная стратегия:

    api_call
    Выполнить внутренний вызов через CoreAPI/handler по action.
    browser_steps
    Выполнить шаги из config.steps (логин, формы, клики и т.д.).
    script
    Выполнить скрипт из config.script_path (с ограничением interpreter/timeout).
    noop
    Технический узел графа.

Реализация в коде:
    - в UI-конструкторе для каждой операции явно задается `kind` (`api_call | browser_steps | script | noop`) и сохраняется в `operations_v3_json`;
    - добавлена клиентская нормализация перед сохранением: для пустого `module` операция приводится к `module=generic`, `kind=browser_steps`;
    - сохранена серверная валидация контракта `module/kind/action`:

      - `kind` проверяется по реестру,
      - `module != generic + kind=api_call` требует `action`,
      - `action` дополнительно сверяется с роутером `core_api.php` и модулем handler.
    - в runtime manual test реализован универсальный dispatcher по `kind`:

      - `api_call` исполняется через handler action,
      - `browser_steps` исполняет единый движок шагов из `operation.config.steps` (+ optional login steps),
      - `script` исполняет `operation.config.script_path` (поддержка `bash|sh|node|php|python3`, timeout),
      - `noop` логируется и пропускается.
    - manual test теперь исполняет не только entrypoint, а весь execution plan по стадиям: `before -> main -> during_groups -> after`.
Статус: выполнено.

* - 6) Рейсы: стартовый набор operation templates

Добавить пресеты в UI “Создать из шаблона”:

    flights_list_fetch — “Получить список рейсов”
    flight_upsert — “Создать/обновить рейс”
    flight_containers_create — “Создать контейнеры рейса”

Для каждого шаблона:

    display_name
    module/action/kind
    пример config.

Это позволит быстрее раскатывать сценарии без ручного JSON.


Реализация в коде:
    - в модалку операций добавлен UI-блок **«Создать из шаблона»**;
    - добавлены пресеты:
      - `flights_list_fetch` — «Получить список рейсов» (`module=warehouse`, `action=warehouse_sync_reports`, `kind=api_call`);
      - `flight_upsert` — «Создать/обновить рейс» (`module=warehouse`, `action=warehouse_sync_item`, `kind=api_call`);
      - `flight_containers_create` — «Создать контейнеры рейса» (`module=warehouse`, `action=warehouse_sync_batch_enqueue`, `kind=api_call`);
    - для каждого пресета подставляется пример `config` и автоматически генерируется уникальный `operation_id` на базе шаблона.
Статус: выполнено.

7) Этапы внедрения (рекомендуемый порядок)
Этап A — Data model + migration (без изменения UI)

    Ввести v3-контракт.
    Реализовать миграцию old->v3.
    Сохранить существующий экран рабочим.

Этап B — Новый UI-конструктор

    Динамические вкладки + кнопка +.
    Единая карточка операции.
    Базовая клиентская валидация.

Этап C — Module/Action intelligence

    Реестр действий по модулям.
    Автоподстановка/валидация module/action/kind.

Этап D — Execution for flights

    Добавить рейсовые action/handlers.
    Подключить шаблоны операций рейсов.

Этап E — Cleanup

    Убрать legacy-поля из формы (report_*, submission_* и т.д.).
    Оставить только универсальный массив операций.

8) Критерии готовности (Definition of Done)

    Можно добавить N операций через + без изменения шаблона.
    Операция имеет display_name/module/action/kind.
    Зависимости валидируются, циклы ловятся.
    generic + browser_steps работает для логина.
    Есть минимум 3 шаблона рейсовых операций.
    Старые коннекторы открываются и не ломаются (миграция корректна).

9) Риски и как снизить

    Риск: сломать старые конфиги.
    Мера: миграция only-on-read + автотест на типовые старые payload.
    Риск: некорректные action от пользователя.
    Мера: whitelist по модулю из реестра.
    Риск: UX перегружается.
    Мера: “базовый режим” (простые поля) + “расширенный JSON”.



10) Flight-list: альтернативный путь через subrunners (рекомендуется)

Контекст:

    Для разных коннекторов слишком разная структура страниц и бизнес-правила.
    Полностью универсальный декларативный mapping/extract DSL может усложнить систему,
    увеличить количество edge-cases и сделать отладку тяжелее.

Предложение:

    Вынести прикладной процессинг в отдельный исполнительный слой:

        /api/connectors/subrunners/connector_modules.php

    Где для каждой задачи (или типа задачи) есть явная функция-обработчик.

Идея архитектуры (гибрид):

    - `operations_v3_json` продолжает хранить оркестрацию (steps, зависимости, entrypoint, run_after и т.д.),
    - извлечение/парсинг/запись в БД выполняет `subrunner`,
    - browser steps отвечают только за “дойти до нужного состояния страницы”.

Минимальный контракт операции для такого подхода:

    {
      "operation_id": "flight_list",
      "display_name": "Получить список рейсов",
      "module": "connectors",
      "kind": "browser_steps",
      "action": "connectors_run_subrunner",
      "config": {
        "steps": [ ... ],
        "subrunner": {
          "name": "flight_list_colibri",
          "options": {
            "table_selector": "#flights_table",
            "timezone": "Asia/Baku",
            "write_mode": "upsert"
          }
        }
      }
    }

Как это работает в runtime:

    1) Выполняются `config.steps` (логин, переходы, ожидания).
    2) После успешных шагов вызывается subrunner по `subrunner.name`.
    3) Subrunner получает browser context + options и делает:
       - парсинг нужной таблицы,
       - нормализацию данных,
       - валидацию,
       - upsert/insert в локальную БД коннектора.
    4) Возвращает структурированный результат (rows_extracted, rows_written, errors).

Почему это лучше для “сложных” коннекторов:

    - меньше магии в JSON и меньше хрупких универсальных правил;
    - проще отладка (точка входа — конкретная функция);
    - проще писать кастомные исключения под сайт/поставщика;
    - быстрее вносить hotfix без ломки общей схемы.

Рекомендации по `connector_modules.php`:

    - Реестр обработчиков:
      `flight_list_colibri => run_flight_list_colibri`,
      `flight_list_vendor_x => run_flight_list_vendor_x`, ...
    - Единый интерфейс функции:
      `function run_x(array $ctx, array $options): array`.
    - Единый формат ответа:
      {
        "status": "ok|error",
        "message": "...",
        "metrics": {
          "rows_extracted": 0,
          "rows_written": 0,
          "rows_skipped": 0
        },
        "errors": []
      }

Что оставить в UI:

    Для `kind=browser_steps` добавить не сложный DSL, а 2 поля:
    - `subrunner.name` (select из реестра),
    - `subrunner.options` (JSON object).

    Это даст баланс: UI остается универсальным, а логика — расширяемой кодом.

Что проверить на backend:

    1. Валидация `subrunner.name` по whitelist/registry.
    2. Защита от запуска неразрешенных функций.
    3. Таймауты и лимиты на обработку.
    4. Транзакционность записи в БД там, где нужно.
    5. Понятный лог успеха/ошибки без hardcoded “Файл успешно скачан...”.

Совместимость:

    - Если `subrunner` не задан, сценарий работает как текущий browser_steps.
    - Старые операции не ломаются.
    - Новые сложные операции идут через subrunner-подход.

11) Runtime stack: Node.js + PHP или только PHP?

Короткий ответ:

    Не обязательно делать жесткую связку Node.js + PHP.
    Базовый и предпочтительный путь для текущей архитектуры — PHP-оркестратор + subrunners в PHP.

Рекомендуемый baseline:

    - PHP (core_api + connectors runtime) управляет графом операций,
    - browser steps и вызов subrunner идут из существующего PHP-пайплайна,
    - subrunner-функции реализуются в `/api/connectors/subrunners/connector_modules.php`.

Когда добавлять Node.js имеет смысл:

    - если нужен тяжелый браузерный scraping/JS-eval на сложных SPA,
    - если требуются npm-библиотеки, которых нет/неудобно в PHP,
    - если нужны отдельные worker-процессы для долгих задач.

Тогда схема гибридная:

    - PHP остается orchestrator/source of truth,
    - Node.js запускается как опциональный execution backend (worker/service) для конкретных subrunner-задач,
    - контракт между PHP и Node — через явный task payload + structured result.

Минимальный принцип выбора backend для subrunner:

    `subrunner.backend = "php" | "node"` (по умолчанию `php`).

Практический вывод для текущего этапа:

    1. Стартуем с PHP-only (быстрее внедрить и проще сопровождать).
    2. Сохраняем точку расширения под Node без обязательной миграции всего runtime.
    3. Подключаем Node точечно только там, где есть явная техническая выгода.
