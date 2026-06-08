# CAMEX_AZ MVP connector

This directory keeps the existing CAMEX_AZ connector copy, including its Config, DTO, Http, Logging, Orchestrator, Services, runners, plans, and README materials. New probe functionality should be added on top of that structure instead of replacing it with a skeleton.

## Login probe / dual authentication

CAMEX_AZ uses two-layer authentication:
1. HTTP htaccess auth
2. web login form auth

The login probe is available as `run_probe_login.php`. It reuses the existing CAMEX_AZ config, HTTP client, and session classes, applies HTTP basic/digest credentials to every request, then performs the web login form flow and checks the dashboard path.

Example:

```bash
php run_probe_login.php \
  --base-url="https://FORWARDER_HOST" \
  --http-auth-type=basic \
  --http-auth-login="HTACCESS_USER" \
  --http-auth-password="HTACCESS_PASS" \
  --login="WEB_USER" \
  --password="WEB_PASS" \
  --login-path="/login" \
  --dashboard-path="/" \
  --session-file="/tmp/camex_az_cookie.txt" \
  --debug-dir="/tmp/camex_az_debug"
```

The probe writes JSON to stdout. It does not include HTTP auth passwords, web passwords, cookies, `Set-Cookie`, or authorization values in JSON output.


### DB-backed connector config

When the `connectors` row is active (`is_active = 1`), the probe can load CAMEX_AZ settings from the database. CLI arguments override database values, database columns override `scenario_json`, and `scenario_json` overrides defaults.

```bash
php www/scripts/mvp/app/CAMEX_AZ/run_probe_login.php --connector-id=3 | jq .
```

Example connector row update:

```sql
UPDATE connectors
SET
  system_type = 'CAMEX_AZ',
  base_url = 'https://az.camex.net',
  auth_type = 'camex_dual',
  auth_username = 'WEB_LOGIN',
  auth_password = 'WEB_PASSWORD',
  http_auth_enabled = 1,
  http_auth_type = 'basic',
  http_auth_username = 'HTACCESS_LOGIN',
  http_auth_password = 'HTACCESS_PASSWORD',
  scenario_json = JSON_OBJECT(
    'paths', JSON_OBJECT(
      'login', '/cadmin/usa/login.php',
      'login_post', '/cadmin/usa/login.php?auth=do',
      'dashboard', '/cadmin/usa/index.php?do=index'
    ),
    'session', JSON_OBJECT(
      'ttl_seconds', 3600
    ),
    'timeout_seconds', 30
  )
WHERE id = 3;
```
