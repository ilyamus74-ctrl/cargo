<?php
/* Smarty version 5.3.1, created on 2026-01-28 19:08:21
  from 'file:cc_about.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697a5ea56eabc0_21020495',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'cc6ac381355220cdd002d9b30e63474951fa085d' => 
    array (
      0 => 'cc_about.html',
      1 => 1769626675,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697a5ea56eabc0_21020495 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>
    <div class="site-blocks-cover overlay" style="background-image: url(/images/main_bg_sklad.jpg);" data-aos="fade" data-stellar-background-ratio="0.5" id="section-home">
      <div class="container">
        <div class="row align-items-center justify-content-center text-center">

          <div class="col-md-8" data-aos="fade-up" data-aos-delay="400">
            

            <h1 class="text-white font-weight-light text-uppercase font-weight-bold" data-aos="fade-up"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainAboutTitleH1");?>
</h1>
            <!--<p class="mb-5" data-aos="fade-up" data-aos-delay="100">A Logistics Company</p>
            <p data-aos="fade-up" data-aos-delay="200"><a href="#" class="btn btn-primary py-3 px-5 text-white">Get Started!</a></p>
	    -->
          </div>
        </div>
      </div>
    </div>  

    <div class="site-section" id="section-about">
      <div class="container">
        <div class="row mb-5">
          
          <div class="col-md-5 ml-auto mb-5 order-md-2" data-aos="fade-up" data-aos-delay="100">
            <img src="/images/img_sklad_2.jpg" alt="Image" class="img-fluid rounded">
          </div>
          <div class="col-md-6 order-md-1" data-aos="fade-up">
            <div class="text-left pb-1 border-primary mb-4">
              <h2 class="text-primary"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainAboutTitle");?>
</h2>
            </div>
            <p><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainAboutText");?>
</p>
            <!--<p class="mb-5">Error minus sint nobis dolor laborum architecto, quaerat. Voluptatum porro expedita labore esse velit veniam laborum quo obcaecati similique iusto delectus quasi!</p>
            -->
            <!--<ul class="ul-check list-unstyled success">
              <li>Error minus sint nobis dolor</li>
              <li>Voluptatum porro expedita labore esse</li>
              <li>Voluptas unde sit pariatur earum</li>
            </ul>-->
            
          </div>
          
        </div>
      </div>
    </div>
<?php }
}
