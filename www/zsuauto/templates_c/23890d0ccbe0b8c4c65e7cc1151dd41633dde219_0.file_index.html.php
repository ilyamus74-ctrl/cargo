<?php
/* Smarty version 5.3.1, created on 2024-07-12 08:56:06
  from 'file:NiceAdmin/index.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6690efa6d56bc2_00583814',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '23890d0ccbe0b8c4c65e7cc1151dd41633dde219' => 
    array (
      0 => 'NiceAdmin/index.html',
      1 => 1720707629,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:NiceAdmin/head.html' => 1,
    'file:NiceAdmin/header.html' => 1,
    'file:NiceAdmin/Sidebar.html' => 1,
    'file:NiceAdmin/viewNewAnnounce.html' => 1,
    'file:NiceAdmin/viewPublishAnnounce.html' => 1,
    'file:NiceAdmin/viewRequestMsg.html' => 1,
    'file:NiceAdmin/footer.html' => 1,
    'file:NiceAdmin/vendorJS.html' => 1,
  ),
))) {
function content_6690efa6d56bc2_00583814 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates/NiceAdmin';
?><!DOCTYPE html>
<html lang="en">
<?php $_smarty_tpl->renderSubTemplate("file:NiceAdmin/head.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
<body>

<?php $_smarty_tpl->renderSubTemplate("file:NiceAdmin/header.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

<?php $_smarty_tpl->renderSubTemplate("file:NiceAdmin/Sidebar.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>


<?php if ($_smarty_tpl->getValue('pageview') == "viewNewAnnounce") {
$_smarty_tpl->renderSubTemplate("file:NiceAdmin/viewNewAnnounce.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageview') == "viewPublishAnnounce") {
$_smarty_tpl->renderSubTemplate("file:NiceAdmin/viewPublishAnnounce.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageview') == "viewRequestMsgAll") {
$_smarty_tpl->renderSubTemplate("file:NiceAdmin/viewRequestMsg.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}?>


<?php $_smarty_tpl->renderSubTemplate("file:NiceAdmin/footer.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<?php $_smarty_tpl->renderSubTemplate("file:NiceAdmin/vendorJS.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>


</body>

</html><?php }
}
