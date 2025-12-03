<?php
/* Smarty version 5.3.1, created on 2025-12-03 14:54:55
  from 'file:404.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69304f3fa8e439_49288194',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '3cdf7a90676ab0ce9254ade896f8444809685b69' => 
    array (
      0 => '404.html',
      1 => 1760542921,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:header.html' => 1,
    'file:navBar.html' => 1,
    'file:footer.html' => 1,
    'file:downJS.html' => 1,
  ),
))) {
function content_69304f3fa8e439_49288194 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><!DOCTYPE html>
<html lang="en">
 <?php $_smarty_tpl->renderSubTemplate('file:header.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
    <body id="page-top">
 <?php $_smarty_tpl->renderSubTemplate('file:navBar.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
        <!-- Masthead-->
        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading"><h1>Page not found 404 </h1></div>
            </div>
        </header>

 






<?php $_smarty_tpl->renderSubTemplate('file:footer.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>




         <?php $_smarty_tpl->renderSubTemplate('file:downJS.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
    </body>
</html>
<?php }
}
