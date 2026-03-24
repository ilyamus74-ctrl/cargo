<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/api/core_helpers.php';

auth_require_login();
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forwarder Scan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1f2937; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 6px 24px rgba(15, 23, 42, .08); max-width: 760px; }
        h1 { margin-top: 0; font-size: 24px; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .field { display: flex; flex-direction: column; min-width: 220px; flex: 1; }
        label { font-size: 12px; color: #4b5563; margin-bottom: 6px; }
        input { border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 15px; }
        .actions { display: flex; gap: 8px; margin-top: 8px; }
        button { border: 0; border-radius: 8px; padding: 10px 14px; font-size: 14px; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-print { background: #047857; color: #fff; }
        .result { margin-top: 16px; border-radius: 10px; padding: 14px; border: 1px solid transparent; }
        .result--pending { background: #eff6ff; border-color: #bfdbfe; }
        .result--ok { background: #ecfdf5; border-color: #86efac; }
        .result--error { background: #fef2f2; border-color: #fecaca; }
        .hidden { display: none; }
        .meta { margin-top: 8px; font-size: 14px; color: #374151; }
        .meta div { margin: 4px 0; }
        @media print {
            body { margin: 0; background: #fff; }
            .card > :not(#print-area) { display: none !important; }
            #print-area { display: block !important; border: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Forwarder scan</h1>
    <div class="row">
        <div class="field">
            <label for="track">Трек</label>
            <input id="track" type="text" autocomplete="off" autofocus>
        </div>
        <div class="field">
            <label for="container">Контейнер</label>
            <input id="container" type="text" autocomplete="off" value="24369">
        </div>
    </div>
    <div class="actions">
        <button id="scanBtn" class="btn-primary" type="button">Проверить</button>
        <button id="resetBtn" class="btn-secondary" type="button">Сброс</button>
        <button id="printBtn" class="btn-print hidden" type="button">Печать</button>
    </div>

    <div id="result" class="result result--pending hidden" aria-live="polite"></div>

    <div id="print-area" class="result hidden">
        <strong>Label payload</strong>
        <div id="print-payload" class="meta"></div>
    </div>
</div>

<script>
(() => {
    const trackEl = document.getElementById('track');
    const containerEl = document.getElementById('container');
    const resultEl = document.getElementById('result');
    const printBtn = document.getElementById('printBtn');
    const printArea = document.getElementById('print-area');
    const printPayloadEl = document.getElementById('print-payload');
    const scanBtn = document.getElementById('scanBtn');
    const resetBtn = document.getElementById('resetBtn');

    let lastPayload = null;

    const failureReasons = {
        NOT_DECLARED: 'Посылка не задекларирована у форвардера.',
        INVALID_TRACK: 'Некорректный трек или контейнер.',
        SESSION_EXPIRED: 'Сессия форвардера истекла. Повторите попытку.',
        TEMP_ERROR: 'Техническая ошибка при запросе к форвардеру.'
    };

    function focusTrack() {
        window.setTimeout(() => {
            trackEl.focus();
            trackEl.select();
        }, 0);
    }

    function renderSuccess(data) {
        resultEl.className = 'result result--ok';
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = `
            <strong>✅ ACCEPTED</strong>
            <div class="meta">
                <div><b>Трек:</b> ${escapeHtml(data.track || '')}</div>
                <div><b>Internal ID:</b> ${escapeHtml(data.internal_id || '—')}</div>
                <div><b>Вес:</b> ${escapeHtml(data.weight || '—')}</div>
                <div><b>Клиент:</b> ${escapeHtml(data.client_name || '—')}</div>
            </div>
        `;
        printBtn.classList.remove('hidden');
        printArea.classList.remove('hidden');
        printPayloadEl.innerHTML = `
            <div><b>track:</b> ${escapeHtml(data.label_payload?.track || '')}</div>
            <div><b>container:</b> ${escapeHtml(data.label_payload?.container || '')}</div>
            <div><b>internal_id:</b> ${escapeHtml(data.label_payload?.internal_id || '—')}</div>
            <div><b>weight:</b> ${escapeHtml(data.label_payload?.weight || '—')}</div>
            <div><b>client_name:</b> ${escapeHtml(data.label_payload?.client_name || '—')}</div>
        `;
    }

    function renderFailure(status) {
        const reason = failureReasons[status] || `Ошибка: ${status}`;
        resultEl.className = 'result result--error';
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = `<strong>❌ ${escapeHtml(status)}</strong><div class="meta">${escapeHtml(reason)}</div>`;
        printBtn.classList.add('hidden');
        printArea.classList.add('hidden');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function runScan() {
        const track = String(trackEl.value || '').trim();
        const container = String(containerEl.value || '').trim();

        resultEl.className = 'result result--pending';
        resultEl.classList.remove('hidden');
        resultEl.textContent = 'Проверяем...';
        printBtn.classList.add('hidden');
        printArea.classList.add('hidden');

        const body = new URLSearchParams();
        body.set('track', track);
        body.set('container', container);

        try {
            const resp = await fetch('/api/forwarder/scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            });
            const data = await resp.json();
            lastPayload = data;

            if (data.status === 'ACCEPTED') {
                renderSuccess(data);
            } else {
                renderFailure(String(data.status || 'TEMP_ERROR'));
            }
        } catch (err) {
            renderFailure('TEMP_ERROR');
        } finally {
            focusTrack();
        }
    }

    scanBtn.addEventListener('click', runScan);
    resetBtn.addEventListener('click', () => {
        trackEl.value = '';
        resultEl.classList.add('hidden');
        printBtn.classList.add('hidden');
        printArea.classList.add('hidden');
        lastPayload = null;
        focusTrack();
    });
    printBtn.addEventListener('click', () => {
        if (!lastPayload || lastPayload.status !== 'ACCEPTED') {
            return;
        }
        window.print();
        focusTrack();
    });

    trackEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runScan();
        }
    });

    focusTrack();
})();
</script>
</body>
</html>
