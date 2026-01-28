<?php
/* Smarty version 5.3.1, created on 2026-01-27 19:41:12
  from 'file:404.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697914d8ed3218_68813347',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9aec7753b58dd6bcf403467905e0bea842b2989e' => 
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
function content_697914d8ed3218_68813347 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
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
