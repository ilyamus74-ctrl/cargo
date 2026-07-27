#!/usr/bin/php -q
<?
include("/home/infonc4/www/www/settings/connect.php");
$_name_news_table="wh_news_world";
$_name_anonce_table="wh_anonce_in_world";
$_name_to_last="in_world";
$id_main=1;
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


$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img` FROM `$_name_news_table` WHERE `active`='1'   ORDER BY `date_in` ASC ") or die ("Invalid:" . mysql_error());
	while((list($name,$main_url,$url,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img,$preg_text_full,$preg_f_img)=mysql_fetch_row($list_news)))
	{
	echo "$name | $main_url | $url | $preg_one<br>";
	if(!empty($url))
		{ 
		ccurl($url);
		$out_html=ccurl($url);
		//echo "$out_html";
		//echo "$preg_one";
		//preg_match_all('|$preg_one|Uis',$out_html, $ccc);
		//$out_html=str_replace("<![CDATA[","",$out_html);
		//$out_html=str_replace("]]>","",$out_html);
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
		    $anonce_n[1]=html_entity_decode($anonce_n[1]);
		    $name_n[1]=html_entity_decode($name_n[1]);
		    //$anonce_n[1]=str_replace("&amp;ndash;","",$anonce_n[1]);
		    //$name_n[1]=str_replace("&amp;ndash;","",$name_n[1]);
		    ccurl($link_n[1]);
		    $out_html_full=ccurl($link_n[1]);
		    echo "!!!!begin";
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
			
			echo "$item9";
			$item_text_full__=strip_tags((html_entity_decode($item9)));
			$item_text_full__=str_replace("'","",$item_text_full__);
			//echo "$item_text_full__";
			
			}
			foreach($txt_full_n01 as $key19=>$item19)
			{
			//echo "$item19";
			//preg_match('|'.$preg_f_img.'|Uis',$item19, $img_n19);
			/*if(stristr($img_n19[1],"http://") === FALSE)
					{
				if(stristr($img_n19[1],$main_url) === FALSE) { 
									    echo "Не найдено $main_url";  
									    $img_n19[1]="http://$main_url$img_n19[1]";
									    }
					}
					$ext_file19=substr($img_n19[1],-4);//echo $link_n[1];
					echo "FILE  $ext_file19  FILE";
					$new_file19=substr_replace(sha1(microtime(true)), '', 12);
			*/
			//$item_text_full__1=strip_tags((html_entity_decode($item19)));
			//echo "AAA $img_n19[0] BBB $new_file19AAA";
			}
			//print_r($item_n19);
		    //echo "$out_html_full";
		    echo "end!!!!";
		    print_r($link_n[1]);
		    //print_r($anonce_n[1]);
		    //print_r($txt_n[1]);
		    //print_r($img_n[1]);
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
		//		if($size_r[0]>140) $cp=exec("/usr/bin/convert /home/infonc4/www/www/img/news/$new_file$ext_file -resize 140x /home/infonc4/www/www/img/news/$new_file$ext_file");
//preg_replace('|function.*?\}|Uis',$item_text_full__,$item_text_full__1);
//print_r($item_text_full__1);
//echo "$item_text_full__";
$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`)
				 VALUES ('$id_main','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."')") or die("Invalid query: " . mysql_error());
				$llasett=mysql_insert_id();
//echo "$llaset";
//sleep(10);
$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
sleep(10);

				echo "zapisal $name_n[1]\r\n";
				unset($txt_full_n);
				//$img_n19[1]="";
				//$img_n19[0]="";
				}
		    echo "$total";
		    echo "<br>\n\r";
		
		    }
		}
	}

echo "<br>news<br>";

?>