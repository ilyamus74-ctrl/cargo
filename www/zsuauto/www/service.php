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


#echo "aaaa";
$data['main_text']="Послуги для військових яки ми робимо, спрямованні наблизити перемогу нашої країни. Ми розуміємо що кожен повинен бути на своєму місці та робити свою справу краще там де він може. Тому ми знаходячись в Европі готові допомагати в пошуку автомобілів для фронту та доставлення їх на передову. Ми робимо повний супровід документально та прозоро щоб все було просто та зручно і не виникало зайвих запитань. В будь-якому разі ми відповімо на ваші питання та допоможемо знайти технічне справне авто за вашим побажанням яке стане надійним побратимом на фронті.";
$data['main_text_h1']="Послуги які ми робимо для військових";
$data['description']="Послуги які ми робимо для військових. - Загальне розуміння як ми допомогаємо військовим";
$data['title']="Послуги які ми робимо для військових";
$data['keywords']="Послуги які ми робимо для військових";
$smarty->assign("data",$data);

//print_r($_SERVER);
$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);

$smarty->assign("pageView","service");
$smarty->display('index.html');

?>
