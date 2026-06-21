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
/*
$data['main_text']="Оскільки ми переважно знаходимося в Европі ми доступні переважно по месенджерах:
<br>Watsapp, Telegram, Viber, Signal за номером телефону<b> +38 063 9607216</b>
<br>Також можна зв'язатися через систему повідомлень сайту натиснувши княпочку «Зв’язатись з нами!»
<br>Альтернативний канал зв'язку це електронна пошта:
<br>Звертайтеся, ми завжди раді допомогти нашим захисникам!";
*/
$data['main_text_h1']="Адмінка";
$data['description']="Адмінка";
//$data['title']="Як з нами зв'язатися? - Волонтери з допомоги купівлі та пригону авто.";
//$data['keywords']="Як з нами зв'язатися? - Волонтери з допомоги купівлі та пригону авто.";
$smarty->assign("data",$data);

$smarty->assign("pageView","ABRA");
$smarty->display('index.html');

?>
