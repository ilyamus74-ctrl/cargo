<?php
/* Smarty version 5.3.1, created on 2026-02-26 11:09:11
  from 'file:cells_NA_API_warehouse_item_stock_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a029d71b8248_65627253',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '76c261023c84dc78e924fef3e9010fc442e0a73f' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock_modal.html',
      1 => 1772103935,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a029d71b8248_65627253 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><form id="item-stock-modal-form" class="row g-3">
  <input type="hidden" name="item_id" value="<?php echo $_smarty_tpl->getValue('item')['id'];?>
">

  <div class="col-md-4">
    <label for="tuid" class="form-label">
      TUID
      <span id="ocrCarrierInfo" class="text-muted"></span>
    </label>
    <input type="text" class="form-control" id="tuid" name="tuid" required value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['tuid'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-4">
    <label for="trackingNo" class="form-label">Трек-номер</label>
    <input type="text" class="form-control" id="trackingNo" name="tracking_no" required value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['tracking_no'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-4">
    <label for="carrierName" class="form-label">Перевозчик</label>
    <input type="text" class="form-control" id="carrierName" name="carrier_name" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['carrier_name'], ENT_QUOTES, 'UTF-8', true);?>
">
    <input type="hidden" id="senderName" name="carrier_code" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['carrier_code'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-4">
    <label class="form-label" for="receiverCountry">Страна получателя</label>
    <select class="form-select"
            id="receiverCountry"
            name="receiver_country_code">
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('dest_country'), 'b');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('b')->value) {
$foreach0DoElse = false;
?>
        <option value="<?php echo $_smarty_tpl->getValue('b')['code_iso2'];?>
" <?php if ($_smarty_tpl->getValue('b')['code_iso2'] == $_smarty_tpl->getValue('item')['receiver_country_code']) {?>selected<?php }?>><?php echo $_smarty_tpl->getValue('b')['name_en'];?>
</option>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </select>
  </div>

  <div class="col-md-6">
    <label for="receiverName" class="form-label">Получатель</label>
    <input type="text" class="form-control" id="receiverName" name="receiver_name" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['receiver_name'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-6">
    <label for="receiverAddress" class="form-label">Ячейка</label>
    <input type="text" class="form-control" id="receiverAddress" name="receiver_address" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['receiver_address'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-6">
    <label for="cellId" class="form-label">Ячейки (адрес хранения)</label>
    <select class="form-select" id="cellId" name="cell_id">
      <option value="">— не назначать —</option>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach1DoElse = false;
?>
        <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
" <?php if ($_smarty_tpl->getValue('cell')['id'] == $_smarty_tpl->getValue('item')['cell_id']) {?>selected<?php }?>><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </select>
  </div>


  <div class="col-md-6">
    <label class="form-label" for="receiverCompany">Компания форвард</label>
    <select class="form-select"
            id="receiverCompany"
            name="receiver_company">
      <option value="COLIBRI" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'COLIBRI') {?>selected<?php }?>>COLIBRI</option>
      <option value="KOLLI" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'KOLLI') {?>selected<?php }?>>KOLLI</option>
      <option value="ASER" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'ASER') {?>selected<?php }?>>ASER</option>
      <option value="CAMEX" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'CAMEX') {?>selected<?php }?>>CAMEX</option>
      <option value="KARGOFLEX" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'KARGOFLEX') {?>selected<?php }?>>KARGOFLEX</option>
      <option value="CAMARATC" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'CAMARATC') {?>selected<?php }?>>CAMARATC</option>
      <option value="POSTLINK" <?php if ($_smarty_tpl->getValue('item')['receiver_company'] == 'POSTLINK') {?>selected<?php }?>>POSTLINK</option>
    </select>
  </div>

  <div class="col-md-6">
    <label for="senderName" class="form-label">Форвард CODE</label>
    <input type="text" class="form-control" id="carrierCode" name="sender_code" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['sender_name'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-12">
    <div id="warehouseStockAddonsSection"
         data-addons-map='<?php echo htmlspecialchars((string)json_encode($_smarty_tpl->getValue('addons_map')), ENT_QUOTES, 'UTF-8', true);?>
'
         data-addons-raw-map='<?php echo htmlspecialchars((string)json_encode($_smarty_tpl->getValue('addons_raw_map')), ENT_QUOTES, 'UTF-8', true);?>
'
         data-item-addons='<?php echo htmlspecialchars((string)json_encode($_smarty_tpl->getValue('item_addons')), ENT_QUOTES, 'UTF-8', true);?>
'>
      <label class="form-label">ДопИнфо</label>
      <div id="warehouseStockAddonsControls" class="row g-2"></div>
      <div id="warehouseStockAddonsEmpty" class="form-text text-muted">Для выбранной компании форварда нет настроенной ДопИнфо.</div>
      <input type="hidden" id="warehouseStockAddonsJson" name="addons_json" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item_addons_json'), ENT_QUOTES, 'UTF-8', true);?>
">
      <input type="hidden" id="warehouseStockAddonsDebug" name="debug" value="">
    </div>
  </div>


  <div class="col-md-6">
    <label for="standDevice" class="form-label">Устройство измерения</label>
    <select class="form-select" id="standDevice" name="stand_device_uid">
      <option value="">— выберите устройство —</option>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('stand_devices'), 'device');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('device')->value) {
$foreach2DoElse = false;
?>
        <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_uid'], ENT_QUOTES, 'UTF-8', true);?>
" data-device-token="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_token'], ENT_QUOTES, 'UTF-8', true);?>
">
          <?php if ($_smarty_tpl->getValue('device')['name']) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['name'], ENT_QUOTES, 'UTF-8', true);
} else {
echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_uid'], ENT_QUOTES, 'UTF-8', true);
}?>
        </option>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </select>
  </div>

  <div class="col-md-6 d-flex align-items-end">
    <button type="button" class="btn btn-outline-secondary" id="standMeasureBtn">
      Получить измерения
    </button>
  </div>

  <div class="col-md-3">
    <label for="weightKg" class="form-label">Вес, кг</label>
    <input type="number" step="0.001" class="form-control" id="weightKg" name="weight_kg" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['weight_kg'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-3">
    <label for="sizeL" class="form-label">Длина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeL" name="size_l_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_l_cm'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-3">
    <label for="sizeW" class="form-label">Ширина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeW" name="size_w_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_w_cm'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-3">
    <label for="sizeH" class="form-label">Высота, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeH" name="size_h_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_h_cm'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="save_item_stock">
      Сохранить
    </button>
  </div>
</form>



<?php echo '<script'; ?>
>
  (function () {
    var section = document.getElementById('warehouseStockAddonsSection');
    if (!section || section.__addonsBound) return;
    section.__addonsBound = true;

    var companySelect = document.getElementById('receiverCompany');
    var controls = document.getElementById('warehouseStockAddonsControls');
    var emptyNode = document.getElementById('warehouseStockAddonsEmpty');
    var hiddenInput = document.getElementById('warehouseStockAddonsJson');
    var debugInput = document.getElementById('warehouseStockAddonsDebug');
    if (!companySelect || !controls || !emptyNode || !hiddenInput || !debugInput) return;

    var addonsMap = {};
    var addonsRawMap = {};
    var selectedAddons = {};
    try { addonsMap = JSON.parse(section.getAttribute('data-addons-map') || '{}') || {}; } catch (e) { addonsMap = {}; }
    try { addonsRawMap = JSON.parse(section.getAttribute('data-addons-raw-map') || '{}') || {}; } catch (e) { addonsRawMap = {}; }
    try { selectedAddons = JSON.parse(section.getAttribute('data-item-addons') || '{}') || {}; } catch (e) { selectedAddons = {}; }

    function normalizeForwarderName(raw) {
      return String(raw || '').trim().toUpperCase();
    }

    function updateDebug(forwarder) {
      var raw = addonsRawMap[forwarder];
      debugInput.value = typeof raw === 'string' ? raw : '';
    }

    function persist() {
      var payload = {};
      controls.querySelectorAll('select[data-addon-key]').forEach(function (select) {
        var addonKey = select.getAttribute('data-addon-key') || '';
        if (!addonKey) return;
        var val = String(select.value || '').trim();
        if (val === '') return;
        payload[addonKey] = val;
      });
      hiddenInput.value = Object.keys(payload).length ? JSON.stringify(payload) : '';
    }

    function createSelect(addonKey, optionsMap, initialValue) {
      var col = document.createElement('div');
      col.className = 'col-md-6';

      var label = document.createElement('label');
      label.className = 'form-label';
      label.textContent = addonKey;

      var select = document.createElement('select');
      select.className = 'form-select';
      select.setAttribute('data-addon-key', addonKey);

      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = '— выберите —';
      select.appendChild(emptyOpt);

      Object.keys(optionsMap || {}).forEach(function (valueKey) {
        var opt = document.createElement('option');
        opt.value = String(valueKey);
        opt.textContent = String(optionsMap[valueKey]);
        if (String(initialValue || '') === String(valueKey)) {
          opt.selected = true;
        }
        select.appendChild(opt);
      });

      select.addEventListener('change', persist);

      col.appendChild(label);
      col.appendChild(select);
      controls.appendChild(col);
    }

    function render() {
      controls.innerHTML = '';
      var forwarder = normalizeForwarderName(companySelect.value);
      updateDebug(forwarder);
      var extra = addonsMap[forwarder];
      if ((!Array.isArray(extra) || !extra.length) && forwarder) {
        Object.keys(addonsMap).some(function (rawKey) {
          var normalizedKey = normalizeForwarderName(rawKey);
          if (!normalizedKey) return false;

          var isMatch = normalizedKey === forwarder
            || normalizedKey.indexOf(forwarder) === 0
            || forwarder.indexOf(normalizedKey) === 0;
          if (!isMatch) return false;

          extra = addonsMap[rawKey];
          return Array.isArray(extra) && extra.length;
        });
      }
      if (!Array.isArray(extra) || !extra.length) {
        emptyNode.style.display = '';
        hiddenInput.value = '';
        return;
      }

      emptyNode.style.display = 'none';
      extra.forEach(function (group) {
        if (!group || typeof group !== 'object') return;
        Object.keys(group).forEach(function (addonKey) {
          var optionsMap = group[addonKey];
          if (!optionsMap || typeof optionsMap !== 'object') return;
          createSelect(addonKey, optionsMap, selectedAddons[addonKey]);
        });
      });
      persist();
    }

    companySelect.addEventListener('change', render);
    companySelect.addEventListener('input', render);
    render();
  })();
<?php echo '</script'; ?>
>
<?php }
}
