#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const {
  mkTempDir,
  buildLaunchPlans,
  launchBrowserWithFallback,
  createPageWithWarmup,
  safeRm,
} = require('./lib/connector_browser_core');

function nowIso() {
  return new Date().toISOString();
}

function emit(message) {
  process.stdout.write(JSON.stringify(message) + '\n');
}

function normalizeString(value) {
  return String(value || '').trim();
}

function validateJob(rawJob) {
  const job = rawJob && typeof rawJob === 'object' ? rawJob : {};
  const payload = job.payload && typeof job.payload === 'object' ? job.payload : {};

  const normalized = {
    job_id: normalizeString(job.job_id) || `job_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    actor_id: normalizeString(job.actor_id),
    operation_type: normalizeString(job.operation_type),
    operation_profile: normalizeString(job.operation_profile),
    container_id: normalizeString(job.container_id),
    payload,
  };

  const missing = [];
  for (const key of ['actor_id', 'operation_type', 'operation_profile', 'container_id']) {
    if (!normalized[key]) missing.push(key);
  }
  if (typeof normalized.payload !== 'object' || Array.isArray(normalized.payload)) {
    missing.push('payload');
  }

  if (missing.length) {
    const err = new Error(`Invalid job. Missing/invalid fields: ${missing.join(', ')}`);
    err.code = 'INVALID_JOB';
    throw err;
  }

  return normalized;
}

class ForwardSessionWorker {
  constructor(options = {}) {
    this.workerId = normalizeString(options.workerId) || `fw_worker_${process.pid}`;
    this.tempDirBase = normalizeString(options.tempDir);
    this.browserProduct = normalizeString(options.browserProduct || 'auto') || 'auto';
    this.idleTimeoutMs = Math.max(1000, Number(options.idleTimeoutMs || 10 * 60 * 1000));
    this.startUrl = normalizeString(options.startUrl);
    this.browser = null;
    this.page = null;
    this.userDataDir = '';
    this.runtimeHomeDir = '';
    this.actorId = '';
    this.currentJob = null;
    this.queue = [];
    this.isProcessing = false;
    this.idleTimer = null;
    this.sessionStatus = 'cold';
    this.state = {
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
      last_job_id: '',
      last_operation_type: '',
      last_error: '',
      queue_size: 0,
    };
  }

  snapshotState(extra = {}) {
    return {
      ...this.state,
      session_status: this.sessionStatus,
      actor_id: this.actorId,
      status: this.currentJob ? 'busy' : 'idle',
      queue_size: this.queue.length,
      last_activity_at: nowIso(),
      ...extra,
    };
  }

  touchState(patch = {}) {
    this.state = {
      ...this.state,
      ...patch,
      worker_id: this.workerId,
      actor_id: patch.actor_id ?? this.actorId,
      session_status: patch.session_status ?? this.sessionStatus,
      status: this.currentJob ? 'busy' : (patch.status || 'idle'),
      queue_size: patch.queue_size ?? this.queue.length,
      last_activity_at: nowIso(),
    };
  }

  scheduleIdleTimeout() {
    if (this.idleTimer) {
      clearTimeout(this.idleTimer);
      this.idleTimer = null;
    }
    if (this.currentJob || this.queue.length > 0) return;
    this.idleTimer = setTimeout(async () => {
      try {
        await this.shutdown('idle_timeout');
        emit({ ok: true, event: 'worker_idle_shutdown', worker_id: this.workerId, state: this.snapshotState({ status: 'idle' }) });
      } catch (err) {
        emit({ ok: false, event: 'worker_idle_shutdown_failed', worker_id: this.workerId, message: err.message || String(err) });
      }
    }, this.idleTimeoutMs);
    if (typeof this.idleTimer.unref === 'function') {
      this.idleTimer.unref();
    }
  }

  async ensureSession(job) {
    if (this.browser && this.page) {
      this.sessionStatus = 'ready';
      this.touchState({ actor_id: this.actorId, session_status: 'ready', expected_next_action: 'accept_job' });
      return;
    }

    this.userDataDir = mkTempDir(this.tempDirBase, 'forward-worker-profile-');
    this.runtimeHomeDir = mkTempDir(this.tempDirBase, 'forward-worker-home-');
    fs.mkdirSync(path.join(this.runtimeHomeDir, '.config'), { recursive: true });
    fs.mkdirSync(path.join(this.runtimeHomeDir, '.cache'), { recursive: true });

    const launched = await launchBrowserWithFallback(
      buildLaunchPlans(this.browserProduct, this.userDataDir, this.runtimeHomeDir),
      false
    );
    this.browser = launched.browser;
    this.page = await createPageWithWarmup(this.browser, {
      viewport: job.payload.viewport,
      viewport_width: job.payload.viewport_width,
      viewport_height: job.payload.viewport_height,
      width: job.payload.width,
      height: job.payload.height,
      window_size: job.payload.window_size,
    });

    if (this.startUrl) {
      await this.page.goto(this.startUrl, { waitUntil: 'domcontentloaded' });
    }

    this.sessionStatus = 'ready';
    this.touchState({
      actor_id: this.actorId,
      session_status: 'ready',
      expected_next_action: 'accept_job',
      status: 'idle',
    });
  }

  async executeJob(job) {
    if (this.actorId && this.actorId !== job.actor_id) {
      const err = new Error(`Worker ${this.workerId} is sticky to actor ${this.actorId}, got ${job.actor_id}`);
      err.code = 'ACTOR_MISMATCH';
      throw err;
    }
    if (!this.actorId) {
      this.actorId = job.actor_id;
    }

    await this.ensureSession(job);
    this.touchState({
      actor_id: this.actorId,
      status: 'busy',
      current_container_id: job.container_id,
      operation_profile: job.operation_profile,
      expected_next_action: 'complete_job',
      last_job_id: job.job_id,
      last_operation_type: job.operation_type,
      last_error: '',
    });

    if (job.operation_type === 'navigate' || job.operation_type === 'goto_url') {
      const url = normalizeString(job.payload.url);
      if (!url) {
        throw new Error('navigate payload.url is required');
      }
      await this.page.goto(url, { waitUntil: job.payload.wait_until || 'domcontentloaded' });
      this.touchState({ expected_next_action: 'accept_job' });
      return {
        ok: true,
        job_id: job.job_id,
        worker_id: this.workerId,
        action: 'navigated',
        url,
      };
    }

    if (job.operation_type === 'noop' || job.operation_type === 'ping') {
      this.touchState({ expected_next_action: 'accept_job' });
      return {
        ok: true,
        job_id: job.job_id,
        worker_id: this.workerId,
        action: 'noop',
      };
    }

    if (job.operation_type === 'add_parcel_to_forward_container') {
      this.touchState({ expected_next_action: 'forward_operation_not_implemented' });
      return {
        ok: false,
        job_id: job.job_id,
        worker_id: this.workerId,
        message: 'add_parcel_to_forward_container is not implemented yet; worker/session lifecycle is ready.',
      };
    }

    throw new Error(`Unsupported operation_type: ${job.operation_type}`);
  }

  enqueue(jobInput) {
    const job = validateJob(jobInput);
    return new Promise((resolve) => {
      this.queue.push({ job, resolve });
      this.touchState({ queue_size: this.queue.length, expected_next_action: 'process_queue' });
      this.scheduleIdleTimeout();
      void this.processQueue();
    });
  }

  async processQueue() {
    if (this.isProcessing) return;
    this.isProcessing = true;

    while (this.queue.length > 0) {
      const item = this.queue.shift();
      if (!item) continue;
      const { job, resolve } = item;
      this.currentJob = job;
      let response;
      try {
        response = await this.executeJob(job);
      } catch (err) {
        const message = err?.message || String(err);
        this.touchState({ last_error: message, expected_next_action: 'accept_job' });
        response = {
          ok: false,
          job_id: job.job_id,
          worker_id: this.workerId,
          message,
          code: err?.code || undefined,
        };
      } finally {
        this.currentJob = null;
        this.touchState({ status: 'idle', queue_size: this.queue.length });
      }
      resolve({ ...response, state: this.snapshotState() });
    }

    this.isProcessing = false;
    this.scheduleIdleTimeout();
  }

  async shutdown(reason = 'shutdown') {
    if (this.idleTimer) {
      clearTimeout(this.idleTimer);
      this.idleTimer = null;
    }
    if (this.browser) {
      try {
        await this.browser.close();
      } catch (_) {
        // ignore shutdown errors
      }
    }
    this.browser = null;
    this.page = null;
    await safeRm(this.userDataDir);
    await safeRm(this.runtimeHomeDir);
    this.userDataDir = '';
    this.runtimeHomeDir = '';
    this.sessionStatus = 'closed';
    this.touchState({
      status: 'idle',
      session_status: 'closed',
      expected_next_action: reason === 'idle_timeout' ? 'start_session' : 'stopped',
      queue_size: this.queue.length,
    });
  }
}

function parseCliArgs(argv) {
  const options = {};
  for (let i = 0; i < argv.length; i += 1) {
    const part = argv[i];
    if (part === '--worker-id') options.workerId = argv[i + 1];
    if (part === '--temp-dir') options.tempDir = argv[i + 1];
    if (part === '--browser' || part === '--browser-product') options.browserProduct = argv[i + 1];
    if (part === '--start-url') options.startUrl = argv[i + 1];
    if (part === '--idle-timeout-ms') options.idleTimeoutMs = Number(argv[i + 1] || 0);
  }
  return options;
}

async function main() {
  const args = process.argv.slice(2);
  if (args.includes('--help') || args.includes('-h')) {
    process.stdout.write([
      'Usage: node forward_session_worker.js [--worker-id fw_worker_01] [--idle-timeout-ms 600000]',
      'JSON line protocol over stdin:',
      '  {"command":"enqueue","job":{...}}',
      '  {"command":"get_state"}',
      '  {"command":"shutdown"}',
    ].join('\n') + '\n');
    process.exit(0);
  }

  const worker = new ForwardSessionWorker(parseCliArgs(args));
  emit({ ok: true, event: 'worker_started', worker_id: worker.workerId, state: worker.snapshotState() });

  const rl = readline.createInterface({ input: process.stdin, crlfDelay: Infinity });
  let commandChain = Promise.resolve();
  let closedByCommand = false;

  async function handleLine(line) {
    const raw = String(line || '').trim();
    if (!raw) return;

    let message;
    try {
      message = JSON.parse(raw);
    } catch (_) {
      emit({ ok: false, event: 'invalid_json', message: 'Input line is not valid JSON' });
      return;
    }

    const command = normalizeString(message.command || 'enqueue') || 'enqueue';

    if (command === 'get_state') {
      emit({ ok: true, event: 'state', worker_id: worker.workerId, state: worker.snapshotState() });
      return;
    }

    if (command === 'shutdown') {
      closedByCommand = true;
      await worker.shutdown('shutdown');
      emit({ ok: true, event: 'worker_stopped', worker_id: worker.workerId, state: worker.snapshotState() });
      rl.close();
      return;
    }

    if (command === 'enqueue') {
      try {
        const result = await worker.enqueue(message.job);
        emit({ event: 'job_result', ...result });
      } catch (err) {
        emit({ ok: false, event: 'enqueue_failed', message: err?.message || String(err), code: err?.code || undefined });
      }
      return;
    }

    emit({ ok: false, event: 'unknown_command', message: `Unknown command: ${command}` });
  }

  rl.on('line', (line) => {
    commandChain = commandChain
      .then(() => handleLine(line))
      .catch((err) => {
        emit({ ok: false, event: 'command_failed', message: err?.message || String(err) });
      });
  });

  rl.on('close', async () => {
    await commandChain;
    if (!closedByCommand) {
      await worker.shutdown('stdin_closed');
    }
  });
}

main().catch((err) => {
  emit({ ok: false, event: 'fatal', message: err?.message || String(err) });
  process.exit(1);
});

