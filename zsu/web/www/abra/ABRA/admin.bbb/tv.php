<?php
$url="http://fakty.ictv.ua/ru/index/view-media/id/123046/album/83";
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
     curl_setopt($ch, CURLOPT_TIMEOUT, 60);
     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers1);
     curl_setopt($curl, CURLOPT_URL, $url); 
     $html = curl_exec($curl);
    return "$html";
//	$ccc1=$ccc[0];
    }

$out_html=ccurl($url);
preg_match_all('|><ifr.*(.*allowfullscreen.*).*>|Uis',$out_html, $tv_test);
print_r($tv_test);

//echo"$tv_test[0]";
?>