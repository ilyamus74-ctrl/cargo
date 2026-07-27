<?php
session_start();
include("/home/zsuauto/web/configs/connectDB.php");


$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY)  ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$newCars[]=$idpp;
	}

$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE `active_announce` != 'S'  ORDER BY `id` ASC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCars[]=$idpp;
	}

$smarty->assign("newCars",$newCars);
$smarty->assign("allCars",$allCars);
$data['main_text']="Цей ресурс саме для тебе! Ми шукаємо та викладаємо надійні, технічно справні та доступні авто з Европи за адекватні кошти.<br>Є питання ?";
$data['main_text_h1']="Ти військовий та тобі потрібно авто для бойових завдань, але не за всі кошти світу?";
$data['description']="Допомога в пошуку, придбання, та пригону авто для віськових на фронт від волонтерів.";
$data['title']="Допомога в пошуку, придбання, та пригону авто для віськових на фронт від волонтерів";
$data['keywords']="Купити авто для ЗСУ недорого";
$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);

//$smarty->assign("SESSION",$_SESSION);
$smarty->assign("data",$data);
$smarty->display('index.html');

?>
