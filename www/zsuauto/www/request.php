<?php
session_start();


//echo "requestus\r\n";
//echo "post\r\n";
//print_r($_POST);
//echo "session\r\n";
//print_r($_SESSION);

if($_POST['secCode'] == $_SESSION['secCode']){
include("/home/zsuauto/web/configs/connectDB.php");

//echo "в обработке";
$_SESSION['secCode'] = md5(microtime(true));
		$insert="INSERT INTO `zs_requests` (`lotImgDir`,`name`,`phone`,`date`) VALUES ('".$dbcnx->real_escape_string($_POST['car'])."','".$dbcnx->real_escape_string($_POST['name'])."','".$dbcnx->real_escape_string($_POST['phone'])."',now())";
		$dbcnx->query($insert);
//echo "в обработке";
print_r($_SESSION['secCode']);
}
else{
echo "error secCode";
}

//print_r($_SESSION);
//print_r($_POST);
//print_r($_GET);
//print_r($_SERVER);
?>