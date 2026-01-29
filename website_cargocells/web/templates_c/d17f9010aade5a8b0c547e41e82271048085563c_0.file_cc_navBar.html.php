<?php
/* Smarty version 5.3.1, created on 2026-01-29 09:20:03
  from 'file:cc_navBar.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b2643829b24_70058935',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd17f9010aade5a8b0c547e41e82271048085563c' => 
    array (
      0 => 'cc_navBar.html',
      1 => 1769678400,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b2643829b24_70058935 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>  <!-- <div class="site-wrap"> -->

    <div class="site-mobile-menu site-navbar-target">
      <div class="site-mobile-menu-header">
        <div class="language-switcher site-mobile-menu-language" aria-label="Language switcher">
          <a href="/lang?set=uk" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'uk') {?> is-active<?php }?>">UA</a>
          <a href="/lang?set=ru" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'ru') {?> is-active<?php }?>">RU</a>
          <a href="/lang?set=de" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'de') {?> is-active<?php }?>">DE</a>
          <a href="/lang?set=en" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'en') {?> is-active<?php }?>">EN</a>
        </div>
        <div class="site-mobile-menu-close mt-3">
          <span class="icon-close2 js-menu-toggle"></span>
        </div>
      </div>
      <div class="site-mobile-menu-body"></div>
    </div>
    
    <header class="site-navbar py-3 js-site-navbar site-navbar-target" role="banner" id="site-navbar">

      <div class="container">
        <div class="row align-items-center">
          
          <div class="col-11 col-xl-2 site-logo">

            <h1 class="mb-0">
              <a href="./" class="text-white h2 mb-0 cargo-logo">
                <img class="cargo-logo__mark" src="/images/apple-touch-icon.png" alt="CargoCells logo">
                <span class="cargo-logo__text">
                  <span class="cargo-logo__cargo">Cargo</span>
                  <span class="cargo-logo__cells">Cells</span>
                </span>
              </a>
            </h1>

          </div>
          <div class="col-12 col-md-10 d-none d-xl-block">
            <nav class="site-navigation position-relative text-right" role="navigation">
              <div class="site-nav-group">
                <ul class="site-menu js-clone-nav mx-auto d-none d-lg-block">
                  <li<?php if ($_smarty_tpl->getValue('main')) {?> class="active"<?php }?>><a href="./" class="nav-link<?php if ($_smarty_tpl->getValue('main')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnHome");?>
</a></li>
                  <li class="has-children<?php if ($_smarty_tpl->getValue('about') || $_smarty_tpl->getValue('ourteam')) {?> active<?php }?>">
                    <a href="./about" class="nav-link<?php if ($_smarty_tpl->getValue('about') || $_smarty_tpl->getValue('ourteam')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnAboutUs");?>
</a>
                    <ul class="dropdown">
                      <li<?php if ($_smarty_tpl->getValue('howitwork')) {?> class="active"<?php }?>><a href="./howitwork" class="nav-link<?php if ($_smarty_tpl->getValue('howitwork')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnHowitwork");?>
</a></li>
                      <li<?php if ($_smarty_tpl->getValue('ourteam')) {?> class="active"<?php }?>><a href="./ourteam" class="nav-link<?php if ($_smarty_tpl->getValue('ourteam')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnOurTeam");?>
</a></li>
                    </ul>
                  </li>
                  <li<?php if ($_smarty_tpl->getValue('service')) {?> class="active"<?php }?>><a href="./service" class="nav-link<?php if ($_smarty_tpl->getValue('service')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnServices");?>
</a></li>
                  <li<?php if ($_smarty_tpl->getValue('industries')) {?> class="active"<?php }?>><a href="./industries" class="nav-link<?php if ($_smarty_tpl->getValue('industries')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnIndustries");?>
</a></li>
                  <!--<li><a href="#section-blog" class="nav-link">Blog</a></li>-->
                  <!--<li<?php if ($_smarty_tpl->getValue('contact')) {?> class="active"<?php }?>><a href="contact" class="nav-link<?php if ($_smarty_tpl->getValue('contact')) {?> active<?php }?>"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnContact");?>
</a></li>-->
                </ul>
                <div class="language-switcher" aria-label="Language switcher">
                 <a href="/lang?set=uk" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'uk') {?> is-active<?php }?>">UA</a>
                  <a href="/lang?set=ru" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'ru') {?> is-active<?php }?>">RU</a>
                  <a href="/lang?set=de" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'de') {?> is-active<?php }?>">DE</a>
                  <a href="/lang?set=en" class="language-switcher__link<?php if ($_smarty_tpl->getValue('xlang') == 'en') {?> is-active<?php }?>">EN</a>
                </div>
              </div>
            </nav>
          </div>


          <div class="d-inline-block d-xl-none ml-md-0 mr-auto py-3" style="position: relative; top: 3px;"><a href="#" class="site-menu-toggle js-menu-toggle"><span class="icon-menu h3"></span></a></div>

          </div>

        </div>
      </div>
      
    </header>
<?php }
}
