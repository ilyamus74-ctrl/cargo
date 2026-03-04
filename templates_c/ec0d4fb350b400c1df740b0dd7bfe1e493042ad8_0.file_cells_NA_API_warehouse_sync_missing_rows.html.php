<?php
/* Smarty version 5.3.1, created on 2026-03-04 13:21:54
  from 'file:cells_NA_API_warehouse_sync_missing_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a831f22bdf50_08671381',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'ec0d4fb350b400c1df740b0dd7bfe1e493042ad8' => 
    array (
      0 => 'cells_NA_API_warehouse_sync_missing_rows.html',
      1 => 1772630186,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a831f22bdf50_08671381 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('sync_missing_items'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['receiver_company'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['receiver_country_code'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['cell_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
    <td><code><?php echo (($tmp = $_smarty_tpl->getValue('item')['report_table'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</code></td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('sync_missing_items') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="5" class="text-center text-muted">Нет посылок для сравнения</td>
  </tr>
<?php }
}
}
