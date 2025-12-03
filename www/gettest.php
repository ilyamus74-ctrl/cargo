<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 0) Константы
$domain = 'messages';
$base   = realpath(__DIR__ . '/../locale');  // ДОЛЖЕН дать /home/easyt/web/locale
$loc    = 'de_DE';                           // меняй на ru_RU / uk_UA для проверки

// 1) Чистим окружение, чтобы оно не перебивало LC_MESSAGES
putenv('LANG');       // unset
putenv('LANGUAGE');   // unset
putenv('LC_ALL');     // unset

// 2) Выставляем локаль (обе категории, с UTF-8)
setlocale(LC_ALL,      "$loc.UTF-8", $loc, "$loc.utf8");
setlocale(LC_MESSAGES, "$loc.UTF-8", $loc, "$loc.utf8");

// 3) Привязываем домен и кодировку
bindtextdomain($domain, $base);
bind_textdomain_codeset($domain, 'UTF-8');
textdomain($domain);

// 4) Диагностика
header('Content-Type: text/plain; charset=UTF-8');
echo "realpath: $base\n";
echo "LC_MESSAGES: " . setlocale(LC_MESSAGES, 0) . "\n";
echo "bind: " . bindtextdomain($domain, null) . "\n";

// Проверим что файл существует и читается именно там, где должен
$mo = "$base/$loc/LC_MESSAGES/$domain.mo";
echo "mo_exists: " . (is_readable($mo) ? "yes ($mo)" : "NO ($mo)") . "\n";

// 5) Пробы (ИСПОЛЬЗУЙ ТОЧНОЕ msgid из PO!)
$keys = ['simpleAbout.H2','simpleAboutH3','asd'];
foreach ($keys as $k) {
    $v = dgettext($domain, $k);
    echo "$k => $v\n";
}
