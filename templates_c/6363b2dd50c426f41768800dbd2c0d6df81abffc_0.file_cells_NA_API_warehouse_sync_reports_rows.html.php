<?php
/* Smarty version 5.3.1, created on 2026-03-05 10:22:35
  from 'file:cells_NA_API_warehouse_sync_reports_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a9596b383c38_33569019',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6363b2dd50c426f41768800dbd2c0d6df81abffc' => 
    array (
      0 => 'cells_NA_API_warehouse_sync_reports_rows.html',
      1 => 1772632120,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a9596b383c38_33569019 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('sync_reported_items'), 'item');
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
    <td><?php echo (($tmp = $_smarty_tpl->getValue('item')['report_created_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('sync_reported_items')) {?>
  <tr>
    <td colspan="6" class="text-center text-muted">Нет посылок из отчета форварда на складе</td>
  </tr>
<?php }
}
}
