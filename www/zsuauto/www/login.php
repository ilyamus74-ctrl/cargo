<?php
session_start();
include_once("../configs/connectDB.php");

//print_r($_POST);
//echo "login\r\n";
///echo $password = md5("123456");


if($_POST['todo'] == "enter"){

//function get_currency($dbcnx_zalogjoint_opp){
//echo "enter";
    $sql_get="SELECT `id`,`login`,`name` FROM `zs_adminusers` WHERE `login`='".$dbcnx->real_escape_string($_POST['user'])."' AND `password`='".md5($_POST['password'])."' ORDER BY `id` DESC LIMIT 1";
    $sss1=$dbcnx->query($sql_get);
	while($idpp = $sss1->fetch_assoc()){
	$currency=$idpp;
	//print_r($idpp);
	    if($idpp['id'] != 0){
	    $_SESSION['admin_user']=$idpp;
	    $upd="UPDATE zs_adminusers SET `last_enter` = now(),`count`=+1 WHERE `id`='".$idpp['id']."'";
	    $dbcnx->query($upd);
	    header("Location: /abra/index.php");
	    exit;
	    }
	}
	//return $currency;
//}
	    if(empty($_SESSION['admin_user']['id'])){
	    header("Location: /ABRA");
	    }
}
else{
    header("Location: /ABRA");
}

if($_GET['todo'] == "exit"){
    unset($_SESSION);
    header("Location: /ABRA");
}
else{
    header("Location: /ABRA");

}

?>