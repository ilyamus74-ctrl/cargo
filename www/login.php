<?php
session_start();
require_once __DIR__ . '/../configs/secure.php';
/*
if (!isset($smarty)) {
    // на случай кривого вызова
    require_once __DIR__ . '/../libs/Smarty.class.php';
    $smarty = class_exists('\\Smarty\\Smarty') ? new \Smarty\Smarty : new Smarty();
    require_once __DIR__ . '/patch.php';
}
*/

$header_data['domainName']=$domainName;
/*
$header_data['title']="mainAboutTitle";
$header_data['description']="mainAboutTitle";
$header_data['keywords']="mainAboutKeywords";
$header_data['author']="";
$header_data['canonical']=$canonical;
$header_data['siteName']="";
$header_data['http-equiv']="";
$header_data['charset']="";
$header_data['soc_og_title']="mainAboutTitle";
$header_data['soc_og_description']="mainAboutTitle";
$header_data['twitter_title']="mainAboutTitle";
$header_data['twitter_description']="mainAboutTitle";
*/
$smarty->assign('header_data', $header_data);
$smarty->assign('login','login');

$smarty->display('cells_login_main.html');

//echo "login";

?>