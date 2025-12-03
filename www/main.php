<?php
echo "main.php";
/*
session_start();
require_once __DIR__ . '/../configs/secure.php';

//include("/home/zsuauto/web/configs/connectDB.php");
*/
/*
$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE  `active_announce` != 'S' AND DATE(`date_in_announce`) > (NOW() - INTERVAL 3 DAY)  ORDER BY `id` DESC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$newCars[]=$idpp;
	}

$preg_list="SELECT * FROM `zs_announce_auto_uk` WHERE `active_announce` != 'S'  ORDER BY `id` ASC LIMIT 8";
$sss1=$dbcnx->query($preg_list);
	while($idpp = $sss1->fetch_assoc()){
	$idpp['img_announce']=json_decode($idpp['img_announce']);
	$allCars[]=$idpp;
	}

$smarty->assign("newCars",$newCars);
$smarty->assign("allCars",$allCars);
//$data['main_text']="Цей ресурс саме для тебе! Ми шукаємо та викладаємо надійні, технічно справні та доступні авто з Европи за адекватні кошти.<br>Є питання ?";
$data['main_text_h1']="Gräßler Sicherheitsdienste ";
$data['main_text_h2']="Die Sicherheit Ihres Unternehmens rund um die Uhr.";
$data['main_text_h3']="Sicherheit an schwierigen Orten";
$data['description']="Gräßler Sicherheitsdienste";
$data['title']="Gräßler Sicherheitsdienste";
$data['keywords']="Gräßler Sicherheitsdienste , Sicherheit, Familiensicherheitsunternehmen, Zuverlässigkeit, Proffessiolität, Schutz, Sicherheitssystem ";
$data['mainMenu']="main";

//$smarty->assign("SESSION",$_SESSION);
$smarty->assign("data",$data);
$smarty->display('index.html');
*/
// простой хендлер главной страницы
// ожидает, что $smarty уже инициализирован в index.php
/*
if (!isset($smarty)) {
    // на случай кривого вызова
    require_once __DIR__ . '/../libs/Smarty.class.php';
    $smarty = class_exists('\\Smarty\\Smarty') ? new \Smarty\Smarty : new Smarty();
    require_once __DIR__ . '/patch.php';
}
*/
/*
$lang = $_SESSION['lang'] ?? 'uk'; // пример
$base = 'https://easytrade.one';
$pathByLang = ['ru'=>'/ru/','uk'=>'/ua/','en'=>'/en/','de'=>'/de/'];
$canonical = $base . ($pathByLang[$lang] ?? '/');
*/
// заголовки/мета — по желанию
//$smarty->assign('page_title', _('Home'));
/*
$header_data['domainName']=$domainName;
$header_data['title']="mainTitle";
$header_data['description']="mainDescription";
$header_data['keywords']="mainKeywords";
$header_data['author']="";
$header_data['canonical']=$canonical;
$header_data['siteName']="";
$header_data['http-equiv']="";
$header_data['charset']="";
$header_data['soc_og_title']="main_soc_og_title";
$header_data['soc_og_description']="main_soc_og_dscription";
$header_data['twitter_title']="main_twitter_title";
$header_data['twitter_description']="main_twitter_description";


$smarty->assign('header_data', $header_data);
$smarty->assign('main','main');
*/
//$smarty->display('index.html');

?>