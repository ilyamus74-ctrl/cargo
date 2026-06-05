#!/usr/bin/env node
const puppeteer = require('puppeteer');

function buildCookieHeader(cookies) {
    return cookies
        .map((cookie) => `${cookie.name}=${cookie.value}`)
        .join('; ');
}

function extractBearerToken() {
    const token = localStorage.getItem('auth_token')
        || localStorage.getItem('token')
        || sessionStorage.getItem('auth_token')
        || sessionStorage.getItem('token');
    if (typeof token === 'string' && token.trim() !== '') {
        return token.trim();
    }
    return '';
}

async function run() {
    const [loginUrl, login, password] = process.argv.slice(2);
    if (!loginUrl) {
        console.error('loginUrl required');
        process.exit(1);
    }

    const browser = await puppeteer.launch({
        headless: false,
        defaultViewport: null,
    });

    try {
        const page = await browser.newPage();

        page.on('dialog', async (dialog) => {
            try {
                await dialog.dismiss();
            } catch (err) {
                console.error('dialog error', err);
            }
        });

        await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

        if (login) {
            const selectors = [
                'input[type="email"]',
                'input[name="email"]',
                'input[name="login"]',
                'input[name="username"]',
                'input[name="user"]',
                '#login',
                '#username',
            ];
            for (const selector of selectors) {
                const input = await page.$(selector);
                if (input) {
                    await input.click({ clickCount: 3 });
                    await input.type(login);
                    break;
                }
            }
        }

        if (password) {
            const selectors = [
                'input[type="password"]',
                'input[name="password"]',
                '#password',
            ];
            for (const selector of selectors) {
                const input = await page.$(selector);
                if (input) {
                    await input.click({ clickCount: 3 });
                    await input.type(password);
                    break;
                }
            }
        }

        console.log('Окно браузера открыто. Пройдите капчу/вход.');
        await page.bringToFront();

        const initialCookies = await page.cookies();
        const initialCookieNames = new Set(initialCookies.map((cookie) => cookie.name));
        const maxWaitMs = 10 * 60 * 1000;
        const pollIntervalMs = 1000;
        let cookies = initialCookies;
        let authToken = '';
        let elapsedMs = 0;

        while (elapsedMs < maxWaitMs) {
            await page.waitForTimeout(pollIntervalMs);
            elapsedMs += pollIntervalMs;
            cookies = await page.cookies();
            authToken = await page.evaluate(extractBearerToken);
            const hasNewCookie = cookies.some((cookie) => !initialCookieNames.has(cookie.name));
            if (hasNewCookie || authToken) {
                break;
            }
        }

        if (!authToken && cookies.length === 0) {
            throw new Error('Не удалось получить токен или cookie за отведенное время.');
        }

        const cookieHeader = buildCookieHeader(cookies);

        const payload = {
            auth_token: authToken,
            auth_cookies: cookieHeader,
            auth_token_expires_at: '',
        };

        process.stdout.write(`${JSON.stringify(payload)}\n`);
    } catch (err) {
        console.error(err);
        process.exit(1);
    } finally {
        await browser.close();
    }
}

run();
