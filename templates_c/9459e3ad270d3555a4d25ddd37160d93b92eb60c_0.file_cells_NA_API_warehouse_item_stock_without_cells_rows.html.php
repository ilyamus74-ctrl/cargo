<?php
/* Smarty version 5.3.1, created on 2026-01-16 10:08:32
  from 'file:cells_NA_API_warehouse_item_stock_without_cells_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696a0e20cc22a0_14090837',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9459e3ad270d3555a4d25ddd37160d93b92eb60c' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock_without_cells_rows.html',
      1 => 1768557891,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696a0e20cc22a0_14090837 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('parcels_without_cells'), 'parcel');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('parcel')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td>
      <button type="button"
              class="btn btn-link p-0 js-core-link"
              data-core-action="open_item_stock_modal"
              data-item-id="<?php echo $_smarty_tpl->getValue('parcel')['id'];?>
">
        <?php echo (($tmp = $_smarty_tpl->getValue('parcel')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

      </button>
    </td>
    <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
      <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <?php }?>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['created_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('parcels_without_cells') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>3<?php } else { ?>2<?php }?>" class="text-center text-muted">
      Нет посылок без ячеек
    </td>
  </tr>
<?php }
}
}
