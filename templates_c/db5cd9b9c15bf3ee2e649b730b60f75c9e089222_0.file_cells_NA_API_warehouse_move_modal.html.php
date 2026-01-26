<?php
/* Smarty version 5.3.1, created on 2026-01-26 18:51:32
  from 'file:cells_NA_API_warehouse_move_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6977b7b4dd06b0_71607465',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'db5cd9b9c15bf3ee2e649b730b60f75c9e089222' => 
    array (
      0 => 'cells_NA_API_warehouse_move_modal.html',
      1 => 1769453378,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6977b7b4dd06b0_71607465 (\Smarty\Template $_smarty_tpl) {
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
" disabled>
  </div>

  <div class="col-md-4">
    <label for="trackingNo" class="form-label">Трек-номер</label>
    <input type="text" class="form-control" id="trackingNo" name="tracking_no" required value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['tracking_no'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-md-4">
    <label for="carrierName" class="form-label">Перевозчик</label>
    <input type="text" class="form-control" id="carrierName" name="carrier_name" readonly value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['carrier_name'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
    <input type="hidden" id="senderName" name="carrier_code" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['carrier_code'], ENT_QUOTES, 'UTF-8', true);?>
">
  </div>

  <div class="col-md-4">
    <label class="form-label" for="receiverCountry">Страна получателя</label>
    <select class="form-select"
            id="receiverCountry"
            name="receiver_country_code"
            disabled>
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
" disabled>
  </div>

  <div class="col-md-6">
    <label for="receiverAddress" class="form-label">Ячейка</label>
    <input type="text" class="form-control" id="receiverAddress" name="receiver_address" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['receiver_address'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
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
            name="receiver_company"
            disabled>
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
" disabled>
  </div>

  <div class="col-md-6">
    <label for="standDevice" class="form-label">Устройство измерения</label>
    <select class="form-select" id="standDevice" name="stand_device_uid" disabled>
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
    <button type="button" class="btn btn-outline-secondary" id="standMeasureBtn" disabled>
      Получить измерения
    </button>
  </div>

  <div class="col-md-3">
    <label for="weightKg" class="form-label">Вес, кг</label>
    <input type="number" step="0.001" class="form-control" id="weightKg" name="weight_kg" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['weight_kg'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-md-3">
    <label for="sizeL" class="form-label">Длина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeL" name="size_l_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_l_cm'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-md-3">
    <label for="sizeW" class="form-label">Ширина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeW" name="size_w_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_w_cm'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-md-3">
    <label for="sizeH" class="form-label">Высота, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeH" name="size_h_cm" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['size_h_cm'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="warehouse_move_save_cell">
      Сохранить
    </button>
  </div>
</form>
<?php }
}
