<?php
/* Smarty version 5.3.1, created on 2026-03-16 09:34:08
  from 'file:cells_NA_API_warehouse_item_out_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69b7ce90b36787_13631546',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'bd646df81d3142031c09b22eafacba74fb43fccf' => 
    array (
      0 => 'cells_NA_API_warehouse_item_out_rows.html',
      1 => 1773653016,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69b7ce90b36787_13631546 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('item_out_rows'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['tracking_no'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_company'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_country_code'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['cell_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td>
      <div class="small text-primary"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['status'] ?? null)===null||$tmp==='' ? 'to_send' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php if ($_smarty_tpl->getValue('item')['status_message']) {?>
        <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['status_message'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
    </td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('item_out_rows') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="6" class="text-center text-muted">Нет посылок в статусе to_send</td>
  </tr>
<?php }
}
}
