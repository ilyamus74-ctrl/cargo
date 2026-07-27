<?php
session_start();
include("/home/zsuauto/web/configs/connectDB.php");

//print_r($_POST);
//print_r($_GET);
//echo "start";

//require('patch.php');

/*
require('../libs/Smarty.class.php');
$smarty = new Smarty;
require('patch.php');
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;
$smarty->assign(THEME,$theme);
*/
if(empty($theme)){
include_once("setlocale/locale.php");
require_once("../libs/Smarty.class.php");
//$smarty = new Smarty;
$smarty = new \Smarty\Smarty;

require_once("patch.php");
$theme="/templates";
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 0;
//$smarty->setErrorReporting(E_ALL & ~E_WARNING & ~E_NOTICE);
$smarty->assign("THEME",$theme);
}

if(!empty($url_razborka[2])){
$dRazborkaUrl=explode("-",$url_razborka[2]);
//$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `img_dir` = '".$url_razborka[2]."'";
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `img_dir` = '".$dRazborkaUrl[0]."'";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$viewCar=$idpp;
	}
	//print_r($viewCar);
$smarty->assign("viewCar",$viewCar);
}
else{
header("Location: /all-cars");
}

$cc=0;
foreach($viewCar['img_announce'] as $key=>$item){
    if(empty($vImg)) {$vImg='"image":["https://'.$domainName.'/img/announce/'.$viewCar['img_dir'].'/'.$item.'"';}
    if(array_key_last($viewCar['img_announce']) == $key) {$vImg.=',"https://'.$domainName.'/img/announce/'.$viewCar['img_dir'].'/'.$item.'"]';}
    else {$vImg.=',"https://'.$domainName.'/img/announce/'.$viewCar['img_dir'].'/'.$item.'"';}
    if($cc == 5) { $vImg.="]";
	break;
    
    }
$cc++;
}
//$vImg="\"image\":[ https://".$domainName."/img/announce/".$viewCar['img_dir']."/".$viewCar['img_announce'][0]." ]";
if(empty($viewCar['erstzulassung'])) {$viewCar['erstzulassung']="1998";}
$microprope='<script type="application/ld+json">{
      "@context": "https://schema.org",
      "@type": "Car",
      "name": "'.$viewCar['name_announce'].'",
      '.$vImg.',
      "url": "https://'.$domainName.'/view/'.$viewCar['img_dir'].'-'.$viewCar['url_announce'].'",
      "offers": {
        "@type": "Offer",
        "availability": "https://schema.org/InStock",
        "price": '.$viewCar['price'].',
        "vehicleIdentificationNumber": "",
        "priceCurrency": "EUR"
      },
      "itemCondition": "https://schema.org/UsedCondition",
      "brand": {
        "name": "'.$viewCar['marke'].' '.$viewCar['modell'].'"
        
      },
      "model": "'.$viewCar['modell'].'",
      "vehicleConfiguration": "",
      "vehicleModelDate": "'.preg_replace('/[^0-9]/','',$viewCar['erstzulassung']).'-01-01",
      "mileageFromOdometer": {
        "@type": "QuantitativeValue",
        "value": "'.$viewCar['kilometerstand'].'",
        "unitCode": "KMT"
      },
      "vehicleInteriorType": "Типовий",
      "color": "'.$viewCar['farbe'].'",
      "vehicleInteriorColor": "",
      "bodyType": "'.$viewCar['fahrzeugtyp'].'",
      "driveWheelConfiguration": "https://schema.org/FourWheelDriveConfiguration",
      "knownVehicleDamages":"'.$viewCar['fahrzeugzustand'].'",
      "vehicleEngine": {
        "fuelType": "'.$viewCar['kraftstoffart'].'",
        "enginePower": "'.$viewCar['Leistung'].'"
      },
      "vehicleTransmission": "'.$viewCar['Getriebe'].'",
      "numberOfDoors": "'.$viewCar['anzahl_turen'].'",
      "vehicleSeatingCapacity": ""
    }
  </script>';



function normalize_int($v){ return (int)preg_replace('/\D+/', '', (string)$v); }
function km_label($n){ return number_format((int)$n, 0, '.', ' ').' км'; }

function month_num_from_str($s){
  $map = [
    // укр
    'січень'=>1,'лютий'=>2,'березень'=>3,'квітень'=>4,'травень'=>5,'червень'=>6,'липень'=>7,'серпень'=>8,'вересень'=>9,'жовтень'=>10,'листопад'=>11,'грудень'=>12,
    // de + fallback
    'januar'=>1,'februar'=>2,'märz'=>3,'maerz'=>3,'april'=>4,'mai'=>5,'juni'=>6,'juli'=>7,'august'=>8,'september'=>9,'oktober'=>10,'november'=>11,'dezember'=>12
  ];
  $s = mb_strtolower((string)$s, 'UTF-8');
  foreach($map as $k=>$n){ if(strpos($s,$k)!==false) return str_pad($n,2,'0',STR_PAD_LEFT); }
  return '01';
}
function parse_model_date($s){
  if(!$s) return null;
  if(preg_match('/(19|20)\d{2}/', $s, $m)){
    $y = $m[0];
    return $y.'-'.month_num_from_str($s); // YYYY-MM
  }
  return null;
}
function prune($arr){ // рекурсивно выкидывает пустое
  foreach($arr as $k=>$v){
    if(is_array($v)) $arr[$k]=prune($v);
    if($arr[$k]==='' || $arr[$k]===null || (is_array($arr[$k]) && $arr[$k]===[])) unset($arr[$k]);
  }
  return $arr;
}

// ===== входные =====
$domain = $domainName; // у тебя уже есть
$brand  = trim($viewCar['marke']  ?? '');
$model  = trim($viewCar['modell'] ?? '');
$km     = normalize_int($viewCar['kilometerstand'] ?? 0);
$price  = (string)normalize_int($viewCar['price'] ?? 0);
$color  = trim($viewCar['farbe'] ?? '');
$gear   = trim($viewCar['Getriebe'] ?? '');
$fuel   = trim($viewCar['kraftstoffart'] ?? '');
$power  = trim($viewCar['Leistung'] ?? ''); // "102 PS"
$body   = trim($viewCar['fahrzeugtyp'] ?? '');
$state  = trim($viewCar['fahrzeugzustand'] ?? '');
$hu     = trim($viewCar['hu_bis'] ?? '');

$ym     = parse_model_date($viewCar['erstzulassung'] ?? '');
$year   = $ym ? substr($ym,0,4) : (preg_match('/(19|20)\d{2}/', (string)($viewCar['erstzulassung'] ?? ''), $m) ? $m[0] : '');

$imgBase = 'https://'.$domain.'/img/announce/'.($viewCar['img_dir'] ?? '').'/';
$images  = [];
foreach(($viewCar['img_announce'] ?? []) as $i=>$img){
  $images[] = $imgBase.$img;
  if($i===7) break; // до 8 картинок достаточно
}
if(!$images) $images[] = 'https://'.$domain.'/assets/logo/favicon.png';

// грубая эвристика по мотору (если в name_announce есть "2.5" и т.п.)
$engine = '';
if (preg_match('/\d\.\d\s*[A-Za-zА-Яа-я\-]*/u', (string)($viewCar['name_announce'] ?? ''), $m2)) {
  $engine = trim($m2[0]);
}

// slug (ASCII) — можно заменить на свой
$slug = 'car/'.($viewCar['id'] ?? 'x').'/'.preg_replace('~[^a-z0-9\-]+~','-',
  strtolower(iconv('UTF-8','ASCII//TRANSLIT',$brand.' '.$model.' '.$year.' '.$km.'km'))
);
$canonical = 'https://'.$domain.'/'.$slug;

// ===== SEO тексты =====
$title = sprintf(
  '%s %s%s%s — %s • €%s | ZSU Auto',
  $brand,
  $model,
  $engine ? (' '.$engine) : '',
  $year   ? (' ('.$year.')') : '',
  $km ? km_label($km) : '',
  $price ?: '—'
);

$descBits = [];
if($year)                 $descBits[] = $year.' р.';
if($fuel)                 $descBits[] = $fuel;
if($gear)                 $descBits[] = $gear;
if($power)                $descBits[] = $power;
if($km)                   $descBits[] = km_label($km);
if($hu)                   $descBits[] = 'ТО до '.$hu;
if($color)                $descBits[] = 'колір: '.$color;

$meta_description = sprintf('%s %s — %s. %s',
  $brand, $model,
  $price ? '€'.$price : 'ціна за запитом',
  implode(', ', $descBits)
);

// ===== JSON-LD =====
$jsonld = [
  '@context'=>'https://schema.org',
  '@type'=>'Vehicle',
  'name'=>trim($brand.' '.$model.' '.($engine? $engine.' ':'').($year? '('.$year.')':'')),
  'brand'=>['@type'=>'Brand','name'=>$brand],
  'model'=>$model,
  'vehicleModelDate'=>$year ?: null,      // YYYY
  'productionDate'=>$ym ?: null,          // YYYY-MM
  'bodyType'=>$body ?: null,
  'fuelType'=>$fuel ?: null,
  'vehicleTransmission'=>$gear ?: null,
  'vehicleEngine'=>[
    '@type'=>'EngineSpecification',
    'name'=>$engine ?: null,
    'fuelType'=>$fuel ?: null
  ],
  'mileageFromOdometer'=>[
    '@type'=>'QuantitativeValue',
    'value'=>$km ?: null,
    'unitCode'=>'KMT'
  ],
  'color'=>$color ?: null,
  'itemCondition'=>'https://schema.org/UsedCondition',
  'image'=>$images,
  'url'=>$canonical,
  'offers'=>[
    '@type'=>'Offer',
    'price'=>$price ?: null,
    'priceCurrency'=>'EUR',
    'availability'=>'https://schema.org/InStock',
    'url'=>$canonical,
    'seller'=>['@type'=>'Organization','name'=>'ZSU Auto']
  ],
  'additionalProperty'=>[
    ['@type'=>'PropertyValue','name'=>'Стан','value'=>$state ?: 'Вживане'],
    ['@type'=>'PropertyValue','name'=>'ТО дійсне до','value'=>$hu ?: null],
  ],
];
$jsonld = prune($jsonld);
$jsonld_str = '<script type="application/ld+json">'.json_encode($jsonld, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';

// ===== единый массив для Smarty =====
$meta = [
  'title'       => $title,
  'description' => $meta_description,
  'keywords'    => $viewCar['name_announce'] ?? ($brand.' '.$model),
  'canonical'   => $canonical,
  'og' => [
    'title'       => $title,
    'description' => $meta_description,
    'type'        => 'product',
    'url'         => $canonical,
    'site_name'   => 'ZSU Auto',
    'image'       => $images[0] ?? ''
  ],
  'h1'    => sprintf('%s %s%s%s — €%s',
              $brand,$model,
              $engine?(' '.$engine):'',
              $year?(' ('.$year.')'):'',
              $price ?: '—'
            ),
  'intro' => $viewCar['short_text_announce'] ?: 'Авто на ходу. Деталі й комплектація нижче.',
  'jsonld' => $jsonld_str
];

$breadcrumbs = [
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type'=>'ListItem','position'=>1,'name'=>'Головна','item'=>'https://'.$domain.'/'],
    ['@type'=>'ListItem','position'=>2,'name'=>$brand,'item'=>'https://'.$domain.'/brand/'.strtolower($brand)],
    ['@type'=>'ListItem','position'=>3,'name'=>$model,'item'=>'https://'.$domain.'/model/'.strtolower($model)],
    ['@type'=>'ListItem','position'=>4,'name'=>$meta['h1'] ?? ($brand.' '.$model),'item'=>$canonical],
  ]
];

$org = [
  '@type'=>'Organization',
  'name'=>'ZSU Auto',
  'url'=>'https://'.$domain.'/',
  'logo'=>'https://'.$domain.'/assets/logo/favicon.png',
  'contactPoint'=>[
    '@type'=>'ContactPoint',
    'contactType'=>'sales',
    'telephone'=>'+380XXXXXXXXX'
  ]
];

$jsonld_graph = [
  '@context'=>'https://schema.org',
  '@graph'=>[$jsonld, $breadcrumbs, $org]
];

$meta['jsonld'] = '<script type="application/ld+json">'.json_encode($jsonld_graph, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';


$smarty->assign('meta', $meta);

$smarty->assign("microprope",$microprope);
//print_r($_SERVER);
$smarty->assign("reqUrl","https://".$domainName.$_SERVER['REDIRECT_URL']);
//get_status_orders($dbcnx_sklad,$provider);


//echo "aaaa";
/*
$data['main_text']='
Ми група волонтерів яка прагне допомогти нашим захисникам в здобутті перемоги над агресором. Ми прагнемо цього не менше за наших захисників. Тому ми витрачаємо свій час та сили на пошук автівок для наших захисників на фронт для виконання бойових та логістичних завдань.
<br>Ми запустили цей проєкт та долучаємо небайдужих людей які мають час та змогу в пошуку <a href="avto-dlya-zsu.html" target="_blank" style="color:white">Авто для ЗСУ</a> по всій Европі.
<br><a href="poshuk-i-pokupka-avto-dlya-zsu.html" target="_blank" style="color:white">Пошук і покупка авто для ЗСУ</a> за кордоном це той ще квест тому радимо звертатися до тих хто його вже пройшов та знає про всі підводні камінці.

<br><br>
<a href="prodag-ta-kupivlya-avto-dlya-zsu.html" target="_blank" style="color:white">Продаж та купівля авто для ЗСУ</a> можливе тільки на території іноземних країн таких як Німеччина, Польща, Чехія, Словаччина та інші. В Україну такі авто для ЗСУ ввозиться як гуманітарний вантаж (допомога) та передається на облік волонтерському фонду для подальшої передачі військовим на вимогу. Таке авто не може бути відчужено , продано не певний період часу.
<br>На питання <a href="zvidki-deshevshe-prignati-avto.html" target="_blank" style="color:white">Звідки дешевше пригнати авто?</a> Немає сто відсоткових відповідей. Хтось готовий подарувати авто для військових, а іноді просять суму начеб то авто тільки з конвеєру заводу виїхало. Можна з певністю сказати одне, що добре авто в гарному технічному стані буде коштуватиме дорожче навіть в самій дешевій країні Европи, в такому разі не соромно купити авто для військових. Тим паче якщо є <a href="kupit-avto-dlya-zsu-nedorogo.html" target="_blank" style="color:white">купити авто для ЗСУ недорого</a> відповідно стану.
<br> 
<br><a href="prigon-avto-dlya-viyskovih.html" target="_blank" style="color:white">Пригон авто для військових</a> зазвичай триває від декількох діб до одного тижня. Процедура оформлення залежить від часу оформлення документів та відстані до кордону. Чим більша відстань тим довше їхати. Зазвичай це 1000км за 10 годин. Тут треба робити поправки на черги на кордоні та час відпочинку в дорозі.						

';*/
//print_r($url_razborka);
//$data['main_text_h1']="Детально про авто ";
$data['description']="Опис об'яви від продавця авто для ЗСУ - ".$viewCar['name_announce'];
$data['title']=$viewCar['name_announce'];
$data['keywords']=$viewCar['name_announce'];
$smarty->assign("data",$data);


$smarty->assign("pageView","view");
//$smarty->display("index.tpl");
$smarty->display("index.html");

?>
