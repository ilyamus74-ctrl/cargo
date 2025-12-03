<?php
declare(strict_types=1);

// 0) Сессия
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../configs/secure.php';

// 1) Язык (БЕЗ префиксов в URL)
$supportedLocales = ['de','en','uk','ru'];
$localeMap  = ['de'=>'de_DE','en'=>'en_US','uk'=>'uk_UA','ru'=>'ru_RU'];

// берем из сессии/куки, по умолчанию 'uk'
$lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'uk');
if (!in_array($lang, $supportedLocales, true)) {
    $lang = 'uk';
}

// по желанию: ?lang=ru для переключения (но без влияния на URL)
if (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLocales, true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
    if (!headers_sent()) {
        setcookie('lang', $lang, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}

$domainText = 'messages';
$localeBase = realpath(__DIR__ . '/../locale');
$localeSys  = $localeMap[$lang] ?? 'uk_UA';

// Gettext
putenv('LANG');
putenv('LANGUAGE');
putenv('LC_ALL');

setlocale(LC_ALL,      "{$localeSys}.UTF-8", $localeSys, "{$localeSys}.utf8");
setlocale(LC_MESSAGES, "{$localeSys}.UTF-8", $localeSys, "{$localeSys}.utf8");

bindtextdomain($domainText, $localeBase);
bind_textdomain_codeset($domainText, 'UTF-8');
textdomain($domainText);

// Анти-кеш
header('Vary: Cookie, Accept-Language, Accept-Encoding');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 2) Путь без языковых префиксов
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = preg_replace('#//+#', '/', $uri);
$path = trim($uri, '/');   // "" | "login.html" | "main" ...

// 3) Smarty
require_once __DIR__ . '/../libs/Smarty.class.php';
$smarty = class_exists('\\Smarty\\Smarty') ? new \Smarty\Smarty : new Smarty();
require_once __DIR__ . '/patch.php';

// плагины перевода
$smarty->registerPlugin('modifier', '__', function($msgid) use ($domainText) {
    return dgettext($domainText, (string)$msgid);
});
$smarty->registerPlugin('function', 't', function($params) use ($domainText) {
    $key = $params['key'] ?? '';
    return dgettext($domainText, (string)$key);
});

// базовые assign'ы
$theme = "/templates";
$smarty->debugging      = false;
$smarty->caching        = false;
$smarty->cache_lifetime = 0;

$smarty->assign("THEME",  $theme);
$smarty->assign("xlang",  $lang);       // теперь это просто код языка, НЕ часть URL
$smarty->assign("locale", $localeSys);

$domainName = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$smarty->assign("domainName", $domainName);

if (empty($_SESSION['secCode'])) {
    $_SESSION['secCode'] = bin2hex(random_bytes(16));
}
$smarty->assign("secCode", $_SESSION['secCode']);

$smarty->assign('currentPath', $path);

// 4) Мини-роутер БЕЗ /ua/ /ru/

$ROUTES = [
    ''           => 'main.php',
    'main'       => 'main.php',
    'login.html' => 'login.php',
    'login.php'  => 'login.php',
    'logout.html'=> 'logout.php',
    'logout.php' => 'logout.php',
    // сюда потом добавишь остальные страницы
];

// скрипты, доступные без авторизации
$PUBLIC_SCRIPTS = [
    'login.php',
    '404.php',
];

if (isset($ROUTES[$path])) {
    $script = $ROUTES[$path];

    if (!in_array($script, $PUBLIC_SCRIPTS, true)) {
       auth_require_login();   // без $lang, просто редирект на /login.html
    }

    // login.php, main.php, logout.php и т.п.
    include_once __DIR__ . '/' . $script;
    exit;
}


// 404
http_response_code(404);
include_once __DIR__ . '/404.php';
