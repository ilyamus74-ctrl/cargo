<?php
/* Smarty version 5.3.1, created on 2026-01-29 15:54:08
  from 'file:cells_NA_API_tools_management_user_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b82a0ec4c23_45792990',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '8eba10272473f8657adfdac0ad133f3b01db0575' => 
    array (
      0 => 'cells_NA_API_tools_management_user_modal.html',
      1 => 1769701289,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b82a0ec4c23_45792990 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><form id="tool-storage-move-form" class="row g-3">
  <input type="hidden" name="tool_id" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['id'], ENT_QUOTES, 'UTF-8', true);?>
">

  <div class="col-12">
    <label class="form-label" for="toolName">Инструмент</label>
    <input type="text" class="form-control" id="toolName" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['name'], ENT_QUOTES, 'UTF-8', true);?>
" disabled>
  </div>

  <div class="col-12">
    <label class="form-label" for="toolAssignedUser">Пользователь</label>
    <select class="form-select" id="toolAssignedUser" name="user_id">
      <option value="">— не выбран —</option>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('users'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
        <?php $_smarty_tpl->assign('userLabel', (($tmp = $_smarty_tpl->getValue('item')['full_name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('item')['username'] ?? null : $tmp), false, NULL);?>
        <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['id'], ENT_QUOTES, 'UTF-8', true);?>
" <?php if ($_smarty_tpl->getValue('item')['id'] == $_smarty_tpl->getValue('tool')['assigned_user_id']) {?>selected<?php }?>><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('userLabel'), ENT_QUOTES, 'UTF-8', true);?>
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
