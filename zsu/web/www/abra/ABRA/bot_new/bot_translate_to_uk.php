<?php
echo "TRANSLATE TO UK";
require 'vendor/autoload.php';

use Google\Cloud\Translate\V2\TranslateClient;


include("/home/zsuauto/web/configs/connectDB.php");
include_once("srav.php");
include_once("sde.php");

$_name_anonce_table="zs_announce_auto";
$_name_to_last="zs_auto";
$id_main=1;


$preg_list="SELECT `name_announce`,`text_full_announce`,`img_announce`,`img_dir`,`marke`,`modell`,`kilometerstand`,`fahrzeugzustand`,`erstzulassung`,`kraftstoffart`,`Leistung`,`Getriebe`,`fahrzeugtyp`,`anzahl_turen`,`hu_bis`,`umweltplakette`,`schadstoffklasse`,`price`,`farbe` FROM `$_name_anonce_table` WHERE `active_announce`='A'   ORDER BY `id` ASC  ";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$AllPreg[]=$idpp;
	}
//print_r($AllPreg);

foreach ($AllPreg as $key=>$item){
	$chkDB=chkInDB($item,$dbcnx);
	    if($chkDB == "NO"){
	    echo "\r\n OPNKI\r\n";
	    	$cuter=preg_replace('/\<a.*\>(.*)\<\/a\>/', ' ', $item['text_full_announce']);
	//	$item['text_announce_uk']=strip_tags($cuter);
		$item['url_announce_uk']=translit(translate($item['name_announce']));
		$item['url_announce_uk']=str_replace(" ","-",$item['url_announce_uk']);
		$item['url_announce_uk']=str_replace(":-",":",$item['url_announce_uk']);
		$item['url_announce_uk']=str_replace("--","-",$item['url_announce_uk']);

		$item['text_announce_uk']=translate($cuter);
		$item['name_announce_uk']=translate($item['name_announce']);
		$item['fahrzeugzustand_uk']=translate($item['fahrzeugzustand']);
		$item['umweltplakette_uk']=translate($item['umweltplakette']);
		$item['erstzulassung_uk']=translate($item['erstzulassung']);
		$item['kraftstoffart_uk']=translate($item['kraftstoffart']);
		$item['Getriebe_uk']=translate($item['Getriebe']);
		$item['hu_bis_uk']=translate($item['hu_bis']);
		$item['farbe_uk']=translate($item['farbe']);
		$item['fahrzeugtyp_uk']=translate($item['fahrzeugtyp']);
//		$item['fahrzeugzustand_uk']=translate($item['fahrzeugzustand']);
//		print_r($item);
		
		$sqlInsert="INSERT INTO `zs_announce_auto_uk` (`name_announce`,`url_announce`,`text_full_announce`,`img_announce`,`img_dir`,`date_in_announce`,`location`,`active_announce`,
		`marke`,`modell`,`kilometerstand`,`fahrzeugzustand`,`erstzulassung`,`kraftstoffart`,`Leistung`,`Getriebe`,`fahrzeugtyp`,`anzahl_turen`,`hu_bis`,`umweltplakette`,`schadstoffklasse`,`price`,`farbe`)
		 VALUES ('".$dbcnx->real_escape_string($item['name_announce_uk'])."','".$dbcnx->real_escape_string($item['url_announce_uk'])."','".$dbcnx->real_escape_string($item['text_announce_uk'])."','".$dbcnx->real_escape_string($item['img_announce'])."',
		 '".$dbcnx->real_escape_string($item['img_dir'])."',now(),'".$dbcnx->real_escape_string($item['location'])."','".$dbcnx->real_escape_string($item['active_announce'])."','".$dbcnx->real_escape_string($item['marke'])."',
		 '".$dbcnx->real_escape_string($item['modell'])."','".$dbcnx->real_escape_string($item['kilometerstand'])."','".$dbcnx->real_escape_string($item['fahrzeugzustand_uk'])."','".$dbcnx->real_escape_string($item['erstzulassung_uk'])."',
		 '".$dbcnx->real_escape_string($item['kraftstoffart_uk'])."', '".$dbcnx->real_escape_string($item['Leistung'])."', '".$dbcnx->real_escape_string($item['Getriebe_uk'])."', '".$dbcnx->real_escape_string($item['fahrzeugtyp_uk'])."',
		  '".$dbcnx->real_escape_string($item['anzahl_turen'])."','".$dbcnx->real_escape_string($item['hu_bis_uk'])."','".$dbcnx->real_escape_string($item['umweltplakette_uk'])."','".$dbcnx->real_escape_string($item['schadstoffklasse'])."',
		  '".$dbcnx->real_escape_string($item['price'])."','".$dbcnx->real_escape_string($item['farbe_uk'])."')";
		//echo $sqlInsert;
	//	echo $insert="INSERT INTO `zs_announce_auto` (`url_announce`) VALUES ('".$item."')";
		$dbcnx->query($sqlInsert);

	    
	    }
	echo $chkDB;
	//print_r($item['text_announce']);
//////////	
	//echo "\r\n--------------------- \r\n";
//	chkInDB($data,$dbcnx;)
	//$rTranslate=translate($item['text_announce_uk']);
	//print_r($rTranslate);
}
//print_r($arr);

//echo "\r\n".$time_start = microtime(true);


function translit($str) {
    $ukrainian =  array('А', 'Б', 'В', 'Г','Ґ','ґ', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'I', 'Ь', 'Э', 'Ю', 'Я', 'Ї','а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'і', 'ь', 'э', 'ю', 'я','ї');
     
    $translit = array('A', 'B', 'V', 'G', 'G','g','D', 'E', 'E', 'Gh', 'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'C', 'Ch', 'Sh', 'Sch', 'Y', 'I', 'Y', 'E', 'Yu', 'Ya','I', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'i', 'y', 'e', 'yu', 'ya','i');

    return str_replace($ukrainian, $translit, $str);
}


function translate($data){
$translate = new TranslateClient([
    'key' => 'AIzaSyBxO3DBxpNNSSORzAW2sWTxj2zlC3LpExo'
]);

// Translate text from english to french.
//$result = $translate->translate('Guten tag liben Frau und Her!', [
//    'target' => 'uk'
//]);

//$translate->setSource('de');
$result = $translate->translate($data, [
    'target' => 'uk'
]);
print_r($result);
//echo $result['text'] . "\n";
return $result['text'];
}

function chkInDB($data,$dbcnx){
//print_r($data);
    foreach($data as $key=>$item){
	if($key == "img_dir"){
	//echo "---------------------\r\n";print_r($item);
    
    $preg_list="SELECT `id` FROM `zs_announce_auto_uk`  WHERE `img_dir` = '".$item."'";
    $sss=$dbcnx->query($preg_list);
	if($dbcnx->affected_rows == 0) {
	//	print_r($dbcnx->affected_rows); 
	//	echo "nenoshlo $preg_list\r\n";
	//	echo $insert="INSERT INTO `zs_announce_auto` (`url_announce`) VALUES ('".$item."')";
	//	$dbcnx->query($insert);
	$chkDB="NO";
		}
	else {
	///print_r($dbcnx->affected_rows); echo "nashlo  $preg_list\r\n";
	$chkDB="YES";
	    }
	}
    }
    return $chkDB;
}

?>