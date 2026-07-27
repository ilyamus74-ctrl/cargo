<?php
session_start();
//print_r($_POST);
//print_r($_GET);
//echo "start";

//require('patch.php');

/*
require('../libs/Smarty.class.php');
$smarty = new Smarty;
require('patch.php');
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;
$smarty->assign(THEME,$theme);
*/

//get_status_orders($dbcnx_sklad,$provider);


//echo "aaaa";
$smarty->assign("pageView","zvidki-deshevshe-prignati-avto.html");
//$smarty->display("index.tpl");
$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);

$smarty->display("index.html");

?>
