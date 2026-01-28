<?php
/* Smarty version 5.3.1, created on 2026-01-28 08:52:04
  from 'file:cc_navBar.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6979ce34bb6a49_52582950',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd17f9010aade5a8b0c547e41e82271048085563c' => 
    array (
      0 => 'cc_navBar.html',
      1 => 1769590182,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6979ce34bb6a49_52582950 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>  <!-- <div class="site-wrap"> -->

    <div class="site-mobile-menu site-navbar-target">
      <div class="site-mobile-menu-header">
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
              <a href="index.html" class="text-white h2 mb-0 cargo-logo">
                <span class="cargo-logo__cargo">Cargo</span><span class="cargo-logo__cells">Cells</span>
              </a>
            </h1>

          </div>
          <div class="col-12 col-md-10 d-none d-xl-block">
            <nav class="site-navigation position-relative text-right" role="navigation">

              <ul class="site-menu js-clone-nav mx-auto d-none d-lg-block">
                <li><a href="#section-home" class="nav-link">Home</a></li>
                <li class="has-children">
                  <a href="#section-about" class="nav-link">About Us</a>
                  <ul class="dropdown">
                    <li><a href="#section-how-it-works" class="nav-link">How It Works</a></li>
                    <li><a href="#section-our-team" class="nav-link">Our Team</a></li>
                  </ul>
                </li>
                <li><a href="#section-services" class="nav-link">Services</a></li>
                <li><a href="#section-industries" class="nav-link">Industries</a></li>
                <li><a href="#section-blog" class="nav-link">Blog</a></li>
                <li><a href="#section-contact" class="nav-link">Contact</a></li>
              </ul>
            </nav>
          </div>


          <div class="d-inline-block d-xl-none ml-md-0 mr-auto py-3" style="position: relative; top: 3px;"><a href="#" class="site-menu-toggle js-menu-toggle"><span class="icon-menu h3"></span></a></div>

          </div>

        </div>
      </div>
      
    </header>
<?php }
}
