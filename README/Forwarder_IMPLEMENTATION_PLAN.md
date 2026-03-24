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
