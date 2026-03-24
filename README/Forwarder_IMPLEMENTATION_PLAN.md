# Forwarder PHP Engine — MVP Plan

## Status legend
- [x] done
- [ ] not done

## 0) Foundation
- [x] Create folder structure (`Config`, `DTO`, `Http`, `Services`, `Orchestrator`, `Logging`).
- [x] Add base namespace/autoload check for `app/Forwarder/*`.
- [x] Define environment variables (`DEV_COLIBRI_BASE_URL`, `DEV_COLIBRI_LOGIN`, `DEV_COLIBRI_PASSWORD`, timeouts).

## 1) Configuration layer
- [x] Create `Config/endpoints.php` with endpoint map (`login_get`, `login_post`, `check_position`, `check_package`, etc.).
- [x] Add request/response schema notes per endpoint (required fields, expected `case`).
- [x] Add centralized timeout/retry settings.

## 2) HTTP + Session
- [x] Implement `Http/ForwarderHttpClient.php` (GET/POST wrapper, headers, JSON/form support).
- [x] Implement `Http/SessionManager.php` (cookie jar, XSRF extraction, relogin trigger).
- [x] Add automatic headers (`X-Requested-With`, `X-XSRF-TOKEN`, optional `X-CSRF-TOKEN`).

## 3) DTO + Logging
- [x] Implement `DTO/StepResult.php` (`ok`, `status_code`, `business_code`, `payload`, `latency_ms`).
- [x] Implement `Logging/ForwarderLogger.php` with operation-level correlation id.
- [x] Mask secrets/tokens in logs.

## 4) Services
- [x] Implement `Services/LoginService.php` (`login`, `ensureAuthenticated`).
- [x] Implement `Services/ContainerService.php` (`checkPosition`, `checkPackage`).
- [x] Implement `Services/FlightService.php` (first stub methods: `searchFlight`, `addFlight`, `deleteFlight`).

## 5) Orchestrator
- [x] Implement `Orchestrator/ForwarderWorkflow.php` for one scan flow:
  1. ensure session
  2. check position
  3. check package
  4. normalize response for UI
- [x] Add business statuses (`ACCEPTED`, `NOT_DECLARED`, `SESSION_EXPIRED`, `TEMP_ERROR`).

## 6) UI test endpoint (MVP)
- [x] Add simple internal endpoint/form in your system for manual test run.
- [x] Input: `track`, `container`.
- [x] Output: normalized status + short message + key fields for label.

## 7) Reliability and rollout
- [ ] Add idempotency key (`track + container`).
- [ ] Add retry policy only for technical errors (timeouts/5xx).
- [ ] Run shadow test on limited traffic and compare with current process.
- [ ] Final switch when success/error rates are acceptable.

---

## Current sprint focus (start here)
- [x] `endpoints.php`
- [x] `ForwarderHttpClient.php`
- [x] `SessionManager.php`
- [x] `LoginService.php`
- [x] Minimal UI test endpoint


## Next execution plan (agreed A→D)

### Stage A — Session Gateway (1–2 days)
- [X] Create `ForwarderSessionClient` service class.
- [X] Implement `ensureSession()`:
  - if no valid session → perform login;
  - if request returns `401` / `419` / redirect to login → relogin + single retry.
- [X] Persist cookie jar (file or Redis) with TTL slightly lower than server session TTL.
- [X] Auto-inject auth headers on requests:
  - `X-XSRF-TOKEN` from cookie;
  - `X-CSRF-TOKEN` (when required, extract from login page meta/hidden input).
- [X] Send both `X-XSRF-TOKEN` and `X-CSRF-TOKEN` by default to match current upstream behavior.

### Stage B — Backend API for UI (1 day)
- [X] Add endpoint `POST /api/forwarder/scan`.
- [X] Input payload:
```json
{"track":"...","container":"24369"}
```
- [X] Flow:
  1. `check-position`;
  2. if success → `check-package`;
  3. return compact DTO (not full upstream payload).
- [X] Target response shape:
```json
{
  "status":"ACCEPTED",
  "track":"...",
  "internal_id":"CBR...",
  "weight":"0.700",
  "client_name":"...",
  "label_payload": {}
}
```

### Stage C — Operator UI (1 day)
- [X] Display green success state + print action/button.
- [X] Display red failure reason (`NOT_DECLARED`, `INVALID_TRACK`, `SESSION_EXPIRED`, etc.).
- [X] Return focus to scan input immediately after result render.

### Stage D — Reliability (1–2 days)
- [X] Idempotency key on `(track, container)` for single processing/result.
- [X] Retry only for technical errors (timeouts / HTTP 5xx), not for business/ops outcomes.
- [X] Add audit log fields:
  - inbound `track`;
  - forwarder `request_id` / `correlation_id`;
  - elapsed milliseconds;
  - final normalized status.

## Definition of Done (implementation wave)
- [X] Stage A-D completed with integration test evidence (`php www/scripts/mvp/app/Forwarder/tests/forwarder_integration_smoke.php`).
- [X] `/api/forwarder/scan` returns compact DTO used directly by operator UI.
- [X] Audit logs allow end-to-end tracing per scan (`track` + `correlation_id`).
- [X] Rollback switch documented for safe disable of forwarder flow.


### Rollback switch (implemented)
- Env flag: `FORWARDER_FLOW_ENABLED` (default: enabled).
- Set `FORWARDER_FLOW_ENABLED=0` (or `false` / `off` / `no`) to disable forwarder scan flow without code rollback.
- Disabled mode returns compact `TEMP_ERROR` DTO and logs the event with `track` + `correlation_id`.
