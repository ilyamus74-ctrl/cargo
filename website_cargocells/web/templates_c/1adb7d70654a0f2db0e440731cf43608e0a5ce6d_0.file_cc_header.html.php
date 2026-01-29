<?php
/* Smarty version 5.3.1, created on 2026-01-29 09:57:44
  from 'file:cc_header.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b2f18034046_61731329',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '1adb7d70654a0f2db0e440731cf43608e0a5ce6d' => 
    array (
      0 => 'cc_header.html',
      1 => 1769680661,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b2f18034046_61731329 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>  <head>
    <title><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['title']);?>
</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['description']);?>
">
        <!-- 🕸️ Canonical URL -->
        <link rel="canonical" href="<?php echo $_smarty_tpl->getValue('canonical');?>
">
        <link rel="alternate" hreflang="de" href="<?php echo $_smarty_tpl->getValue('canonical');?>
">
        <link rel="alternate" hreflang="uk" href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/ua/<?php echo $_smarty_tpl->getValue('currentPath');?>
">
        <link rel="alternate" hreflang="en" href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/en/<?php echo $_smarty_tpl->getValue('currentPath');?>
">
        <link rel="alternate" hreflang="ru" href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/ru/<?php echo $_smarty_tpl->getValue('currentPath');?>
">
        <link rel="alternate" hreflang="x-default" href="<?php echo $_smarty_tpl->getValue('canonical');?>
">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,700,900|Display+Playfair:200,300,400,700"> 
    <link rel="stylesheet" href="/fonts/icomoon/style.css">

    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/magnific-popup.css">
    <link rel="stylesheet" href="/css/jquery-ui.css">
    <link rel="stylesheet" href="/css/owl.carousel.min.css">
    <link rel="stylesheet" href="/css/owl.theme.default.min.css">

    <link rel="stylesheet" href="/css/bootstrap-datepicker.css">

    <link rel="stylesheet" href="/fonts/flaticon/font/flaticon.css">



    <link rel="stylesheet" href="/css/aos.css">

    <link rel="stylesheet" href="/css/style.css">



  </head>
<?php }
}
