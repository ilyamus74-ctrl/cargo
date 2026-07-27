<?php
session_start();
//$_SESSION['locale_i']=1;
//$_SESSION['locale_b']="ru_UA";
//print_r($_COOKIE);
////$locle_i=$_COOKIE["loclae_i"];
////$locle_b=$_COOKIE["loclae_b"];
////$locle_c=$_COOKIE["loclae_c"];
//echo "$locle_i";
if(empty($_SESSION['locale_i']) or empty($_SESSION['locale_b']) )
    {
	if(empty($locle_i)) 
	    {
	    $lang_set=$_SERVER['HTTP_ACCEPT_LANGUAGE'];
	    $lang_set=explode(",",$lang_set);
		    if(stristr($lang_set[0], "-") == TRUE) { $lang_set=explode("-",$lang_set[0]);}
	    //print_r($lang_set);
		if(!empty($lang_set[0]))
		{
	//    $list_lang=mysql_query("SELECT `id_lang`,`name`,`locale` FROM `wh_lang` WHERE `name`='$lang_set[0]'") or die ("Invalid:" . mysql_error());
	//    list($id_lang,$locale_c,$locale_b)=mysql_fetch_row($list_lang);
		    if($id_lang!=""){
		    $_SESSION['locale_i']=$id_lang;
		    $_SESSION['locale_b']=$locale_b;
		    $_SESSION['locale_c']=$locale_c;
		//    setcookie("loclae_i",$id_lang,time()+60*60*24*182); //в секундах полгода
		//    setcookie("loclae_b",$locale_b,time()+60*60*24*182); //в секундах полгода
		//    setcookie("loclae_c",$locale_c,time()+60*60*24*182); //в секундах полгода
			    }
		else
		    {  $_SESSION['locale_i']=1;
		    $_SESSION['locale_b']="uk_UA";
		//    setcookie("loclae_i",1,time()+60*60*24*182); //в секундах полгода
		//    setcookie("loclae_b","ru_UA",time()+60*60*24*182); //в секундах полгода
		//    setcookie("loclae_c","ru",time()+60*60*24*182); //в секундах полгода
		    } //установка языка по умолчанию русский
		}
		else {	$_SESSION['locale_i']=1;
		    $_SESSION['locale_b']="uk_UA";
		    $_SESSION['locale_c']="uk";
		     } //установка языка по умолчанию русский
	    }
	else {
	$_SESSION['locale_i']=$locle_i;
	$_SESSION['locale_b']=$locle_b;
	$_SESSION['locale_c']=$locle_c;
	    }
    }
/*
$new_ilang=mysql_escape_string($_POST['new_ilang']);
if(!empty($new_ilang))
	{
	//echo "$new_ilang";
	$list_lang=mysql_query("SELECT `id_lang`,`name`,`locale` FROM `wh_lang` WHERE `id_lang`='$new_ilang'") or die ("Invalid:" . mysql_error());
	list($id_lang,$locale_c,$locale_b)=mysql_fetch_row($list_lang);
		    $_SESSION['locale_i']=$id_lang;
		    $_SESSION['locale_b']=$locale_b;
		    $_SESSION['locale_c']=$locale_c;
		    setcookie("loclae_i",$id_lang,time()+60*60*24*182); //в секундах полгода
		    setcookie("loclae_b",$locale_b,time()+60*60*24*182); //в секундах полгода
		    setcookie("loclae_c",$locale_c,time()+60*60*24*182); //в секундах полгода
	}
setcookie("loclae_i",$_SESSION['locale_i'],time()+60*60*24*182); //в секундах полгода
setcookie("loclae_b",$_SESSION['locale_b'],time()+60*60*24*182); //в секундах полгода
setcookie("loclae_c",$_SESSION['locale_c'],time()+60*60*24*182); //в секундах полгода
*/
//echo "a";
setlocale(LC_ALL,$_SESSION['locale_b']);
bindtextdomain("messages","/home/zsuauto/web/locale");
bind_textdomain_codeset("messages","UTF-8");
textdomain("messages");

?>
