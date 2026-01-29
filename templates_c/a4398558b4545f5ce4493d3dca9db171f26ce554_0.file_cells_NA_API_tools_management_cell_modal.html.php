<?php
/* Smarty version 5.3.1, created on 2026-01-29 15:54:04
  from 'file:cells_NA_API_tools_management_cell_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b829cd655e1_51185008',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'a4398558b4545f5ce4493d3dca9db171f26ce554' => 
    array (
      0 => 'cells_NA_API_tools_management_cell_modal.html',
      1 => 1769701229,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b829cd655e1_51185008 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><form id="tool-storage-move-form" class="row g-3">
  <input type="hidden" name="tool_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['id'], ENT_QUOTES, 'UTF-8', true);?>
">
  <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('tool')['assigned_user_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">

  <div class="col-12">
    <label class="form-label" for="toolName">Инструмент</label>
    <input type="text" class="form-control" id="toolName" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['name'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-12">
    <label class="form-label" for="toolStorageCell">Ячейка хранения</label>
    <select class="form-select" id="toolStorageCell" name="cell_id">
      <option value="">— не выбрано —</option>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach0DoElse = false;
?>
        <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
" <?php if ($_smarty_tpl->getValue('cell')['code'] == $_smarty_tpl->getValue('tool')['location']) {?>selected<?php }?>><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </select>
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="tools_management_save_move">
      Сохранить
    </button>
  </div>
</form>
<?php }
}
