<?php
/* Smarty version 5.3.1, created on 2026-01-28 20:23:02
  from 'file:cc_footer.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697a702625dda8_55521364',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'c04041a320139755f07ee762bf1547924b2876d9' => 
    array (
      0 => 'cc_footer.html',
      1 => 1769631776,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697a702625dda8_55521364 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>    <footer class="site-footer">
      <div class="container">
        <div class="row">
          <div class="col-md-9">
            <div class="row">
              <div class="col-md-5 mr-auto">
                <h2 class="footer-heading mb-4"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerAboutUs");?>
</h2>
                <p><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerAboutUsText");?>
</p>
              </div>
              
              <div class="col-md-3">
                <h2 class="footer-heading mb-4"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerQuickLinks");?>
</h2>
                <ul class="list-unstyled">
                  <li><a href="about"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnAboutUs");?>
</a></li>
                  <li><a href="service"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnServices");?>
</a></li>
                  <!--<li><a href="#">Testimonials</a></li>-->
                  <li><a href="contact"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("btnContact");?>
</a></li>
                </ul>
              </div>
              <div class="col-md-3">
                <h2 class="footer-heading mb-4"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerFollowUs");?>
</h2>
                <a href="#" class="pl-0 pr-3"><span class="icon-facebook"></span></a>
                <a href="#" class="pl-3 pr-3"><span class="icon-twitter"></span></a>
                <a href="#" class="pl-3 pr-3"><span class="icon-instagram"></span></a>
                <a href="#" class="pl-3 pr-3"><span class="icon-linkedin"></span></a>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <h2 class="footer-heading mb-4"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerSubscribe");?>
</h2>
            <form action="#" method="post">
              <div class="input-group mb-3">
                <input type="text" class="form-control border-secondary text-white bg-transparent" placeholder="Enter Email" aria-label="Enter Email" aria-describedby="button-addon2">
                <div class="input-group-append">
                  <button class="btn btn-primary text-white" type="button" id="button-addon2"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("footerSubscribeSend");?>
</button>
                </div>
              </div>
            </form>
          </div>
        </div>
        <div class="row pt-5 mt-5 text-center">
          <div class="col-md-12">
            <div class="border-top pt-5">
              <p>
            <!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
            Copyright &copy;<?php echo '<script'; ?>
>document.write(new Date().getFullYear());<?php echo '</script'; ?>
> All rights reserved | This template is made with <i class="icon-heart" aria-hidden="true"></i> by <a href="https://colorlib.com" target="_blank" >Colorlib</a>
            <!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
            </p>
            </div>
          </div>
          
        </div>
      </div>
    </footer>
  <!-- </div> -->
<?php }
}
