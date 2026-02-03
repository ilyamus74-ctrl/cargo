const systemUrlInput = document.getElementById('system-url');
const connectorIdInput = document.getElementById('connector-id');
const tokenKeysInput = document.getElementById('token-keys');
const statusEl = document.getElementById('status');
const sendBtn = document.getElementById('send-btn');

function setStatus(message, isError = false) {
    statusEl.textContent = message;
    statusEl.style.color = isError ? '#b00020' : '#2e7d32';
}

function parseTokenKeys(value) {
    return value
        .split(',')
        .map((key) => key.trim())
        .filter(Boolean);
}

async function loadSettings() {
    const data = await chrome.storage.sync.get(['systemUrl', 'connectorId', 'tokenKeys']);
    if (data.systemUrl) systemUrlInput.value = data.systemUrl;
    if (data.connectorId) connectorIdInput.value = data.connectorId;
    tokenKeysInput.value = data.tokenKeys || 'auth_token, token';
}

async function saveSettings() {
    await chrome.storage.sync.set({
        systemUrl: systemUrlInput.value.trim(),
        connectorId: connectorIdInput.value.trim(),
        tokenKeys: tokenKeysInput.value.trim(),
    });
}

async function getActiveTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}

async function getCookiesForUrl(url) {
    const cookies = await chrome.cookies.getAll({ url });
    return cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join('; ');
}

async function getTokenFromPage(tabId, keys) {
    const results = await chrome.scripting.executeScript({
        target: { tabId },
        func: (tokenKeys) => {
            const keysList = Array.isArray(tokenKeys) ? tokenKeys : [];
            for (const key of keysList) {
                const value = localStorage.getItem(key) || sessionStorage.getItem(key);
                if (value) {
                    return value;
                }
            }
            return '';
        },
        args: [keys],
    });
    return results?.[0]?.result || '';
}

async function sendAuthData(systemUrl, connectorId, authToken, authCookies) {
    const formData = new FormData();
    formData.append('action', 'manual_confirm_extension');
    formData.append('connector_id', connectorId);
    formData.append('auth_token', authToken);
    formData.append('auth_cookies', authCookies);
    formData.append('auth_token_expires_at', '');

    const response = await fetch(`${systemUrl.replace(/\\/$/, '')}/core_api.php`, {
        method: 'POST',
        body: formData,
        credentials: 'include',
    });
    return response.json();
}

sendBtn.addEventListener('click', async () => {
    setStatus('Собираем данные...');
    const systemUrl = systemUrlInput.value.trim();
    const connectorId = connectorIdInput.value.trim();
    const tokenKeys = parseTokenKeys(tokenKeysInput.value || '');

    if (!systemUrl || !connectorId) {
        setStatus('Укажите адрес системы и ID коннектора.', true);
        return;
    }

    try {
        await saveSettings();
        const tab = await getActiveTab();
        if (!tab?.id || !tab.url) {
            setStatus('Не удалось получить активную вкладку.', true);
            return;
        }

        const [authCookies, authToken] = await Promise.all([
            getCookiesForUrl(tab.url),
            getTokenFromPage(tab.id, tokenKeys),
        ]);

        if (!authCookies && !authToken) {
            setStatus('Токены или cookies не найдены.', true);
            return;
        }

        const result = await sendAuthData(systemUrl, connectorId, authToken, authCookies);
        if (result?.status === 'ok') {
            setStatus('Данные отправлены успешно.');
        } else {
            setStatus(result?.message || 'Ошибка отправки данных.', true);
        }
    } catch (error) {
        setStatus(`Ошибка: ${error.message}`, true);
    }
});

loadSettings();
