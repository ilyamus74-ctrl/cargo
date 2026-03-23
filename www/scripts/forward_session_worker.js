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

    this.actorId = '';
    this.currentContainerId = '';
    this.operationProfile = '';
    this.lastJob = null;
    this.launchMeta = {
      product: this.browserProduct,
      executablePath: null,
    };
  }

  isStarted() {
    return !!(this.browser && this.page);
  }

  touch() {
    this.lastActivityAt = nowIso();
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
    this.touch();

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

    this.touch();

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

    if (job.operation_type === 'noop' || job.operation_type === 'ping') {
      if (!this.isStarted()) {
        await this.start();
      } else {
        this.touch();
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
      } else {
        this.touch();
      }

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

    this.lastActivityAt = nowIso();

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
        'One JSON command per line, for example:',
        '  {"command":"status"}',
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
  validateJob,
  runCli,
};
