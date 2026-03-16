
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

*** - 1) Новый JSON-контракт операций (v3)
*** - 1.1. Структура

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

*** - 1.2. Справочники

    module: warehouse | connectors | devices | tools | users | system | generic
    kind: api_call | browser_steps | script | noop
    Правило: если модуль не выбран, то module = generic, kind = browser_steps
    
*** - 1.3. Почему так

Роутер уже модульный (action -> api/...), это естественно ложится в контрак

*** - 2) UI: вкладки как динамический список + “+”
*** - 2.1. Новый UX

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

*** - 2.2. Карточка операции (единая форма)

Поля:
    Основные: display_name, operation_id, enabled, entrypoint, on_dependency_fail
    Связь с системой: module, kind, action
    Зависимости: run_after, run_with, run_finally (JSON-массив operation_id)
    config (JSON объект) — универсальное поле для параметров.

*** - 2.3. UI-валидации

    operation_id уникален (на клиенте до отправки).
    module != generic + kind = api_call => action обязателен.
    generic + пустой module => автозаполнение module=generic, kind=browser_steps.
    run_* должны содержать существующие operation_id.

*** - 3) Backend: универсальный parser/save/validate
*** - 3.1. Новый путь обработки формы

Заменить сборку payload “по 3 фиксированным операциям” на цикл по массиву операций.
Сейчас сборка фиксирована и завязана на поля report_*, submission_*, track_and_label_info_*.

*** - 3.2. Валидация

Расширить текущую runtime-валидацию:

    уникальность operation_id;
    корректность ссылок в зависимостях;
    контроль циклов (у вас уже есть граф и топосорт);
    проверка module/kind/action по правилам.

Граф исполнения уже реализован, можно переиспользовать.

*** - 3.3. Совместимость

    На чтении:
        если старый формат (report/submission/...) — мигрировать в v3 в памяти;
    На сохранении:
        писать v3;
    Временный fallback:
        если нужен старый тест-раннер, строить “compat view” для report/submission

*** - 4) Реестр действий по модулям (для dropdown action)

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
    
*** - 5) Исполнение операций по kind

Минимальная стратегия:

    api_call
    Выполнить внутренний вызов через CoreAPI/handler по action.
    browser_steps
    Выполнить шаги из config.steps (логин, формы, клики и т.д.).
    script
    Зарезервировать интерфейс (пока можно disabled/feature-flag).
    noop
    Технический узел графа.

*** - 6) Рейсы: стартовый набор operation templates

Добавить пресеты в UI “Создать из шаблона”:

    flights_list_fetch — “Получить список рейсов”
    flight_upsert — “Создать/обновить рейс”
    flight_containers_create — “Создать контейнеры рейса”

Для каждого шаблона:

    display_name
    module/action/kind
    пример config.

Это позволит быстрее раскатывать сценарии без ручного JSON.

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

