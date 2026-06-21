<?php
session_start();
include("/home/zsuauto/web/configs/connectDB.php");

if(empty($_SESSION['searchQuery']) || $_SESSION['searchQuery'] == "all"){
$_SESSION['searchQuery']="all";
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'   ORDER BY `id` DESC LIMIT 16";
$preg_list_count ="SELECT COUNT(`id`) FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'  ORDER BY `id` DESC";

//print_r('first');
//print_r($preg_list);
//print_r($preg_list_count);
//print_r('\r\n<br>');
//$_SESSION['searchQuery']['type']="all";
//$_SESSION['searchQuery']['marke']="all";
//$_SESSION['searchQuery']['price']="all";
//$_SESSION['searchQuery']['stan']="all";
}
else{
	    foreach($_SESSION['searchQuery'] as $key=>$item){
		if($key == "price" && $item != "all" ){
		if(empty($addon)) { $addon =" AND `".$key."` <= '".$item."' ";  }
		
		}
		if($key == "marke" && $item != "all" ){
		if(empty($addon2)) { $addon2 =" AND `".$key."` = '".$item."' ";  }
		
		}
		if($key == "type" && $item != "all" ){
		if(empty($addon3)) { $addon3 =" AND `".$key."` = '".$item."' ";  }
		
		}
		if($key == "stan" && $item != "all" ){
		if(empty($addon4)) { $addon4 =" AND `".$key."` = '".$item."' ";  }
		
		}
	    	//if($key == "lastIdCar" && $item != "" ){
		//if(empty($addon5)) { $addon5 =" AND `id` < '".$item."' ";  }
		
		//}
	    }

$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5." ORDER BY `id` DESC LIMIT 16";
$preg_list_count ="SELECT COUNT(`id`) FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5."  ORDER BY `id` DESC";

//print_r('second');
//print_r($preg_list);
//print_r('\r\n<br>');

}
//print_r($preg_list);
//print_r($preg_list_count);
//$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5." ORDER BY `id` DESC LIMIT 8";
//$preg_list_count ="SELECT COUNT(`id`) FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5."  ORDER BY `id` DESC";

//$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'   ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
    $limitText=150;
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	//    if(strlen($idpp['name_announce'])>$limitText) $idpp['name_announce']=substr($idpp['name_announce'],0,$limitText);
//	$idpp['name_announce']=preg_replace("/ціна:.*/","...",$idpp['name_announce']);
//	$idpp['name_announce']=preg_replace("/коробка:.*/","...",$idpp['name_announce']);
	//print_r($fff);
	$allCars[]=$idpp;
	}
$smarty->assign("allCars",$allCars);


$sss1=$dbcnx->query($preg_list_count);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCarsCount=$idpp;
	}
$smarty->assign("allCarsCount",$allCarsCount['COUNT(`id`)']);

if(($allCarsCount['COUNT(`id`)'] - 16) > 0){
    $smarty->assign("allCarsLeft",($allCarsCount['COUNT(`id`)'])-16);
}
//print_r($allCarsCount);

#echo "aaaa";
$smarty->assign("SESSION",$_SESSION);

$data['main_text']="Ми запустили бота який шукає певні марки авто для військових які коштують не всі гроші світу.<br> Бот викладає реальні (дійсні) оголошення автомобілів які можна подивитись та замовити пригон їх з Европи а саме Германії в Україну.";
$data['main_text_h1']="Машини які доступні в продажу для військових";
$data['description']="Машини які доступні в продажу для військових - Загальна база з постійними оновленнями оголошень машин які доступні для військових.";
$data['title']="Машини які доступні в продажу для військових";
$data['keywords']="Машини які доступні в продажу для військових";
$smarty->assign("data",$data);

$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);


$smarty->assign("pageView","allCars");
$smarty->display('index.html');

?>
