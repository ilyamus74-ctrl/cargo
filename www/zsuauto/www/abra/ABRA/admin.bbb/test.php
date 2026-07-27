#!/usr/bin/php -q
<?
include("/home/infonc4/www/www/settings/connect.php");
include_once("srav.php");
include_once("sde.php");
//include_once("cod_detected.php");

$view_id=mysql_escape_string($_POST['id']);
$id_wplang=mysql_escape_string($_POST['id_wplang']);
$table=mysql_escape_string($_POST['table']);
$id_main=$view_id;
//echo "$id_wplang";
/*
$_name_news_table="wh_news_business";
$_name_anonce_table="wh_anonce_business";
$_name_to_last="business";
$id_main=2;
*/
$headers1 = array(
"GET /HTTP/1.1",
"User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1",
"Content-type: text/xml;charset=\"utf-8\"",
"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",            "Accept-Language: en-us,en;q=0.5",
"Accept-Encoding: gzip,deflate",
"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
"Keep-Alive: 300",      
"Connection: keep-alive",
"Authorization: Basic " . base64_encode($credentials));
function ccurl($url)
    {
    //echo "$url";
    $curl = curl_init();
     curl_setopt($curl, CURLOPT_COOKIESESSION, true); 
     curl_setopt($curl, CURLOPT_COOKIEFILE, "cookiefile"); 
     curl_setopt($curl, CURLOPT_COOKIEJAR, "cookiejar"); 
     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
     //curl_setopt($curl, CURLOPT_FAILONERROR, true);
     //curl_setopt($curl, CURLOPT_AUTOREFERER, true); 
     curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
     //curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3'); 
     curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3'); 
     curl_setopt($ch, CURLOPT_TIMEOUT, 60);
     //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers1);
     curl_setopt($curl, CURLOPT_URL, $url); 
     $html = curl_exec($curl);
    return "$html";
//	$ccc1=$ccc[0];
    }


echo "<br>Начало БОТА<br>\r\n";
$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`rss`,`charset` FROM `$table` WHERE `id_wp`='$view_id' AND `lang`='$id_wplang'") or die ("Invalid:" . mysql_error());
//$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`rss` FROM `$_name_news_table` WHERE `active`=1   ORDER BY `date_in` ASC ") or die ("Invalid:" . mysql_error());
	while((list($name,$main_url,$url,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img,$preg_text_full,$preg_f_img,$preg_video,$rss,$charset)=mysql_fetch_row($list_news)))
    {
	//echo "$name $url $preg_one";
	if(!empty($url))
	{ 
		//ccurl($url);
		$out_html=ccurl($url);
		$cdata=simplexml_load_string($out_html);//work_parse_xml
	if(!empty($cdata) or !empty($rss)) { include("rss.php"); }
	else   {
	
	//$two_img[];
	//preg_match_all('|'.$preg_text_full.'|Uis',$out_html,$two_img);
	echo "<strong><b>NO RSS</b></strong><br>\r\n";
		preg_match_all('|'.$preg_one.'|Uis',$out_html, $ccc);
		//print_r($ccc);
		foreach($ccc[1] as $key=>$item)
		    {
		    preg_match('|'.$preg_link.'|Uis',$item, $link_n);
		    
		    if(stristr($link_n[1],$main_url) === FALSE) { 
								if(stristr($link_n[1],"http://") === FALSE) {
								//$link_n[1]=str_replace("http://","",$link_n[1]);
								$link_n[1]="http://$main_url$link_n[1]";
								}
								}
		    preg_match('|'.$preg_name.'|Uis',$item, $name_n);
		    $name_n[1]=trim($name_n[1]);
		    if(!empty($charset)) $name_n[1]=iconv($charset,"utf-8",$name_n[1]); 
		    preg_match('|'.$preg_anonce.'|Uis',$item, $anonce_n);
		    $anonce_n[1]=trim(str_replace("\r\n","",$anonce_n[1]));
		    //$anonce_n[1]=strip_tags($anonce_n[1]);
		    //preg_match('|'.$preg_img.'|Uis',$item, $img_n);
		    preg_match('|'.$preg_txt.'|Uis',$item, $txt_n);
		    preg_match('|'.$preg_img.'|Uis',$item, $img_n);
		    print_r($img_n[1]);
			//$HTTP=array(1=>"http://","https://");
			if(stristr($img_n[1],"http") === FALSE )
			//if(stristr($img_n[1],"http://")  === FALSE)
					{
				if(stristr($img_n[1],$main_url) === FALSE) { 
									    //echo "Не найдено $main_url";  
									    $img_n[1]="http://$main_url$img_n[1]";
									    }
					}
					    $ext_file=substr($img_n[1],-4);//echo $link_n[1];
					    //echo "<b><i>$ext_file</i><b>";
					    $ext_file=mb_strtolower($ext_file);
					    if($ext_file=="jpeg") $ext_file=".jpg";
					    $new_file=substr_replace(sha1(microtime(true)), '', 12);
			}
			//dobavil
			
				if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" ){
								$img_n[1]="";
										}
			//dobavil
			//file_put_contents("/home/pafin/www/www/img/news/$new_file$ext_file", file_get_contents($img_n[1]));
		//    print_r($link_n[1]);
		    $anonce_n[1]=html_entity_decode($anonce_n[1]);
		    $name_n[1]=html_entity_decode($name_n[1]);
		    echo mb_detect_encoding($name_n[1],"auto");
		    //
		    //echo $name_n[1]=iconv("windows-1251","utf-8",$name_n[1]);
		    //ccurl($link_n[1]);
		    if(preg_match('#[а-яё]+#i',$link_n[1])) {$link_n[1]=my_url_encode($link_n[1]); echo "$link_n_[1]";}
		    $out_html_full=ccurl($link_n[1]);
		//    echo "!!!!begin";
		    //echo "$preg_text_full";
		    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
		    //print_r($txt_full_n);
		    $txt_full_n1=$txt_full_n[1];
		    $txt_full_n01=$txt_full_n[0];
		    //echo "$txt_full_n12";
		    $txt_full_n11=strip_tags($txt_full_n1,"<a>");
		    $txt_full_n11=trim($txt_full_n11);
		    
		    //print_r($txt_full_n11);
		    foreach($txt_full_n1 as $key9=>$item9)
			{
			$item9=preg_replace('|<script[^>]*?>.*?</script>|si','',$item9);
			//$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item9);
			//$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
		//	echo "$item9";
			$item_text_full__=strip_tags((html_entity_decode($item9)));
			$item_text_full__=str_replace("'","",$item_text_full__);
			//$item_text_full__=trim($item_text_full__);
			}//
			if(!empty($charset)) $item_text_full__=iconv($charset,"utf-8",$item_text_full__);
			//print_r($out_html_full);
				if(empty($img_n[1])) 	{
				preg_match('|'.$preg_f_img.'|Uis',$out_html_full,$img_BIG); //rabotaet
				if(stristr($img_BIG[1],"http://") === FALSE)
					{
				if(stristr($img_BIG[1],$main_url) === FALSE) { 
									    echo "Не найдено $main_url";  
									    $img_BIG[1]="http://$main_url$img_BIG[1]";
									    }
					}
					$ext_file_BIG=substr($img_BIG[1],-4);//echo $link_n[1];
					//echo "$ext_file";
					//$new_file=substr_replace(sha1(microtime(true)), '', 12);
				
				$img_n[1]=$img_BIG[1];
				$ext_file=substr($img_n[1],-4); }
			//include("srav.php");
			//echo "";
			//know charset begin
			/*$chch1="; charset=\"(.*)\"";
			$chch2="; charset='(.*)'";
			//$chch3='; harset=(.*)'';
			$chch4="; charset=(.*)\"";
			preg_match('|'.$chch1.'|Uis',$out_html,$charset);
			if(empty($charset[1])) preg_match('|'.$chch2.'|Uis',$out_html_full,$charset);
			if(empty($charset[1])) preg_match('|'.$chch3.'|Uis',$out_html_full,$charset);
			if(empty($charset[1])) preg_match('|'.$chch4.'|Uis',$out_html_full,$charset);
			print_r($charset);
			//know charset end*/
			//echo $coDD=detect_encoding($name_n[1]);
			preg_match('|'.$preg_video.'|Uis',$out_html_full, $videos);
			$iframe_chek=substr($videos[1], -3);
			if($iframe_chek!=".js"){
			if(!empty($videos[1])) $vidos="<iframe src=\"$videos[1]\" frameborder=\"0\" height=\"100%\" width=\"100%\"></iframe>";
			else  $vidos="";	}
			//$videoos="$videos[1]";
			//$vidos="<iframe src='$videoos' frameborder=\"0\" height=\"100%\" width=\"100%\"></iframe>";
			//echo "$vidos";
			 preg_match( '|([\w/+]+)(;\s*charset=(\S+))?|i', $out_html_full, $charset );
			 print_r($charset);
			  
			$out22=srav("$name_n[1]","$item_text_full__");
			echo "<b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n <b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n
			<b>IMG_LINK_BIG:</b>$img_BIG[1]<br>\r\n
			<a href=\"$img_n[1]\" target=\"_blank\">ПРОВЕРКА КАРТИНКИ</a><br>\r\n
			<b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\nСовпадение<b>$out22</b><br>\r\n
			<b>VIDEO:</b>$vidos<br>\r\n";
			echo "##########################################################";
		//    echo "end!!!!";
		/*
		    print_r($link_n[1]);
		    $test_b=mysql_query("SELECT `name_news` FROM `$_name_anonce_table` WHERE `url_news`='$link_n[1]' LIMIT 1 ") or die ("Invalid:" . mysql_error());
		    $total=mysql_num_rows($test_b);
			if($total==0)
			    {
			if(!empty($img_n19[1])) $put=exec("/usr/bin/wget $img_n19[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file");
			else { $put=exec("/usr/bin/wget $img_n[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file"); }
			sleep (1);
			chmod("/home/infonc4/www/www/img/news/$new_file$ext_file",0644);
			$size_r = getimagesize("/home/infonc4/www/www/img/news/$new_file$ext_file");
			print_r ($size_r);
			echo "<br>$size_r[0]<br>";
			$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`) VALUES ('$id_main','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."')") or die("Invalid query: " . mysql_error());
			$llasett=mysql_insert_id();
			$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
			$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
	//sleep(10);
		*/
			    
				unset($llasett);
				unset($txt_full_n);
				unset($total);
			    }
			
			unset($llasett);
			unset($txt_full_n);
			unset($total);
	    }
	}
echo "<br>Конец БОТА<br>\r\n";

?>