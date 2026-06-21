<?php
session_start();
//print_r($_POST);
//print_r($_GET);
//echo "start";

//require('patch.php');


require_once('../libs/Smarty.class.php');
//$smarty = new Smarty;
$smarty = new \Smarty\Smarty;

require_once('patch.php');
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;
//$smarty->setErrorReporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$smarty->assign("THEME",$theme);

$data['main_text']="Вибачте, такої сторінки не існує, помилка 404";
$data['main_text_h1']="Вибачте, такої сторінки не існує, помилка 404";
$data['description']="Вибачте, такої сторінки не існує, помилка 404";
$data['title']="Вибачте, такої сторінки не існує, помилка 404";
$data['keywords']="Вибачте, такої сторінки не існує, помилка 404";
$smarty->assign("data",$data);

//print_r($_SERVER);
$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME']."/404");

//echo "aaaa";
$smarty->assign("pageView","404");
$smarty->display("index.html");
//echo "bbb";

?>
