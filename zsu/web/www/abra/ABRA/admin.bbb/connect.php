<?php
session_start();
$dblocation = "localhost";
$dbname = "nc4info";
$dbuser = "infoNC4";
$dbpasswd = "infoNEWS";
$dbcnx = @mysql_connect($dblocation,$dbuser,$dbpasswd);
@mysql_query('set character_set_client="utf8"');
@mysql_query('set character_set_results="utf8"');
@mysql_query('set collation_connection="utf8_general_ci"');
if (!$dbcnx) 
{
echo( "<P> В настоящий момент сервер базы данных не доступен, поэтому корректное отображение страницы невозможно. </P>" );
exit();
}
if (!@mysql_select_db($dbname, $dbcnx)) 
{
echo( "<P> В настоящий момент база данных не доступна, поэтому корректное отображение страницы невозможно. .</P>" );
exit();
}
?>
