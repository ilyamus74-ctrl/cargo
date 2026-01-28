<?php
/* Smarty version 5.3.1, created on 2026-01-27 19:41:12
  from 'file:header.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697914d8eee7d3_94574965',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'faf6a5614eb08fb4d8a40f5570bfb1a441de58a9' => 
    array (
      0 => 'header.html',
      1 => 1760884911,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697914d8eee7d3_94574965 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['description']);?>
" />
        <title><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['title']);?>
</title>
        <meta name="keywords" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['keywords']);?>
">
        <meta name="author" content="" />
        <!-- 🕸️ Canonical URL -->
        <link rel="canonical" href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('canonical'), ENT_QUOTES, 'UTF-8', true);?>
">
        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('alternates'), 'url', false, 'hre');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('hre')->value => $_smarty_tpl->getVariable('url')->value) {
$foreach0DoElse = false;
?>
        <link rel="alternate" hreflang="<?php echo $_smarty_tpl->getValue('hre');?>
" href="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('url'), ENT_QUOTES, 'UTF-8', true);?>
">
        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
        <link rel="alternate" hreflang="x-default" href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/">
        <meta property="og:locale" content="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('og_locale'), ENT_QUOTES, 'UTF-8', true);?>
">

        <!-- 📱 Open Graph (для соцсетей) -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['soc_og_title']);?>
">
        <meta property="og:description" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['soc_og_description']);?>
">
        <meta property="og:image" content="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/img/easytrade/EasyTrade.png">
        <meta property="og:url" content="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('canonical'), ENT_QUOTES, 'UTF-8', true);?>
">
        <meta property="og:site_name" content="EasyTrade">

        <!-- 🐦 Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['twitter_title']);?>
">
        <meta name="twitter:description" content="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')($_smarty_tpl->getValue('header_data')['twitter_description']);?>
">
        <meta name="twitter:image" content="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/img/easytrade/EasyTrade.png">
        <meta name="twitter:site" content="@EasyTrade_AI">


        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/favicon.ico" />
        <!-- Font Awesome icons (free version)-->
        <?php echo '<script'; ?>
 src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"><?php echo '</script'; ?>
>
        <!-- Google fonts-->
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/css/styles.css" rel="stylesheet" />
    </head>
<?php }
}
