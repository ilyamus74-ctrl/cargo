<?php
/* Smarty version 5.3.1, created on 2025-12-03 13:18:57
  from 'file:cells_index.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_693038c1e583f7_69333748',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9f016b177064f041b034d0b4df037240f51dcec5' => 
    array (
      0 => 'cells_index.html',
      1 => 1764767914,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:cells_header.html' => 1,
    'file:cells_navBar.html' => 1,
    'file:simpleAbout.html' => 1,
    'file:cells_login.html' => 1,
    'file:footer.html' => 1,
    'file:downJS.html' => 1,
  ),
))) {
function content_693038c1e583f7_69333748 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><!DOCTYPE html>
<html lang="<?php echo $_smarty_tpl->getValue('xlang');?>
">
 <?php $_smarty_tpl->renderSubTemplate('file:cells_header.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
    <body id="page-top">
 <?php $_smarty_tpl->renderSubTemplate('file:cells_navBar.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
        <!-- Masthead-->
<!--        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("EasyTradingMain");?>
<br></div>
                <div class="masthead-heading text-uppercase"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("EasyTradingSignalsMain");?>
<br><br><br><br></div>
                <?php if (!$_smarty_tpl->getValue('predictions')) {?>
                 <a class="btn btn-primary btn-xl text-uppercase" href="predictions.html"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("ToPredictsButton");?>
</a>
                <?php }?>
            </div>
        </header>
-->
<?php if ($_smarty_tpl->getValue('main')) {
$_smarty_tpl->renderSubTemplate('file:simpleAbout.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}?>

<?php if ($_smarty_tpl->getValue('login')) {
$_smarty_tpl->renderSubTemplate('file:cells_login.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}?>

<?php $_smarty_tpl->renderSubTemplate('file:footer.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>




 <?php $_smarty_tpl->renderSubTemplate('file:downJS.html', $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
    </body>
</html>
<?php }
}
