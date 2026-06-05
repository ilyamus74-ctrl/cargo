#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const {
  sleep,
  readBoolOption,
  readNumberOption,
  applyVars,
  convertIsoDateToDotFormat,
  normalizeDateForNativeInput,
  isLikelyExportSelector,
  findSelectorWithFallback,
  findElementHandleByText,
  buildLaunchPlans,
  launchBrowserWithFallback,
  waitForStableDownloadedFileInDirs,
  safeRm,
  saveStepScreenshot,
  saveFinalHtmlSnapshot,
  writeArtifactNote,
  serializePageCookies,
  mkTempDir,
  configureDownloadBehavior,
  runWithTransientRetry,
  safeGoto,
  clickNearElement,
  clickNearExportButton,
  createPageWithWarmup,
  shouldLogDownloadResponse,
  tryParseContentLength,
  isLikelySpreadsheetDownloadResponse,
  persistDownloadedFileIfNeeded,
  selectorCandidates,
} = require('./lib/connector_browser_core');



(async () => {
  const args = process.argv.slice(2);
  const payloadRaw = args[0] || '{}';

  if (args[0] === '--help' || args[0] === '-h') {
    process.stdout.write(
      [
        'Usage: node test_connector_operations_browser.js "{...json payload...}"',
        'Debug options:',
        '  payload.debug_download_network=true (or env CONNECTOR_DEBUG_DOWNLOAD_NETWORK=1)',
        '  payload.min_file_size_bytes=50000 (or env CONNECTOR_MIN_FILE_SIZE_BYTES=50000)',
      ].join('\n') + '\n'
    );
    process.exit(0);
  }


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
  const defaultPostGotoWaitMs = Math.max(0, Number(payload.post_goto_wait_ms ?? 1200));
  const defaultBeforeExportClickWaitMs = Math.max(0, Number(payload.before_export_click_wait_ms ?? 1500));
  const minFileSizeBytes = Math.max(0, readNumberOption(payload, 'min_file_size_bytes', 'CONNECTOR_MIN_FILE_SIZE_BYTES', 1));
  const debugDownloadNetwork = readBoolOption(payload, 'debug_download_network', 'CONNECTOR_DEBUG_DOWNLOAD_NETWORK', false);
  const expectDownload = readBoolOption(payload, 'expect_download', 'EXPECT_DOWNLOAD', true);
  const errorSelector = applyVars(String(payload.error_selector || ''), vars).trim();
  const errorWaitMs = Math.max(0, Number(payload.error_wait_ms ?? 1500));

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

    const downloadNetworkLog = [];
    let expectedDownloadSizeBytes = 0;
    page.on('response', async (response) => {
      const contentLength = tryParseContentLength(response);
      if (isLikelySpreadsheetDownloadResponse(response) && contentLength) {
        expectedDownloadSizeBytes = Math.max(expectedDownloadSizeBytes, contentLength);
      }
      if (!debugDownloadNetwork || !shouldLogDownloadResponse(response)) return;
      downloadNetworkLog.push({
        time: new Date().toISOString(),
        status: response.status(),
        url: response.url(),
        content_length: contentLength,
      });
    });


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

        const postGotoWaitMs = Math.max(0, Number(step.post_goto_wait_ms ?? defaultPostGotoWaitMs));
        if (postGotoWaitMs > 0) {
          await sleep(postGotoWaitMs);
        }

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { url, postGotoWaitMs } });
        continue;
      }

      if (action === 'click') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error('click.selector is required');
        const requireVisible = step.visible !== false;
        const isExportClick = isLikelyExportSelector(selector) || step.is_export_click === true;
        const beforeClickWaitMs = Math.max(
          0,
          Number(
            step.before_click_wait_ms
              ?? (isExportClick ? (step.before_export_click_wait_ms ?? defaultBeforeExportClickWaitMs) : 0)
          )
        );

        await runWithTransientRetry(async () => {
          const matchedSelector = await findSelectorWithFallback(page, selector, { visible: requireVisible });
          if (beforeClickWaitMs > 0) {
            await sleep(beforeClickWaitMs);
          }
          await page.click(matchedSelector);
        });
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector, beforeClickWaitMs, isExportClick } });
        continue;
      }


      if (action === 'click_by_text') {
        const selector = applyVars(step.selector || '', vars);
        const text = applyVars(String(step.text ?? step.value ?? ''), vars);
        if (!selector) throw new Error('click_by_text.selector is required');
        if (!text) throw new Error('click_by_text.text is required');
        const requireVisible = step.visible !== false;
        const matchMode = String(step.match || 'contains').trim().toLowerCase();
        const beforeClickWaitMs = Math.max(0, Number(step.before_click_wait_ms || 0));

        await runWithTransientRetry(async () => {
          const handle = await findElementHandleByText(page, selector, text, {
            timeout: Number(step.timeout_ms || 30000),
            visible: requireVisible,
            match: matchMode,
          });
          try {
            if (beforeClickWaitMs > 0) {
              await sleep(beforeClickWaitMs);
            }
            await handle.evaluate((node) => {
              node.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' });
            });
            await handle.click();
          } finally {
            await handle.dispose();
          }
        });
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({
          time: new Date().toISOString(),
          step: stepNo,
          action,
          status: 'ok',
          screenshot: shot || undefined,
          meta: { selector, text, match: matchMode, beforeClickWaitMs }
        });
        continue;
      }


      // "fill" = ввести текст в поле
      if (action === 'fill' || action === 'type') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error(`${action}.selector is required`);
        const requireVisible = step.visible !== false;
        const ignoreReadonly = step.ignore_readonly !== false;
        const ignoreDisabled = step.ignore_disabled !== false;
        const ignoreMissing = step.ignore_missing === true || step.optional === true;

        // поддержка: step.text / step.value / step.var
        let text = '';
        if (typeof step.text === 'string') text = step.text;
        else if (typeof step.value === 'string') text = step.value;
        else if (typeof step.var === 'string') text = String(vars[step.var] ?? '');
        text = applyVars(text, vars);

        let skippedReason = '';
        await runWithTransientRetry(async () => {
          let matchedSelector = '';
          try {
            matchedSelector = await findSelectorWithFallback(page, selector, { visible: requireVisible });
          } catch (err) {
            if (!ignoreMissing) {
              throw err;
            }
            skippedReason = `selector_not_found:${selector}`;
            return;
          }

          const editabilityMeta = await page.$eval(matchedSelector, (el) => {
            const element = el;
            const readOnly = !!element?.readOnly || element?.hasAttribute?.('readonly') === true;
            const disabled = !!element?.disabled || element?.hasAttribute?.('disabled') === true;
            return { readOnly, disabled };
          });

          if (editabilityMeta?.readOnly && ignoreReadonly) {
            skippedReason = `readonly:${matchedSelector}`;
            return;
          }
          if (editabilityMeta?.disabled && ignoreDisabled) {
            skippedReason = `disabled:${matchedSelector}`;
            return;
          }

          const fieldMeta = await page.$eval(matchedSelector, (el) => {
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

            await page.$eval(matchedSelector, (el, val) => {
              const input = el;
              input.focus();
              input.value = val;
              input.dispatchEvent(new Event('input', { bubbles: true }));
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }, nativeDateValue);

            const blurNearSelector = applyVars(step.blur_near_selector || step.after_fill_click_near_selector || '', vars);
            const blurOffsetX = Number(step.blur_near_offset_x ?? step.after_fill_click_near_offset_x ?? -14);
            const blurOffsetY = Number(step.blur_near_offset_y ?? step.after_fill_click_near_offset_y ?? 0);
            if (blurNearSelector) {
              await clickNearElement(page, blurNearSelector, blurOffsetX, blurOffsetY);
            } else if (step.auto_blur_after_fill !== false) {
              await clickNearExportButton(page, blurOffsetX, blurOffsetY);
            }
            return;
          }

          await page.focus(matchedSelector);
          const typedText = convertIsoDateToDotFormat(text);

          if (step.clear !== false) {
            // чистим поле
            await page.click(matchedSelector, { clickCount: 3 });
            await page.keyboard.press('Backspace');
          }
          await page.$eval(matchedSelector, (el) => {
            const node = el;
            const tagName = String(node?.tagName || '').toLowerCase();
            const type = String(node?.getAttribute?.('type') || '').toLowerCase();
            const unsupportedTypes = new Set(['number', 'date', 'time', 'datetime-local', 'month', 'week']);
            if (tagName === 'input' && unsupportedTypes.has(type)) {
              return;
            }
            if (typeof node.setSelectionRange === 'function') {
              node.setSelectionRange(0, 0);
            }
          });

          await page.type(matchedSelector, typedText, { delay: Number(step.delay_ms || 0) });
          const blurNearSelector = applyVars(step.blur_near_selector || step.after_fill_click_near_selector || '', vars);
          const blurOffsetX = Number(step.blur_near_offset_x ?? step.after_fill_click_near_offset_x ?? -14);
          const blurOffsetY = Number(step.blur_near_offset_y ?? step.after_fill_click_near_offset_y ?? 0);
          if (blurNearSelector) {
            await clickNearElement(page, blurNearSelector, blurOffsetX, blurOffsetY);
          } else if (step.auto_blur_after_fill !== false) {
            await clickNearExportButton(page, blurOffsetX, blurOffsetY);
          }
        });

        if (skippedReason) {
          stepLog.push({
            time: new Date().toISOString(),
            step: stepNo,
            action,
            status: 'skip',
            meta: { selector, reason: skippedReason, ignoreReadonly, ignoreDisabled, ignoreMissing },
          });
          continue;
        }

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector } });
        continue;
      }


      if (action === 'press') {
        const selector = applyVars(step.selector || '', vars);
        const key = String(step.key || step.value || 'Enter').trim() || 'Enter';
        if (!selector) throw new Error('press.selector is required');

        await runWithTransientRetry(async () => {
          const matchedSelector = await findSelectorWithFallback(page, selector, { visible: step.visible !== false });
          await page.focus(matchedSelector);
          await page.keyboard.press(key);
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector, key } });
        continue;
      }

      if (action === 'select') {
        const selector = applyVars(step.selector || '', vars);
        if (!selector) throw new Error('select.selector is required');
        const rawValue = step.value ?? (typeof step.var === 'string' ? vars[step.var] : '');
        const value = applyVars(String(rawValue ?? ''), vars);

        const textValue = applyVars(String(step.text ?? step.label ?? ''), vars).trim();
        const matchMode = String(step.match || (textValue ? 'text' : 'value')).trim().toLowerCase();

        await runWithTransientRetry(async () => {
          const matchedSelector = await findSelectorWithFallback(page, selector, { visible: step.visible !== false });
          if (matchMode === 'text' || matchMode === 'label' || matchMode === 'contains') {
            const selectedValue = await page.$eval(matchedSelector, (el, desiredText, desiredMode) => {
              const normalize = (input) => String(input || '').replace(/\s+/g, ' ').trim().toLowerCase();
              const wanted = normalize(desiredText);
              if (!wanted) return '';
              const select = el;
              const options = Array.from(select.options || []);
              const option = options.find((item) => {
                const label = normalize(item.textContent || item.label || '');
                if (desiredMode === 'contains') {
                  return label.includes(wanted);
                }
                return label === wanted;
              });
              if (!option) return '';
              select.value = option.value;
              select.dispatchEvent(new Event('input', { bubbles: true }));
              select.dispatchEvent(new Event('change', { bubbles: true }));
              return option.value;
            }, textValue, matchMode);
            if (!selectedValue) {
              throw new Error(`No option selected for ${selector} by text: ${textValue}`);
            }
            return;
          }

          const selected = await page.select(matchedSelector, value);
          if (!selected || selected.length === 0) {
            throw new Error(`No option selected for ${selector} by value: ${value}`);
          }
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector, value, text: textValue || undefined, match: matchMode } });
        continue;
      }

      if (action === 'wait_for_hidden') {
        const selectorRaw = applyVars(step.selector || '', vars);
        if (!selectorRaw) throw new Error('wait_for_hidden.selector is required');
        const timeout = Number(step.timeout_ms || 10000);
        const selectors = selectorRaw.split(',').map((s) => s.trim()).filter(Boolean);

        await runWithTransientRetry(async () => {
          await page.waitForFunction((arr) => {
            const isHidden = (el) => {
              if (!el) return true;
              const style = window.getComputedStyle(el);
              return style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity || '1') === 0;
            };
            return arr.every((sel) => {
              const nodes = Array.from(document.querySelectorAll(sel));
              if (!nodes.length) return true;
              return nodes.every((node) => isHidden(node));
            });
          }, { timeout }, selectors);
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector: selectorRaw, timeout } });
        continue;
      }

      if (action === 'wait_for_regex') {
        const selector = applyVars(step.selector || '', vars);
        const pattern = applyVars(step.pattern || '', vars);
        if (!selector) throw new Error('wait_for_regex.selector is required');
        if (!pattern) throw new Error('wait_for_regex.pattern is required');
        const timeout = Number(step.timeout_ms || 10000);

        await runWithTransientRetry(async () => {
          await page.waitForFunction((sel, rxSource) => {
            const root = document.querySelector(sel);
            if (!root) return false;
            try {
              const rx = new RegExp(rxSource, 'm');
              return rx.test(root.textContent || '') || rx.test(root.innerHTML || '');
            } catch (_) {
              return false;
            }
          }, { timeout }, selector, pattern);
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector, pattern, timeout } });
        continue;
      }

      if (action === 'wait_for') {
        const selector = applyVars(step.selector || '', vars);
        const timeout = Number(step.timeout_ms || 10000);
        const requireVisible = step.visible !== false;

        if (selector) {
          await runWithTransientRetry(() => findSelectorWithFallback(page, selector, { timeout, visible: requireVisible }));
        } else {
          await runWithTransientRetry(() => sleep(timeout));
        }
        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector: selector || undefined, timeout } });
        continue;
      }

      if (action === 'wait_for_hidden' || action === 'wait_hidden') {
        const selector = applyVars(step.selector || '', vars);
        const timeout = Number(step.timeout_ms || 10000);
        if (!selector) throw new Error(`${action}.selector is required`);

        await runWithTransientRetry(async () => {
          const candidates = selectorCandidates(selector);
          let lastErr = null;
          for (const candidate of candidates) {
            try {
              await page.waitForSelector(candidate, { timeout, hidden: true });
              return;
            } catch (err) {
              lastErr = err;
            }
          }
          const finalErr = new Error(`Element still visible for selector: ${selector}`);
          finalErr.cause = lastErr;
          throw finalErr;
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector, timeout } });
        continue;
      }

      if (action === 'wait_for_regex' || action === 'wait_match') {
        const selector = applyVars(step.selector || '', vars);
        const timeout = Number(step.timeout_ms || 10000);
        const patternRaw = applyVars(String(step.pattern || step.regex || ''), vars);
        const flagsRaw = String(step.flags || '').trim();
        const source = String(step.source || 'html').trim().toLowerCase();
        if (!patternRaw) throw new Error(`${action}.pattern (or regex) is required`);

        await runWithTransientRetry(async () => {
          const candidates = selector ? selectorCandidates(selector) : [null];
          let lastErr = null;
          for (const candidate of candidates) {
            try {
              await page.waitForFunction(
                ({ sel, pattern, flags, sourceMode }) => {
                  const target = sel ? document.querySelector(sel) : document.body;
                  if (!target) return false;
                  const haystack = sourceMode === 'text'
                    ? String(target.textContent || '')
                    : String(target.innerHTML || '');
                  try {
                    const re = new RegExp(pattern, flags);
                    return re.test(haystack);
                  } catch (_) {
                    return false;
                  }
                },
                { timeout },
                { sel: candidate, pattern: patternRaw, flags: flagsRaw, sourceMode: source }
              );
              return;
            } catch (err) {
              lastErr = err;
            }
          }
          const finalErr = new Error(`Regex not matched for selector: ${selector || '<document>'}`);
          finalErr.cause = lastErr;
          throw finalErr;
        });

        const shot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo, action) : null;
        stepLog.push({ time: new Date().toISOString(), step: stepNo, action, status: 'ok', screenshot: shot || undefined, meta: { selector: selector || undefined, pattern: patternRaw, flags: flagsRaw || undefined, source } });
        continue;
      }


      if (action === 'download') {
        const timeoutMs = Number(step.timeout_ms || 30000);
        const effectiveMinSizeBytes = Math.max(
          minFileSizeBytes,
          expectedDownloadSizeBytes > 0 ? Math.floor(expectedDownloadSizeBytes * 0.9) : 0
        );
        const downloaded = await waitForStableDownloadedFileInDirs([downloadDir, fallbackDownloadDir], fileExtension, timeoutMs, {
          minSizeBytes: effectiveMinSizeBytes,
          stablePollsRequired: 2,
          stableWindowMs: 1500,
        });
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
            file_is_zip_container: downloaded.is_zip_container,
            min_file_size_bytes: minFileSizeBytes,
            effective_min_file_size_bytes: effectiveMinSizeBytes,
            expected_download_size_bytes: expectedDownloadSizeBytes || undefined,
            final_html_path: await saveFinalHtmlSnapshot(page, artifactsDir) || undefined,
            cookies: await serializePageCookies(page) || undefined,
            step_log: stepLog,
            artifacts_dir: artifactsDir,
            network_log: debugDownloadNetwork ? downloadNetworkLog : undefined,
          }) + '\n'
        );

        await browser.close();
        await safeRm(userDataDir);
        await safeRm(runtimeHomeDir);
        process.exit(0);
      }

      throw new Error(`Unsupported action: ${action} (supported: goto/click/fill/type/press/select/wait_for/wait_for_hidden/wait_for_regex/download)`);
    }

    async function collectErrorTextIfAny() {
      if (!errorSelector) return '';
      try {
        const handle = await page.waitForSelector(errorSelector, { timeout: errorWaitMs });
        if (!handle) return '';
        const txt = await page.$eval(errorSelector, (el) => String(el?.innerText || el?.textContent || '').trim());
        return String(txt || '').trim();
      } catch (_) {
        return '';
      }
    }

    if (!expectDownload) {
      const capturedErrorText = await collectErrorTextIfAny();
      const finalShot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo + 1, 'final') : null;
      stepLog.push({ time: new Date().toISOString(), step: stepNo + 1, action: 'final_no_download', status: 'ok', screenshot: finalShot || undefined });
      const finalHtmlPath = await saveFinalHtmlSnapshot(page, artifactsDir);

      process.stdout.write(
        JSON.stringify({
          ok: true,
          message: 'Сценарий выполнен без скачивания файла',
          executable_path: executablePath,
          browser_product: resolvedBrowserProduct,
          resolved_error_selector: errorSelector || undefined,
          captured_error_text: capturedErrorText || undefined,
          final_html_path: finalHtmlPath || undefined,
          cookies: await serializePageCookies(page) || undefined,
          step_log: stepLog,
          artifacts_dir: artifactsDir,
          network_log: debugDownloadNetwork ? downloadNetworkLog : undefined,
        }) + '\n'
      );

      await browser.close();
      await safeRm(userDataDir);
      await safeRm(runtimeHomeDir);
      process.exit(0);
}

    // если не было явного download step — попробуем найти файл в конце
    const downloaded = await waitForStableDownloadedFileInDirs([downloadDir, fallbackDownloadDir], fileExtension, 30000, {
      minSizeBytes: Math.max(
        minFileSizeBytes,
        expectedDownloadSizeBytes > 0 ? Math.floor(expectedDownloadSizeBytes * 0.9) : 0
      ),
      stablePollsRequired: 2,
      stableWindowMs: 1500,
    });
    if (!downloaded) throw new Error(`Download step missing or file not found (.${fileExtension})`);
    const persistedDownloaded = persistDownloadedFileIfNeeded(downloaded, runtimeHomeDir, downloadDir);

    const finalShot = captureScreenshots ? await saveStepScreenshot(page, artifactsDir, stepNo + 1, 'final') : null;
    stepLog.push({ time: new Date().toISOString(), step: stepNo + 1, action: 'final_download_probe', status: 'ok', screenshot: finalShot || undefined });
    const finalHtmlPath = await saveFinalHtmlSnapshot(page, artifactsDir);

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
        file_is_zip_container: downloaded.is_zip_container,
        min_file_size_bytes: minFileSizeBytes,
        expected_download_size_bytes: expectedDownloadSizeBytes || undefined,
        final_html_path: finalHtmlPath || undefined,
        cookies: await serializePageCookies(page) || undefined,
        step_log: stepLog,
        artifacts_dir: artifactsDir,
        network_log: debugDownloadNetwork ? downloadNetworkLog : undefined,
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

