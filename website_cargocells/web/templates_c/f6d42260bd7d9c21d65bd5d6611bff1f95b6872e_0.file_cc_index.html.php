<?php
/* Smarty version 5.3.1, created on 2026-01-28 08:21:16
  from 'file:cc_index.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6979c6fcc28961_37531070',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f6d42260bd7d9c21d65bd5d6611bff1f95b6872e' => 
    array (
      0 => 'cc_index.html',
      1 => 1769588460,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:cc_header.html' => 1,
    'file:cc_navBar.html' => 1,
    'file:cc_main.html' => 1,
    'file:cc_footer.html' => 1,
    'file:cc_js.html' => 1,
  ),
))) {
function content_6979c6fcc28961_37531070 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?><!DOCTYPE html>
<html lang="<?php echo $_smarty_tpl->getValue('xlang');?>
">
 <?php $_smarty_tpl->renderSubTemplate('file:cc_header.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
  <body data-spy="scroll" data-target=".site-navbar-target" data-offset="200">
 <?php $_smarty_tpl->renderSubTemplate('file:cc_navBar.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

<?php $_smarty_tpl->renderSubTemplate('file:cc_main.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>


<?php $_smarty_tpl->renderSubTemplate('file:cc_footer.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

<?php $_smarty_tpl->renderSubTemplate('file:cc_js.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

  </body>
</html><?php }
}
