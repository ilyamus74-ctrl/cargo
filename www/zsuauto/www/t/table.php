<?php


echo "data from table ->".$_GET['table'];

include("/home/zsuauto/web/www/t/connectDB.php");

if(!empty($_GET['table'])){
echo "show \r\n <br>";
    $sql_db = "SELECT * FROM `".$_GET['table']."` ORDER BY `id` ASC  ";
//    $sql_db = "SELECT * FROM `economic_news_model_grok` ORDER BY `id` ASC LIMIT 5000";
    $result_db = $dbcnx->query($sql_db);
    while ($row = $result_db->fetch_assoc()) {
    $t[]=$row;
    //print_r($row);
    }
}
print_r($t);

?>