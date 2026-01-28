<?php
declare(strict_types=1);

// ------------------------------
// 0) Сессия
// ------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ------------------------------
// 1) Поддерживаемые языки и конфиг путей/локалей
// ------------------------------
$supportedLocales = ['de','en','uk','ru'];
$languages = [
  'ru' => ['code'=>'ru','path'=>'/ru/','aliases'=>['/ru/'],'og'=>'ru_RU'],
  'uk' => ['code'=>'uk','path'=>'/ua/','aliases'=>['/ua/','/uk/'],'og'=>'uk_UA'], // /uk/ как алиас к /ua/
  'en' => ['code'=>'en','path'=>'/en/','aliases'=>['/en/'],'og'=>'en_US'],
  'de' => ['code'=>'de','path'=>'/de/','aliases'=>['/de/'],'og'=>'de_DE'],
];

// host + текущий чистый путь
$base   = 'https://cargocells.com';
$uriRaw = $_SERVER['REQUEST_URI'] ?? '/';
$uri    = parse_url($uriRaw, PHP_URL_PATH) ?: '/';
$uri    = preg_replace('#//+#', '/', $uri);

// разобьём путь
$parts = array_values(array_filter(explode('/', trim($uri, '/'))));

// --- NEW: сопоставление сегмента в URL -> код языка --- //
$segmentToLang = [];
foreach ($languages as $code => $conf) {
    foreach ($conf['aliases'] as $alias) {
        $seg = trim($alias, '/');     // "/ua/" -> "ua"
        if ($seg !== '') $segmentToLang[$seg] = $code;
    }
    // на всякий случай добавим и "правильный" сегмент из path
    $properSeg = trim($conf['path'], '/'); // "/ua" -> "ua"
    if ($properSeg !== '') $segmentToLang[$properSeg] = $code;
}

// ------------------------------
// 2) /lang?set=xx или ?lang=xx — переключение языка (302 на ту же страницу с новым префиксом)
// ------------------------------
$requestedLang = null;

if (($parts[0] ?? '') === 'lang' && isset($_GET['set']) && in_array($_GET['set'], $supportedLocales, true)) {
    $requestedLang = $_GET['set'];
} elseif (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLocales, true)) {
    $requestedLang = $_GET['set'];
} elseif (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLocales, true)) {
    $requestedLang = $_GET['lang'];
}

if ($requestedLang !== null) {
    $_SESSION['lang'] = $requestedLang;
    if (!headers_sent()) {
        setcookie('lang', $requestedLang, ['path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    }

    // вернёмся на ту же страницу, но с префиксом нового языка
    $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '/', PHP_URL_PATH) ?? '/';
    $ref = preg_replace('#//+#', '/', $ref);
    $refTrim = trim($ref, '/');
    $first = strtok($refTrim, '/');

    if ($first && isset($segmentToLang[$first])) {
        // срежем старый префикс (включая алиасы вроде /ua/)
        $refTrim = trim(substr($refTrim, strlen($first)), '/');
    }

    $target = '/' . $requestedLang . '/' . $refTrim;
    if ($refTrim === '') $target = '/' . $requestedLang . '/';

    header('Location: ' . $target, true, 302);
    exit;
}

// ------------------------------
// 3) Язык из URL/сессии/куки, фиксация
// ------------------------------
$firstSeg = $parts[0] ?? null;
$langFromUrl = null;

if ($firstSeg !== null && isset($segmentToLang[$firstSeg])) {
    $langFromUrl = $segmentToLang[$firstSeg]; // например "ua" -> "uk"
}

if ($langFromUrl !== null) {
    $lang = $langFromUrl;
    array_shift($parts);  // срезаем сегмент "ua" или "uk" и т.п.
} else {
    $lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'uk');
    if (!isset($languages[$lang])) $lang = 'uk';
}

$_SESSION['lang'] = $lang;
if (!headers_sent()) {
    setcookie('lang', $lang, ['path'=>'/','httponly'=>true,'samesite'=>'Lax']);
}

// внутренний путь без языкового префикса
$path = implode('/', $parts); // "" | "about.html" | "team.html"

// ------------------------------
// 4) Gettext
// ------------------------------
$domainText = 'messages';
$localeBase = realpath(__DIR__ . '/../locale'); // абсолютный путь обязателен
$localeMap  = ['de'=>'de_DE','en'=>'en_US','uk'=>'uk_UA','ru'=>'ru_RU'];
$localeSys  = $localeMap[$lang] ?? 'uk_UA';

// Чистим переменные окружения, чтобы не перебивали LC_MESSAGES
putenv('LANG'); putenv('LANGUAGE'); putenv('LC_ALL');

// Выставляем локаль
setlocale(LC_ALL,      "{$localeSys}.UTF-8", $localeSys, "{$localeSys}.utf8");
setlocale(LC_MESSAGES, "{$localeSys}.UTF-8", $localeSys, "{$localeSys}.utf8");

// Привязка домена
bindtextdomain($domainText, $localeBase);
bind_textdomain_codeset($domainText, 'UTF-8');
textdomain($domainText);

// Анти-кеш для языковых страниц
header('Vary: Cookie, Accept-Language, Accept-Encoding');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ------------------------------
// 5) Каноникал / OG / hreflang
// ------------------------------
$langConf = $languages[$lang] ?? $languages['en'];

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = preg_replace('#//+#', '/', $uri);

// 5.1 Какой алиас реально в URL
$matchedAlias = null;
foreach ($langConf['aliases'] as $alias) {
    if (strpos($uri, $alias) === 0) { $matchedAlias = $alias; break; }
}

// 5.2 Локальный путь внутри языка
// Было: если алиас не найден — считать главной. Это ложно для /about.html.
// Делаем правильно: используем $path (он уже без префикса языка).
$localPathInsideLang = '/';
if ($matchedAlias !== null) {
    $localPathInsideLang = substr($uri, strlen($matchedAlias) - 1); // "/about.html" или "/"
} else {
    $localPathInsideLang = '/' . ltrim($path, '/');                  // "/about.html" если был /about.html
    if ($localPathInsideLang === '/') $localPathInsideLang = '/';
}

// 5.3 Нормализуем к «правильному» слагу языка в каноникале
$langBase  = rtrim($base, '/') . rtrim($langConf['path'], '/');      // https://easytrade.one/ua
$canonical = $langBase . $localPathInsideLang;                       // https://easytrade.one/ua/about.html или /ua/

// 5.4 Если зашли по старому алиасу /uk/ — 301 на /ua/
if ($matchedAlias === '/uk/') {
    header('Location: ' . $canonical, true, 301);
    exit;
}

// 5.5 Сформируем hreflang для этой же страницы на всех языках
$slug = ltrim($localPathInsideLang, '/'); // "about.html" или "" (главная)

$alternates = [];
foreach ($languages as $l => $conf) {
    $langRoot = rtrim($conf['path'], '/');                            // "/de"
    $pathLang = $slug ? ($langRoot . '/' . $slug) : ($langRoot . '/'); // "/de/about.html" или "/de/"
    $pathLang = preg_replace('#//+#', '/', $pathLang);
    $alternates[$l] = rtrim($base, '/') . $pathLang;
}

$ogLocale = $langConf['og'];

// ------------------------------
// 6) Smarty
// ------------------------------
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

$smarty->assign("THEME", $theme);
$smarty->assign("xlang", $lang);
$smarty->assign("locale", $localeSys);

$domainName = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$smarty->assign("domainName", $domainName);

if (empty($_SESSION['secCode'])) {
    $_SESSION['secCode'] = bin2hex(random_bytes(16));
}
$smarty->assign("secCode", $_SESSION['secCode']);

$smarty->assign('currentPath', $path);
$smarty->assign('canonical',   $canonical);
$smarty->assign('og_locale',   $ogLocale);
$smarty->assign('alternates',  $alternates);

// ------------------------------
// 7) Мини-роутер
// ------------------------------
$ROUTES = [
    ''           => 'main.php',
    '/' => 'main.php',
    'about.html' => 'about.php',
    'about' => 'about.php',
    'service.html'  => 'service.php',
    'service'  => 'service.php',
    'ourteam.html'  => 'ourteam.php',
    'ourteam'  => 'ourteam.php',
    'howitwork'  => 'howitwork.php',
    'industries'  => 'industries.php',
    'contact'  => 'contact.php',
//    'contact.html'  => 'contact.php',
//    'predictions.html'  => 'predictions.php',
//    'ingest_ohlc.php'  => 'ingest_ohlc.php',
    // добавишь остальные при необходимости
];

if (isset($ROUTES[$path])) {
    include_once __DIR__ . '/' . $ROUTES[$path];
    exit;
}

// 404
http_response_code(404);
include_once __DIR__ . '/404.php';
