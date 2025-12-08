<?php
/* Smarty version 5.3.1, created on 2025-12-08 10:49:46
  from 'file:cells_NA_API_warehouse_item_in_batch.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6936ad4a8b4e49_03691753',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7e8c2c62a4cb34a58255b0e76fcea2eec9328b7b' => 
    array (
      0 => 'cells_NA_API_warehouse_item_in_batch.html',
      1 => 1765190969,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6936ad4a8b4e49_03691753 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><form id="item-in-modal-form" class="row g-3">
  <input type="hidden" name="batch_uid" value="<?php echo $_smarty_tpl->getValue('batch_uid');?>
">

  <div class="col-md-4">
    <label for="tuid" class="form-label">TUID</label>
    <input type="text" class="form-control" id="tuid" name="tuid" required>
  </div>

  <div class="col-md-4">
    <label for="trackingNo" class="form-label">Трек-номер</label>
    <input type="text" class="form-control" id="trackingNo" name="tracking_no" required>
  </div>

  <div class="col-md-4">
    <label for="receiverCountry" class="form-label">Страна получателя</label>
    <input type="text" class="form-control" id="receiverCountry" name="receiver_country_name" placeholder="Germany / DE">
  </div>

  <div class="col-md-6">
    <label for="receiverName" class="form-label">Получатель</label>
    <input type="text" class="form-control" id="receiverName" name="receiver_name">
  </div>

  <div class="col-md-6">
    <label for="receiverAddress" class="form-label">Адрес</label>
    <input type="text" class="form-control" id="receiverAddress" name="receiver_address">
  </div>

  <div class="col-md-6">
    <label for="receiverCompany" class="form-label">Компания получателя</label>
    <input type="text" class="form-control" id="receiverCompany" name="receiver_company">
  </div>

  <div class="col-md-6">
    <label for="senderName" class="form-label">Отправитель</label>
    <input type="text" class="form-control" id="senderName" name="sender_name">
  </div>

  <div class="col-md-3">
    <label for="weightKg" class="form-label">Вес, кг</label>
    <input type="number" step="0.001" class="form-control" id="weightKg" name="weight_kg">
  </div>

  <div class="col-md-3">
    <label for="sizeL" class="form-label">Длина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeL" name="size_l_cm">
  </div>

  <div class="col-md-3">
    <label for="sizeW" class="form-label">Ширина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeW" name="size_w_cm">
  </div>

  <div class="col-md-3">
    <label for="sizeH" class="form-label">Высота, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeH" name="size_h_cm">
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="add_new_item_in">
      Добавить посылку
    </button>
  </div>
</form>

<hr>

<h5>Посылки в партии <?php echo $_smarty_tpl->getValue('batch_uid');?>
</h5>

<table class="table table-sm align-middle">
  <thead>
    <tr>
      <th>#</th>
      <th>Трек</th>
      <th>Получатель</th>
      <th>Вес</th>
      <th>Габариты</th>
      <th>Создано</th>
    </tr>
  </thead>
  <tbody>
    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('items'), 'p');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('p')->value) {
$foreach0DoElse = false;
?>
      <tr>
        <td><?php echo $_smarty_tpl->getValue('p')['id'];?>
</td>
        <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('p')['tracking_no'], ENT_QUOTES, 'UTF-8', true);?>
</td>
        <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('p')['receiver_name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
        <td><?php if ($_smarty_tpl->getValue('p')['weight_kg']) {
echo $_smarty_tpl->getValue('p')['weight_kg'];?>
 кг<?php }?></td>
        <td>
          <?php if ($_smarty_tpl->getValue('p')['size_l_cm'] || $_smarty_tpl->getValue('p')['size_w_cm'] || $_smarty_tpl->getValue('p')['size_h_cm']) {?>
            <?php echo $_smarty_tpl->getValue('p')['size_l_cm'];?>
×<?php echo $_smarty_tpl->getValue('p')['size_w_cm'];?>
×<?php echo $_smarty_tpl->getValue('p')['size_h_cm'];?>
 см
          <?php }?>
        </td>
        <td><?php echo $_smarty_tpl->getValue('p')['created_at'];?>
</td>
      </tr>
    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    <?php if (!$_smarty_tpl->getValue('items')) {?>
      <tr>
        <td colspan="6" class="text-center text-muted">
          В этой партии ещё нет посылок
        </td>
      </tr>
    <?php }?>
  </tbody>
</table>

<div class="mt-3 text-end">
  <button type="button"
          class="btn btn-success js-core-link"
          data-core-action="commit_item_in_batch"
          data-batch-uid="<?php echo $_smarty_tpl->getValue('batch_uid');?>
">
    Завершить партию (на склад)
  </button>
</div>
<?php }
}
