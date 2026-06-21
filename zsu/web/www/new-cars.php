<?php
session_start();
include("/home/zsuauto/web/configs/connectDB.php");


if(empty($_SESSION['searchQuery']) || $_SESSION['searchQuery'] == "all"){
$_SESSION['searchQuery']="all";
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'  AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY)  ORDER BY `id` DESC ";
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
	    	if($key == "lastIdCar" && $item != "" ){
		if(empty($addon5)) { $addon5 =" AND `id` < '".$item."' ";  }
		
		}
	    }


}
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY) ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5." ORDER BY `id` DESC ";
$preg_list_count ="SELECT COUNT(`id`) FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY) ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5."  ORDER BY `id` DESC";


//$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'   ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCars[]=$idpp;
	}
$smarty->assign("allCars",$allCars);


$sss1=$dbcnx->query($preg_list_count);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCarsCount=$idpp;
	}
$smarty->assign("allCarsCount",$allCarsCount['COUNT(`id`)']);

if(($allCarsCount['COUNT(`id`)'] - 8) > 0){
    $smarty->assign("allCarsLeft",($allCarsCount['COUNT(`id`)'])-8);
}

/*
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'  AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY)  ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$newCars[]=$idpp;
	}
$smarty->assign("newCars",$newCars);
*/
$smarty->assign("SESSION",$_SESSION);
$data['main_text']="Саме нові надходження об’яв з Европейських майданчиків автомобілів доступних на продаж від власників. Всі автомобілі технічно справні та можуть пересуватися своїм ходом.";
$data['main_text_h1']="Нові авто які з'явилися в продажу ЕС за останній тиждень";
$data['description']="Нові авто які з'явилися в продажу ЕС за останній тиждень - Нові оголошення з продажу автівок для військових";
$data['title']="Нові авто які з'явилися в продажу ЕС за останній тиждень";
$data['keywords']="Нові авто які з'явилися в продажу ЕС за останній тиждень";
$smarty->assign("data",$data);
$smarty->assign("pageView","newCars");

$smarty->assign("reqUrl","https://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL']);

$smarty->display('index.html');

?>
