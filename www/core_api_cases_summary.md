# core_api.php switch case overview

This document summarizes the remaining `case` branches in `www/core_api.php`, grouped by theme, with their responses and notable dependencies useful for future module work.

## Common dependencies

* Includes: `bootstrap.php`, `api/core_helpers.php`, `api/user_actions.php`. Global services such as `$dbcnx` (database) and `$smarty` (templates) are used throughout the switch, along with the authenticated `$user`.【F:www/core_api.php†L5-L22】
* Authentication helpers: `auth_require_login()`, `auth_require_role()`, `auth_current_user()`, `auth_has_role()` are invoked to guard endpoints and retrieve the current user.【F:www/core_api.php†L9-L23】【F:www/core_api.php†L734-L787】
* Utility/helpers: `audit_log()`, `generate_uuid()`, `generate_tool_qr_images()`, `collect_tool_qr_images()`, `remove_directory_recursive()`, `parse_tool_photos()`, `map_tool_to_template()`, etc., are referenced by several branches (see per-topic sections for usage).【F:www/core_api.php†L168-L346】【F:www/core_api.php†L409-L455】

## User management

* `get_user_info`, `set_lang`, `get_user_panel_html` delegate to helper functions for user info, language updates, and UI snippets.【F:www/core_api.php†L27-L38】
* User CRUD: `view_users`, `form_new_user`, `save_user`, `form_edit_user` all call dedicated handlers to render lists/forms or persist user changes.【F:www/core_api.php†L40-L55】
* Dependencies: rely on the auth helpers noted above plus the handler implementations in `api/user_actions.php`; responses are handler-defined associative arrays.

## Tool stock (warehouse tools)

* View/list: `view_tools_stock`/`tools_stock` fetch tools via `fetch_tools_list($dbcnx)` and render `cells_NA_API_tools_stock.html`. Response: `{status: 'ok', html}`.【F:www/core_api.php†L57-L90】
* Forms: `form_new_tool_stock` builds an empty tool payload, assigns `$user`, and renders `cells_NA_API_tools_stock_form.html`; response returns HTML.【F:www/core_api.php†L74-L118】
* Edit form: `form_edit_tool_stock` loads by `tool_id`, maps DB row with `map_tool_to_template()`, and renders the same form template; errors if id missing/not found.【F:www/core_api.php†L104-L141】
* Save/create/update/delete: `save_tool` validates fields, parses dates, handles deletion (including filesystem cleanup via `remove_directory_recursive`), updates or inserts into `tool_resources`, recalculates QR codes/photos, and logs via `audit_log`. Responses include status/message and optional `tool_id`/`deleted` flag.【F:www/core_api.php†L142-L482】
* Upload photo: `upload_tool_photo` stores an uploaded image for a tool (by `tool_id`), updates `img_path`, and returns updated photo list. Requires GD image handling and directory write access.【F:www/core_api.php†L483-L597】
* Dependencies: database `$dbcnx`, Smarty `$smarty`, filesystem under `www/img/tools_stock`, GD functions (`getimagesize`, `imagecreatefromstring`, etc.), QR utilities (`generate_tool_qr_images`, `collect_tool_qr_images`), and audit logging.

## Devices

* `view_devices` loads device rows and renders `cells_NA_API_devices.html` with `{status: 'ok', html}` response.【F:www/core_api.php†L598-L636】
* `activate_device` (admin-only) toggles `is_active`, sets `activated_at` when activating, logs `DEVICE_ACTIVATE/DEACTIVATE`, and returns status/message.【F:www/core_api.php†L638-L677】
* `form_edit_device` fetches device details plus last 20 audit logs, assigns to Smarty, and renders `cells_NA_API_devices_profile.html`; errors for invalid id/missing device.【F:www/core_api.php†L679-L760】
* `save_device` (admin) supports deletion or updates `notes`/`is_active` with audit logging; responds with status/message and `deleted` flag when applicable.【F:www/core_api.php†L762-L838】
* Dependencies: `$dbcnx`, `$smarty`, audit logs table, `auth_require_role`, and templates `cells_NA_API_devices*.html`.

## Warehouse cell settings

* `setting_cells` queries existing cells and renders `cells_NA_API_warehouse_cells.html` for configuration view; response `{status: 'ok', html}`.【F:www/core_api.php†L866-L896】
* `add_new_cells` validates cell code range (e.g., `A10`–`A99`), avoids duplicates, inserts into `cells`, generates QR PNGs via `qrencode`, rereads list, and returns status/message/html.【F:www/core_api.php†L899-L1035】
* `delete_cell` removes cell record and QR image file, audits deletion, reloads list, and returns status/message/html.【F:www/core_api.php†L1036-L1129】
* Dependencies: `$dbcnx`, filesystem `www/img/cells`, `qrencode` CLI, Smarty templates, and `audit_log`.

## Warehouse item intake & stock

* Batch listings: `warehouse_item_in`/`item_in` list open intake batches (with user scoping) and render `cells_NA_API_warehouse_item_in.html`. Response `{status: 'ok', html}`.【F:www/core_api.php†L1132-L1200】
* Stock overview: `item_stock` lists completed batches from `warehouse_item_stock` and renders `cells_NA_API_warehouse_item_stock.html`. Response `{status: 'ok', html}`.【F:www/core_api.php†L1201-L1262】
* Batch details: `open_item_in_batch` loads or creates a batch, fetches its parcels (scoped by role), destination country list, stand devices, loads OCR templates/dicts, and renders `cells_NA_API_warehouse_item_in_batch.html` with HTML response.【F:www/core_api.php†L1265-L1366】
* Add parcel: `add_new_item_in` creates or reuses a batch, captures parcel metadata (tracking, receiver/sender, dimensions), inserts into `warehouse_item_in`, logs `WAREHOUSE_IN_ADD_PARCEL`, and returns `{status: 'ok', message, batch_uid}`.【F:www/core_api.php†L1368-L1543】
* Delete parcel: `delete_item_in` enforces ownership/role, removes an uncommitted parcel, reloads batch list with destination countries, re-renders batch HTML, and returns status/message/html.【F:www/core_api.php†L1545-L1698】
* Commit batch: `commit_item_in_batch` copies uncommitted parcels to `warehouse_item_stock`, marks them committed, logs `WAREHOUSE_IN_COMMIT`, and responds with completion message.【F:www/core_api.php†L1700-L1808】
* Dependencies: `$dbcnx`, `auth_require_login`, role checks, Smarty templates for intake and stock views, `audit_log`, OCR support files `ocr_templates.php` and `ocr_dicts.php`, and destination country/device reference data. File system paths for label/box images are placeholders (null in current logic).

## Miscellaneous

* `users_regen_qr` delegates to `handle_users_regen_qr()` to regenerate user QR codes, returning handler-defined status/HTML.【F:www/core_api.php†L1127-L1134】
