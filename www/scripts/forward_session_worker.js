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
  return new Date().toISOString()
}

function makeWorkerId() {
  return `forward_worker_${Date.now()}_${process.pid}`;
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
    this.launchMeta = {
      product: this.browserProduct,
      executablePath: null,
    };
  }

  isStarted() {
    return !!(this.browser && this.page)
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
  runCli,
};
