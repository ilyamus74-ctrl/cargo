<?php
/* Smarty version 5.3.1, created on 2026-06-18 08:02:30
  from 'file:cells_NA_API_warehouse_cell_forwarder_mappings.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a33a616d05984_13642051',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '930ba836faf0c51529c9bfff8f83d23b28b99b80' => 
    array (
      0 => 'cells_NA_API_warehouse_cell_forwarder_mappings.html',
      1 => 1781767690,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a33a616d05984_13642051 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="warehouse-cell-forwarder-mappings">
  <h5>Связи форвардов для ячейки <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</h5>
  <form class="row g-2 mb-3" id="cell-forwarder-mapping-form">
    <input type="hidden" name="cell_id" value="<?php echo $_smarty_tpl->getValue('cell')['id'];?>
">
    <div class="col-md-3">
      <label class="form-label">Connector</label>
      <select class="form-select" name="connector_id" required>
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('connectors'), 'connector');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('connector')->value) {
$foreach0DoElse = false;
?>
          <option value="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
</option>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Country</label>
      <input type="text" class="form-control" name="country_code" placeholder="optional">
    </div>
    <div class="col-md-3">
      <label class="form-label">Forwarder position</label>
      <select class="form-select" name="forwarder_position_code" required>
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('forwarder_positions'), 'position');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('position')->value) {
$foreach1DoElse = false;
?>
          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('position')['position_code'], ENT_QUOTES, 'UTF-8', true);?>
" data-connector-id="<?php echo $_smarty_tpl->getValue('position')['connector_id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('position')['position_code'], ENT_QUOTES, 'UTF-8', true);
if ($_smarty_tpl->getValue('position')['position_label']) {?> — <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('position')['position_label'], ENT_QUOTES, 'UTF-8', true);
}?></option>
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Comment</label>
      <input type="text" class="form-control" name="comment">
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> active</label>
    </div>
    <div class="col-12">
      <button type="button" class="btn btn-primary js-core-link" data-core-action="save_cell_forwarder_mapping">Сохранить связь</button>
    </div>
  </form>
  <table class="table table-sm">
    <thead><tr><th>Connector</th><th>Position</th><th>Country</th><th>Status</th><th>Comment</th><th></th></tr></thead>
    <tbody>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('mappings'), 'mapping');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('mapping')->value) {
$foreach2DoElse = false;
?>
        <tr>
          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('mapping')['connector_name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('mapping')['connector_id'] ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
          <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('mapping')['forwarder_position_code'], ENT_QUOTES, 'UTF-8', true);?>
</span></td>
          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('mapping')['country_code'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
          <td><?php if ($_smarty_tpl->getValue('mapping')['is_active']) {?>active<?php } else { ?>inactive<?php }?></td>
          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('mapping')['comment'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
          <td><button type="button" class="btn btn-sm btn-outline-danger js-core-link" data-core-action="delete_cell_forwarder_mapping" data-mapping-id="<?php echo $_smarty_tpl->getValue('mapping')['id'];?>
">Удалить</button></td>
        </tr>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      <?php if (!$_smarty_tpl->getValue('mappings')) {?><tr><td colspan="6" class="text-muted">Связей нет</td></tr><?php }?>
    </tbody>
  </table>
</div>
<?php }
}
