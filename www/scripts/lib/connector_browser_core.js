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

function readBoolOption(payload, key, envKey, defaultValue = false) {
  if (payload && Object.prototype.hasOwnProperty.call(payload, key)) {
    const raw = payload[key];
    if (typeof raw === 'boolean') return raw;
    const txt = String(raw || '').trim().toLowerCase();
    if (txt === '1' || txt === 'true' || txt === 'yes' || txt === 'on') return true;
    if (txt === '0' || txt === 'false' || txt === 'no' || txt === 'off') return false;
  }

  const envRaw = String(process.env[envKey] || '').trim().toLowerCase();
  if (envRaw === '1' || envRaw === 'true' || envRaw === 'yes' || envRaw === 'on') return true;
  if (envRaw === '0' || envRaw === 'false' || envRaw === 'no' || envRaw === 'off') return false;

  return !!defaultValue;
}

function readNumberOption(payload, key, envKey, defaultValue = 0) {
  if (payload && Object.prototype.hasOwnProperty.call(payload, key)) {
    const n = Number(payload[key]);
    if (Number.isFinite(n)) return n;
  }

  const envRaw = String(process.env[envKey] || '').trim();
  if (envRaw !== '') {
    const n = Number(envRaw);
    if (Number.isFinite(n)) return n;
  }

  return Number(defaultValue);
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

function isLikelyExportSelector(selector) {
  const s = String(selector || "").trim().toLowerCase();
  if (!s) return false;
  return (
    s.includes("export") ||
    s.includes("download") ||
    s.includes("выгруз") ||
    s.includes("скачат")
  );
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


async function findElementHandleByText(page, selector, text, options = {}) {
  const timeout = Number(options.timeout || 30000);
  const visible = options.visible !== false;
  const matchMode = String(options.match || 'contains').trim().toLowerCase();
  const normalizedText = String(text || '').replace(/\s+/g, ' ').trim().toLowerCase();
  if (!selector) {
    throw new Error('findElementHandleByText.selector is required');
  }
  if (!normalizedText) {
    throw new Error('findElementHandleByText.text is required');
  }

  await page.waitForFunction(
    ({ selector: selectorValue, text: expectedText, visibleOnly, match }) => {
      const normalize = (input) => String(input || '').replace(/\s+/g, ' ').trim().toLowerCase();
      const isVisible = (node) => {
        if (!node) return false;
        const style = window.getComputedStyle(node);
        if (!style) return false;
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity || '1') === 0) {
          return false;
        }
        const rect = node.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      };
      return Array.from(document.querySelectorAll(selectorValue)).some((node) => {
        if (visibleOnly && !isVisible(node)) {
          return false;
        }
        const haystack = normalize(node.textContent || node.innerText || '');
        if (!haystack) {
          return false;
        }
        if (match === 'exact') {
          return haystack === expectedText;
        }
        return haystack.includes(expectedText);
      });
    },
    { timeout },
    { selector, text: normalizedText, visibleOnly: visible, match: matchMode }
  );

  const handles = await page.$$(selector);
  for (const handle of handles) {
    const isMatch = await page.evaluate((node, expectedText, visibleOnly, match) => {
      const normalize = (input) => String(input || '').replace(/\s+/g, ' ').trim().toLowerCase();
      const isVisible = (element) => {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        if (!style) return false;
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity || '1') === 0) {
          return false;
        }
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      };
      if (visibleOnly && !isVisible(node)) {
        return false;
      }
      const haystack = normalize(node.textContent || node.innerText || '');
      if (!haystack) {
        return false;
      }
      if (match === 'exact') {
        return haystack === expectedText;
      }
      return haystack.includes(expectedText);
    }, handle, normalizedText, visible, matchMode);
    if (isMatch) {
      return handle;
    }
    await handle.dispose();
  }

  throw new Error(`No element found for selector ${selector} with text ${text}`);
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


function looksLikeZipContainer(filePath) {
  try {
    const fd = fs.openSync(filePath, 'r');
    const buf = Buffer.alloc(4);
    fs.readSync(fd, buf, 0, 4, 0);
    fs.closeSync(fd);
    return buf[0] === 0x50 && buf[1] === 0x4b;
  } catch (_) {
    return false;
  }
}

async function waitForStableDownloadedFileInDirs(dirs, ext, timeoutMs, opts = {}) {
  const started = Date.now();
  const minSizeBytes = Math.max(0, Number(opts.minSizeBytes || 0));
  const stablePollsRequired = Math.max(1, Number(opts.stablePollsRequired || 2));
  const stableWindowMs = Math.max(0, Number(opts.stableWindowMs || 1000));
  const seen = new Map();

  while (Date.now() - started < timeoutMs) {
    for (const dir of dirs) {
      if (!dir || !fs.existsSync(dir)) continue;

      const downloaded = await waitForDownloadedFile(dir, ext, 500);
      if (!downloaded || !downloaded.fullPath) continue;

      const key = downloaded.fullPath;
      const prev = seen.get(key);
      const isSameSize = prev && prev.size === downloaded.size;
      const stableCount = isSameSize ? (prev.stableCount + 1) : 1;
      const firstSeenAt = prev?.firstSeenAt || Date.now();
      const lastChangedAt = isSameSize ? (prev?.lastChangedAt || Date.now()) : Date.now();
      seen.set(key, { size: downloaded.size, stableCount, firstSeenAt, lastChangedAt });

      if (downloaded.size < minSizeBytes) continue;
      if (stableCount < stablePollsRequired) continue;
      if (Date.now() - lastChangedAt < stableWindowMs) continue;

      return {
        ...downloaded,
        is_zip_container: looksLikeZipContainer(downloaded.fullPath),
      };
    }

    await sleep(250);
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


async function saveFinalHtmlSnapshot(page, artifactsDir) {
  if (!page || !artifactsDir) return '';

  try {
    const html = await page.content();
    const fullPath = path.join(artifactsDir, 'final_page.html');
    fs.writeFileSync(fullPath, html, 'utf8');
    return fullPath;
  } catch (_) {
    return '';
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

async function serializePageCookies(page) {
  if (!page || typeof page.cookies !== 'function') return '';

  try {
    const cookies = await page.cookies();
    if (!Array.isArray(cookies) || cookies.length === 0) return '';

    return cookies
      .filter((cookie) => cookie && cookie.name)
      .map((cookie) => `${cookie.name}=${cookie.value ?? ''}`)
      .join('; ');
  } catch (_) {
    return '';
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

async function clickNearElement(page, selector, offsetX = -14, offsetY = 0) {
  if (!selector) return false;

  const point = await page.$eval(selector, (el, dx, dy) => {
    const rect = el.getBoundingClientRect();
    const x = Math.max(2, Math.floor(rect.left + dx));
    const y = Math.max(2, Math.floor(rect.top + rect.height / 2 + dy));
    return { x, y };
  }, Number(offsetX), Number(offsetY));

  if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.y)) return false;
  await page.mouse.click(point.x, point.y);
  return true;
}

async function clickNearExportButton(page, offsetX = -14, offsetY = 0) {
  const handles = await page.$$('button, a, input[type="button"], input[type="submit"]');
  for (const handle of handles) {
    try {
      const matches = await handle.evaluate((el) => {
        const text = String(el?.innerText || el?.value || '').trim().toLowerCase();
        return text === 'export' || text.includes('export');
      });
      if (!matches) continue;

      const box = await handle.boundingBox();
      if (!box) continue;

      const x = Math.max(2, Math.floor(box.x + Number(offsetX)));
      const y = Math.max(2, Math.floor(box.y + box.height / 2 + Number(offsetY)));
      await page.mouse.click(x, y);
      return true;
    } catch (_) {
      // continue
    }
  }
  return false;
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


function shouldLogDownloadResponse(response) {
  try {
    const headers = response.headers() || {};
    const ct = String(headers['content-type'] || '').toLowerCase();
    const cd = String(headers['content-disposition'] || '').toLowerCase();
    const u = String(response.url() || '').toLowerCase();
    return (
      cd.includes('attachment') ||
      ct.includes('spreadsheet') ||
      ct.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') ||
      u.includes('export') ||
      u.includes('download')
    );
  } catch (_) {
    return false;
  }
}



function tryParseContentLength(response) {
  try {
    const headers = response.headers() || {};
    const raw = headers['content-length'];
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
  } catch (_) {
    return null;
  }
}

function isLikelySpreadsheetDownloadResponse(response) {
  try {
    const headers = response.headers() || {};
    const ct = String(headers['content-type'] || '').toLowerCase();
    const cd = String(headers['content-disposition'] || '').toLowerCase();
    const u = String(response.url() || '').toLowerCase();
    return (
      ct.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') ||
      ct.includes('spreadsheet') ||
      (cd.includes('attachment') && cd.includes('.xlsx')) ||
      u.includes('.xlsx')
    );
  } catch (_) {
    return false;
  }
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

module.exports = {
  sleep,
  readBoolOption,
  readNumberOption,
  applyVars,
  convertIsoDateToDotFormat,
  normalizeDateForNativeInput,
  isLikelyExportSelector,
  selectorCandidates,
  findSelectorWithFallback,
  findElementHandleByText,
  resolveViewport,
  uniqueExistingPaths,
  resolveExecutableCandidates,
  buildLaunchPlans,
  launchBrowserWithFallback,
  waitForDownloadedFile,
  looksLikeZipContainer,
  waitForStableDownloadedFileInDirs,
  safeRm,
  safeFilePart,
  saveStepScreenshot,
  saveFinalHtmlSnapshot,
  writeArtifactNote,
  ensureDirExists,
  serializePageCookies,
  mkTempDir,
  configureDownloadBehavior,
  isTransientFrameError,
  runWithTransientRetry,
  safeGoto,
  clickNearElement,
  clickNearExportButton,
  createPageWithWarmup,
  waitForDownloadedFileInDirs,
  shouldLogDownloadResponse,
  tryParseContentLength,
  isLikelySpreadsheetDownloadResponse,
  persistDownloadedFileIfNeeded,
};
