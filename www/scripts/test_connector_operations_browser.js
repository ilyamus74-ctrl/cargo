#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const puppeteer = require('puppeteer');

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function applyVars(value, vars) {
  if (typeof value !== 'string') return value;
  return value.replace(/\$\{([a-zA-Z0-9_]+)\}/g, (_, key) => (vars[key] ?? ''));
}

function resolveExecutableCandidates() {
  const fromEnv = (process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
  const candidates = [
    fromEnv,
    '/usr/bin/chromium-browser',
    '/usr/bin/chromium',
    '/usr/lib64/chromium-browser/chromium-browser',
    '/usr/lib/chromium/chromium',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/google-chrome',
  ].filter(Boolean);

  const expanded = [];
  for (const candidate of candidates) {
    expanded.push(candidate);
    try {
      const rp = fs.realpathSync(candidate);
      if (rp && rp !== candidate) expanded.push(rp);
    } catch (_) {}
  }

  const existing = [];
  const seen = new Set();
  for (const candidate of expanded) {
    if (seen.has(candidate)) continue;
    seen.add(candidate);
    try {
      if (fs.existsSync(candidate)) existing.push(candidate);
    } catch (_) {}
  }

  return existing;
}

async function launchBrowserWithFallback(puppeteerLib, executableCandidates, sslIgnore, userDataDir, runtimeHomeDir) {
  const baseArgs = [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-crash-reporter',
    '--disable-breakpad',
    '--disable-features=Crashpad,CrashReporting',
    '--disable-crashpad-for-testing',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-dev-shm-usage',
    '--no-zygote',
    `--user-data-dir=${userDataDir}`,
  ];

  const launchEnv = {
    ...process.env,
    HOME: runtimeHomeDir,
    XDG_CONFIG_HOME: path.join(runtimeHomeDir, '.config'),
    XDG_CACHE_HOME: path.join(runtimeHomeDir, '.cache'),
  };

  const strategyTemplates = [
    { name: 'primary', opts: { args: baseArgs, env: launchEnv } },
    { name: 'pipe-mode', opts: { pipe: true, args: baseArgs, env: launchEnv } },
    // headless:'shell' доступен не везде; оставляем как опциональный фолбэк
    { name: 'headless-shell', opts: { headless: 'shell', args: baseArgs, env: launchEnv } },
  ];

  const candidates = [undefined, ...executableCandidates];
  const errors = [];
  let lastErr = null;

  for (const candidate of candidates) {
    for (const strategy of strategyTemplates) {
      const strategyName = `${strategy.name}${candidate ? `@${candidate}` : '@default'}`;
      try {
        return await puppeteerLib.launch({
          headless: true,
          ignoreHTTPSErrors: sslIgnore,
          executablePath: candidate,
          ...strategy.opts,
        });
      } catch (err) {
        const message = err?.message ? err.message : String(err);
        errors.push(`${strategyName}: ${message}`);
        lastErr = err;
      }
    }
  }

  const e = new Error('Browser launch failed. Attempts: ' + errors.join(' | '));
  e.cause = lastErr;
  throw e;
}

async function waitForDownloadedFile(dir, ext, timeoutMs) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    const files = fs
      .readdirSync(dir)
      .filter((name) => !name.endsWith('.crdownload'))
      .filter((name) => name.toLowerCase().endsWith(`.${ext.toLowerCase()}`));

    if (files.length > 0) {
      files.sort((a, b) => {
        const aM = fs.statSync(path.join(dir, a)).mtimeMs;
        const bM = fs.statSync(path.join(dir, b)).mtimeMs;
        return bM - aM;
      });
      const fullPath = path.join(dir, files[0]);
      const size = fs.statSync(fullPath).size;
      return { fullPath, size };
    }
    await sleep(500);
  }
  return null;
}

async function safeRm(dir) {
  if (!dir) return;
  try {
    fs.rmSync(dir, { recursive: true, force: true });
  } catch (_) {}
}

(async () => {
  const args = process.argv.slice(2);
  const payloadRaw = args[0] || '{}';

  let payload;
  try {
    payload = JSON.parse(payloadRaw);
  } catch (_) {
    process.stdout.write(JSON.stringify({ ok: false, message: 'Invalid JSON payload' }) + '\n');
    process.exit(1);
  }

  const vars = payload.vars || {};
  const steps = Array.isArray(payload.steps) ? payload.steps : [];
  const fileExtension = String(payload.file_extension || 'xlsx').toLowerCase();
  const sslIgnore = !!payload.ssl_ignore;

  if (steps.length === 0) {
    process.stdout.write(JSON.stringify({ ok: false, message: 'No steps provided' }) + '\n');
    process.exit(1);
  }

  const downloadDir = fs.mkdtempSync(path.join(os.tmpdir(), 'connector-op-'));
  let browser;
  let userDataDir = '';
  let runtimeHomeDir = '';

  const executableCandidates = resolveExecutableCandidates();
  const executablePath = executableCandidates[0] || null;

  try {
    userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'connector-browser-profile-'));
    runtimeHomeDir = fs.mkdtempSync(path.join(os.tmpdir(), 'connector-browser-home-'));
    fs.mkdirSync(path.join(runtimeHomeDir, '.config'), { recursive: true });
    fs.mkdirSync(path.join(runtimeHomeDir, '.cache'), { recursive: true });

    browser = await launchBrowserWithFallback(puppeteer, executableCandidates, sslIgnore, userDataDir, runtimeHomeDir);

    const page = await browser.newPage();
    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(60000);

    // headers
    const extraHeaders = {};
    if (payload.cookies && typeof payload.cookies === 'string' && payload.cookies.trim() !== '') {
      extraHeaders.Cookie = payload.cookies.trim();
    }
    if (payload.auth_token && typeof payload.auth_token === 'string' && payload.auth_token.trim() !== '') {
      extraHeaders.Authorization = `Bearer ${payload.auth_token.trim()}`;
    }
    if (Object.keys(extraHeaders).length) {
      await page.setExtraHTTPHeaders(extraHeaders);
    }

    // downloads
    const client = await page.target().createCDPSession();
    await client.send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: downloadDir });

    for (const step of steps) {
      const action = String(step.action || '').trim();
      if (!action) continue;

      if (action === 'goto') {
        const url = applyVars(step.url || '', vars);
        if (!url) throw new Error('goto.url is required');
        await page.goto(url, { waitUntil: step.wait_until || 'domcontentloaded' });
        continue;
      }

      if (action === 'click') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error('click.selector is required');
        await page.waitForSelector(selector, { visible: !!step.visible });
        await page.click(selector);
        continue;
      }

      // "fill" = ввести текст в поле
      if (action === 'fill' || action === 'type') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error(`${action}.selector is required`);

        // поддержка: step.text / step.value / step.var
        let text = '';
        if (typeof step.text === 'string') text = step.text;
        else if (typeof step.value === 'string') text = step.value;
        else if (typeof step.var === 'string') text = String(vars[step.var] ?? '');
        text = applyVars(text, vars);

        await page.waitForSelector(selector, { visible: !!step.visible });
        await page.focus(selector);

        if (step.clear !== false) {
          // чистим поле
          await page.click(selector, { clickCount: 3 });
          await page.keyboard.press('Backspace');
        }

        await page.type(selector, text, { delay: Number(step.delay_ms || 0) });
        continue;
      }

      if (action === 'wait_for') {
        const selector = applyVars(step.selector || '', vars);
        const timeout = Number(step.timeout_ms || 10000);

        if (selector) {
          await page.waitForSelector(selector, { timeout, visible: !!step.visible });
        } else {
          await page.waitForTimeout(timeout);
        }
        continue;
      }

      if (action === 'download') {
        const timeoutMs = Number(step.timeout_ms || 30000);
        const downloaded = await waitForDownloadedFile(downloadDir, fileExtension, timeoutMs);
        if (!downloaded) throw new Error(`Download not found with .${fileExtension} within ${timeoutMs}ms`);

        process.stdout.write(
          JSON.stringify({
            ok: true,
            message: 'Файл успешно скачан через browser steps',
            file_path: downloaded.fullPath,
            file_size: downloaded.size,
            file_extension: fileExtension,
            executable_path: executablePath,
          }) + '\n'
        );

        await browser.close();
        await safeRm(userDataDir);
        await safeRm(runtimeHomeDir);
        process.exit(0);
      }

      throw new Error(`Unsupported action: ${action}`);
    }

    // если не было явного download step — попробуем найти файл в конце
    const downloaded = await waitForDownloadedFile(downloadDir, fileExtension, 30000);
    if (!downloaded) throw new Error(`Download step missing or file not found (.${fileExtension})`);

    process.stdout.write(
      JSON.stringify({
        ok: true,
        message: 'Файл успешно скачан через browser steps',
        file_path: downloaded.fullPath,
        file_size: downloaded.size,
        file_extension: fileExtension,
        executable_path: executablePath,
      }) + '\n'
    );

    await browser.close();
    await safeRm(userDataDir);
    await safeRm(runtimeHomeDir);
    process.exit(0);
  } catch (err) {
    if (browser) {
      try {
        await browser.close();
      } catch (_) {}
    }
    await safeRm(userDataDir);
    await safeRm(runtimeHomeDir);

    process.stdout.write(
      JSON.stringify({
        ok: false,
        message: err?.message || 'Browser test failed',
        executable_path: executablePath,
      }) + '\n'
    );
    process.exit(1);
  }
})();

