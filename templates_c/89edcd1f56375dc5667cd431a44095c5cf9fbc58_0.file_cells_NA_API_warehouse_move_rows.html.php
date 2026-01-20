<?php
/* Smarty version 5.3.1, created on 2026-01-20 10:49:20
  from 'file:cells_NA_API_warehouse_move_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696f5db0d7a102_49271223',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '89edcd1f56375dc5667cd431a44095c5cf9fbc58' => 
    array (
      0 => 'cells_NA_API_warehouse_move_rows.html',
      1 => 1768905579,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696f5db0d7a102_49271223 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('move_items'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['source'] == 'stock') {?>
        <button type="button"
                class="btn btn-link p-0 js-core-link"
                data-core-action="open_item_stock_modal"
                data-item-id="<?php echo $_smarty_tpl->getValue('item')['id'];?>
">
          <?php echo (($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

        </button>
      <?php } else { ?>
        <?php echo (($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

      <?php }?>
    </td>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['source'] == 'stock') {?>
        <span class="badge bg-success">Склад</span>
      <?php } else { ?>
        <span class="badge bg-warning text-dark">Приемка</span>
      <?php }?>
    </td>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['cell_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
      <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <?php }?>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['created_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('move_items') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>5<?php } else { ?>4<?php }?>" class="text-center text-muted">
      Совпадений не найдено
    </td>
  </tr>
<?php }
}
}
