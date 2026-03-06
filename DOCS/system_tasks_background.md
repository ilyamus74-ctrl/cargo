# System tasks / background jobs

## Что реализовано

- Добавлен раздел **"Задания системы"** (`action: system_tasks`) с CRUD задач.
- Введены таблицы:
  - `system_tasks`
  - `system_task_runs`
  - `warehouse_sync_batch_jobs`
  - `warehouse_sync_batch_job_items`
- Кнопка `all_sync` теперь ставит пакетную синхронизацию в очередь (`warehouse_sync_batch_enqueue`), а не выполняет цикл в браузере.
- Добавлен cron-раннер: `www/scripts/cron/system_tasks_runner.php`.
- Seed по умолчанию создаёт задания:
  - `operation_1_hourly` (`endpoint_action=operation_1`, 60 мин)
  - `warehouse_sync_batch_worker` (`endpoint_action=warehouse_sync_batch_worker`, 1 мин)
  - `warehouse_sync_reconcile_half_sync_30m` (`endpoint_action=warehouse_sync_reconcile`, 30 мин)

## Как включить cron

Пример crontab (раз в минуту):

```cron
* * * * * /usr/bin/php /path/to/project/www/scripts/cron/system_tasks_runner.php >> /var/log/system_tasks_runner.log 2>&1
```

Да — именно так и запускать: добавить `system_tasks_runner.php` в cron на каждую минуту.

Практический чеклист:

1. Проверить путь к PHP:

```bash
which php
```

2. Проверить одноразовый запуск вручную (под тем же пользователем, что будет в cron):

```bash
/usr/bin/php /path/to/project/www/scripts/cron/system_tasks_runner.php
```

3. Добавить задание в cron:

```bash
crontab -e
```

```cron
* * * * * /usr/bin/php /path/to/project/www/scripts/cron/system_tasks_runner.php >> /var/log/system_tasks_runner.log 2>&1
```

4. Проверить лог:

```bash
tail -f /var/log/system_tasks_runner.log
```

Ожидаемые строки:
- `[system_tasks_runner] ran=... errors=...`
- или `[system_tasks_runner] lock busy, skip` (нормально, если второй запуск попал на работающий предыдущий).

## Как добавить новое задание

В меню **"Задания системы"**:
1. Указать `code`, `name`, `endpoint_action`, `interval_minutes`.
2. Включить `Разрешено к выполнению`.
3. Сохранить.

> Для нового типа endpoint добавьте обработку в `system_tasks_execute()`.


### Пример для кнопки `reconcile half_sync`

Чтобы перенести действие кнопки `warehouse_sync_reconcile` в фон:

- `endpoint_action`: `warehouse_sync_reconcile`
- `interval_minutes`: `30`
- `is_enabled`: `1`
- `payload_json` (опционально): `{"limit":200}`

Важно: сам `system_tasks_runner.php` должен оставаться в cron **каждую минуту**.
Период в 30 минут контролируется полем `interval_minutes` у задачи, а не расписанием cron
