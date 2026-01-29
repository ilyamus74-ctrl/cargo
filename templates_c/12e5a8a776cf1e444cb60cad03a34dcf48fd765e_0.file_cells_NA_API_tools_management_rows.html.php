<?php
/* Smarty version 5.3.1, created on 2026-01-29 15:44:30
  from 'file:cells_NA_API_tools_management_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b805ed3c0b4_15579993',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '12e5a8a776cf1e444cb60cad03a34dcf48fd765e' => 
    array (
      0 => 'cells_NA_API_tools_management_rows.html',
      1 => 1769701271,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b805ed3c0b4_15579993 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('tools'), 'tool');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('tool')->value) {
$foreach0DoElse = false;
?>
  <?php $_smarty_tpl->assign('userLabel', (($tmp = $_smarty_tpl->getValue('tool')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), false, NULL);?>
  <?php $_smarty_tpl->assign('cellLabelRaw', (($tmp = $_smarty_tpl->getValue('tool')['location'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), false, NULL);?>
  <?php $_smarty_tpl->assign('cellLabel', $_smarty_tpl->getSmarty()->getModifierCallback('replace')($_smarty_tpl->getValue('cellLabelRaw'),'CELL:',''), false, NULL);?>
  <tr>
    <td>
      <button type="button"
              class="btn btn-link p-0 js-core-link"
              data-core-action="tools_management_open_modal"
              data-tool-id="<?php echo $_smarty_tpl->getValue('tool')['id'];?>
">
        <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['name'], ENT_QUOTES, 'UTF-8', true);?>

      </button>
      <div class="small text-muted"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('tool')['serial_number'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
    </td>
    <td>
      <button type="button"
              class="btn btn-link p-0 js-core-link"
              data-core-action="tools_management_open_user_modal"
              data-tool-id="<?php echo $_smarty_tpl->getValue('tool')['id'];?>
">
        <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('userLabel'), ENT_QUOTES, 'UTF-8', true);?>

      </button>
    </td>
    <td>
      <button type="button"
              class="btn btn-link p-0 js-core-link"
              data-core-action="tools_management_open_cell_modal"
              data-tool-id="<?php echo $_smarty_tpl->getValue('tool')['id'];?>
">
        <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cellLabel'), ENT_QUOTES, 'UTF-8', true);?>

      </button>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('tool')['updated_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('tools') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="4" class="text-center text-muted">
      Совпадений не найдено
    </td>
  </tr>
<?php }
}
}
