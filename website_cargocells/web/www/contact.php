<?php
session_start();


// простой хендлер главной страницы
// ожидает, что $smarty уже инициализирован в index.php
if (!isset($smarty)) {
    // на случай кривого вызова
    require_once __DIR__ . '/../libs/Smarty.class.php';
    $smarty = class_exists('\\Smarty\\Smarty') ? new \Smarty\Smarty : new Smarty();
    require_once __DIR__ . '/patch.php';
}

// заголовки/мета — по желанию
$header_data['domainName']=$domainName;
$header_data['title']="ceoContactTitle";
$header_data['description']="ceoContactTitle";
$header_data['keywords']="ceoContactKeywords";
$header_data['author']="";
$header_data['canonical']=$canonical;
$header_data['siteName']="";
$header_data['http-equiv']="";
$header_data['charset']="";
$header_data['soc_og_title']="ceoContactTitle";
$header_data['soc_og_description']="ceoContactTitle";
$header_data['twitter_title']="ceoContactTitle";
$header_data['twitter_description']="ceoContactTitle";
$smarty->assign('header_data', $header_data);

// заголовки/мета — по желанию
$smarty->assign('page_title', _('Home'));
$smarty->assign('contact','contact');

$smarty->display('cc_index.html');

?>
