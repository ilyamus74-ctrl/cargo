<?php
session_start();
/*
$smarty->template_dir = "../templates";
$smarty->compile_dir = "../templates_c";
$smarty->config_dir = "../configs";
$smarty->cache_dir = "../cache";
*/
$smarty->setTemplateDir('/home/cellscargo/web/templates/');
$smarty->setCompileDir('/home/cellscargo/web/templates_c/');
$smarty->setConfigDir('/home/cellscargo/web/configs/');
$smarty->setCacheDir('/home/cellscargo/web/cache/');
//echo "patch";
?>