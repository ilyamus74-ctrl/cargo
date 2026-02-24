#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const puppeteer = require('puppeteer');
const puppeteerExtra = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteerExtra.use(StealthPlugin());

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function applyVars(value, vars) {
  if (typeof value !== 'string') return value;
  return value.replace(/\$\{([a-zA-Z0-9_]+)\}/g, (_, key) => (vars[key] ?? ''));
}

function convertIsoDateToDotFormat(value) {
  const v = String(value || '').trim();
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (!m) return value;
  return `${m[3]}.${m[2]}.${m[1]}`;
}


function normalizeDateForNativeInput(value) {
  const v = String(value || '').trim();
  let m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;

  m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(v);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;

  return '';
}

function selectorCandidates(selector) {
  const base = String(selector || '').trim();
  if (!base) return [];

  const candidates = [base];
  const m = /^input\[name=["']?([^"'\]]+)["']?\]$/i.exec(base);
  if (m) {
    const name = m[1];
    candidates.push(`[name="${name}"]`);
    candidates.push(`#${name}`);
    candidates.push(`input#${name}`);
  }

  return Array.from(new Set(candidates));
}

async function findSelectorWithFallback(page, selector, options = {}) {
  const timeout = Number(options.timeout || 30000);
  const visible = !!options.visible;
  const candidates = selectorCandidates(selector);
  let lastErr = null;

  for (const candidate of candidates) {
    try {
      await page.waitForSelector(candidate, { timeout, visible });
      return candidate;
    } catch (err) {
      lastErr = err;
    }
  }

  const finalErr = new Error(`No element found for selector: ${selector}`);
  finalErr.cause = lastErr;
  throw finalErr;
}


function resolveViewport(payload) {
  const DEFAULT_WIDTH = 1600;
  const DEFAULT_HEIGHT = 900;

  const raw = String(payload?.viewport || payload?.window_size || '').trim().toLowerCase();
  const m = /^(\d{3,5})[x×](\d{3,5})$/.exec(raw);
  if (m) {
    return { width: Number(m[1]), height: Number(m[2]) };
  }

  const width = Number(payload?.viewport_width || payload?.width || DEFAULT_WIDTH);
  const height = Number(payload?.viewport_height || payload?.height || DEFAULT_HEIGHT);

  return {
    width: Number.isFinite(width) && width >= 320 ? Math.floor(width) : DEFAULT_WIDTH,
    height: Number.isFinite(height) && height >= 240 ? Math.floor(height) : DEFAULT_HEIGHT,
  };
}

function uniqueExistingPaths(candidates) {
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
    if (!candidate || seen.has(candidate)) continue;
    seen.add(candidate);
    try {
      if (fs.existsSync(candidate)) existing.push(candidate);
    } catch (_) {}
  }

  return existing;
}


function resolveExecutableCandidates(product) {
  const normalized = String(product || 'chrome').toLowerCase();

  if (normalized === 'firefox') {
    const fromEnv = (process.env.FIREFOX_EXECUTABLE_PATH || process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
    return uniqueExistingPaths([
      fromEnv,
      '/usr/bin/firefox',
      '/usr/lib64/firefox/firefox',
      '/usr/lib/firefox/firefox',
    ].filter(Boolean));
  }

  const fromEnv = (process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
  return uniqueExistingPaths([
    fromEnv,
    '/usr/bin/chromium-browser',
    '/usr/bin/chromium',
    '/usr/lib64/chromium-browser/chromium-browser',
    '/usr/lib/chromium/chromium',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/google-chrome',
  ].filter(Boolean));
}

function buildLaunchPlans(browserProduct, userDataDir, runtimeHomeDir) {
  const launchEnv = {
    ...process.env,
    HOME: runtimeHomeDir,
    XDG_CONFIG_HOME: path.join(runtimeHomeDir, '.config'),
    XDG_CACHE_HOME: path.join(runtimeHomeDir, '.cache'),
  };

  const chromeArgs = [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-gpu',
    '--single-process',
    '--disable-site-isolation-trials',
    '--disable-blink-features=AutomationControlled',
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

  const firefoxArgs = [
    '--headless',
    '--no-remote',
    '--profile',
    userDataDir,
  ];

  const chromePlan = {
    product: 'chrome',
    lib: puppeteer,
    candidates: resolveExecutableCandidates('chrome'),
    strategies: [
      { name: 'primary', opts: { args: chromeArgs, env: launchEnv } },
      { name: 'pipe-mode', opts: { pipe: true, args: chromeArgs, env: launchEnv } },
      { name: 'headless-shell', opts: { headless: 'shell', args: chromeArgs, env: launchEnv } },
    ],
  };

  const firefoxPlan = {
    product: 'firefox',
    lib: puppeteer,
    candidates: resolveExecutableCandidates('firefox'),
    strategies: [
      { name: 'firefox', opts: { product: 'firefox', browser: 'firefox', args: firefoxArgs, env: launchEnv } },
    ],
    includeDefaultCandidate: false,
  };

  const mode = String(browserProduct || 'chrome').toLowerCase();
  if (mode === 'firefox') return [firefoxPlan];
  if (mode === 'auto') return [chromePlan, firefoxPlan];
  return [chromePlan];
}
async function launchBrowserWithFallback(launchPlans, sslIgnore) {
  const errors = [];
  let lastErr = null;

  for (const plan of launchPlans) {
    const candidates = plan.includeDefaultCandidate === false ? [...plan.candidates] : [undefined, ...plan.candidates];
    if (!candidates.length) {
      errors.push(`${plan.product}: no executable candidates found`);
      continue;
    }
    for (const candidate of candidates) {
      for (const strategy of plan.strategies) {
        const strategyName = `${plan.product}:${strategy.name}${candidate ? `@${candidate}` : '@default'}`;
        try {
          const browser = await plan.lib.launch({
            headless: true,
            ignoreHTTPSErrors: sslIgnore,
            executablePath: candidate,
            ...strategy.opts,
          });
          return { browser, product: plan.product, executablePath: candidate || null };
        } catch (err) {
          const message = err?.message ? err.message : String(err);
          errors.push(`${strategyName}: ${message}`);
          lastErr = err;
        }
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
function safeFilePart(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9._-]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'step';
}

async function saveStepScreenshot(page, dir, stepNo, action) {
  if (!page || !dir) return null;

  const fileName = `${String(stepNo).padStart(2, '0')}-${safeFilePart(action)}.png`;
  const fullPath = path.join(dir, fileName);
  try {
    await page.screenshot({ path: fullPath, fullPage: true });
    return fullPath;
  } catch (_) {
    return null;
  }
}


function writeArtifactNote(dir, fileName, text) {
  if (!dir || !fileName) return null;
  const fullPath = path.join(dir, fileName);
  try {
    fs.writeFileSync(fullPath, String(text || ''), 'utf8');
    return fullPath;
  } catch (_) {
    return null;
  }
}

function ensureDirExists(dir) {
  if (!dir) return false;
  try {
    fs.mkdirSync(dir, { recursive: true });
    return true;
  } catch (_) {
    return false;
  }
}

function mkTempDir(preferredBaseDir, prefix) {
  const normalizedBase = (preferredBaseDir || '').trim();
  if (normalizedBase !== '' && ensureDirExists(normalizedBase)) {
    try {
      return fs.mkdtempSync(path.join(normalizedBase, prefix));
    } catch (_) {}
  }

  return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

async function configureDownloadBehavior(page, downloadDir) {
  const errors = [];
  const sessionFactories = [
    { name: 'page.createCDPSession', create: async () => page.createCDPSession() },
    { name: 'page.target.createCDPSession', create: async () => page.target().createCDPSession() },
  ];

  for (const factory of sessionFactories) {
    try {
      const client = await factory.create();
      await client.send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: downloadDir });
      return { configured: true, warning: '' };
    } catch (err) {
      errors.push(`${factory.name}: ${err?.message || String(err)}`);
    }
  }

  return {
    configured: false,
    warning: 'Не удалось настроить папку загрузок через CDP. Будет использована стандартная папка браузера. ' + errors.join(' | '),
  };
}



function isTransientFrameError(err) {
  const msg = err?.message ? String(err.message) : String(err || '');
  return (
    msg.includes('Requesting main frame too early') ||
    msg.includes('Navigating frame was detached') ||
    msg.includes('Execution context was destroyed')
  );
}

async function runWithTransientRetry(fn, opts = {}) {
  const maxAttempts = Number(opts.maxAttempts || 5);
  const baseDelayMs = Number(opts.baseDelayMs || 400);
  let lastErr = null;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (!isTransientFrameError(err) || attempt === maxAttempts) {
        throw err;
      }
      await sleep(baseDelayMs * attempt);
    }
  }

  throw lastErr || new Error('Transient retry failed');
}

async function safeGoto(page, url, options) {
  await runWithTransientRetry(() => page.goto(url, options), { maxAttempts: 6, baseDelayMs: 450 });
}



async function createPageWithWarmup(browser, payload) {
  const maxAttempts = 5;
  const viewport = resolveViewport(payload);

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    let page = null;
    try {
      page = await browser.newPage();
      page.setDefaultTimeout(30000);
      page.setDefaultNavigationTimeout(60000);
      await page.setViewport(viewport);

      // Даём chromium небольшой старт под apache/php-fpm до первого реального шага.
      await sleep(200 * attempt);
      await runWithTransientRetry(
        () => page.goto('about:blank', { waitUntil: 'domcontentloaded', timeout: 15000 }),
        { maxAttempts: 4, baseDelayMs: 300 }
      );
      await sleep(500);

      return page;
    } catch (err) {
      if (page) {
        try {
          await page.close();
        } catch (_) {}
      }

      if (!isTransientFrameError(err) || attempt === maxAttempts) {
        throw err;
      }
      await sleep(350 * attempt);
    }
  }

  throw new Error('Unable to initialize browser page');
}


async function waitForDownloadedFileInDirs(dirs, ext, timeoutMs) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    for (const dir of dirs) {
      if (!dir || !fs.existsSync(dir)) continue;
      const downloaded = await waitForDownloadedFile(dir, ext, 500);
      if (downloaded) return downloaded;
    }
  }
  return null;
}

function persistDownloadedFileIfNeeded(downloaded, runtimeHomeDir, stableDownloadDir) {
  if (!downloaded || !downloaded.fullPath) return downloaded;

  const fullPath = path.resolve(downloaded.fullPath);
  const runtimeRoot = runtimeHomeDir ? path.resolve(runtimeHomeDir) : '';
  if (!runtimeRoot || (!fullPath.startsWith(runtimeRoot + path.sep) && fullPath !== runtimeRoot)) {
    return downloaded;
  }

  try {
    fs.mkdirSync(stableDownloadDir, { recursive: true });
    const fileName = path.basename(fullPath || '') || `downloaded_${Date.now()}`;
    const targetPath = path.join(stableDownloadDir, `${Date.now()}_${fileName}`);
    fs.copyFileSync(fullPath, targetPath);
    const size = fs.statSync(targetPath).size;
    return { fullPath: targetPath, size };
  } catch (_) {
    return downloaded;
  }
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
  const forceCdpDownloadBehavior = !!payload.force_cdp_download_behavior;
  const tempDirBase = typeof payload.temp_dir === 'string' ? payload.temp_dir.trim() : '';
  const browserProduct = String(payload.browser_product || payload.browser || 'auto').toLowerCase();

  if (steps.length === 0) {
    process.stdout.write(JSON.stringify({ ok: false, message: 'No steps provided' }) + '\n');
    process.exit(1);
  }

  const downloadDir = mkTempDir(tempDirBase, 'connector-op-');
  const artifactsDir = mkTempDir(tempDirBase, 'connector-op-artifacts-');
  const captureScreenshots = payload.capture_screenshots !== false;
  const stepLog = [];
  let browser;
  let userDataDir = '';
  let runtimeHomeDir = '';

  let executablePath = null;
  let resolvedBrowserProduct = browserProduct;

  try {
    userDataDir = mkTempDir(tempDirBase, 'connector-browser-profile-');
    runtimeHomeDir = mkTempDir(tempDirBase, 'connector-browser-home-');
    fs.mkdirSync(path.join(runtimeHomeDir, '.config'), { recursive: true });
    fs.mkdirSync(path.join(runtimeHomeDir, '.cache'), { recursive: true });

    const launchPlans = buildLaunchPlans(browserProduct, userDataDir, runtimeHomeDir);
    const launched = await launchBrowserWithFallback(launchPlans, sslIgnore);
    browser = launched.browser;
    executablePath = launched.executablePath;
    resolvedBrowserProduct = launched.product;

    const page = await createPageWithWarmup(browser, payload);

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
    const fallbackDownloadDir = path.join(runtimeHomeDir, 'Downloads');
    fs.mkdirSync(fallbackDownloadDir, { recursive: true });

    let downloadBehavior = { configured: false, warning: '' };
    if (resolvedBrowserProduct === 'firefox') {
      downloadBehavior = {
        configured: false,
        warning: 'CDP download behavior disabled for firefox.',
      };
    } else if (forceCdpDownloadBehavior) {
      try {
        downloadBehavior = await configureDownloadBehavior(page, downloadDir);
      } catch (err) {
        downloadBehavior = {
          configured: false,
          warning: 'CDP download behavior crashed unexpectedly: ' + (err?.message || String(err)),
        };
      }
    } else {
      downloadBehavior = {
        configured: false,
        warning: 'CDP download behavior skipped (force_cdp_download_behavior=false). Using browser default Downloads folder.',
      };
    }

    let stepNo = 0;
    for (const step of steps) {
      stepNo += 1;
      const action = String(step.action || '').trim();
      if (!action) {
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action: 'empty', status: 'skip', message: 'empty action' });
        continue;
      }

      stepLog.push({
        time: new Date().toISOString(),
        step: stepNo,
        action,
        status: 'start',
        url: step.url || undefined,
        selector: step.selector || undefined,
      });

      if (action === 'goto') {
        const url = applyVars(step.url || '', vars);
        if (!url) throw new Error('goto.url is required');
        await safeGoto(page, url, { waitUntil: step.wait_until || 'domcontentloaded' });
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { url } });
        continue;
      }

      if (action === 'click') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error('click.selector is required');
        await runWithTransientRetry(async () => {
          await page.waitForSelector(selector, { visible: !!step.visible });
          await page.click(selector);
        });
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector } });
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

        await runWithTransientRetry(async () => {
          await page.waitForSelector(selector, { visible: !!step.visible });

          const fieldMeta = await page.$eval(selector, (el) => {
            const element = el;
            const tagName = String(element?.tagName || '').toLowerCase();
            const type = String(element?.getAttribute?.('type') || '').toLowerCase();
            return { isNativeDateInput: tagName === 'input' && type === 'date' };
          });

          if (fieldMeta?.isNativeDateInput) {
            const nativeDateValue = normalizeDateForNativeInput(text);
            if (!nativeDateValue) {
              throw new Error(`Cannot normalize date value for native input[type=date]: ${text}`);
            }

            await page.$eval(selector, (el, val) => {
              const input = el;
              input.focus();
              input.value = val;
              input.dispatchEvent(new Event('input', { bubbles: true }));
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }, nativeDateValue);
            return;
          }
          
          await page.focus(selector);
          const typedText = convertIsoDateToDotFormat(text);

          if (step.clear !== false) {
            // чистим поле
            await page.click(selector, { clickCount: 3 });
            await page.keyboard.press('Backspace');
          }
          await page.$eval(selector, (el) => {
            if (typeof el.setSelectionRange === 'function') {
              el.setSelectionRange(0, 0);
            }
          });

          await page.type(selector, typedText, { delay: Number(step.delay_ms || 0) });
        });
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector } });
        continue;
      }

      if (action === 'wait_for') {
        const selector = applyVars(step.selector || '', vars);
        const timeout = Number(step.timeout_ms || 10000);

        if (selector) {
          await runWithTransientRetry(() => page.waitForSelector(selector, { timeout, visible: !!step.visible }));
        } else {
          await runWithTransientRetry(() => sleep(timeout));
        }
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector: selector || undefined, timeout } });
        continue;
      }

      if (action === 'download') {
        const timeoutMs = Number(step.timeout_ms || 30000);
        const downloaded = await waitForDownloadedFileInDirs([downloadDir, fallbackDownloadDir], fileExtension, timeoutMs);
        if (!downloaded) throw new Error(`Download not found with .${fileExtension} within ${timeoutMs}ms`);
        const persistedDownloaded = persistDownloadedFileIfNeeded(downloaded, runtimeHomeDir, downloadDir);

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { timeoutMs } });

        process.stdout.write(
          JSON.stringify({
            ok: true,
            message: 'Файл успешно скачан через browser steps',
            file_path: persistedDownloaded.fullPath,
            file_size: persistedDownloaded.size,
            file_extension: fileExtension,
            executable_path: executablePath,
            browser_product: resolvedBrowserProduct,
            download_cdp_configured: !!downloadBehavior.configured,
            download_warning: downloadBehavior.warning || undefined,
            step_log: stepLog,
            artifacts_dir: artifactsDir,
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
    const downloaded = await waitForDownloadedFileInDirs([downloadDir, fallbackDownloadDir], fileExtension, 30000);
    if (!downloaded) throw new Error(`Download step missing or file not found (.${fileExtension})`);

    const finalShot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo + 1, 'final') : null;
    stepLog.push({ time: new Date().toISOString(), step: stepNo + 1, action: 'final_download_probe', status: 'ok', screenshot: finalShot || undefined });

    process.stdout.write(
      JSON.stringify({
        ok: true,
        message: 'Файл успешно скачан через browser steps',
        file_path: persistedDownloaded.fullPath,
        file_size: persistedDownloaded.size,
        file_extension: fileExtension,
        executable_path: executablePath,
        browser_product: resolvedBrowserProduct,
        download_cdp_configured: !!downloadBehavior.configured,
        download_warning: downloadBehavior.warning || undefined,
        step_log: stepLog,
        artifacts_dir: artifactsDir,
      }) + '\n'
    );

    await browser.close();
    await safeRm(userDataDir);
    await safeRm(runtimeHomeDir);
    process.exit(0);
  } catch (err) {

    let pageRef = null;
    if (browser) {
      try {
        const pages = await browser.pages();
        pageRef = pages[0] || null;
      } catch (_) {}
    }

    const errorShot = captureScreenshots ? await saveStepScreenshot(pageRef, artifactsDir, stepLog.length + 1, 'error') : null;
    const errorNote = !errorShot
      ? writeArtifactNote(
          artifactsDir,
          `${String(stepLog.length + 1).padStart(2, '0')}-error.txt`,
          `Screenshot was not captured.\nReason: ${err?.message || 'Browser test failed'}\nTime: ${new Date().toISOString()}\n`
        )
      : null;
    stepLog.push({
      time: new Date().toISOString(),
      step: stepLog.length + 1,
      action: 'error',
      status: 'fail',
      message: err?.message || 'Browser test failed',
      screenshot: errorShot || undefined,
      note: errorNote || undefined,
    });


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
        browser_product: resolvedBrowserProduct,
        step_log: stepLog,
        artifacts_dir: artifactsDir,
      }) + '\n'
    );
    process.exit(1);
  }
})();

