<?php
session_start();
/*
$smarty->template_dir = "../templates";
$smarty->compile_dir = "../templates_c";
$smarty->config_dir = "../configs";
$smarty->cache_dir = "../cache";
*/
$smarty->setTemplateDir('/home/zsuauto/web/templates/');
$smarty->setCompileDir('/home/zsuauto/web/templates_c/');
$smarty->setConfigDir('/home/zsuauto/web/configs/');
$smarty->setCacheDir('/home/zsuauto/web/cache/');
//echo "patch";
?>