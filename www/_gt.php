<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$domain = 'messages';
$base   = realpath(__DIR__ . '/../locale'); // ДОЛЖЕН вернуть реальный путь
$loc    = 'ru_RU';

var_dump(['realpath'=>$base]);

putenv('LANG'); putenv('LANGUAGE'); putenv('LC_ALL');

setlocale(LC_ALL,      "$loc.UTF-8", $loc, "$loc.utf8");
setlocale(LC_MESSAGES, "$loc.UTF-8", $loc, "$loc.utf8");

bindtextdomain($domain, $base);
bind_textdomain_codeset($domain, 'UTF-8');
textdomain($domain);

echo "LC_MESSAGES=" . setlocale(LC_MESSAGES,0) . "\n";
echo "bind=" . bindtextdomain($domain, null) . "\n";
echo "probe=" . dgettext($domain, "simpleAbout.H2") . "\n";
