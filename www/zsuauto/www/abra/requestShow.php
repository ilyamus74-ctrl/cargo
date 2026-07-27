<?php
session_start();


//echo "requestus\r\n";
//echo "post\r\n";
//print_r($_POST);
//echo "session\r\n";
//print_r($_SESSION);


if(!empty($_POST['showCar'])){
include("/home/zsuauto/web/configs/connectDB.php");
require_once("../../libs/Smarty.class.php");
//$smarty = new Smarty;
$smarty = new \Smarty\Smarty;
require_once("../patch.php");
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;



$preg_list="SELECT * FROM `zs_announce_auto` WHERE  `img_dir` = '".$_POST['showCar']."'";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$viewCar=$idpp;
	}
//	print_r($viewCar);
$smarty->assign("viewCar",$viewCar);
//$smarty->setErrorReporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$smarty->assign("THEME",$theme);
$smarty->assign("viewCars",$viewCars);

$domainName="zsuauto.info";
$smarty->assign("domainName",$domainName);
$smarty->display('NiceAdmin/viewNewAnnounceInnerModal.html');

}

//print_r($_POST);
/*
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
*/
//print_r($_SESSION);
//print_r($_POST);
//print_r($_GET);
//print_r($_SERVER);
?>