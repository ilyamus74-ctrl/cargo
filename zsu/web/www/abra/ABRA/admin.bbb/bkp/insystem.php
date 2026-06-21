<?
session_start();
//echo "aaaaa";
$view_wp=mysql_escape_string($_POST['view_wp']);

//print_r($_SERVER);
//if($view_wp>0)

include("list_rubrik.php");

$sql_list_wp=mysql_query("SELECT `id`,`id_wp`,`name`,`url`,`lang`,`country` FROM `wh_wp_list") or die ("Invalid:" . mysql_error());
	//list($id_w,$id_wp,$name,$$url,$lang,$county)=mysql_fetch_row($list_wp);
	while((list($id_w,$id_wp,$name,$url,$lang,$county)=mysql_fetch_row($sql_list_wp)))
	{
	$list_w['id_w']=$id_w;
	$list_w['id_wp']=$id_wp;
	$list_w['name']=$name;
	$list_w['url']=$url;
	$list_w['lang']=$lang;
	$list_w['country']=$country;
	//echo "$id_w,$id_wp,$name,$url,$lang,$county";
	$list_wp[]=$list_w;
	}
	


$smarty->assign(list_wp,$list_wp);
$smarty->assign(insystem,"Вы в системе!");
$smarty->display('admin.html');



?>