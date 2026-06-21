<?php

echo "img change";
$size = getimagesize("/home/zsuauto/web/www/img/announce/1720009514.281529/1720009514.2815_0.jpg");
print_r($size);
$size = getimagesize("/home/zsuauto/web/www/img/announce/1720115848.305132/1720115848.305132_0.jpg");
print_r($size);
/*
$x=288; $y=202; // my final thumb
$ratio_thumb=$x/$y; // ratio thumb

list($xx, $yy) = getimagesize($image); // original size
$ratio_original=$xx/$yy; // ratio original

if ($ratio_original>=$ratio_thumb) {
    $yo=$yy; 
    $xo=ceil(($yo*$x)/$y);
    $xo_ini=ceil(($xx-$xo)/2);
    $xy_ini=0;
} else {
    $xo=$xx; 
    $yo=ceil(($xo*$y)/$x);
    $xy_ini=ceil(($yy-$yo)/2);
    $xo_ini=0;
}

imagecopyresampled($thumb, $source, 0, 0, $xo_ini, $xy_ini, $x, $y, $xo, $yo);
*/
?>