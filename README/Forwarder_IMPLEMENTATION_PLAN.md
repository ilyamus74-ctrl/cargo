# Forwarder PHP Engine — MVP Plan

## Status legend
- [x] done
- [ ] not done

## 0) Foundation
- [x] Create folder structure (`Config`, `DTO`, `Http`, `Services`, `Orchestrator`, `Logging`).
- [ ] Add base namespace/autoload check for `app/Forwarder/*`.
- [ ] Define environment variables (`FORWARDER_BASE_URL`, `FORWARDER_LOGIN`, `FORWARDER_PASSWORD`, timeouts).

## 1) Configuration layer
- [ ] Create `Config/endpoints.php` with endpoint map (`login_get`, `login_post`, `check_position`, `check_package`, etc.).
- [ ] Add request/response schema notes per endpoint (required fields, expected `case`).
- [ ] Add centralized timeout/retry settings.

## 2) HTTP + Session
- [ ] Implement `Http/ForwarderHttpClient.php` (GET/POST wrapper, headers, JSON/form support).
- [ ] Implement `Http/SessionManager.php` (cookie jar, XSRF extraction, relogin trigger).
- [ ] Add automatic headers (`X-Requested-With`, `X-XSRF-TOKEN`, optional `X-CSRF-TOKEN`).

## 3) DTO + Logging
- [ ] Implement `DTO/StepResult.php` (`ok`, `status_code`, `business_code`, `payload`, `latency_ms`).
- [ ] Implement `Logging/ForwarderLogger.php` with operation-level correlation id.
- [ ] Mask secrets/tokens in logs.

## 4) Services
- [ ] Implement `Services/LoginService.php` (`login`, `ensureAuthenticated`).
- [ ] Implement `Services/ContainerService.php` (`checkPosition`, `checkPackage`).
- [ ] Implement `Services/FlightService.php` (first stub methods: `searchFlight`, `addFlight`, `deleteFlight`).

## 5) Orchestrator
- [ ] Implement `Orchestrator/ForwarderWorkflow.php` for one scan flow:
  1. ensure session
  2. check position
  3. check package
  4. normalize response for UI
- [ ] Add business statuses (`ACCEPTED`, `NOT_DECLARED`, `SESSION_EXPIRED`, `TEMP_ERROR`).

## 6) UI test endpoint (MVP)
- [ ] Add simple internal endpoint/form in your system for manual test run.
- [ ] Input: `track`, `container`.
- [ ] Output: normalized status + short message + key fields for label.

## 7) Reliability and rollout
- [ ] Add idempotency key (`track + container`).
- [ ] Add retry policy only for technical errors (timeouts/5xx).
- [ ] Run shadow test on limited traffic and compare with current process.
- [ ] Final switch when success/error rates are acceptable.

---

## Current sprint focus (start here)
- [ ] `endpoints.php`
- [ ] `ForwarderHttpClient.php`
- [ ] `SessionManager.php`
- [ ] `LoginService.php`
- [ ] Minimal UI test endpoint
