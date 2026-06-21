<?php

$sec=30;
header("Refresh: $sec; https://zsuauto.info/t/index2.php");

$csvData=file_get_contents("future_tradesV2.csv");
$lines = explode(PHP_EOL, $csvData);
$array = array();
foreach ($lines as $line) {
    $futureArr[] = str_getcsv($line);
}
//print_r($futureArr);

foreach ($futureArr as $key=>$item){
    if(empty($dtable)){$dtable="<table>
  <tr>
    <th>timestamp_utc</th>
    <th>direction_pred Down=0 / UP=1</th>
    <th>magnitude_pred</th>
    <th>price_entry</th>
    <th>price_exit</th>
    <th>direction_prob</th>
    <th>currency_pair</th>
    <th>event (news)</th>
    <th>is_removed (0 not deleted news)</th>
    <th>priority</th>
  </tr>";}
    else{
     $dtable.=" <tr>
    <td>".$item[0]."</td>
    <td>".$item[1]."</td>
    <td>".$item[2]."</td>
    <td>".$item[3]."</td>
    <td>".$item[4]."</td>
    <td>".$item[5]."</td>
    <td>".$item[6]."</td>
    <td>".$item[7]."</td>
    <td>".$item[8]."</td>
    <td>".$item[9]."</td>
  </tr>";

    }
    
//print_r($item);
}
 $dtable.="</table>";

$table="
<!DOCTYPE html>
<html>
<head>
<style>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
</head>
<body>

<h2>EUR/USD Table FUTRURE test</h2>
".$dtable."

</body>
</html>
";

print_r($table);
/*
<table>
  <tr>
    <th>Company</th>
    <th>Contact</th>
    <th>Country</th>
  </tr>
  <tr>
    <td>Alfreds Futterkiste</td>
    <td>Maria Anders</td>
    <td>Germany</td>
  </tr>
  <tr>
    <td>Centro comercial Moctezuma</td>
    <td>Francisco Chang</td>
    <td>Mexico</td>
  </tr>
  <tr>
    <td>Ernst Handel</td>
    <td>Roland Mendel</td>
    <td>Austria</td>
  </tr>
  <tr>
    <td>Island Trading</td>
    <td>Helen Bennett</td>
    <td>UK</td>
  </tr>
  <tr>
    <td>Laughing Bacchus Winecellars</td>
    <td>Yoshi Tannamuri</td>
    <td>Canada</td>
  </tr>
  <tr>
    <td>Magazzini Alimentari Riuniti</td>
    <td>Giovanni Rovelli</td>
    <td>Italy</td>
  </tr>
</table>
*/


?>