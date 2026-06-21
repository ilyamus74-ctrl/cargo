<?php
session_start();
//print_r($_SERVER['GET']);
include_once("setlocale/locale.php");
require_once("../../libs/Smarty.class.php");

include("/home/zsuauto/web/configs/connectDB.php");
$smarty = new \Smarty\Smarty;

require_once("../patch.php");
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;
//$smarty->setErrorReporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$smarty->assign("THEME",$theme);

$smarty->assign("xlang",$_SESSION['locale_c']);
#include_once("config.php");
#include_once("../connect_opp.php");
//print_r($_SERVER);
//print_r($_SESSION);
if(!empty($_SESSION['admin_user']['id'])){
    $smarty->assign("SESSION",$_SESSION);
    $smarty->assign("pageview","viewRequestMsgAll");

    $preg_list="SELECT * FROM `zs_requests`  ORDER BY `id` DESC";


//$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S'   ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allMsg[$idpp['id']]=$idpp;
	}
//print_r($allMsg);
$smarty->assign("allMsg",$allMsg);
$smarty->display('NiceAdmin/index.html');
echo "start";


}
else{
    header("Location: /ABRA");
}

?>
