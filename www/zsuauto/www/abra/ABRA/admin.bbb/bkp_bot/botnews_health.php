#!/usr/bin/php -q
<?
include("/home/infonc4/www/www/settings/connect.php");
/*
$view_id=mysql_escape_string($_POST['id']);
$table=mysql_escape_string($_POST['table']);
*/
$_name_news_table="wh_news_health";
$_name_anonce_table="wh_anonce_health";
$_name_to_last="health";
$id_main=6;

function ccurl($url)
    {
    //echo "$url";
    $curl = curl_init();
     curl_setopt($curl, CURLOPT_COOKIESESSION, true); 
     curl_setopt($curl, CURLOPT_COOKIEFILE, "cookiefile"); 
     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
     curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
     curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3'); 
     curl_setopt($curl, CURLOPT_URL, $url); 
     $html = curl_exec($curl);
    return "$html";
//	$ccc1=$ccc[0];
    }


echo "<br>Начало БОТА<br>\r\n";
//$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`rss` FROM `$table` WHERE `id_wp`='$view_id' ") or die ("Invalid:" . mysql_error());
$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`rss` FROM `$_name_news_table` WHERE `active`=1   ORDER BY `date_in` ASC ") or die ("Invalid:" . mysql_error());
	while((list($name,$main_url,$url,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img,$preg_text_full,$preg_f_img,$rss)=mysql_fetch_row($list_news)))
    {
	//echo "$name $url $preg_one";
	if(!empty($url))
	{ 
		//ccurl($url);
		$out_html=ccurl($url);
		$cdata=simplexml_load_string($out_html);//work_parse_xml
	if(!empty($cdata) or !empty($rss)) { include("rss_.php"); }
	else   {
	echo "<strong><b>NO RSS</b></strong><br>\r\n";
		preg_match_all('|'.$preg_one.'|Uis',$out_html, $ccc);
		//print_r($ccc);
		foreach($ccc[0] as $key=>$item)
		    {
		    preg_match('|'.$preg_link.'|Uis',$item, $link_n);
		    if(stristr($link_n[1],$main_url) === FALSE) { 
								if(stristr($link_n[1],"http://") === FALSE) {
								//$link_n[1]=str_replace("http://","",$link_n[1]);
								$link_n[1]="http://$main_url$link_n[1]";
								}
								}
		    preg_match('|'.$preg_name.'|Uis',$item, $name_n);
		    preg_match('|'.$preg_anonce.'|Uis',$item, $anonce_n);
		    $anonce_n[1]=trim(str_replace("\r\n","",$anonce_n[1]));
		    //preg_match('|'.$preg_img.'|Uis',$item, $img_n);
		    preg_match('|'.$preg_txt.'|Uis',$item, $txt_n);
		    preg_match('|'.$preg_img.'|Uis',$item, $img_n);
			if(stristr($img_n[1],"http://") === FALSE)
					{
				if(stristr($img_n[1],$main_url) === FALSE) { 
									    //echo "Не найдено $main_url";  
									    $img_n[1]="http://$main_url$img_n[1]";
									    }
					}
									    $ext_file=substr($img_n[1],-4);//echo $link_n[1];
									    //echo "$ext_file";
									    $new_file=substr_replace(sha1(microtime(true)), '', 12);
			}		//file_put_contents("/home/pafin/www/www/img/news/$new_file$ext_file", file_get_contents($img_n[1]));
		//    print_r($link_n[1]);
		    $anonce_n[1]=html_entity_decode($anonce_n[1]);
		    $name_n[1]=html_entity_decode($name_n[1]);
		    //ccurl($link_n[1]);
		    $out_html_full=ccurl($link_n[1]);
		//    echo "!!!!begin";
		    //echo "$preg_text_full";
		    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
		    //print_r($txt_full_n);
		    $txt_full_n1=$txt_full_n[1];
		    $txt_full_n01=$txt_full_n[0];
		    //echo "$txt_full_n01";
		    $txt_full_n11=strip_tags($txt_full_n1,"<a>");
		    //print_r($txt_full_n11);
		    foreach($txt_full_n1 as $key9=>$item9)
			{
			$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item9);
			$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
		//	echo "$item9";
			$item_text_full__=strip_tags((html_entity_decode($item9)));
			$item_text_full__=str_replace("'","",$item_text_full__);
			}//
			echo "<b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n <b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n<b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\n";
			echo "##########################################################";
		//    echo "end!!!!";
		
		//    print_r($link_n[1]);
		    $test_b=mysql_query("SELECT `name_news` FROM `$_name_anonce_table` WHERE `url_news`='$link_n[1]' LIMIT 1 ") or die ("Invalid:" . mysql_error());
		    $total=mysql_num_rows($test_b);
			if($total==0)
			    {
			if(!empty($img_n19[1])) $put=exec("/usr/bin/wget $img_n19[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file");
			else { $put=exec("/usr/bin/wget $img_n[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file"); }
			//sleep (5);
			chmod("/home/infonc4/www/www/img/news/$new_file$ext_file",0644);
			chmod("/home/infonc4/www/www/img/news/",0755);

			$size_r = getimagesize("/home/infonc4/www/www/img/news/$new_file$ext_file");
			print_r ($size_r);
		//	echo "<br>$size_r[0]<br>";
			$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`) VALUES ('$id_main','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."')") or die("Invalid query: " . mysql_error());
			$llasett=mysql_insert_id();
			$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
			$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
		//	sleep(10);		
			    }
		//		unset($llasett);
		//		unset($txt_full_n);
		//		unset($total);
			    }
			//unset($llasett);
			//unset($txt_full_n);
			//unset($total);
	    }
	}
echo "<br>Конец БОТА<br>\r\n";

?>