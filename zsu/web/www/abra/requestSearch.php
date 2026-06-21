<?php
session_start();


//echo "requestus\r\n";
//echo "post\r\n";
//print_r($_POST);
//echo "session\r\n";
//print_r($_SESSION);

if(!empty($_POST['secCode'])){
include("/home/zsuauto/web/configs/connectDB.php");
require_once("../libs/Smarty.class.php");
//$smarty = new Smarty;
$smarty = new \Smarty\Smarty;
require_once("patch.php");
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;

//echo "в обработке";
//if($_POST['searchMoreAllCars']!="searchMoreAllCars"){
	if(!empty($_POST['price']) && !empty($_POST['marke']) && !empty($_POST['stan']) && !empty($_POST['type'])){
	    $_SESSION['searchQuery']=$_POST;
	    foreach($_POST as $key=>$item){
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
		if(empty($addon5)) { $addon5 =" AND `id` > '".$item."' ";  }
		
		}
	    }
	}
//    }
    $preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5." ORDER BY `id` ASC LIMIT 8";
    $preg_list_count ="SELECT COUNT(`id`) FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' ".$addon." ".$addon2." ".$addon3." ".$addon4." ".$addon5."  ORDER BY `id` ASC";

    //$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'   ORDER BY `id` DESC LIMIT 8";
    $sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCars[$idpp['id']]=$idpp;
	}

    $sss1=$dbcnx->query($preg_list_count);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCarsCount=$idpp;
	}

if(($allCarsCount['COUNT(`id`)'] - 8) > 0){
    $smarty->assign("allCarsLeft",($allCarsCount['COUNT(`id`)'])-8);
}

//$smarty->setErrorReporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$smarty->assign("THEME",$theme);
$smarty->assign("allCars",$allCars);
$smarty->assign("allCarsCount",$allCarsCount['COUNT(`id`)']);

$domainName="zsuauto.info";
$smarty->assign("domainName",$domainName);

if(!empty($_POST['lastIdCar'])){
$smarty->display('singleAllCars2.html');
}
else{
$smarty->display('singleAllCars.html');
}

}
else{
echo "error secCode";
}

//print_r($_SESSION);
//print_r($_POST);
//print_r($_GET);
//print_r($_SERVER);
?>