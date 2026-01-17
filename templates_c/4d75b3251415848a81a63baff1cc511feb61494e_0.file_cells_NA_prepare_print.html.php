<?php
/* Smarty version 5.3.1, created on 2026-01-17 21:11:03
  from 'file:cells_NA_prepare_print.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696bfae7361757_79207416',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4d75b3251415848a81a63baff1cc511feb61494e' => 
    array (
      0 => 'cells_NA_prepare_print.html',
      1 => 1768684261,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696bfae7361757_79207416 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<! DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Подготовка печати QR</title>
  <style>
    : root {
      color-scheme: light;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
      background:  #f6f7fb;
      color: #101828;
    }

    .print-designer {
      display: grid;
      grid-template-columns: minmax(280px, 380px) 1fr;
      min-height: 100vh;
    }

    .controls {
      padding: 24px;
      background: #fff;
      border-right: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    . controls h1 {
      margin: 0;
      font-size: 20px;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap:  8px;
    }

    .field label {
      font-weight: 600;
      font-size: 14px;
    }

    .field input,
    .field select {
      padding: 8px 10px;
      border: 1px solid #d0d5dd;
      border-radius:  8px;
      font-size:  14px;
    }

    .field . inline {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .checkbox-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    . checkbox-list label {
      font-weight: 500;
    }

    .actions {
      display: flex;
      gap: 12px;
      margin-top:  12px;
    }

    . btn {
      border: none;
      border-radius: 8px;
      padding: 10px 16px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-primary {
      background: #2563eb;
      color: #fff;
    }

    . btn-secondary {
      background: #f3f4f6;
      color: #111827;
    }

    . preview {
      padding: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .preview-sheet {
      background: #fff;
      border:  1px solid #e5e7eb;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
      padding: 16px;
      max-width: 100%;
      overflow:  auto;
    }

    .preview-sheet svg {
      display: block;
      max-width: 100%;
      height: auto;
    }

    @media print {
      body {
        background: #fff;
      }

      .controls {
        display: none;
      }

      .preview {
        padding: 0;
      }

      .preview-sheet {
        border: none;
        box-shadow: none;
        padding: 0;
      }
    }
  </style>
</head>
<body>
  <div class="print-designer">
    <div class="controls">
      <h1>Подготовка печати QR</h1>

      <div class="field">
        <label for="preset">Пресет этикетки</label>
        <select id="preset">
          <option value="custom">Пользовательский</option>
          <option value="20x20">20 × 20 мм</option>
          <option value="30x30">30 × 30 мм</option>
          <option value="50x30">50 × 30 мм</option>
          <option value="70x40">70 × 40 мм</option>
          <option value="a4">A4 (210 × 297 мм)</option>
        </select>
      </div>

      <div class="field">
        <label>Размер этикетки (мм)</label>
        <div class="inline">
          <input type="number" id="label-width" min="10" max="300" step="1" value="50">
          <span>×</span>
          <input type="number" id="label-height" min="10" max="300" step="1" value="30">
        </div>
      </div>

      <div class="field">
        <label for="qr-size">Размер QR (мм)</label>
        <select id="qr-size">
          <option value="20">20</option>
          <option value="25">25</option>
          <option value="30" selected>30</option>
          <option value="40">40</option>
          <option value="50">50</option>
          <option value="75">75</option>
          <option value="100">100</option>
          <option value="150">150</option>
          <option value="200">200</option>

        </select>
      </div>

      <div class="field">
        <label for="font-family">Шрифт</label>
        <select id="font-family">
          <option value="Inter, Segoe UI, sans-serif">Inter / Segoe UI</option>
          <option value="Arial, sans-serif">Arial</option>
          <option value="Roboto, sans-serif">Roboto</option>
          <option value="Courier New, monospace">Courier New</option>
        </select>
      </div>

      <div class="field">
        <label for="font-size">Размер текста (мм)</label>
        <select id="font-size">
          <option value="2.5">2.5</option>
          <option value="3" selected>3</option>
          <option value="3.5">3.5</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
          <option value="7">7</option>
          <option value="8">8</option>
          <option value="12">12</option>
          <option value="14">14</option>
          <option value="16">16</option>
          <option value="18">18</option>
        </select>
      </div>

      <div class="field">
        <label>Подписи</label>
        <div class="checkbox-list" id="label-options"></div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="button" id="print-btn">Печать</button>
        <button class="btn btn-secondary" type="button" id="close-btn">Закрыть</button>
      </div>
    </div>

    <div class="preview">
      <div class="preview-sheet">
        <svg id="qr-svg" xmlns="http://www.w3.org/2000/svg">
          <rect id="qr-bg" x="0" y="0" width="100%" height="100%" fill="#fff"></rect>
          <image id="qr-image" href="" x="0" y="0" width="0" height="0" preserveAspectRatio="none"></image>
          <g id="qr-labels"></g>
        </svg>
      </div>
    </div>
  </div>

  <style id="print-page-style"></style>

  
  <?php echo '<script'; ?>
>
    const params = new URLSearchParams(window. location.search);
    const payload = {
      src: params.get('src') || '',
      title: params.get('title') || 'QR',
      name: params.get('name') || '',
      serial: params.get('serial') || '',
      domain: params.get('domain') || '',
      code: params.get('code') || ''
    };

    const labelOptionsEl = document.getElementById('label-options');
    const qrSvg = document.getElementById('qr-svg');
    const qrImage = document.getElementById('qr-image');
    const qrLabels = document.getElementById('qr-labels');
    const labelWidthInput = document.getElementById('label-width');
    const labelHeightInput = document.getElementById('label-height');
    const qrSizeSelect = document.getElementById('qr-size');
    const fontFamilySelect = document.getElementById('font-family');
    const fontSizeSelect = document.getElementById('font-size');
    const presetSelect = document.getElementById('preset');
    const printStyle = document.getElementById('print-page-style');

    const availableLabels = [
      { key: 'title', label: 'Заголовок', value: payload.title },
      { key: 'name', label: 'Название инструмента', value: payload.name },
      { key: 'serial', label: 'Серийный номер', value: payload.serial },
      { key: 'code', label: 'Код ячейки', value: payload.code },
      { key: 'domain', label: 'Домен', value: payload.domain }
    ].filter(item => item.value);

    function createLabelOption(item) {
      const wrapper = document.createElement('label');
      wrapper.className = 'inline';

      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = true;
      checkbox.dataset.key = item.key;

      const text = document.createElement('span');
      text.textContent = `${item.label}:  ${item.value}`;

      wrapper.appendChild(checkbox);
      wrapper.appendChild(text);
      labelOptionsEl.appendChild(wrapper);

      checkbox.addEventListener('change', renderPreview);
    }

    if (! availableLabels.length) {
      labelOptionsEl.innerHTML = '<span class="text-muted">Нет доступных подписей</span>';
    } else {
      availableLabels.forEach(createLabelOption);
    }

    function getSelectedLabels() {
      const selectedKeys = Array.from(labelOptionsEl.querySelectorAll('input[type="checkbox"]'))
        .filter(input => input.checked)
        .map(input => input.dataset. key);

      return availableLabels.filter(item => selectedKeys.includes(item.key));
    }

    function updatePrintPageSize(widthMm, heightMm) {
      printStyle.textContent = `@page { size: ${widthMm}mm ${heightMm}mm; margin: 0; }`;
    }

    function renderPreview() {
      const labelWidth = Number(labelWidthInput.value) || 50;
      const labelHeight = Number(labelHeightInput.value) || 30;
      const qrSize = Number(qrSizeSelect.value) || 30;
      const fontSize = Number(fontSizeSelect. value) || 3;
      const fontFamily = fontFamilySelect.value;
      const padding = 2;

      qrSvg.setAttribute('width', `${labelWidth}mm`);
      qrSvg.setAttribute('height', `${labelHeight}mm`);
      qrSvg.setAttribute('viewBox', `0 0 ${labelWidth} ${labelHeight}`);

      const qrX = Math.max(padding, (labelWidth - qrSize) / 2);
      const qrY = padding;

      qrImage.setAttribute('href', payload. src);
      qrImage.setAttribute('x', qrX);
      qrImage.setAttribute('y', qrY);
      qrImage.setAttribute('width', qrSize);
      qrImage.setAttribute('height', qrSize);

      while (qrLabels.firstChild) {
        qrLabels.removeChild(qrLabels.firstChild);
      }

      const selectedLabels = getSelectedLabels();
      let textY = qrY + qrSize + fontSize + 1.5;

      selectedLabels. forEach((item) => {
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.textContent = item. value;
        text.setAttribute('x', labelWidth / 2);
        text.setAttribute('y', textY);
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('font-size', `${fontSize}`);
        text.setAttribute('font-family', fontFamily);
        text.setAttribute('fill', '#111827');
        qrLabels.appendChild(text);
        textY += fontSize + 1.5;
      });

      updatePrintPageSize(labelWidth, labelHeight);
    }

    function applyPreset(value) {
      if (value === 'a4') {
        labelWidthInput.value = 210;
        labelHeightInput.value = 297;
      } else if (value. includes('x')) {
        const [width, height] = value. split('x').map(Number);
        labelWidthInput.value = width;
        labelHeightInput.value = height;
      }
      renderPreview();
    }

    document.getElementById('print-btn').addEventListener('click', () => {
      window.print();
    });

    document.getElementById('close-btn').addEventListener('click', () => {
      window.close();
    });

    [labelWidthInput, labelHeightInput, qrSizeSelect, fontFamilySelect, fontSizeSelect]. forEach((el) => {
      el.addEventListener('input', renderPreview);
    });

    presetSelect.addEventListener('change', (event) => {
      applyPreset(event.target.value);
    });

    renderPreview();
  <?php echo '</script'; ?>
>
  
</body>
</html>
<?php }
}
