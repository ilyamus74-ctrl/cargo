<?php
//php7 mysql
$dblocation = "localhost";
$dbname = "cells_TLSCargo";
$dbuser = "TLSCargo";
$dbpasswd = "LOIUByo8776757^%$4333y!!!";
$dbcnx = new mysqli($dblocation,$dbuser,$dbpasswd,$dbname);
$dbcnx->query("set character_set_client=\"utf8\"");
$dbcnx->query("set character_set_results=\"utf8\"");
$dbcnx->query("set collation_connection=\"utf8_general_ci\"");

if($dbcnx->connect_error){
    die ("Connect Error (" . $dbcnx->connect_errno . ")". $dbcnx->connect_error);
	}
//$dbcnx->set_charset('utf-8');



?>
