<?php
/* Smarty version 5.3.1, created on 2026-02-27 18:07:10
  from 'file:cells_NA_API_warehouse_move_box_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a1dd4edc5580_56610728',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd4f16c41119a1ff676dbfb960184274e21da1b12' => 
    array (
      0 => 'cells_NA_API_warehouse_move_box_rows.html',
      1 => 1772215271,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a1dd4edc5580_56610728 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('move_box_items'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td>
      <button type="button"
              class="btn btn-link p-0 js-core-link"
              data-core-action="open_item_stock_modal"
              data-item-id="<?php echo $_smarty_tpl->getValue('item')['id'];?>
">
        <?php echo (($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

      </button>
    </td>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['cell_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
      <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <?php }?>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['stored_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('move_box_items') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>4<?php } else { ?>3<?php }?>" class="text-center text-muted">
      В выбранном источнике нет посылок
    </td>
  </tr>
<?php }
}
}
