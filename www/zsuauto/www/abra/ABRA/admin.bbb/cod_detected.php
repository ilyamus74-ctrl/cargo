<?php
function detect_encoding($string, $pattern_size = 50)
{
    $list = array (www.php.net/array)('cp1251', 'utf-8', 'ascii', '855', 'KOI8R', 'ISO-IR-111', 'CP866', 'KOI8U');
    $c = strlen (www.php.net/strlen)($string);
    if ($c > $pattern_size)
    {
        $string = substr (www.php.net/substr)($string, floor (www.php.net/floor)(($c - $pattern_size) /2), $pattern_size);
        $c = $pattern_size;
    }

    $reg1 = '/(\xE0|\xE5|\xE8|\xEE|\xF3|\xFB|\xFD|\xFE|\xFF)/i';
    $reg2 = '/(\xE1|\xE2|\xE3|\xE4|\xE6|\xE7|\xE9|\xEA|\xEB|\xEC|\xED|\xEF|\xF0|\xF1|\xF2|\xF4|\xF5|\xF6|\xF7|\xF8|\xF9|\xFA|\xFC)/i';

    $mk = 10000;
    $enc = 'ascii';
    foreach ($list as $item)
    {
        $sample1 = @iconv (www.php.net/iconv)($item, 'cp1251', $string);
        $gl = @preg_match_all (www.php.net/preg_match_all)($reg1, $sample1, $arr);
        $sl = @preg_match_all (www.php.net/preg_match_all)($reg2, $sample1, $arr);
        if (!$gl || !$sl) continue;
        $k = abs (www.php.net/abs)(3 - ($sl / $gl));
        $k += $c - $gl - $sl;
        if ($k < $mk)
        {
            $enc = $item;
            $mk = $k;
        }
    }
    return $enc;
}
?>