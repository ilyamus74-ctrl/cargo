<?php
/* Smarty version 5.3.1, created on 2026-03-05 11:09:53
  from 'file:cells_NA_API_warehouse_sync_missing_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a964818a7ea8_33792182',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'ec0d4fb350b400c1df740b0dd7bfe1e493042ad8' => 
    array (
      0 => 'cells_NA_API_warehouse_sync_missing_rows.html',
      1 => 1772708649,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a964818a7ea8_33792182 (\Smarty\Template $_smarty_tpl) {
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
    <td>
      <code><?php echo (($tmp = $_smarty_tpl->getValue('item')['report_table'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</code>
      <?php if ($_smarty_tpl->getValue('item')['sync_status_label']) {?>
        <div class="small <?php echo (($tmp = $_smarty_tpl->getValue('item')['sync_status_class'] ?? null)===null||$tmp==='' ? 'text-muted' ?? null : $tmp);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['sync_status_label'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
      <?php if ($_smarty_tpl->getValue('item')['report_confirmation_label']) {?>
        <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['report_confirmation_label'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
    </td>
    <td>
      <?php if ((($tmp = $_smarty_tpl->getValue('item')['can_sync'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) {?>
        <button
          type="button"
          class="btn btn-sm btn-outline-primary warehouse-sync-row-btn"
          data-item-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"
          data-connector-id="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['connector_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"
          data-parcel="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"
        >
          sync
        </button>
      <?php } else { ?>
        <span class="text-muted small">—</span>
      <?php }?>
    </td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('sync_missing_items') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="6" class="text-center text-muted">Нет посылок для сравнения</td>
  </tr>
<?php }
}
}
