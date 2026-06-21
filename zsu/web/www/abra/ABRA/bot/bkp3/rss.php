<?
//session_start();

//print_r($cdata);
//echo trim($cdata[0]->channel);
/*
echo $cdata->channel->item->title."<br>";
echo $cdata->channel->item->link."<br>";
echo $cdata->channel->item->description."<br>";
echo $cdata->channel->item->fulltext."<br>";
*/

foreach($cdata->channel->item as $item)
    {
	$link_n[1]=$item->link;
	    if(stristr($link_n[1],$main_url) === FALSE) {
			//$link_n[1]=str_replace("http://","",$link_n[1]);
			$link_n[1]="http://$main_url$link_n[1]";
		//	echo $link_n[1];
			}
	$item_img=$item->description;
	    preg_match('|'.$preg_img.'|Uis',$item_img, $img_n);
			if(stristr($img_n[1],"http://") === FALSE)
					{
				if(stristr($img_n[1],$main_url) === FALSE) { 
									    //echo "Не найдено $main_url";  
									    $img_n[1]="http://$main_url$img_n[1]";
									    }
					}
					$ext_file=substr($img_n[1],-4);//echo $link_n[1];
					//echo "$ext_file\r\n\r\n";
					$new_file=substr_replace(sha1(microtime(true)), '', 12);
	
	if($rss==$item->category)
	{
	$name_n[1]=$item->title;
				if(empty($item->fulltext)) {
				    $anonce_n[1]=html_entity_decode($item->description);
				    $anonce_n[1]=strip_tags($anonce_n[1],"<a>");
				    $out_html_full=ccurl($item->link);
				    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
				    foreach($txt_full_n[1] as $key9=>$item9) {
				    $txt_full_n1=strip_tags($item9,"<a>");
				    $item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$txt_full_n1);
				    $item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
				    $item_text_full__=strip_tags((html_entity_decode($item9)));
				    $item_text_full__=str_replace("'","",$item_text_full__);

				    	//запись в БД
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
						//echo "<br>$size_r[0]<br>";
						$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`)
						VALUES ('$id_main','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."')") or die("Invalid query: " . mysql_error());
						$llasett=mysql_insert_id();
						$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
						$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
						unset($txt_full_n);
						unset($item_text_full__);
						//unset($txt_full_n);
						unset($total);				    
				    		unset($anonce_n[1]);
				    		unset($total);
						unset($llasett);
				    		//запись в БД			    
				    					    }
						
							    }
							}
	//elseif(!empty($item->fulltext)) {
	else {
	//echo "$img_n[1]\r\n";
	$anonce_n[1]=html_entity_decode($item->description);
	$anonce_n[1]=strip_tags($anonce_n[1],"<a>");
	$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item->fulltext);
	$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
	//	echo "$item9";
	$item_text_full__=strip_tags((html_entity_decode($item9)));
	$item_text_full__=str_replace("'","",$item_text_full__);
	//запись в БД
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
	
	$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`)
	 VALUES ('$id_main','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."')") or die("Invalid query: " . mysql_error());
	$llasett=mysql_insert_id();
	$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
	$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
	//sleep(10);
	
				echo "zapisal $name_n[1]\r\n";
				unset($txt_full_n);
				unset($item_text_full__);
				unset($total);
				unset($llasett);
				}
			///запись в БД
	echo "Название:";
	echo $name_n[1]."\r\n";
	echo "Линк:";
	echo $link_n[1]."\r\n";
	echo "Категория:";
	echo $item->category."\r\n";
	echo "картинка: $img_n[1]\r\n";
	echo "Описание:$anonce_n[1]\r\n";
	echo "Текст: $item_text_full__\r\n";
	    }
	}    
    }	
	unset($rss);
?>