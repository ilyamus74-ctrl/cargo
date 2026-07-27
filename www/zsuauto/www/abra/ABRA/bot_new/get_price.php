<?php
echo "TRANSLATE TO UK";

include("/home/zsuauto/web/configs/connectDB.php");
include_once("srav.php");
include_once("sde.php");

$_name_news_table="zs_preg_auto";
$_name_anonce_table="zs_announce_auto";
$_name_to_last="zs_auto";
$id_main=1;


//$preg_list="SELECT `id_wp`,`name`,`main_url`,`url`,`preg_count_url`,`preg_link_some`,`preg_link`,`preg_name`,`preg_anonce`,`preg_img`,`preg_text`,`preg_f_img`,`preg_video`,`lang`,`rss`,`charset` FROM `$_name_news_table` WHERE `price` IS NULL ";
echo $preg_list="SELECT `id`,`url_announce` FROM `$_name_anonce_table` WHERE `price` IS NULL LIMIT 100";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllPreg[$idpp['id']]=$idpp;
	}
	foreach($AllPreg as $key=>$item){
	$html=ccurl($item['url_announce']);
///	<h2 class="boxedarticle--price" id="viewad-price">(.*)</h2>
	    preg_match('|<h2 class="boxedarticle--price" id="viewad-price">(.*)</h2>|Uis',$html, $preg_price);
	    $takePrice=preg_replace('/[^0-9]/','',$preg_price[1]);
	    $item['price']=$takePrice;
	print_r($item);
	$sql="UPDATE `zs_announce_auto`SET `price`='".$dbcnx->real_escape_string($takePrice)."' WHERE `id` = '".$key."' ";
	$dbcnx->query($sql);
	sleep(rand(5,11));

	}
	//print_r($AllPreg);
	
	
	

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
echo $time_start = microtime(true);


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
*/
/*
for($i=0;$i<=2;$i++){
$time_start = microtime(true);
echo $time_start . "\r\n";
if(!is_dir("/home/zsuauto/web/www/abra/ABRA/bot_new/".$time_start."")) mkdir("/home/zsuauto/web/www/abra/ABRA/bot_new/".$time_start."");
sleep(1);
}
*/
//	if(!is_dir("/home/zsuauto/web/www/abra/ABRA/bot_new/".$time_start."")) mkdir("/home/zsuauto/web/www/abra/ABRA/bot_new/".$time_start."");

?>