<?
session_start();

require('../../libs/Smarty.class.php');
$smarty = new Smarty;
require('../patch.php');
$theme="../templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
#$smarty->cache_lifetime = 120;
$smarty->assign(THEME,$theme);
$_SESSION['ttturl']="nc4.info";
$smarty->assign(turl,"nc4.info");

//$smarty->display('index.html');

include_once("connect.php");
//print_r($_SERVER);

$login =mysql_escape_string($_POST['login']);
$password = md5(mysql_escape_string($_POST['password']));
//echo "$password";
//exit;


if($_SESSION['admin']==0)
    {
	if(!empty($password) and !empty($login))  {	include("enter.php");	}
	else {include("enter.php");} 
    }

else { include("insystem.php"); }
    

/*
$smarty->assign(see,$rub_test[1]);
$smarty->display('admin.html');
*/

//echo "aaa";
?>
