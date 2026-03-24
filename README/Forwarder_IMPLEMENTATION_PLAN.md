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

## Next execution plan ("go further")

### Sprint A — idempotency (highest priority)
- [ ] Add `IdempotencyService` (in-memory/Redis adapter via interface).
- [ ] Generate deterministic key: `sha256(track + ":" + container)` with normalization (`trim`, `upper`).
- [ ] TTL for lock/result cache: 5–15 min (configurable env).
- [ ] Workflow behavior:
  - if in-flight key exists → return `IN_PROGRESS` (or last known status);
  - if completed key exists → return cached normalized result;
  - else start flow and save final result.
- [ ] Add metrics: idempotency hit-rate, lock wait time.

### Sprint B — technical retry policy
- [ ] Add `RetryPolicy` with exponential backoff + jitter (e.g. 200ms, 500ms, 1000ms).
- [ ] Retry only on technical failures: timeout, network error, HTTP 5xx.
- [ ] No retries for business responses (`NOT_DECLARED`, validation errors, etc.).
- [ ] Add circuit-breaker-like guard for repeated upstream failures (short cooldown).
- [ ] Emit per-attempt logs with `correlation_id` and `attempt_no`.

### Sprint C — shadow rollout
- [ ] Enable dual-run mode (legacy flow + forwarder) for a small traffic slice (5–10%).
- [ ] Store comparison report per request (status match/mismatch + reason).
- [ ] Add dashboard counters: success rate, mismatch rate, p95 latency, session-expired rate.
- [ ] Define go-live criteria (example):
  - mismatch rate < 1% for 3 consecutive days;
  - p95 latency not worse than legacy by >10%;
  - no critical auth/session incidents.

### Sprint D — controlled switch
- [ ] Rollout by stages: 10% → 25% → 50% → 100% with rollback toggle.
- [ ] Keep shadow comparison at least 48h after 100% cutover.
- [ ] Finalize runbook: incident actions, re-login storm handling, temporary disable procedure.

## Definition of Done (MVP+)
- [ ] Idempotency + retry are covered by unit/integration tests.
- [ ] Shadow period completed with agreed quality metrics.
- [ ] On-call/runbook and dashboard links documented in README.