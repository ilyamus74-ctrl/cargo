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
$data['main_text']="<p>
<br>На питання \"Звідки дешевше пригнати авто?\" Немає сто відсоткової відповіді. Хтось готовий подарувати авто для військових, а іноді просять сумму начеб то авто тільки з конвееру заводу виїхало. Можно з певністью сказати одне, що добре авто в гарному технічному стані буде коштуватиме дорожче навідь в самій дешевій країні Европи, в такомц разі не соромно купити авто для військових. Тим паче якщо є змога купити авто для ЗСУ недорого відповідно стану.
<br> 
<br>Пригон авто для військових зазвичай триває від декількох діб до одного тижня. Процедура оформлення залежить від часу оформлення документів та відстані до кордону. Чим більша відстань тим довше їхати. Зазвичай це 1000км за 10 годин. Тут треба робити поправки на черги на кордоні та час відпочинку в дорозі.
</p>";
$data['main_text_h1']="Пригон авто для військових";
$data['description']="Пригон авто для військових - Просте пояснення процедури оформлення , домовленностей поміж покупцем так власником авто при оформлення угоди з авто для військового.";
$data['title']="Пригон авто для військових";
$data['keywords']="Пригон авто для військових";
$smarty->assign("data",$data);
$smarty->assign("pageView","prigon-avto-dlya-viyskovih.html");
$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);

//$smarty->display("index.tpl");
$smarty->display("index.html");

?>
