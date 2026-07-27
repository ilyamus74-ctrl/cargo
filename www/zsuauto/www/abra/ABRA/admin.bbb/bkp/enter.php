<?
session_start();

//$login=mysql_escape_string($_POST['login']);
//$password=mysql_escape_string($_POST['password']);

echo "$login $password";



//$login =mysql_escape_string($_POST['login']);
//$password = md5(mysql_escape_string($_POST['password']));
//$login=mysql_real_escape_string($login);
$query = "SELECT `id`, `login`, `password` FROM `adminusers` WHERE `login` ='$login' AND `password`='$password' LIMIT 1";
$sql = mysql_query($query) or die(mysql_error());
//$listchpu=mysql_query("SELECT `id`,`idzp`,`chpu` FROM `wh_findzp` WHERE `id`=$to2") or die ("Invalid:" . mysql_error());
	list($id_user)=mysql_fetch_row($sql);
//	while((list($oid,$oidzp,$ochpu)=mysql_fetch_row($listchpu)))



//$result = mysql_result($sql,0,0);
//echo "aa $id_user";

if (mysql_num_rows($sql) == 1)
{
$query = " UPDATE `adminusers` SET `count`=`count`+1 , `last_enter`=now() WHERE `login`='$login' ";
$sql = mysql_query($query) or die(mysql_error());
//session_start();
echo "$query";
$_SESSION['login'] = $login;
$_SESSION['iduser'] = $result; 
$_SESSION['admin'] = $id_user; 
$header='/admin/';
header("refresh: 1; $header");
//$content=<<<content
//Авторизация прошла успешно
//content;
print_r($_SESSION['admin']);
}
else
{
//$header='/admin';
//header("refresh: 2; $header");
//session_unset();
//$content=<<<content
//Неправильное имя или пароль
//content;
}


$smarty->assign(enter,"Входим в систему");
$smarty->display('admin.html');



?>