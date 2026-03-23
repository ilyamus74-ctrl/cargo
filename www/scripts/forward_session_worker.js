#!/usr/bin/env node
'use strict';

const readline = require('readline');
const {
  buildLaunchPlans,
  launchBrowserWithFallback,
  createPageWithWarmup,
  serializePageCookies,
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
      operation_profile: '',
      expected_next_action: 'start_session',
      awaiting_popup: false,
      awaiting_label: false,
      last_activity_at: nowIso(),
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

  touch(patch = {}) {
    this.syncContextState(patch);
    this.refreshIdleTimer();
  }

  refreshIdleTimer() {
    if (this.shutdownTimer) {
      clearTimeout(this.shutdownTimer);
      this.shutdownTimer = null;
    }

    if (!this.idleTimeoutMs || !this.isStarted()) {
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

  async handleJob(jobInput) {
    const job = validateJob(jobInput);

    if (this.actorId && this.actorId !== job.actor_id) {
      const err = new Error(`Worker ${this.workerId} is sticky to actor ${this.actorId}, got ${job.actor_id}`);
      err.code = 'ACTOR_MISMATCH';
      throw err;
    }

    if (!this.actorId) {
      this.actorId = job.actor_id;
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
    this.touch({
      actor_id: this.actorId,
      status: 'busy',
      current_container_id: this.currentContainerId,
      operation_profile: this.operationProfile,
      expected_next_action: 'complete_job',
      awaiting_popup: !!job.payload.awaiting_popup,
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
        if (!this.isStarted()) {
          await this.start();
        }
        this.touch({ expected_next_action: 'fill_tracking' });

        return {
          ok: false,
          job,
          action: 'accepted_but_not_implemented',
          message: 'add_parcel_to_forward_container is not implemented yet; job JSON API is validated and accepted.',
          state: await this.getStatus(),
        };
      }


      const err = new Error(`Unsupported operation_type: ${job.operation_type}`);
      err.code = 'UNSUPPORTED_OPERATION';
      throw err;
    } catch (err) {
      this.touch({ expected_next_action: 'accept_job' });
      throw err;
    } finally {
      this.isRunningJob = false;
      if (this.contextState.expected_next_action === 'complete_job') {
        this.touch({ expected_next_action: 'accept_job', status: 'idle' });
      } else {
        this.touch({ status: 'idle' });
      }
    }
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
      context_state: this.getContextState(),
      cookies,
      stop_reason: this.stopReason,
    };
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
    this.syncContextState({
      status: 'idle',
      session_status: 'stopped',
      expected_next_action: reason === 'idle_timeout' ? 'start_session' : 'stopped',
    });

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
