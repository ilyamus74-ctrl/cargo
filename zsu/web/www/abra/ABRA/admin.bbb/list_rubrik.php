<?
session_start();

//print_r($_SERVER['POST']);
$i_preg_table=mysql_escape_string($_POST['preg_table']);
$i_url_link=mysql_escape_string($_POST['url_link']);
$i_main_link=mysql_escape_string($_POST['main_link']);
$i_preg_one=mysql_escape_string($_POST['preg_one']);
$i_preg_link=mysql_escape_string($_POST['preg_link']);
$i_preg_name=mysql_escape_string($_POST['preg_name']);
$i_preg_anonce=mysql_escape_string($_POST['preg_anonce']);
$i_preg_img=mysql_escape_string($_POST['preg_img']);
$i_preg_text=mysql_escape_string($_POST['preg_text']);
$i_preg_f_img=mysql_escape_string($_POST['preg_f_img']);
$i_preg_video=mysql_escape_string($_POST['preg_video']);
$i_view_wp=mysql_escape_string($_POST['i_view_wp']);
$i_active=mysql_escape_string($_POST['active_']);
$i_rss=mysql_escape_string($_POST['rss_']);
$i_charset=mysql_escape_string($_POST['charset']);

$i_save_sudmit=mysql_escape_string($_POST['save']);
$i_delete_sudmit=mysql_escape_string($_POST['delete']);
//echo "$i_save_sudmit $i_delete_sudmit $i_active";
$id_wplang=mysql_escape_string($_POST['id_wplang']);
if(empty($id_wplang)) $id_wplang=1;
echo "$id_wplang - $i_view_wp";
//save_update
if(!empty($i_save_sudmit) and !empty($i_view_wp) and !empty($i_preg_table)) 
	{
	$sql_check="SELECT `id`,`id_wp` FROM `$i_preg_table` WHERE `id_wp`='$i_view_wp' AND `lang`='$id_wplang'";
	$sql_ch=mysql_query($sql_check) or die ("Invalid:" . mysql_error());
	list($id_ch,$id_wp_ch)=mysql_fetch_row($sql_ch);
		if(empty($id_wp_ch)) 
				{
				$sql_name_wp="SELECT `name` FROM `wh_wp_list` WHERE `id`='$i_view_wp'";
				$sql_name=mysql_query($sql_name_wp) or die ("Invalid:" . mysql_error());
				list($name_)=mysql_fetch_row($sql_name);
				//$name_=2;
				$sql_i="INSERT INTO $i_preg_table (`id_wp`,`name`,`main_url`,`url`,`date_in`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`active`,`rss`,`charset`) VALUES ('$i_view_wp','$name_','$i_main_link','$i_url_link',now(),'$i_preg_one','$i_preg_link','$i_preg_name','$i_preg_anonce','$i_preg_img','$i_preg_text','$i_preg_f_img','$i_preg_video','$id_wplang','$i_active','$i_rss','$i_charset')";
				$in_new_wp=mysql_query("$sql_i") or die("Invalid query: " . mysql_error());
				//echo "insert $i_preg_table $sql_i";
				}
			    else {
				//echo"update";
				$sql_upd=mysql_query("UPDATE $i_preg_table SET `main_url`='$i_main_link',`url`='$i_url_link',`preg_one`='$i_preg_one',`preg_link`='$i_preg_link',`preg_name`='$i_preg_name',`preg_anonce`='$i_preg_anonce',`preg_img`='$i_preg_img',`preg_text`='$i_preg_text',`preg_f_img`='$i_preg_f_img',`preg_video`='$i_preg_video',`active`='$i_active',`rss`='$i_rss',`charset`='$i_charset' WHERE `id_wp`='$i_view_wp' AND `lang`='$id_wplang' ") or die ("Invalid query: " . mysql_error());
				}
	$view_wp=$i_view_wp;
	}
//delete 
if(!empty($i_delete_sudmit) and !empty($i_view_wp) and !empty($i_preg_table)) {
						$sql_del ="DELETE FROM $i_preg_table WHERE `id_wp`='$i_view_wp'";
	    					$sql_del = mysql_query($sql_del) or die(mysql_error());
	    					$view_wp=$i_view_wp;
										}


$sql_show="SHOW TABLES FROM $dbname";
$result = mysql_query($sql_show);
//list_rubrik_for_edit
while ($row = mysql_fetch_row($result)) {
    //echo "Таблица: {$row[0]}\n";
	if(stristr($row[0], "wh_news_") == TRUE) //сверка с таблицам
	{
	//	$qquurr="SELECT `id`,`name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`active`,`rss`,`charset` FROM `$row[0]` WHERE `id_wp`='$view_wp'";
		$qquurr="SELECT `id`,`name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`active`,`rss`,`charset` FROM `$row[0]` WHERE `id_wp`='$view_wp' AND `lang`='$id_wplang'";
		//echo "$qquurr";
		$sql_list_wwp=mysql_query($qquurr) or die ("Invalid:" . mysql_error());
		list($id_wh,$name_wh,$main_url,$url_wh,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img,$preg_text,$preg_f_img,$preg_video,$lang,$active,$rss,$charset)=mysql_fetch_row($sql_list_wwp);
			$qq_lang="SELECT `lang_name` FROM `wh_lang` WHERE `id_lang`='$lang'";
			$sql_lang_s=mysql_query($qq_lang) or die ("Invalid:" . mysql_error());
			list($name_lang)=mysql_fetch_row($sql_lang_s);
		
		$list_preg['preg_one']=$preg_one;
		$list_preg['preg_link']=$preg_link;
		$list_preg['preg_anonce']=$preg_anonce;
		$list_preg['preg_name']=$preg_name;
		$list_preg['preg_img']=$preg_img;
		$list_preg['preg_text']=$preg_text;
		$list_preg['preg_f_img']=$preg_f_img;
		$list_preg['main_url']=$main_url;
		$list_preg['url_wh']=$url_wh;
		$list_preg['name_wh']=$name_wh;
		$list_preg['id_wh']=$id_wh;
		$list_preg['preg_video']=$preg_video;
		$list_preg['active']=$active;
		$list_preg['rss']=$rss;
		$list_preg['charset']=$charset;
		$list_preg['id_lang']=$lang;
		$list_preg['name_lang']=$name_lang;
		$list_preg['table']=$row[0];
		$list_preg['id_wplang']=$id_wplang;
		
		$list_db_preg[]=$list_preg;
		$smarty->assign(list_db_preg,$list_db_preg);
		$smarty->assign(view_wp,$view_wp);
		//echo "$name_wh $main_url<br><textarea>$preg_one</textarea>";

	//echo "$row[0]\n\r";
	}
}

//список доступных языков
$qq_lang_all="SELECT `lang_name`,`id_lang` FROM `wh_lang`";
$sql_lang_sall=mysql_query($qq_lang_all) or die ("Invalid:" . mysql_error());
while((list($name_lang_all,$id_all_lang)=mysql_fetch_row($sql_lang_sall)))
{
    $all_lang_list['name_lang_all']=$name_lang_all;
    $all_lang_list['id_all_lang']=$id_all_lang;
    $all_list_lang[]=$all_lang_list;
    $smarty->assign(all_list_lang,$all_list_lang);
//echo "$name_lang_all<br>";
}
//cсписок доступных языков

?>