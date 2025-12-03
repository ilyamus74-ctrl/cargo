<?php
session_start();
/*
$smarty->template_dir = "../templates";
$smarty->compile_dir = "../templates_c";
$smarty->config_dir = "../configs";
$smarty->cache_dir = "../cache";
*/
$smarty->setTemplateDir('/home/easyt/web/templates/');
$smarty->setCompileDir('/home/easyt/web/templates_c/');
$smarty->setConfigDir('/home/easyt/web/configs/');
$smarty->setCacheDir('/home/easyt/web/cache/');
//echo "patch";
?>