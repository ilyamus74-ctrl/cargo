<?php
/* Smarty version 5.3.1, created on 2026-01-15 09:19:57
  from 'file:cells_NA_API_warehouse_cell_form.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6968b13d611570_56460817',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '8c34095ebdc7f751eecff02adc24e943c401ed6f' => 
    array (
      0 => 'cells_NA_API_warehouse_cell_form.html',
      1 => 1768468452,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6968b13d611570_56460817 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><section class="section">
  <div class="row">
    <div class="col-12">
      <form id="cell-profile-form" class="row g-3">
        <input type="hidden" name="cell_id" id="cell_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('edit_cell')['id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
">
        <div class="col-md-6">
          <label for="cellCode" class="form-label">Code</label>
          <input type="text" class="form-control" id="cellCode" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('edit_cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
" readonly>
        </div>
        <div class="col-12">
          <label for="cellDescription" class="form-label">Description</label>
          <textarea class="form-control" id="cellDescription" name="description" rows="3"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('edit_cell')['description'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
        </div>
        <div class="col-12">
          <button type="button" class="btn btn-primary js-core-link" data-core-action="save_cell">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</section><?php }
}
