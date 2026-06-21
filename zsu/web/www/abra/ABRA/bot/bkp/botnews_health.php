#!/usr/bin/php -q
<?
include("/home/infonc4/www/www/settings/connect.php");

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


$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img` FROM `wh_news_health`  ORDER BY `date_in` ASC ") or die ("Invalid:" . mysql_error());
	while((list($name,$main_url,$url,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img)=mysql_fetch_row($list_news)))
	{
	//echo "$name $url $preg_one";
	if(!empty($url))
		{ 
		ccurl($url);
		$out_html=ccurl($url);
		//echo "$preg_one";
		//preg_match_all('|$preg_one|Uis',$out_html, $ccc);
		preg_match_all('|'.$preg_one.'|Uis',$out_html, $ccc);
		//print_r($ccc);
		foreach($ccc[0] as $key=>$item)
		    {
		    preg_match('|'.$preg_link.'|Uis',$item, $link_n);
		    if(stristr($link_n[1],$main_url) === FALSE) { 
								//$link_n[1]=str_replace("http://","",$link_n[1]);
								$link_n[1]="http://$main_url$link_n[1]";
								echo $link_n[1];
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
					//file_put_contents("/home/pafin/www/www/img/news/$new_file$ext_file", file_get_contents($img_n[1]));
					echo "$img_n[1]<br>";
		    print_r($link_n[1]);
		    $anonce_n[1]=str_replace("&amp;ndash;","",$anonce_n[1]);
		    $name_n[1]=str_replace("&amp;ndash;","",$name_n[1]);
		    //print_r($name_n[1]);
		    //print_r($anonce_n[1]);
		    //print_r($txt_n[1]);
		    //print_r($img_n[1]);
		    $test_b=mysql_query("SELECT `name_news` FROM `wh_anonce_health` WHERE `url_news`='$link_n[1]' LIMIT 1 ") or die ("Invalid:" . mysql_error());
		    $total=mysql_num_rows($test_b);
				if($total==0)
				{
				$put=exec("/usr/bin/wget $img_n[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file");
				sleep (1);
				chmod("/home/infonc4/www/www/img/news/$new_file$ext_file",0644);
				$size_r = getimagesize("/home/infonc4/www/www/img/news/$new_file$ext_file");
				print_r ($size_r);
				echo "<br>$size_r[0]<br>";
				if($size_r[0]>140) $cp=exec("/usr/bin/convert /home/infonc4/www/www/img/news/$new_file$ext_file -resize 140x /home/infonc4/www/www/img/news/$new_file$ext_file");
$in_news=mysql_query("INSERT INTO `wh_anonce_health` (`name_news`,`url_news`,`text_news`,`img_news`,`date_in_news`,`name_r`)
				 VALUES ('$name_n[1]','$link_n[1]','$anonce_n[1]','/img/news/$new_file$ext_file',now(),'$name')") or die("Invalid query: " . mysql_error());
				$llasett=mysql_insert_id();
//echo "$llaset";
//sleep(10);
$upd_news_id=mysql_query("UPDATE wh_anonce_health SET id_news='$llasett' WHERE id='$llasett' ") or die ("Invalid query: " . mysql_error());
				echo "zapisal $name_n[1]\r\n";
				}
		    echo "$total";
		    echo "<br>\n\r";
		
		    }
		}
	}

echo "<br>news<br>";

?>