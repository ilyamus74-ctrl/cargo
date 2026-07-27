<?php
/* Smarty version 5.3.1, created on 2024-07-12 08:59:38
  from 'file:index.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6690f07a3762c6_39650118',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b3dcdf54804c9c9933aed71194f4008f18fcd8ff' => 
    array (
      0 => 'index.html',
      1 => 1720766939,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
    'file:head.html' => 1,
    'file:headers.html' => 1,
    'file:main.html' => 1,
    'file:aboutus.html' => 1,
    'file:service.html' => 1,
    'file:view.html' => 1,
    'file:new-cars.html' => 1,
    'file:all-cars.html' => 1,
    'file:contact.html' => 1,
    'file:404.html' => 1,
    'file:zvidki-deshevshe-prignati-avto.html' => 1,
    'file:prigon-avto-dlya-viyskovih.html' => 1,
    'file:kupit-avto-dlya-zsu-nedorogo.html' => 1,
    'file:poshuk-i-pokupka-avto-dlya-zsu.html' => 1,
    'file:prodag-ta-kupivlya-avto-dlya-zsu.html' => 1,
    'file:avto-dlya-zsu.html' => 1,
    'file:abra.html' => 1,
    'file:modal.html' => 1,
    'file:footer.html' => 1,
    'file:js.html' => 1,
  ),
))) {
function content_6690f07a3762c6_39650118 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?><!DOCTYPE html>
<html lang="uk-UA">
<?php $_smarty_tpl->renderSubTemplate("file:head.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>
<body>
<?php $_smarty_tpl->renderSubTemplate("file:headers.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>





<?php if (!$_smarty_tpl->getValue('pageView')) {
$_smarty_tpl->renderSubTemplate("file:main.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}?>


<?php if ($_smarty_tpl->getValue('pageView') == "aboutUs") {
$_smarty_tpl->renderSubTemplate("file:aboutus.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "service") {
$_smarty_tpl->renderSubTemplate("file:service.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "view") {
$_smarty_tpl->renderSubTemplate("file:view.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "newCars") {
$_smarty_tpl->renderSubTemplate("file:new-cars.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "allCars") {
$_smarty_tpl->renderSubTemplate("file:all-cars.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "contact") {
$_smarty_tpl->renderSubTemplate("file:contact.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "404") {
$_smarty_tpl->renderSubTemplate("file:404.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "zvidki-deshevshe-prignati-avto.html") {
$_smarty_tpl->renderSubTemplate("file:zvidki-deshevshe-prignati-avto.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "prigon-avto-dlya-viyskovih.html") {
$_smarty_tpl->renderSubTemplate("file:prigon-avto-dlya-viyskovih.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "kupit-avto-dlya-zsu-nedorogo.html") {
$_smarty_tpl->renderSubTemplate("file:kupit-avto-dlya-zsu-nedorogo.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "poshuk-i-pokupka-avto-dlya-zsu.html") {
$_smarty_tpl->renderSubTemplate("file:poshuk-i-pokupka-avto-dlya-zsu.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "prodag-ta-kupivlya-avto-dlya-zsu.html") {
$_smarty_tpl->renderSubTemplate("file:prodag-ta-kupivlya-avto-dlya-zsu.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "avto-dlya-zsu.html") {
$_smarty_tpl->renderSubTemplate("file:avto-dlya-zsu.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}
if ($_smarty_tpl->getValue('pageView') == "ABRA") {
$_smarty_tpl->renderSubTemplate("file:abra.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
}?>




<?php $_smarty_tpl->renderSubTemplate("file:modal.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
$_smarty_tpl->renderSubTemplate("file:footer.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
$_smarty_tpl->renderSubTemplate("file:js.html", $_smarty_tpl->cache_id, $_smarty_tpl->compile_id, 0, $_smarty_tpl->cache_lifetime, array(), (int) 0, $_smarty_current_dir);
?>

</body>

</html><?php }
}
