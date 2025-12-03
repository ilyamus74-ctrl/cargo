<?php
session_start();
/*
$smarty->template_dir = "../templates";
$smarty->compile_dir = "../templates_c";
$smarty->config_dir = "../configs";
$smarty->cache_dir = "../cache";
*/
$smarty->setTemplateDir('/home/cells/web/templates/');
$smarty->setCompileDir('/home/cells/web/templates_c/');
$smarty->setConfigDir('/home/cells/web/configs/');
$smarty->setCacheDir('/home/cells/web/cache/');
//echo "patch";
?>