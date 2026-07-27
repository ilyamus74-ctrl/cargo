<?
session_start();

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
				    $txt_full_n1=strip_tags($item9,"<a>");
				    $item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$txt_full_n1);
				    $item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
				    $item_text_full__=strip_tags((html_entity_decode($item9)));
				    $item_text_full__=str_replace("'","",$item_text_full__);
				    					    }
				    /*
				    if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" ){
									//$purl=ccurl($url);
									preg_match('|(.*)'.$item->description.'|Uis',$purl,$two_img);
									print_r($two_img);
									     }
					*/
											    }
	else 			{
	$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item->fulltext);
	$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
	$item_text_full__=strip_tags((html_entity_decode($item9)));
	$item_text_full__=str_replace("'","",$item_text_full__);
			/*    if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" ){
									     echo "<br>НЕТ КАРТИНКИ<br>\r\n";
									//preg_match_all('|.$preg_img.|',$out_html_full,$img_ff);
									//print_r($img_ff);
									     }
			*/
				    }
	//img
	//preg_match_all('|<description>(.*)</description>|Uis',$out_html,$two_img); 
	//print_r($two_img);
	
		
	echo "<br><b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n 
	<b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n <b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\n";
	echo "##########################################################<br>";
	
	    }

	}
//echo "$cdata['item']";
?>