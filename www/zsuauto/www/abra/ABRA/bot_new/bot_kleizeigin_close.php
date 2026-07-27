#!/usr/bin/php -q
<?php
//include("/home/infonc4/www/www/settings/connect.php");
include("/home/zsuauto/web/configs/connectDB.php");
include_once("srav.php");
include_once("sde.php");

/*
$view_id=mysql_escape_string($_POST['id']);
$table=mysql_escape_string($_POST['table']);
*/
$_name_news_table="zs_preg_auto";
$_name_anonce_table="zs_announce_auto";
$_name_to_last="zs_auto";
$id_main=1;



///////////////// start main ////////////////


//print_r($preg_name1);
//echo "\r\n 1 \r\n";
//print_r($preg_name2);
//echo "\r\n 2 \r\n";


$preg_list="SELECT `zs_announce_auto`.`url_announce`,`zs_announce_auto`.`img_dir` FROM `".$_name_anonce_table."` INNER JOIN `zs_announce_auto_uk` ON `zs_announce_auto_uk`.`img_dir`=`zs_announce_auto`.`img_dir` WHERE `zs_announce_auto_uk`.`active_announce` !='S'";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllPreg[]=$idpp;
	}
//print_r($AllPreg);
//sleep(22);
foreach($AllPreg as $key=>$item){
	if(!empty($item['url_announce'])){
//	print_r($item['url_announce']);
//	echo "\r\n";
$find1='<div class="outcomemessage-warning">Die gewünschte Anzeige ist nicht mehr verfügbar.</div>';
$find2='showDeletedVeil: true';

//$uuu="https://www.kleinanzeigen.de/s-anzeige/vw-bus-t5-2-5-tdi-multivan-7-sitzer/2779974412-216-8962";
$out_html=ccurl($item['url_announce']);
preg_match('|'.$find1.'|Uis',$out_html, $preg_name1);
preg_match('|'.$find2.'|Uis',$out_html, $preg_name2);

if(!empty($preg_name1[0]) or !empty($preg_name2[0])){
//echo "dleted \r\n";
echo	$upd_sql="UPDATE zs_announce_auto_uk SET `active_announce`='S' WHERE `img_dir`='".$item['img_dir']."' ";
$dbcnx->query($upd_sql);
unset($preg_name1,$preg_name2,$upd_sql);
}
sleep(rand(5,20));    

	//$out_html=ccurl($item['url']);
	
/*	    $out_html=ccurl($item['url']);
	    $countListAllPages=countListAllPages($out_html,$item['preg_count_url']);
	    $preArrayPregLinks=makeArrayPregLinks($out_html,$item['preg_link']);
			foreach($preArrayPregLinks as $keyy=>$itemm)
			{
			$arrayPregLinks[]=$itemm;
			}
		$item['url']=str_replace("seite:99","seite:".$countListAllPages."",$item['url']);
	    for ($i = $countListAllPages; $i >= 1; $i--) {
		$item['url']=str_replace("seite:".$a."/","seite:".$i."/",$item['url']);
		$a=$i;
		    $out_html=ccurl($item['url']);
		        $preArrayPregLinks=makeArrayPregLinks($out_html,$item['preg_link']);
			foreach($preArrayPregLinks as $keyy=>$itemm)
			{
			$arrayPregLinks[]=$itemm;
			}

		echo "\r\n";
		print_r($item['url']);
		echo "\r\n";
	    echo $i;
	    echo "\r\n";
	    }
		foreach($arrayPregLinks as $key_l=>$item_l){
		$links[]=$item['main_url'].findLink($item_l,$item['preg_link_some']);
		}
		chkInDB($links,$dbcnx);
*/
	}
}





//$preg_list="SELECT `id`,`url_announce` FROM `$_name_anonce_table` WHERE `name_announce`  IS NULL AND `id` ='153' LIMIT 1 ";
$preg_list="SELECT `id`,`url_announce` FROM `$_name_anonce_table` WHERE `name_announce`  IS NULL ";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllLinks[]=$idpp;
	}

//$preg_list="SELECT `id_wp`,`name`,`main_url`,`url`,`preg_count_url`,`preg_link_some`,`preg_link`,`preg_name`,`preg_anonce`,`preg_full_announce`,`preg_price`,`preg_location`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`rss`,`charset` FROM `$_name_news_table` WHERE `active`=1   ORDER BY `date_in` ASC ";
$preg_list="SELECT * FROM `$_name_news_table` WHERE `active`=1   ORDER BY `date_in` ASC ";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllPreg=$idpp;
	}

	foreach($AllLinks as $key=>$item){
		$out_html=ccurl($item['url_announce']);
		$data=findData($out_html,$AllPreg);
		print_r($data);
//	    $sql="INSERT INTO 'zs_announce_auto' (`name_announce`,`short_text_announce`,`text_announce`,`text_full_announce`,`img_announce`,`date_in_announce`,`marke`,`modell`,`kilometerstand`,`fahrzeugzustand`,`erstzulassung`,`kraftstoffart`,`Leistung`,`Getriebe`,`fahrzeugtyp`,`anzahl_turen`,`hu_bis`,`umweltplakette`,`schadstoffklasse`) VALUES ('".$data['preg_name']."','".$data['preg_anonce']."','".$data['preg_full_announce']."','".json_encode($data['img']['files'])."',now(),'".$data['details']['preg_marke']."','".$data['details']['preg_modell']."','".$data['details']['kilometerstand']."','".$data['details']['fahrzeugzustand']."','".$data['details']['erstzulassung']."','".$data['details']['kraftstoffart']."','".$data['details']['Leistung']."','".$data['details']['Getriebe']."','".$data['details']['fahrzeugtyp']."','".$data['details']['anzahl_turen']."','".$data['details']['hu_bis']."','".$data['details']['umweltplakette']."','".$data['details']['schadstoffklasse']."') ";
	    $sqlInsert="UPDATE `zs_announce_auto` 
	    SET `id_country`='DE',
	    `name_announce` = '".$dbcnx->real_escape_string($data['preg_name'])."',
	    `short_text_announce` = '".$dbcnx->real_escape_string($data['preg_anonce']['all'])."',
	    `text_announce` = '".$dbcnx->real_escape_string($data['preg_full_announce'])."',
	    `text_full_announce`= '".$dbcnx->real_escape_string($data['preg_full_announce'])."',
	    `img_announce`= '".$dbcnx->real_escape_string(json_encode($data['img']['files']))."',
	    `img_dir`= '".$dbcnx->real_escape_string(json_encode($data['img']['dir']))."',
	    `date_in_announce`= now(),
	    `marke` = '".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_marke'])."',
	    `modell` = '".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_modell'])."',
	    `kilometerstand` = '".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_kilometerstand'])."',
	    `fahrzeugzustand` = '".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_fahrzeugzustand'])."',
	    `erstzulassung`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_erstzulassung'])."',
	    `kraftstoffart`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_kraftstoffart'])."',
	    `Leistung`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_Leistung'])."',
	    `Getriebe`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_Getriebe'])."',
	    `fahrzeugtyp`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_fahrzeugtyp'])."',
	    `anzahl_turen`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_anzahl_turen'])."',
	    `hu_bis`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_hu_bis'])."',
	    `umweltplakette`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_umweltplakette'])."',
	    `schadstoffklasse`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_schadstoffklasse'])."',
	    `location`='".$dbcnx->real_escape_string($data['preg_location'])."',
	    `price`='".$dbcnx->real_escape_string($data['preg_price'])."',
	    `farbe`='".$dbcnx->real_escape_string($data['preg_anonce']['details']['preg_farbe'])."'
	     WHERE `id` = '".$item['id']."'";
	    echo "$sqlInsert";
	    $dbcnx->query($sqlInsert);
	}


///////////////// end main ////////////////

function findData($out_html,$AllPreg){
$time_start = microtime(true);
    preg_match('|'.$AllPreg['preg_name'].'|Uis',$out_html, $preg_name);
    preg_match('|'.$AllPreg['preg_anonce'].'|Uis',$out_html, $preg_anonce);
    preg_match('|'.$AllPreg['preg_full_announce'].'|Uis',$out_html, $preg_full_announce);
    preg_match('|'.$AllPreg['preg_price'].'|Uis',$out_html, $preg_price);
    preg_match('|'.$AllPreg['preg_location'].'|Uis',$out_html, $preg_location);

    preg_match('|'.$AllPreg['preg_marke'].'|Uis',$preg_anonce[1], $preg_marke);
    preg_match('|'.$AllPreg['preg_modell'].'|Uis',$preg_anonce[0], $preg_modell);
    preg_match('|'.$AllPreg['preg_kilometerstand'].'|Uis',$preg_anonce[1], $preg_kilometerstand);
    preg_match('|'.$AllPreg['preg_fahrzeugzustand'].'|Uis',$preg_anonce[1], $preg_fahrzeugzustand);
    preg_match('|'.$AllPreg['preg_erstzulassung'].'|Uis',$preg_anonce[1], $preg_erstzulassung);
    preg_match('|'.$AllPreg['preg_kraftstoffart'].'|Uis',$preg_anonce[1], $preg_kraftstoffart);
    preg_match('|'.$AllPreg['preg_Leistung'].'|Uis',$preg_anonce[1], $preg_Leistung);
    preg_match('|'.$AllPreg['preg_Getriebe'].'|Uis',$preg_anonce[1], $preg_Getriebe);
    preg_match('|'.$AllPreg['preg_fahrzeugtyp'].'|Uis',$preg_anonce[1], $preg_fahrzeugtyp);
    preg_match('|'.$AllPreg['preg_anzahl_turen'].'|Uis',$preg_anonce[1], $preg_anzahl_turen);
    preg_match('|'.$AllPreg['preg_hu_bis'].'|Uis',$preg_anonce[1], $preg_hu_bis);
    preg_match('|'.$AllPreg['preg_umweltplakette'].'|Uis',$preg_anonce[1], $preg_umweltplakette);
    preg_match('|'.$AllPreg['preg_schadstoffklasse'].'|Uis',$preg_anonce[1], $preg_schadstoffklasse);
    preg_match('|'.$AllPreg['preg_farbe'].'|Uis',$preg_anonce[1], $preg_farbe);
    
    preg_match_all('|'.$AllPreg['preg_f_img'].'|Uis',$out_html, $preg_f_img);
    preg_match_all('|<img src="(.*)"|Uis',$preg_f_img[0][0], $preg_f_img_r);
    
    foreach($preg_f_img_r[1] as $key=>$item){
//	echo $item . "\r\n";
	if(!empty($item) and !is_dir("/home/zsuauto/web/www/img/announce/".$time_start."")) mkdir("/home/zsuauto/web/www/img/announce/".$time_start."");
	
	if(!empty($item)){
	    exec("wget '".$item."' -O /home/zsuauto/web/www/img/announce/".$time_start."/".$time_start."_".$key.".jpg");
	    $data['img']['dir']=$time_start;
	    $data['img']['files'][]=$time_start."_".$key.".jpg";
	    }
	
    }
    //print_r($preg_f_img_r);
    
    
    $data['preg_anonce']['details']['preg_marke']=trim($preg_marke[1]);
    $data['preg_anonce']['details']['preg_modell']=trim($preg_modell[1]);
    $data['preg_anonce']['details']['preg_kilometerstand']=trim(preg_replace('/[^0-9]/','',$preg_kilometerstand[1]));
    $data['preg_anonce']['details']['preg_fahrzeugzustand']=trim($preg_fahrzeugzustand[1]);
    $data['preg_anonce']['details']['preg_erstzulassung']=trim($preg_erstzulassung[1]);
    $data['preg_anonce']['details']['preg_kraftstoffart']=trim($preg_kraftstoffart[1]);
    $data['preg_anonce']['details']['preg_Leistung']=trim($preg_Leistung[1]);
    $data['preg_anonce']['details']['preg_Getriebe']=trim($preg_Getriebe[1]);
    $data['preg_anonce']['details']['preg_fahrzeugtyp']=trim($preg_fahrzeugtyp[1]);
    $data['preg_anonce']['details']['preg_anzahl_turen']=trim($preg_anzahl_turen[1]);
    $data['preg_anonce']['details']['preg_hu_bis']=trim($preg_hu_bis[1]);
    $data['preg_anonce']['details']['preg_umweltplakette']=trim($preg_umweltplakette[1]);
    $data['preg_anonce']['details']['preg_schadstoffklasse']=trim($preg_schadstoffklasse[1]);
    $data['preg_anonce']['details']['preg_farbe']=trim($preg_farbe[1]);
/*    print_r($preg_name);
    print_r($preg_anonce);
    print_r($preg_full_announce);
    print_r($preg_price);
    print_r($preg_location);
*/
    $data['preg_name']=$preg_name[1];
    $data['preg_anonce']['all']=$preg_anonce[1];
    $data['preg_full_announce']=$preg_full_announce[1];
    $data['preg_price']=preg_replace('/[^0-9]/','',$preg_price[1]);
    $data['preg_location']=trim($preg_location[1]);
    return $data;
//print_r($AllPreg);
}

function chkInDB($links,$dbcnx){
    foreach($links as $key=>$item){
    $preg_list="SELECT `id` FROM `zs_announce_auto`  WHERE `url_announce` = '".$item."'";
    $sss=$dbcnx->query($preg_list);
	if($dbcnx->affected_rows == 0) {
		print_r($dbcnx->affected_rows); 
		echo "nenoshlo $preg_list\r\n";
		echo $insert="INSERT INTO `zs_announce_auto` (`url_announce`) VALUES ('".$item."')";
		$dbcnx->query($insert);
		}
	else {print_r($dbcnx->affected_rows); echo "nashlo  $preg_list\r\n";}
    }
}

function findLink($item_l,$preg_link_some){
    preg_match('|'.$preg_link_some.'|Uis',$item_l, $link);
    return $link[1];
}

function makeArrayPregLinks($out_html,$preg_link){
    preg_match_all('|'.$preg_link.'|Uis',$out_html, $allLinks);
    //print_r($allLinks);
    //$return = $preg_count_url[1];
    return $allLinks[1];
}

function countListAllPages($out_html,$preg_count_url){
    preg_match('|'.$preg_count_url.'|Uis',$out_html, $preg_count_url);
    $return = $preg_count_url[1];
    return $return;
}

function ccurl($url)
    {
    $headers1 = array(
"GET /HTTP/1.1",
"User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.0.1) Gecko/2008070208 Firefox/3.5.1",
"Content-type: text/xml;charset=\"utf-8\"",
"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",            "Accept-Language: ru,en;q=0.5",
"Accept-Encoding: gzip,deflate",
"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
"Keep-Alive: 300",      
"Connection: keep-alive",
"Authorization: Basic " . base64_encode($credentials));

    echo "$url \r\n";
    $curl = curl_init();
     curl_setopt($curl, CURLOPT_COOKIESESSION, true); 
     curl_setopt($curl, CURLOPT_COOKIEFILE, "cookiefile"); 
     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
     curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
     curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru; rv:1.9.1.3) Gecko/20090824 Firefox/3.5.3'); 
     curl_setopt($curl, CURLOPT_TIMEOUT, 60);
     //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers1);
     curl_setopt($curl, CURLOPT_URL, $url); 
     $html = curl_exec($curl);
    //print_r($html);
    return $html;
//	$ccc1=$ccc[0];
    }


/*
echo "<br>Начало БОТА<br>\r\n";
//$list_news=mysql_query("SELECT `name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`rss` FROM `$table` WHERE `id_wp`='$view_id' ") or die ("Invalid:" . mysql_error());
$list_news=mysql_query("SELECT `id_wp`,`name`,`main_url`,`url`,`preg_one`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`rss`,`charset` FROM `$_name_news_table` WHERE `active`=1   ORDER BY `date_in` ASC ") or die ("Invalid:" . mysql_error());
	while((list($id_wp_,$name,$main_url,$url,$preg_one,$preg_link,$preg_name,$preg_anonce,$preg_img,$preg_text_full,$preg_f_img,$preg_video,$lang,$rss,$charset)=mysql_fetch_row($list_news)))
    {
	//echo "$name $url $preg_one";
	if(!empty($url))
	{ 
		//if(preg_match('#[а-яё]+#i',$url)) $url=my_url_encode($url);
		//ccurl($url);
		$out_html=ccurl($url);
		$cdata=simplexml_load_string($out_html);//work_parse_xml
	if(!empty($cdata) or !empty($rss_)) { include("rss_.php"); }
	else   {
	
	echo "<strong><b>NO RSS</b></strong><br>\r\n";
		preg_match_all('|'.$preg_one.'|Uis',$out_html, $ccc);
		//print_r($ccc);
		//sleep(10);
		foreach($ccc[1] as $key=>$item) //bilo $ccc[0]
		    {
		    preg_match('|'.$preg_link.'|Uis',$item, $link_n);
		    //if(substr_count($link_n[1],$main_url)==0)
		    echo "$link_n[1]<br>\r\n";
		    if(stristr($link_n[1],$main_url) === FALSE) //kasyaki!!! 
								{ 
								if(stristr($link_n[1],"http://") === FALSE) {
								//$link_n[1]=str_replace("http://","",$link_n[1]);
								$link_n[1]="http://$main_url$link_n[1]";
								}
								}
		    echo "end<br>\r\n";
		    preg_match('|'.$preg_name.'|Uis',$item, $name_n);
		    preg_match('|'.$preg_anonce.'|Uis',$item, $anonce_n);
		    $anonce_n[1]=trim(str_replace("\r\n","",$anonce_n[1]));
		    $anonce_n[1]=str_replace("'","",$anonce_n[1]);
		    $anonce_n[1]=str_replace('"','',$anonce_n[1]);
		    //preg_match('|'.$preg_img.'|Uis',$item, $img_n);
		    preg_match('|'.$preg_txt.'|Uis',$item, $txt_n);
		    preg_match('|'.$preg_img.'|Uis',$item, $img_n);
			//if(stristr($img_n[1],"http://")  === FALSE)
			if(stristr($img_n[1],"http")  === FALSE)
					{
				if(stristr($img_n[1],$main_url) === FALSE) { 
									    //echo "Не найдено $main_url";  
									    $img_n[1]="http://$main_url$img_n[1]";
									    }
					}
									    $ext_file=substr($img_n[1],-4);//echo $link_n[1];
									    $ext_file=mb_strtolower($ext_file);
									    if($ext_file=="jpeg") $ext_file=".jpg";
									    //echo "$ext_file";
									    $new_file=substr_replace(sha1(microtime(true)), '', 12);
			}
			if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" and $ext_file!="file" ){ $img_n[1]=""; }
					//file_put_contents("/home/pafin/www/www/img/news/$new_file$ext_file", file_get_contents($img_n[1]));
		//    print_r($link_n[1]);
		    $anonce_n[1]=html_entity_decode($anonce_n[1]);
		    if(!empty($charset)) $name_n[1]=iconv($charset,"utf-8",$name_n[1]);
		    $name_n[1]=html_entity_decode($name_n[1]);
		    $name_n[1]=str_replace("%"," процентов",$name_n[1]);
		    $name_n[1]=str_replace("'","",$name_n[1]);
		    $name_n[1]=str_replace('"',"",$name_n[1]);
		    //$name_n[1]=str_replace("?","_",$name_n[1]);
		    //$name_n[1]=str_replace("<b>","",$name_n[1]);
		    //$name_n[1]=str_replace("<br>","",$name_n[1]);
		    $name_n[1]=strip_tags($name_n[1]);
		    $name_n[1]=trim($name_n[1]);
		    //ccurl($link_n[1]);
		    if(preg_match('#[а-яё]+#i',$link_n[1])) {$link_n[1]=my_url_encode($link_n[1]); echo "$link_n_[1]";}
		    $out_html_full=ccurl($link_n[1]);
		    $link_n[1]=my_url_decode($link_n[1]);
		    echo "$link_n[1]";
		    //echo "!!!!begin";
		    //echo "$preg_text_full";
		    preg_match_all('|'.$preg_text_full.'|Uis',$out_html_full, $txt_full_n);
		    //print_r($txt_full_n);
		    //if(!empty($charset)) $txt_full_n[1]=iconv($charset,"utf-8",$txt_full_n[1]);
		    $txt_full_n1=$txt_full_n[1];
		    $txt_full_n01=$txt_full_n[0];
		    //echo "$txt_full_n01";
		    $txt_full_n11=strip_tags($txt_full_n1,"<a>");
		    //print_r($txt_full_n11);
		    foreach($txt_full_n1 as $key9=>$item9)
			{
			$item9=preg_replace('|Читайт.*?</a>|si','',$item9);
			$item9=preg_replace('|ЧИТАЙТ.*?</a>|si','',$item9);
			$item9=preg_replace('|Читат.*?</a>|si','',$item9);
			//$item9=preg_replace('|<script type="text/javascript">(.*?)</script>|Uis','',$item9);
			$item9=preg_replace('|<script[^>]*?>.*?</script>|si','',$item9);
			//$item9=preg_replace('|<script>(.*?)</script>|Uis','',$item9);
		//	echo "$item9";
			$item_text_full__=strip_tags((html_entity_decode($item9)));
			$item_text_full__=str_replace("'","",$item_text_full__);
			}//
			if(!empty($charset)) $item_text_full__=iconv($charset,"utf-8",$item_text_full__);
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

				$img_n[1]=$img_BIG[1];
				$ext_file=substr($img_n[1],-4); }
			if($ext_file!=".jpg" and $ext_file!=".JPG" and $ext_file!=".png" and $ext_file!=".PNG" and $ext_file!="file" )
			{ $ext_file=""; }
			if(empty($ext_file)) { $new_file="NC4INFO.jpg";  }

			echo "<b>ID:</b>$id_main<br>\r\n <b>NAME:</b>$name_n[1]<br>\r\n <b>LINK:</b>$link_n[1]<br>\r\n <b>ANONCE</b>:$anonce_n[1]<br>\r\n <b>FULLTEXT:</b>$item_text_full__<br>\r\n <b>IMG_LINK:</b>$img_n[1]<br>\r\n<b>IMG:</b>/img/news/$new_file$ext_file<br><br>\r\n";
			echo "##########################################################";
		//    echo "end!!!!";
		
		//    print_r($link_n[1]);
	    $out22=srav("$name_n[1]","$item_text_full__");
	    if($out22>=1) {
			//video cut
			preg_match('|'.$preg_video.'|Uis',$out_html_full, $videos);
			$iframe_chek=substr($videos[1], -3);
			if($iframe_chek!=".js"){
			if(!empty($videos[1])) {$vidos="<br><br><iframe src=\"".$videos[1]."\" frameborder=\"0\" height=\"100%\" width=\"100%\" allowfullscreen=\"\"></iframe>";}
			else  {$vidos="";}
			$item_text_full__="$item_text_full__"."$vidos";
						}
			//echo "$item_text_full__<br>";//video cut		
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
			$in_news=mysql_query("INSERT INTO $_name_anonce_table (`id_main`,`id_lang`,`name_news`,`url_news`,`text_news`,`text_full_news`,`img_news`,`date_in_news`,`name_r`,`id_wp`) VALUES ('$id_main','".$lang."','".$name_n[1]."','".$link_n[1]."','".$anonce_n[1]."','".$item_text_full__."','/img/news/$new_file$ext_file',now(),'".$name."','".$id_wp_."')") or die("Invalid query: " . mysql_error());
			$llasett=mysql_insert_id();
			$in_news_last=mysql_query("INSERT INTO wp_last_list (`rubrika`,`id_news`,`date`)VALUES ('$_name_to_last','$llasett',now())") or die("Invalid query: " . mysql_error());
			$upd_news_id=mysql_query("UPDATE $_name_anonce_table SET `id_news`='$llasett' WHERE `id`='$llasett' ") or die ("Invalid query: " . mysql_error());
		//	sleep(10);		
				    }
				unset($out22);
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
*/
echo "<br>Конец БОТА<br>\r\n";

?>