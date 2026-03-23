#!/usr/bin/env node
'use strict';

const readline = require('readline');
const {
  buildLaunchPlans,
  launchBrowserWithFallback,
  createPageWithWarmup,
  serializePageCookies,
  findSelectorWithFallback,
  findElementHandleByText,
  safeGoto,
  mkTempDir,
  safeRm,
} = require('./lib/connector_browser_core');

function parseJson(value, fallbackValue = {}) {
  try {
    return JSON.parse(value);
  } catch (_) {
    return fallbackValue;
  }
}

function nowIso() {
  return new Date().toISOString();
}

function makeWorkerId() {
  return `forward_worker_${Date.now()}_${process.pid}`;
}

function makeJobId() {
  return `job_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

function normalizeString(value) {
  return String(value || '').trim();
}

function joinReasonParts(parts) {
  return parts.filter(Boolean).join('_and_');
}

function isNonArrayObject(value) {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

function normalizeSelectorList(value) {
  if (Array.isArray(value)) {
    return value.map((item) => normalizeString(item)).filter(Boolean);
  }
  const normalized = normalizeString(value);
  return normalized ? [normalized] : [];
}

function renderTemplate(value, vars = {}) {
  return String(value || '').replace(/\$\{([a-zA-Z0-9_]+)\}/g, (_, key) => String(vars[key] ?? ''));
}

function validateActorId(actorId) {
  const normalized = normalizeString(actorId);
  if (!normalized) {
    const err = new Error('actor_id is required');
    err.code = 'INVALID_ACTOR_ID';
    throw err;
  }
  return normalized;
}

function validateJob(rawJob) {
  const source = rawJob && typeof rawJob === 'object' ? rawJob : {};
  const payload = source.payload && typeof source.payload === 'object' && !Array.isArray(source.payload)
    ? source.payload
    : null;

  const job = {
    job_id: normalizeString(source.job_id) || makeJobId(),
    actor_id: normalizeString(source.actor_id),
    operation_type: normalizeString(source.operation_type),
    operation_profile: normalizeString(source.operation_profile),
    container_id: normalizeString(source.container_id),
    payload: payload || {},
  };

  const invalidFields = [];
  for (const field of ['actor_id', 'operation_type', 'operation_profile', 'container_id']) {
    if (!job[field]) {
      invalidFields.push(field);
    }
  }
  if (!payload) {
    invalidFields.push('payload');
  }

  if (invalidFields.length) {
    const err = new Error(`Invalid job. Missing/invalid fields: ${invalidFields.join(', ')}`);
    err.code = 'INVALID_JOB';
    err.details = { invalid_fields: invalidFields };
    throw err;
  }

  return job;
}

class ForwardWorkerBindingRegistry {
  constructor(options = {}) {
    this.leaseTimeoutMs = Math.max(0, Number(options.lease_timeout_ms ?? options.leaseTimeoutMs ?? 0));
    this.workerFactory = typeof options.worker_factory === 'function'
      ? options.worker_factory
      : () => ({ worker_id: makeWorkerId() });
    this.bindingsByActor = new Map();
    this.bindingsByWorker = new Map();
  }

  computeLeaseExpiry(lastActivityAt) {
    if (!this.leaseTimeoutMs) {
      return '';
    }

    const base = Date.parse(lastActivityAt || nowIso());
    if (Number.isNaN(base)) {
      return '';
    }

    return new Date(base + this.leaseTimeoutMs).toISOString();
  }

  createBinding(workerRecord, actorId, metadata = {}) {
    const workerId = normalizeString(workerRecord?.worker_id || workerRecord?.workerId || workerRecord?.id);
    if (!workerId) {
      throw new Error('workerFactory must return an object with worker_id');
    }

    const now = nowIso();
    return {
      worker_id: workerId,
      actor_id: actorId,
      sticky: true,
      status: 'leased',
      lease_timeout_ms: this.leaseTimeoutMs,
      lease_started_at: now,
      lease_expires_at: this.computeLeaseExpiry(now),
      last_activity_at: now,
      release_reason: '',
      metadata: { ...metadata },
      worker: workerRecord,
    };
  }

  snapshot(binding) {
    if (!binding) {
      return null;
    }

    return {
      worker_id: binding.worker_id,
      actor_id: binding.actor_id,
      sticky: binding.sticky,
      status: binding.status,
      lease_timeout_ms: binding.lease_timeout_ms,
      lease_started_at: binding.lease_started_at,
      lease_expires_at: binding.lease_expires_at,
      last_activity_at: binding.last_activity_at,
      release_reason: binding.release_reason,
      metadata: { ...binding.metadata },
    };
  }

  getBindingByActor(actorId) {
    const normalizedActorId = validateActorId(actorId);
    return this.snapshot(this.bindingsByActor.get(normalizedActorId));
  }

  getBindingByWorker(workerId) {
    const normalizedWorkerId = normalizeString(workerId);
    if (!normalizedWorkerId) {
      return null;
    }
    return this.snapshot(this.bindingsByWorker.get(normalizedWorkerId));
  }

  touchLease(workerId, metadata = {}) {
    const normalizedWorkerId = normalizeString(workerId);
    const binding = this.bindingsByWorker.get(normalizedWorkerId);
    if (!binding) {
      return null;
    }

    const lastActivityAt = nowIso();
    binding.last_activity_at = lastActivityAt;
    binding.lease_expires_at = this.computeLeaseExpiry(lastActivityAt);
    if (metadata && typeof metadata === 'object' && !Array.isArray(metadata)) {
      binding.metadata = {
        ...binding.metadata,
        ...metadata,
      };
    }
    return this.snapshot(binding);
  }

  acquire(actorId, metadata = {}) {
    const normalizedActorId = validateActorId(actorId);
    const existing = this.bindingsByActor.get(normalizedActorId);
    if (existing) {
      this.touchLease(existing.worker_id, metadata);
      return {
        action: 'reuse_worker',
        binding: this.snapshot(existing),
      };
    }

    const workerRecord = this.workerFactory({ actor_id: normalizedActorId, metadata: { ...metadata } });
    const binding = this.createBinding(workerRecord, normalizedActorId, metadata);
    this.bindingsByActor.set(normalizedActorId, binding);
    this.bindingsByWorker.set(binding.worker_id, binding);
    return {
      action: 'create_worker',
      binding: this.snapshot(binding),
    };
  }

  release(workerId, reason = 'released_by_server') {
    const normalizedWorkerId = normalizeString(workerId);
    const binding = this.bindingsByWorker.get(normalizedWorkerId);
    if (!binding) {
      return null;
    }

    this.bindingsByWorker.delete(normalizedWorkerId);
    this.bindingsByActor.delete(binding.actor_id);

    binding.status = 'released';
    binding.release_reason = normalizeString(reason) || 'released_by_server';
    binding.last_activity_at = nowIso();
    binding.lease_expires_at = '';
    return this.snapshot(binding);
  }

  releaseExpired(referenceTime = nowIso()) {
    if (!this.leaseTimeoutMs) {
      return [];
    }

    const referenceTs = Date.parse(referenceTime);
    if (Number.isNaN(referenceTs)) {
      throw new Error('referenceTime must be a valid ISO datetime string');
    }

    const released = [];
    for (const binding of this.bindingsByWorker.values()) {
      const expiryTs = Date.parse(binding.lease_expires_at || '');
      if (!Number.isNaN(expiryTs) && expiryTs <= referenceTs) {
        const snapshot = this.release(binding.worker_id, 'lease_timeout');
        if (snapshot) {
          released.push(snapshot);
        }
      }
    }
    return released;
  }

  listBindings() {
    return Array.from(this.bindingsByWorker.values(), (binding) => this.snapshot(binding));
  }
}

class ForwardSessionWorker {
  constructor(payload = {}) {
    this.payload = payload && typeof payload === 'object' ? payload : {};
    this.workerId = String(this.payload.worker_id || makeWorkerId());
    this.browserProduct = String(this.payload.browser_product || this.payload.browser || 'auto').toLowerCase();
    this.sslIgnore = !!this.payload.ssl_ignore;
    this.tempDirBase = typeof this.payload.temp_dir === 'string' ? this.payload.temp_dir.trim() : '';
    this.idleTimeoutMs = Math.max(0, Number(this.payload.idle_timeout_ms ?? 0));
    this.browser = null;
    this.page = null;
    this.userDataDir = '';
    this.runtimeHomeDir = '';

    this.startedAt = '';
    this.lastActivityAt = '';
    this.stopReason = '';
    this.shutdownTimer = null;
    this.isRunningJob = false;
    this.jobQueue = Promise.resolve();
    this.pendingJobCount = 0;
    this.activeJobId = '';

    this.actorId = '';
    this.currentContainerId = '';
    this.operationProfile = '';
    this.lastJob = null;
    this.launchMeta = {
      product: this.browserProduct,
      executablePath: null,
    };
    this.contextState = this.createContextState();
  }

  isStarted() {
    return !!(this.browser && this.page);
  }


  createContextState() {
    return {
      worker_id: this.workerId,
      actor_id: '',
      status: 'idle',
      session_status: 'cold',
      current_container_id: '',
      pending_container_id: '',
      operation_profile: '',
      expected_next_action: 'start_session',
      awaiting_popup: false,
      awaiting_approval: false,
      awaiting_label: false,
      can_continue_without_reset: false,
      requires_reset: false,
      requires_container_switch: false,
      continuation_action: 'start_session',
      continuation_reason: 'session_not_started',
      last_activity_at: nowIso(),
    };
  }

  buildForwardContinuationDecision(job, snapshot = {}) {
    const pendingUi = [];
    if (snapshot.awaiting_popup) {
      pendingUi.push('popup');
    }
    if (snapshot.awaiting_approval) {
      pendingUi.push('approval');
    }
    if (snapshot.awaiting_label) {
      pendingUi.push('label');
    }

    if (!snapshot.is_started) {
      return {
        can_continue_without_reset: false,
        requires_reset: false,
        requires_container_switch: false,
        continuation_action: 'start_session',
        continuation_reason: 'session_not_started',
        pending_container_id: normalizeString(job?.container_id),
      };
    }

    if (pendingUi.length) {
      return {
        can_continue_without_reset: false,
        requires_reset: false,
        requires_container_switch: false,
        continuation_action: 'resolve_pending_ui',
        continuation_reason: `awaiting_${joinReasonParts(pendingUi)}`,
        pending_container_id: normalizeString(job?.container_id),
      };
    }

    const nextContainerId = normalizeString(job?.container_id);
    const currentContainerId = normalizeString(snapshot.current_container_id);
    const currentOperationProfile = normalizeString(snapshot.operation_profile);
    const hasCurrentContainer = !!currentContainerId;
    const requiresContainerSwitch = hasCurrentContainer && currentContainerId !== nextContainerId;
    const requiresReset = !!currentOperationProfile && currentOperationProfile !== normalizeString(job?.operation_profile);

    if (!hasCurrentContainer) {
      return {
        can_continue_without_reset: false,
        requires_reset: requiresReset,
        requires_container_switch: true,
        continuation_action: 'select_container',
        continuation_reason: 'container_not_selected',
        pending_container_id: nextContainerId,
      };
    }

    if (requiresReset || requiresContainerSwitch) {
      const reason = [];
      if (requiresReset) {
        reason.push('operation_profile_changed');
      }
      if (requiresContainerSwitch) {
        reason.push('container_changed');
      }

      return {
        can_continue_without_reset: false,
        requires_reset: requiresReset,
        requires_container_switch: requiresContainerSwitch,
        continuation_action: requiresReset && requiresContainerSwitch
          ? 'reset_form_and_switch_container'
          : (requiresReset ? 'reset_form' : 'switch_container'),
        continuation_reason: joinReasonParts(reason),
        pending_container_id: nextContainerId,
      };
    }

    return {
      can_continue_without_reset: true,
      requires_reset: false,
      requires_container_switch: false,
      continuation_action: 'continue_same_container',
      continuation_reason: 'context_matches',
      pending_container_id: '',
    };
  }

  getExpectedNextActionForForwardDecision(decision) {
    if (!decision || typeof decision !== 'object') {
      return 'accept_job';
    }
    if (decision.can_continue_without_reset) {
      return 'fill_tracking';
    }
    return decision.continuation_action || 'accept_job';
  }

  buildForwardPayloadRuntime(job) {
    const payload = isNonArrayObject(job?.payload) ? job.payload : {};
    const selectors = isNonArrayObject(payload.selectors) ? payload.selectors : {};
    const vars = {
      actor_id: normalizeString(job?.actor_id),
      container_id: normalizeString(job?.container_id),
      operation_profile: normalizeString(job?.operation_profile),
      tracking: normalizeString(payload.tracking),
      tracking_number: normalizeString(payload.tracking || payload.tracking_number),
      barcode: normalizeString(payload.barcode || payload.tracking || payload.tracking_number),
    };

    const runtime = {
      payload,
      selectors,
      vars,
      navigation_url: normalizeString(payload.navigation_url || payload.url),
      tracking_value: vars.tracking_number,
      success_text: normalizeString(payload.success_text),
    };

    runtime.popup_selectors = normalizeSelectorList(
      payload.popup_selector || selectors.popup || selectors.popup_selector
    );
    runtime.approval_selectors = normalizeSelectorList(
      payload.approval_selector || selectors.approval || selectors.approval_selector
    );
    runtime.label_selectors = normalizeSelectorList(
      payload.label_selector || selectors.label || selectors.label_selector
    );
    runtime.success_selectors = normalizeSelectorList(
      payload.success_selector || selectors.success || selectors.success_selector
    );
    runtime.reset_selectors = normalizeSelectorList(
      payload.reset_selector || selectors.reset || selectors.reset_button
    );
    runtime.submit_selectors = normalizeSelectorList(
      payload.submit_selector || selectors.submit || selectors.submit_button
    );
    runtime.tracking_selectors = normalizeSelectorList(
      payload.tracking_selector || selectors.tracking || selectors.tracking_input || '#tracking'
    );
    runtime.container_selectors = normalizeSelectorList(
      payload.container_selector || selectors.container || selectors.container_input || selectors.container_select
    );
    runtime.container_option_selector = normalizeString(
      payload.container_option_selector || selectors.container_option_selector
    );
    runtime.container_option_text = normalizeString(
      payload.container_option_text || selectors.container_option_text || job?.container_id
    );
    runtime.post_submit_wait_ms = Math.max(0, Number(payload.post_submit_wait_ms || 0));
    runtime.submit_timeout_ms = Math.max(1, Number(payload.submit_timeout_ms || 30000));
    runtime.success_timeout_ms = Math.max(1, Number(payload.success_timeout_ms || 1500));

    return runtime;
  }

  async hasAnySelector(selectors = [], timeout = 250) {
    if (!this.page || !Array.isArray(selectors) || selectors.length === 0) {
      return false;
    }

    for (const selector of selectors) {
      try {
        await findSelectorWithFallback(this.page, selector, { timeout });
        return true;
      } catch (_) {}
    }
    return false;
  }

  async clickFirstAvailableSelector(selectors = [], timeout = 30000) {
    for (const selector of selectors) {
      try {
        const matchedSelector = await findSelectorWithFallback(this.page, selector, { timeout, visible: true });
        await this.page.click(matchedSelector);
        return matchedSelector;
      } catch (_) {}
    }

    throw new Error(`None of the selectors matched: ${selectors.join(', ')}`);
  }

  async fillFirstAvailableSelector(selectors = [], value, timeout = 30000) {
    const text = normalizeString(value);
    if (!text) {
      throw new Error('Tracking value is required');
    }

    for (const selector of selectors) {
      try {
        const matchedSelector = await findSelectorWithFallback(this.page, selector, { timeout, visible: true });
        await this.page.click(matchedSelector, { clickCount: 3 });
        if (typeof this.page.evaluate === 'function') {
          await this.page.evaluate((inputSelector) => {
            const input = document.querySelector(inputSelector);
            if (!input) {
              return false;
            }
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
          }, matchedSelector);
        }
        await this.page.type(matchedSelector, text);
        return matchedSelector;
      } catch (_) {}
    }

    throw new Error(`None of the selectors matched: ${selectors.join(', ')}`);
  }

  async selectContainerForForward(runtime, containerId) {
    const nextContainerId = normalizeString(containerId);
    if (!nextContainerId) {
      throw new Error('container_id is required for forward container selection');
    }

    if (runtime.container_selectors.length === 0 && !runtime.container_option_selector) {
      return {
        selected: false,
        container_id: nextContainerId,
        mode: 'implicit_context_only',
      };
    }

    if (runtime.container_selectors.length > 0) {
      await this.fillFirstAvailableSelector(runtime.container_selectors, nextContainerId);
    }

    if (runtime.container_option_selector) {
      let optionHandle;
      if (typeof this.page.waitForFunction === 'function') {
        optionHandle = await findElementHandleByText(
          this.page,
          runtime.container_option_selector,
          runtime.container_option_text || nextContainerId,
          {
            timeout: runtime.submit_timeout_ms,
            visible: true,
            match: 'contains',
          }
        );
      } else {
        const handles = await this.page.$$(runtime.container_option_selector);
        for (const handle of handles) {
          const isMatch = await handle.evaluate((node, expectedText) => {
            const label = String(node?.textContent || node?.innerText || '').replace(/\s+/g, ' ').trim().toLowerCase();
            return label.includes(String(expectedText || '').replace(/\s+/g, ' ').trim().toLowerCase());
          }, runtime.container_option_text || nextContainerId);
          if (isMatch) {
            optionHandle = handle;
            break;
          }
          await handle.dispose();
        }
        if (!optionHandle) {
          throw new Error(`No container option found for ${runtime.container_option_text || nextContainerId}`);
        }
      }
      try {
        await optionHandle.click();
      } finally {
        await optionHandle.dispose();
      }
    }

    return {
      selected: true,
      container_id: nextContainerId,
      mode: runtime.container_option_selector ? 'selector_and_option' : 'selector_only',
    };
  }

  async executeAddParcelToForwardContainer(job, continuationDecision) {
    if (!this.isStarted()) {
      await this.start();
    }

    const runtime = this.buildForwardPayloadRuntime(job);
    const trackingValue = runtime.tracking_value;
    if (!trackingValue) {
      const err = new Error('add_parcel_to_forward_container payload.tracking is required');
      err.code = 'INVALID_JOB_PAYLOAD';
      throw err;
    }

    if (runtime.navigation_url) {
      const currentUrl = normalizeString(this.page.url());
      if (currentUrl !== runtime.navigation_url) {
        await this.goto(runtime.navigation_url, {
          wait_until: job.payload.wait_until || 'domcontentloaded',
          timeout_ms: job.payload.timeout_ms || 60000,
        });
      }
    }

    if (continuationDecision.requires_reset) {
      if (runtime.reset_selectors.length === 0) {
        this.touch({
          actor_id: this.actorId,
          current_container_id: job.container_id,
          operation_profile: job.operation_profile,
          pending_container_id: continuationDecision.pending_container_id,
          expected_next_action: continuationDecision.continuation_action,
          can_continue_without_reset: false,
          requires_reset: true,
          requires_container_switch: continuationDecision.requires_container_switch,
          continuation_action: continuationDecision.continuation_action,
          continuation_reason: `${continuationDecision.continuation_reason}_and_reset_selector_missing`,
        });

        return {
          ok: false,
          job,
          action: 'reset_required_before_forward_parcel',
          message: 'Forward worker requires reset_selector before continuing with this parcel.',
          continuation_decision: continuationDecision,
          state: await this.getStatus(),
        };
      }
      await this.clickFirstAvailableSelector(runtime.reset_selectors, runtime.submit_timeout_ms);
    }

    if (continuationDecision.requires_container_switch) {
      await this.selectContainerForForward(runtime, job.container_id);
    }

    await this.fillFirstAvailableSelector(runtime.tracking_selectors, trackingValue, runtime.submit_timeout_ms);

    let submitSelectorUsed = '';
    if (runtime.submit_selectors.length > 0) {
      submitSelectorUsed = await this.clickFirstAvailableSelector(runtime.submit_selectors, runtime.submit_timeout_ms);
    }

    if (runtime.post_submit_wait_ms > 0) {
      await new Promise((resolve) => setTimeout(resolve, runtime.post_submit_wait_ms));
    }

    const awaitingPopup = !!job.payload.awaiting_popup
      || await this.hasAnySelector(runtime.popup_selectors, runtime.success_timeout_ms);
    const awaitingApproval = !!job.payload.awaiting_approval
      || await this.hasAnySelector(runtime.approval_selectors, runtime.success_timeout_ms);
    const awaitingLabel = !!job.payload.awaiting_label
      || await this.hasAnySelector(runtime.label_selectors, runtime.success_timeout_ms);
    const hasSuccessSelector = await this.hasAnySelector(runtime.success_selectors, runtime.success_timeout_ms);
    const looksSuccessful = hasSuccessSelector || (!awaitingPopup && !awaitingApproval);

    this.touch({
      actor_id: this.actorId,
      current_container_id: job.container_id,
      operation_profile: job.operation_profile,
      pending_container_id: '',
      expected_next_action: looksSuccessful ? 'fill_tracking' : 'resolve_pending_ui',
      can_continue_without_reset: looksSuccessful,
      requires_reset: false,
      requires_container_switch: false,
      continuation_action: looksSuccessful ? 'continue_same_container' : 'resolve_pending_ui',
      continuation_reason: looksSuccessful ? 'operation_completed' : 'awaiting_post_submit_ui',
      awaiting_popup: awaitingPopup,
      awaiting_approval: awaitingApproval,
      awaiting_label: awaitingLabel,
    });

    return {
      ok: looksSuccessful,
      job,
      action: looksSuccessful ? 'parcel_added_to_forward_container' : 'parcel_submitted_pending_ui',
      continuation_decision: continuationDecision,
      result: {
        tracking: trackingValue,
        container_id: job.container_id,
        submit_selector: submitSelectorUsed,
        success_detected: hasSuccessSelector,
        awaiting_popup: awaitingPopup,
        awaiting_approval: awaitingApproval,
        awaiting_label: awaitingLabel,
      },
      state: await this.getStatus(),
    };
  }

  syncContextState(patch = {}) {
    const sessionStatus = this.isStarted() ? 'ready' : (this.stopReason ? 'stopped' : 'cold');
    const status = patch.status || (this.isRunningJob ? 'busy' : 'idle');
    this.contextState = {
      ...this.contextState,
      ...patch,
      worker_id: this.workerId,
      actor_id: patch.actor_id ?? this.actorId,
      current_container_id: patch.current_container_id ?? this.currentContainerId,
      operation_profile: patch.operation_profile ?? this.operationProfile,
      session_status: patch.session_status ?? sessionStatus,
      status,
      last_activity_at: nowIso(),
    };
    this.lastActivityAt = this.contextState.last_activity_at;
    return this.contextState;
  }

  getContextState() {
    return {
      ...this.contextState,
      worker_id: this.workerId,
      actor_id: this.actorId,
      current_container_id: this.currentContainerId,
      operation_profile: this.operationProfile,
      session_status: this.isStarted() ? 'ready' : this.contextState.session_status,
      status: this.isRunningJob ? 'busy' : this.contextState.status,
      last_activity_at: this.lastActivityAt || this.contextState.last_activity_at,
    };
  }

  getQueueState() {
    return {
      pending_jobs: this.pendingJobCount,
      is_running_job: this.isRunningJob,
      active_job_id: this.activeJobId,
    };
  }

  touch(patch = {}) {
    this.syncContextState(patch);
    this.refreshIdleTimer();
  }

  refreshIdleTimer() {
    if (this.shutdownTimer) {
      clearTimeout(this.shutdownTimer);
      this.shutdownTimer = null;
    }

    if (!this.idleTimeoutMs || !this.isStarted() || this.isRunningJob || this.pendingJobCount > 0) {
      return;
    }
    this.shutdownTimer = setTimeout(async () => {
      try {
        await this.stop('idle_timeout');
        this.emitEvent({ event: 'idle_timeout', worker_id: this.workerId, stopped_at: nowIso() });
      } catch (err) {
        this.emitEvent({
          event: 'idle_timeout_error',
          worker_id: this.workerId,
          stopped_at: nowIso(),
          error: err?.message || String(err),
        });
      }
    }, this.idleTimeoutMs);
    if (typeof this.shutdownTimer.unref === 'function') {
      this.shutdownTimer.unref();
    }
  }

  emitEvent(payload) {
    process.stdout.write(JSON.stringify(payload) + '\n');
  }

  async start() {
    if (this.isStarted()) {
      this.touch();
      return this.getStatus();
    }

    this.userDataDir = mkTempDir(this.tempDirBase, 'forward-worker-profile-');
    this.runtimeHomeDir = mkTempDir(this.tempDirBase, 'forward-worker-home-');
    const launchPlans = buildLaunchPlans(this.browserProduct, this.userDataDir, this.runtimeHomeDir);
    const launched = await launchBrowserWithFallback(launchPlans, this.sslIgnore);
    this.browser = launched.browser;
    this.page = await createPageWithWarmup(this.browser, this.payload);
    this.launchMeta = {
      product: launched.product,
      executablePath: launched.executablePath,
    };

    this.startedAt = nowIso();
    this.touch({
      session_status: 'ready',
      status: 'idle',
      expected_next_action: 'accept_job',
    });

    return this.getStatus();
  }

  async goto(url, options = {}) {
    if (!this.isStarted()) {
      await this.start();
    }

    const targetUrl = String(url || '').trim();
    if (!targetUrl) {
      throw new Error('goto.url is required');
    }

    await safeGoto(this.page, targetUrl, {
      waitUntil: options.wait_until || 'domcontentloaded',
      timeout: Number(options.timeout_ms || 60000),
    });

    this.touch({ expected_next_action: 'accept_job' });

    return {
      url: this.page.url(),
      title: await this.page.title(),
    };
  }

  async runJob(jobInput) {
    const job = validateJob(jobInput);

    if (this.actorId && this.actorId !== job.actor_id) {
      const err = new Error(`Worker ${this.workerId} is sticky to actor ${this.actorId}, got ${job.actor_id}`);
      err.code = 'ACTOR_MISMATCH';
      throw err;
    }

    if (!this.actorId) {
      this.actorId = job.actor_id;
    }

    let continuationDecision = null;
    if (job.operation_type === 'add_parcel_to_forward_container' && !this.isStarted()) {
      await this.start();
    }

    if (job.operation_type === 'add_parcel_to_forward_container') {
      continuationDecision = this.buildForwardContinuationDecision(job, {
        is_started: this.isStarted(),
        current_container_id: this.currentContainerId,
        operation_profile: this.operationProfile,
        awaiting_popup: this.contextState.awaiting_popup,
        awaiting_approval: this.contextState.awaiting_approval,
        awaiting_label: this.contextState.awaiting_label,
      });
    }

    this.currentContainerId = job.container_id;
    this.operationProfile = job.operation_profile;
    this.lastJob = {
      job_id: job.job_id,
      actor_id: job.actor_id,
      operation_type: job.operation_type,
      operation_profile: job.operation_profile,
      container_id: job.container_id,
      accepted_at: nowIso(),
    };

    this.isRunningJob = true;
    this.activeJobId = job.job_id;
    this.touch({
      actor_id: this.actorId,
      status: 'busy',
      current_container_id: this.currentContainerId,
      operation_profile: this.operationProfile,
      expected_next_action: 'complete_job',
      awaiting_popup: !!job.payload.awaiting_popup,
      awaiting_approval: !!job.payload.awaiting_approval,
      awaiting_label: !!job.payload.awaiting_label,
    });


    try {
      if (job.operation_type === 'noop' || job.operation_type === 'ping') {
        if (!this.isStarted()) {
          await this.start();
        } else {
          this.touch({ expected_next_action: 'accept_job' });
        }

        return {
          ok: true,
          job,
          action: 'noop',
          state: await this.getStatus(),
        };
      }


      if (job.operation_type === 'navigate' || job.operation_type === 'goto_url') {
        const url = normalizeString(job.payload.url);
        if (!url) {
          const err = new Error('navigate payload.url is required');
          err.code = 'INVALID_JOB_PAYLOAD';
          throw err;
        }

        const result = await this.goto(url, job.payload);
        return {
          ok: true,
          job,
          action: 'navigated',
          result,
          state: await this.getStatus(),
        };
      }


      if (job.operation_type === 'add_parcel_to_forward_container') {
        this.touch({
          expected_next_action: this.getExpectedNextActionForForwardDecision(continuationDecision),
          pending_container_id: continuationDecision.pending_container_id,
          can_continue_without_reset: continuationDecision.can_continue_without_reset,
          requires_reset: continuationDecision.requires_reset,
          requires_container_switch: continuationDecision.requires_container_switch,
          continuation_action: continuationDecision.continuation_action,
          continuation_reason: continuationDecision.continuation_reason,
          awaiting_popup: !!job.payload.awaiting_popup,
          awaiting_approval: !!job.payload.awaiting_approval,
          awaiting_label: !!job.payload.awaiting_label,
        });


        if (continuationDecision.continuation_action === 'resolve_pending_ui') {
          return {
            ok: false,
            job,
            action: 'blocked_by_pending_ui',
            message: 'Forward worker detected pending popup/approval/label state before adding the next parcel.',
            continuation_decision: continuationDecision,
            state: await this.getStatus(),
          };
        }

        return this.executeAddParcelToForwardContainer(job, continuationDecision);
      }


      const err = new Error(`Unsupported operation_type: ${job.operation_type}`);
      err.code = 'UNSUPPORTED_OPERATION';
      throw err;
    } catch (err) {
      this.touch({ expected_next_action: 'accept_job' });
      throw err;
    } finally {
      this.isRunningJob = false;
      this.activeJobId = '';
      if (this.contextState.expected_next_action === 'complete_job') {
        this.touch({ expected_next_action: 'accept_job', status: 'idle' });
      } else {
        this.touch({ status: 'idle' });
      }
    }
  }

  async handleJob(jobInput) {
    this.pendingJobCount += 1;
    const runNext = async () => {
      this.pendingJobCount -= 1;
      return this.runJob(jobInput);
    };

    const queuedJob = this.jobQueue.then(runNext, runNext);
    this.jobQueue = queuedJob.then(() => undefined, () => undefined);
    return queuedJob;
  }

  async getStatus() {
    const started = this.isStarted();
    const currentUrl = started ? this.page.url() : '';
    const cookies = started ? await serializePageCookies(this.page) : '';

    return {
      worker_id: this.workerId,
      status: started ? 'ready' : 'stopped',
      browser_product: this.launchMeta.product,
      executable_path: this.launchMeta.executablePath,
      started_at: this.startedAt,
      last_activity_at: this.lastActivityAt,
      idle_timeout_ms: this.idleTimeoutMs,
      current_url: currentUrl,
      has_browser: !!this.browser,
      has_page: !!this.page,
      actor_id: this.actorId,
      current_container_id: this.currentContainerId,
      operation_profile: this.operationProfile,
      last_job: this.lastJob,
      queue_state: this.getQueueState(),
      context_state: this.getContextState(),
      cookies,
      stop_reason: this.stopReason,
    };
  }

  resetSessionStateForIdleTimeout() {
    this.actorId = '';
    this.currentContainerId = '';
    this.operationProfile = '';
    this.lastJob = null;
    this.contextState = this.createContextState();
    this.syncContextState({
      session_status: 'stopped',
      expected_next_action: 'start_session',
      status: 'idle',
    });
  }

  async stop(reason = 'shutdown') {
    this.stopReason = reason;

    if (this.shutdownTimer) {
      clearTimeout(this.shutdownTimer);
      this.shutdownTimer = null;
    }

    const page = this.page;
    const browser = this.browser;
    this.page = null;
    this.browser = null;
    if (page) {
      try {
        await page.close();
      } catch (_) {}
    }

    if (browser) {
      try {

        await browser.close();
      } catch (_) {}
    }
    const userDataDir = this.userDataDir;
    const runtimeHomeDir = this.runtimeHomeDir;
    this.userDataDir = '';
    this.runtimeHomeDir = '';
    await safeRm(userDataDir);
    await safeRm(runtimeHomeDir);


    this.isRunningJob = false;

    if (reason === 'idle_timeout') {
      this.resetSessionStateForIdleTimeout();
    } else {
      this.syncContextState({
        status: 'idle',
        session_status: 'stopped',
        expected_next_action: 'stopped',
      });
    }

    return {
      worker_id: this.workerId,
      status: 'stopped',
      stopped_at: this.lastActivityAt,
      stop_reason: this.stopReason,
    };
  }
}

async function runCli() {
  const args = process.argv.slice(2);
  if (args[0] === '--help' || args[0] === '-h') {
    process.stdout.write(
      [
        'Usage: node forward_session_worker.js "{...json payload...}"',
        '',
        'The worker starts a persistent browser/page session and accepts JSON commands over stdin.',
        'Server-side binding rule: one actor_id leases one sticky worker until release or idle timeout.',
        'One JSON command per line, for example:',
        '  {"command":"status"}',
        '  {"command":"context_state"}',
        '  {"command":"goto","url":"https://example.com"}',
        '  {"command":"validate_job","job":{"actor_id":"user_42_forward_x","operation_type":"add_parcel_to_forward_container","operation_profile":"continue_same_container","container_id":"CNT-001","payload":{"tracking":"123456789"}}}',
        '  {"command":"job","job":{"actor_id":"user_42_forward_x","operation_type":"noop","operation_profile":"continue_same_container","container_id":"CNT-001","payload":{}}}',
        '  {"command":"shutdown"}',
      ].join('\n') + '\n'
    );
    return;
  }

  const payload = parseJson(args[0] || '{}', null);
  if (!payload) {
    process.stdout.write(JSON.stringify({ ok: false, error: 'Invalid JSON payload' }) + '\n');
    process.exitCode = 1;
    return;
  }

  const worker = new ForwardSessionWorker(payload);
  let commandChain = Promise.resolve();
  let closing = false;


  const handleShutdown = async (reason) => {
    if (closing) return;
    closing = true;
    try {
      const result = await worker.stop(reason);
      process.stdout.write(JSON.stringify({ ok: true, event: 'shutdown', result }) + '\n');
    } catch (err) {
      process.stdout.write(JSON.stringify({ ok: false, event: 'shutdown', error: err?.message || String(err) }) + '\n');
      process.exitCode = 1;
    }
  };


  process.on('SIGINT', () => {
    void handleShutdown('sigint').finally(() => process.exit());
  });
  process.on('SIGTERM', () => {
    void handleShutdown('sigterm').finally(() => process.exit());
  });


  try {
    if (payload.autostart !== false) {
      const result = await worker.start();
      process.stdout.write(JSON.stringify({ ok: true, event: 'started', result }) + '\n');
    } else {
      process.stdout.write(JSON.stringify({ ok: true, event: 'ready', result: await worker.getStatus() }) + '\n');
    }

  } catch (err) {
    process.stdout.write(JSON.stringify({ ok: false, event: 'start_failed', error: err?.message || String(err) }) + '\n');
    process.exitCode = 1;
    return;
  }
  const rl = readline.createInterface({
    input: process.stdin,
    crlfDelay: Infinity,
  });

  rl.on('line', (line) => {
    const trimmed = String(line || '').trim();
    if (!trimmed) return;

    commandChain = commandChain.then(async () => {
      const message = parseJson(trimmed, null);
      if (!message || typeof message !== 'object') {
        process.stdout.write(JSON.stringify({ ok: false, error: 'Invalid command JSON' }) + '\n');
        return;
      }

      const requestId = message.request_id ?? null;
      const command = String(message.command || '').trim().toLowerCase();


      try {
        let result;
        if (command === 'start') {
          result = await worker.start();
        } else if (command === 'status' || command === 'ping') {
          if (worker.isStarted()) worker.touch();
          result = await worker.getStatus();
        } else if (command === 'context_state' || command === 'state') {
          if (worker.isStarted()) worker.touch();
          result = worker.getContextState();
        } else if (command === 'validate_job') {
          result = validateJob(message.job);
        } else if (command === 'job' || command === 'run_job') {
          result = await worker.handleJob(message.job);
        } else if (command === 'goto') {
          result = await worker.goto(message.url, message);
        } else if (command === 'shutdown' || command === 'stop') {
          result = await worker.stop(command);
          closing = true;
          rl.close();
        } else {
          throw new Error(`Unsupported command: ${command || '<empty>'}`);
        }

        process.stdout.write(JSON.stringify({ ok: true, request_id: requestId, command, result }) + '\n');

        if (closing) {
          process.exit(0);
        }
      } catch (err) {
        process.stdout.write(JSON.stringify({ ok: false, request_id: requestId, command, error: err?.message || String(err) }) + '\n');
      }
    });
  });


  rl.on('close', () => {
    if (!closing) {
      commandChain = commandChain.finally(async () => {
        await handleShutdown('stdin_closed');
        process.exit();
      });
    }
  });
}

if (require.main === module) {
  runCli().catch(async (err) => {
    process.stdout.write(JSON.stringify({ ok: false, error: err?.message || String(err) }) + '\n');
    process.exitCode = 1;
  });
}

module.exports = {
  ForwardSessionWorker,
  ForwardWorkerBindingRegistry,
  validateActorId,
  validateJob,
  runCli,
};
