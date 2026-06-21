<?
session_start();
//include("srav.php");

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
	    if(stristr($link_n[1],$main_url) === FALSE) {
			if(stristr($link_n[1],"http://") === FALSE) {
			$link_n[1]="http://$main_url$link_n[1]";
								    }
							}
		//echo $item->description;
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
	if($rss==$item->category or $rss=="NO_CAT")
	    {
	$name_n[1]=$item->title;
				if(empty($item->fulltext)) {
				    $out_html_full=ccurl($item->link);
				    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
				    foreach($txt_full_n[1] as $key9=>$item9) {
				    echo mb_detect_encoding($item9);
				    $txt_full_n1=strip_tags($item9,"<a>");
				    $item9=preg_replace('|Читайт.*?</a>|si','',$item9);
				    //$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$txt_full_n1);
				    //$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
				    $item9=preg_replace('|<script[^>]*?>.*?</script>|si','',$item9);
				    $item_text_full__=strip_tags((html_entity_decode($item9)));
				    $item_text_full__=str_replace("'","",$item_text_full__);
				    					    }
				    			    }
	else 			{
	$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item->fulltext);
	$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
	$item_text_full__=strip_tags((html_entity_decode($item9)));
	$item_text_full__=str_replace("'","",$item_text_full__);
						    }
		
		if(empty($img_n[1])) 	{
	preg_match('|'.$preg_f_img.'|Uis',$out_html_full,$img_BIG); //rabotaet
	//echo "$img_BIG[0]";	//rabotaet
	$img_n[1]=$img_BIG[1];
	$ext_file=substr($img_n[1],-4); }
					
			if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" )
			{ $ext_file=""; }
	
	if(empty($ext_file)) {
	$out_html_full=ccurl($item->link);
	preg_match('|'.$preg_f_img.'|Uis',$out_html_full,$img_BIG);
	$img_n[1]=$img_BIG[1];
	$ext_file=substr($img_n[1],-4); }
	echo "$img_n[1]";
			    
			if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" )
			{ $ext_file=""; }
	//if(empty($item_text_full__) and !empty($anonce_n[1])){ $item_text_full__=$anonce_n[1]; $anonce_n[1]="";}
	
	//echo "$item_text_full__";
	$out22=srav("$name_n[1]","$item_text_full__");
	//echo "<b>$out2</b>";
	
		    	//video cut
			preg_match('|'.$preg_video.'|Uis',$out_html_full, $videos);
			if(!empty($videos[1])) {$vidos="<br><iframe src=\"".$videos[1]."\" frameborder=\"0\" height=\"100%\" width=\"100%\"></iframe>";}
			else  {$vidos="";}
			$item_text_full__="$item_text_full__"."$vidos";
			//echo "$item_text_full__<br>";//video cut
	echo "<br><b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n 
	<b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n 
	<b>IMG_LINK_BIG:</b>$img_BIG[1]<br>\r\n 
	<b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\nСовпадение<b>$out22</b>\r\n" ;
	echo "##########################################################<br>";
	    //zapis
	    /*
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
	    //zapis
	    */
	    //$ext_file="";$img_BIG[1]="";$img_n[1]="";
	    //unset($ext_file,$img_BIG[1],$img_n[1]);
	    //$out_html_full="";
	    }

	}
//echo "$cdata['item']";
?>