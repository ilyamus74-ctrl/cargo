<?
session_start();
//include_once("srav.php");

//print_r($cdata);
//echo trim($cdata[0]->channel);
/*
echo $cdata->channel->item->title."<br>";
echo $cdata->channel->item->link."<br>";
echo $cdata->channel->item->description."<br>";
echo $cdata->channel->item->fulltext."<br>";
*/
echo "<strong><b>RSS</b></strong><br>\r\n";
preg_match_all('|<description>(.*)</description>|Uis',$out_html,$two_img);
//$purl=ccurl($url);
foreach($cdata->channel->item as $item)
	{
	
	$link_n[1]=$item->link;
	    echo "rss $link_n[1]<br>\r\n";
	    if(stristr($link_n[1],$main_url) === FALSE) {
			if(stristr($link_n[1],"http://") === FALSE) {
			$link_n[1]="http://$main_url$link_n[1]";
								    }
							}
		echo "end rss";
	    preg_match('|'.$preg_img.'|Uis',$item->description, $img_n);
			if(stristr($img_n[1],"http://") === FALSE)
					{
				//if(stristr($img_n[1],$main_url) === FALSE) { 
					//echo "Не найдено $main_url";  
					$img_n[1]="http://$main_url$img_n[1]";
				//					    }
					}
					$ext_file=substr($img_n[1],-4);//echo $link_n[1];
					//echo "$ext_file";
					$new_file=substr_replace(sha1(microtime(true)), '', 12);
					if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" ){
									foreach($two_img[1] as $kimg=>$kitem)
										{
										if(substr_count($kitem,$link_n[1])>0)
											{
											preg_match('|src="(.*)"|Uis',$kitem,$nn_img);
											$img_n[1]=$nn_img[1];
											$ext_file=substr($img_n[1],-4);
											//echo "$img_n[1]<br>";
											}
										else { $img_n[1]=""; }
										}
										
									
									}
	$anonce_n[1]=html_entity_decode($item->description);
	$anonce_n[1]=strip_tags($anonce_n[1],"<a>");
	$anonce_n[1]=str_replace("'","",$anonce_n[1]);
	$anonce_n[1]=str_replace('"','',$anonce_n[1]);
	if($rss==$item->category or $rss=="NO_CAT")
	    {
	$name_n[1]=$item->title;
	$name_n[1]=str_replace("%"," процентов",$name_n[1]);
	$name_n[1]=str_replace("'","",$name_n[1]);
	$name_n[1]=str_replace('"','',$name_n[1]);
	//$name_n[1]=str_replace("'s","s",$name_n[1]);
	//$name_n[1]=str_replace(":","_",$name_n[1]);
	$name_n[1]=trim($name_n[1]);
				if(empty($item->fulltext)) {
				    $out_html_full=ccurl($item->link);
				    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
				    foreach($txt_full_n[1] as $key9=>$item9) {
				    $item9=preg_replace('|Читайт.*?</a>|si','',$item9);
				    $txt_full_n1=strip_tags($item9,"<a>");
				    $item9=preg_replace('|<script[^>]*?>.*?</script>|si','',$item9);
				    //$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$txt_full_n1);
				    //$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
				    $item_text_full__=strip_tags((html_entity_decode($item9)));
				    $item_text_full__=str_replace("'","",$item_text_full__);
				    					    }
				    			    }
	else 			{
	$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item->fulltext);
	$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
	$item_text_full__=strip_tags((html_entity_decode($item9)));
	$item_text_full__=str_replace("'","",$item_text_full__);
	//$item_text_full__=str_replace('"','',$item_text_full__);
	
						    }
			if(empty($img_n[1])) 	{
			preg_match('|'.$preg_f_img.'|Uis',$out_html_full,$img_BIG); //rabotaet
			$img_n[1]=$img_BIG[1];
			$ext_file=substr($img_n[1],-4); }
			if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" )
			{ $ext_file=""; }
	
	//if(empty($item_text_full__) and !empty($anonce_n[1])){ $item_text_full__=$anonce_n[1]; $anonce_n[1]="";}
	//    include("srav.php");
	echo "<br><b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n 
	<b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n<b>IMG_LINK_BIG:</b>$img_BIG[1]<br>\r\n 	<b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\n";
	echo "##########################################################<br>";
	    //zapis
	    $out22=srav("$name_n[1]","$item_text_full__");
	    if($out22>=1) {
		    	//video cut
			preg_match('|'.$preg_video.'|Uis',$out_html_full, $videos);
			if(!empty($videos[1])) {$vidos="<br><iframe src=\"".$videos[1]."\" frameborder=\"0\" height=\"100%\" width=\"100%\"></iframe>";}
			else  {$vidos="";}
			$item_text_full__="$item_text_full__"."$vidos";
			//echo "$item_text_full__<br>";//video cut
	    $test_b=mysql_query("SELECT `name_news` FROM `$_name_anonce_table` WHERE `url_news`='$link_n[1]' LIMIT 1 ") or die ("Invalid:" . mysql_error());
	    $total=mysql_num_rows($test_b);
				if($total==0)
				{
				if(!empty($img_n19[1])) $put=exec("/usr/bin/wget $img_n19[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file");
				else { $put=exec("/usr/bin/wget $img_n[1] -O /home/infonc4/www/www/img/news/$new_file$ext_file"); }
				sleep (2);
				chmod("/home/infonc4/www/www/img/news/$new_file$ext_file",0644);
				chmod("/home/infonc4/www/www/img/news/",0755);
				
				$size_r = getimagesize("/home/infonc4/www/www/img/news/$new_file$ext_file");
				print_r ($size_r);
				echo "<br>$size_r[0]<br>";
				if(empty($ext_file)) { $new_file="NC4INFO.jpg";  }
		$zapros="INSERT INTO $_name_anonce_table (`id_main`,`id_lang`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`,`id_wp`) VALUES ('$id_main','".$lang."','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."','".$id_wp_."')";
	echo "||| $zapros |||";
	$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`id_lang`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`,`id_wp`) VALUES ('$id_main','".$lang."','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."','".$id_wp_."')") or die("Invalid query: " . mysql_error());
	$llasett=mysql_insert_id();
	$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
	$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
	
	unset($out_html_full);
	//sleep(10);
	    //zapis
	    unset($out22);
			    }
	    
				}
	    }
	}
    unset($cdata);
//echo "$cdata['item']";
?>