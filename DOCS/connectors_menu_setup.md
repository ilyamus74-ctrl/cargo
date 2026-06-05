# Добавление пункта меню Connectors (DB)

Ниже SQL для добавления пункта меню **Connectors** в группу `settings` и выдачи доступа роли `ADMIN`.

```sql
-- Добавить пункт меню в settings
INSERT INTO menu_items (menu_key, group_code, title, icon, action, sort_order, is_active)
VALUES ('settings.connectors', 'settings', 'Connectors', 'bi bi-plug', 'view_connectors', 30, 1)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    icon = VALUES(icon),
    action = VALUES(action),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

-- Разрешить пункт меню для роли ADMIN
INSERT INTO role_menu (role_code, menu_key, is_allowed)
VALUES ('ADMIN', 'settings.connectors', 1)
ON DUPLICATE KEY UPDATE
    is_allowed = VALUES(is_allowed);
```

> Примечание: если у вас другой `sort_order` для группы Settings, отрегулируйте значение в `menu_items`.
