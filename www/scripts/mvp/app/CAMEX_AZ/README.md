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
