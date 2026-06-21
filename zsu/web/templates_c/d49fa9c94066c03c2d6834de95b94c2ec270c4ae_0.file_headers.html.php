<?php
/* Smarty version 5.3.1, created on 2025-10-29 10:36:37
  from 'file:headers.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6901ee350b4bc5_93310065',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd49fa9c94066c03c2d6834de95b94c2ec270c4ae' => 
    array (
      0 => 'headers.html',
      1 => 1761734195,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6901ee350b4bc5_93310065 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>    <head>
        <!-- meta data -->
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta property=”og:locale” content=”uk_UA” />
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

        <!--font-family-->
		<link href="https://fonts.googleapis.com/css?family=Poppins:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

		<link href="https://fonts.googleapis.com/css?family=Rufina:400,700" rel="stylesheet">
        
        <!-- title of site -->
        <title><?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['title'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['title'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('data')['title'];
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['title'], ENT_QUOTES, 'UTF-8', true);
}?></title>

	<meta name="description" content="<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['description'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['description'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('data')['description'];
}?>">

	<meta name="keywords" content="<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['keywords'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['keywords'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('data')['keywords'];
}?>">
	<meta name="robots" content="index,follow">
	<meta name="author" content="">
	<link rel="canonical" href="<?php echo $_smarty_tpl->getValue('reqUrl');?>
">
	<?php echo '<script'; ?>
 async src="https://www.googletagmanager.com/gtag/js?id=G-8HQD9F3E7S"><?php echo '</script'; ?>
>
	<?php echo '<script'; ?>
 src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/js/googleAnalytics.js"><?php echo '</script'; ?>
>
	<?php echo '<script'; ?>
 sync src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/js/zsu.js"><?php echo '</script'; ?>
>

	<meta property=”og:title” content=”<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['title'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['title'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('data')['title'];
}?>"/>
	<meta property=”og:description” content=”<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['description'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['description'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('data')['description'];
}?>"/>
	<meta property=”og:site_name” content=”<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['site_name'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['site_name'], ENT_QUOTES, 'UTF-8', true);
} else {
echo $_smarty_tpl->getValue('viewCar')['url_announce'];?>
 <?php echo $_smarty_tpl->getValue('data')['description'];
}?>” />
	<meta property=”og:image” content='<?php if (htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['image'], ENT_QUOTES, 'UTF-8', true)) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['image'], ENT_QUOTES, 'UTF-8', true);
} else { ?>https://<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/logo/favicon.png”<?php }?>' />
	<meta property=”og:type” content=”<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['type'], ENT_QUOTES, 'UTF-8', true);?>
” />
	<meta property=”og:url” content=”<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['og']['url'], ENT_QUOTES, 'UTF-8', true);?>
” />
	<?php echo $_smarty_tpl->getValue('meta')['jsonld'];?>

	
	
	<!--<meta http-equiv="refresh" content="30">-->
        <!-- For favicon png -->
		<link rel="shortcut icon" type="image/icon" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/logo/favicon.png"/>
       
        <!--font-awesome.min.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/font-awesome.min.css">

        <!--linear icon css-->
		<link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/linearicons.css">

        <!--flaticon.css-->
		<link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/flaticon.css">

		<!--animate.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/animate.css">

        <!--owl.carousel.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/owl.carousel.min.css">
		<link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/owl.theme.default.min.css">
		
        <!--bootstrap.min.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/bootstrap.min.css">
		
		<!-- bootsnav -->
		<link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/bootsnav.css" >	
        
        <!--style.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/style.css">
        
        <!--responsive.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/responsive.css">
        <!--responsive.css-->
        <link rel="stylesheet" href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/css/zsu.css">
        
        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		
        <!--[if lt IE 9]>
			<?php echo '<script'; ?>
 src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"><?php echo '</script'; ?>
>
			<?php echo '<script'; ?>
 src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"><?php echo '</script'; ?>
>
        <![endif]-->

    </head>
	
		<!--welcome-hero start -->
		<section id="home" class="welcome-hero">

			<!-- top-area Start -->
			<div class="top-area">
				<div class="header-area">
					<!-- Start Navigation -->
				    <nav class="navbar navbar-default bootsnav  navbar-sticky navbar-scrollspy"  data-minus-value-desktop="70" data-minus-value-mobile="55" data-speed="1000">

				        <div class="container">

				            <!-- Start Header Navigation -->
				            <div class="navbar-header">
				                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-menu">
				                    <i class="fa fa-bars"></i>
				                </button>
				                <a class="navbar-brand" href="index.html">Авто для ЗСУ від волонтерів!<span></span></a>

				            </div><!--/.navbar-header-->
				            <!-- End Header Navigation -->

				            <!-- Collect the nav links, forms, and other content for toggling -->
				            <div class="collapse navbar-collapse menu-ui-design" id="navbar-menu">
				                <ul class="nav navbar-nav navbar-right" data-in="fadeInDown" data-out="fadeOutUp">
				                    <!--<li class=" scroll active"><a href="#home">Головна</a></li>
-->
				                    <li><a href="/">Головна</a></li>
				                    <li><a href="/service" target="blank">Послуги</a></li>
				                    <li><a href="/all-cars">Доступні авто</a></li>
				                    <li><a href="/new-cars">Нові авто в продажу</a></li>
				                    <!--<li class="scroll"><a href="#brand">brands</a></li>-->
				                    <li><a href="/contact">Зв'язок з нами</a></li>
				                    <li><a href="/aboutus">Хто ми? (Про нас)</a></li>
				                </ul><!--/.nav -->
				            </div><!-- /.navbar-collapse -->
				        </div><!--/.container-->
				    </nav><!--/nav-->
				    <!-- End Navigation -->
				</div><!--/.header-area-->
			    <div class="clearfix"></div>

			</div><!-- /.top-area-->
			<!-- top-area End -->
<?php }
}
