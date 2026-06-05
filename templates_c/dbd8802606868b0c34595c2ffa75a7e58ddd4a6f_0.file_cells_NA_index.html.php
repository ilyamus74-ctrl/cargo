<?php
/* Smarty version 5.3.1, created on 2026-03-17 08:10:11
  from 'file:cells_NA_index.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69b90c6353dc56_26545503',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'dbd8802606868b0c34595c2ffa75a7e58ddd4a6f' => 
    array (
      0 => 'cells_NA_index.html',
      1 => 1764922246,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:cells_NA_header.html' => 1,
    'file:cells_NA_header_menu.html' => 1,
    'file:cells_NA_sidebar.html' => 1,
    'file:cells_NA_main.html' => 1,
    'file:cells_NA_footer.html' => 1,
  ),
))) {
function content_69b90c6353dc56_26545503 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><!DOCTYPE html>
<html lang="en">

<?php $_smarty_tpl->renderSubTemplate('file:cells_NA_header.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

<body>
<?php $_smarty_tpl->renderSubTemplate('file:cells_NA_header_menu.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
$_smarty_tpl->renderSubTemplate('file:cells_NA_sidebar.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>


<?php $_smarty_tpl->renderSubTemplate('file:cells_NA_main.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>



<?php $_smarty_tpl->renderSubTemplate('file:cells_NA_footer.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

</body>

</html><?php }
}
