<?php

include("/home/zsuauto/web/configs/connectDB.php");
$maxString=20000;


$preg_list="SELECT `url_announce`,`img_dir` FROM `zs_announce_auto_uk` WHERE `img_dir`!= ''  ORDER BY `id` ASC ";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllPreg[]=$idpp;
	}
	print_r($AllPreg);
//always – при каждом входе на страницу;
//hourly – каждый час;
//daily – каждый день;
//weekly – каждую неделю;
//monthly – каждый месяц;
//yearly – каждый год;
//never – никогда.
$changeFreq="weekly";
$i=1;
$files=2;
$dateNow=date("Y-m-d");
$dateTimeNow=date("h:i:s");
$dateNow=$dateNow."T".$dateTimeNow."+02:00";
$xmlbBody="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<?xml-stylesheet type=\"text/xsl\" href=\"https://zsuauto.info/sitemap".$files.".xml\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";
$xmlbBodyEnd="</urlset>";
    foreach($AllPreg as $key=>$item){

	if(empty($body)){
	    $body="<url><loc>https://zsuauto.info/view/".$item['img_dir']."-".$item['url_announce']."</loc>\n<lastmod>".$dateNow."</lastmod>\n<changefreq>".$changeFreq."</changefreq><priority>0.8</priority>\n</url>\n";
	}
	else{
	    $body.="<url><loc>https://zsuauto.info/view/".$item['img_dir']."-".$item['url_announce']."</loc>\n<lastmod>".$dateNow."</lastmod>\n<changefreq>".$changeFreq."</changefreq><priority>0.8</priority>\n</url>\n";
	}

	    if($i == $maxString){
		$data=$xmlbBody.$body.$xmlbBodyEnd;
		save($files,$data);
		$i=1;
		$body="";
		$files++;
		}
	    else if(array_key_last($AllPreg) == $key){
		$data=$xmlbBody.$body.$xmlbBodyEnd;
		save($files,$data);
		$i=1;
		$body="";
		}
        $i++;
    }

function save($files,$data){
$myfile = fopen("/home/zsuauto/web/www/sitemap".$files.".xml", "w") or die("Unable to open file!");
fwrite($myfile, $data);
fclose($myfile);
}
/*
define('SITE',true);
require_once("../admin/conf.php");
$itog="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">
<url><loc>".$h."</loc><changefreq>daily</changefreq><priority>0.5</priority></url>\r\n";
$query=mysql_query("SELECT id,url FROM jblog_posts WHERE label='stream' ORDER by id DESC");  
while($d=mysql_fetch_assoc($query))$itog.="<url><loc>".$h."p".$d['id']."-".$d['url'].".html</loc><changefreq>daily</changefreq><priority>0.5</priority></url>";
$itog.="</urlset>";
if(writeData("sitemap.xml",$itog)){mysql_query("UPDATE jblog_maintenance SET create_sitemap=NOW()") or die(mysql_error());echo "Operation complete";}
*/
?>
