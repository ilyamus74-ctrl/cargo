# Connectors: Node/PHP execution map and migration strategy

## Goal
Свернуть использование Node в коннекторах без удаления самого механизма (чтобы можно было вернуть при необходимости).

## Where Node is used now
1. **Browser report download** (`download_mode=browser`) via `scripts/test_connector_operations_browser.js`.
2. **Submission test** (operation #2 UI test flow) via the same browser Node script.
3. **`kind=browser_steps`** operations via browser Node script.
4. **`kind=script` with `interpreter=node`** for custom JS scripts.

All these flows are in `connector_actions.php`.

## What was changed
Added centralized policy gate:
- `connectors_is_node_execution_enabled()`
- `connectors_assert_node_execution_allowed()`

And wired it into all Node launch points listed above.

### Environment switch
- `CONNECTORS_ALLOW_NODE=0` disables all Node execution paths with explicit runtime errors and recommendation to use PHP modes.
- If variable is absent, Node remains enabled (backward compatible).

## Practical migration path (recommended)
1. For each connector, create/validate `report_php` (or `kind=script` + `interpreter=php`) with `entrypoint=true`.
2. Keep legacy Node operations in config as non-entrypoint fallback.
3. Switch scheduler to entrypoint-aware selection (already done in codebase).
4. Set `CONNECTORS_ALLOW_NODE=0` in environment when all critical connectors are on PHP path.
5. Monitor `connector_operation_runs` for any residual Node-dependent failures.
6. If needed in future, rollback is one env change: `CONNECTORS_ALLOW_NODE=1`.

## Why this approach
- Keeps architecture extensible.
- Avoids hard-deleting Node-specific logic.
- Gives reversible operational control through environment, not code rollback.
