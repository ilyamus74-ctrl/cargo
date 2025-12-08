<?php

include_once("/home/easyt/web/configs/connectDB.php"); 

$table=$_GET['table'];
$table="predictETH3";
//print_r($table);
$cache_result = $dbcnx->query("
    SELECT *
    FROM ".$table."
    WHERE `ts_utc` >=  DATE(NOW()) - INTERVAL 1 DAY OR `ts_utc` <  DATE(NOW()) + INTERVAL 1 DAY

");
//$volatility_cache = [];
//$trend_cache = [];
//$max_volatility = 0;
while ($row = $cache_result->fetch_assoc()) {
//    print_r($row);
    $arr[$row['id']]=$row;
//    $key = $row['currency'] . '|' . $row['event'];
//    $volatility_cache[$key] = (float)$row['volatility'];
//    $trend_cache[$key] = $row['trend'] !== null ? (float)$row['trend'] : 0;
//    $max_volatility = max($max_volatility, $row['volatility']);
}
$rrr=json_encode($arr);
print_r($rrr);
//print_r(json_encode($arr)));

?>