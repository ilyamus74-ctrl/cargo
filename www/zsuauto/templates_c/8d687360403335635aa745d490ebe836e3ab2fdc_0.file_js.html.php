<?php
/* Smarty version 5.3.1, created on 2024-07-12 08:59:38
  from 'file:js.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6690f07a3a04f4_27832670',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '8d687360403335635aa745d490ebe836e3ab2fdc' => 
    array (
      0 => 'js.html',
      1 => 1720176411,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6690f07a3a04f4_27832670 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>	
		<!-- Include all js compiled plugins (below), or include individual files as needed -->
	<?php echo '<script'; ?>
 src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/js/jquery.js"><?php echo '</script'; ?>
>
        <!--modernizr.min.js-->
        <?php echo '<script'; ?>
 src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js"><?php echo '</script'; ?>
>
		<!--bootstrap.min.js-->
        <?php echo '<script'; ?>
 src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/js/bootstrap.min.js"><?php echo '</script'; ?>
>
		<!-- bootsnav js -->
		<?php echo '<script'; ?>
 async src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/js/bootsnav.js"><?php echo '</script'; ?>
>
		<!--owl.carousel.js-->
        <?php echo '<script'; ?>
 src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/js/owl.carousel.min.js"><?php echo '</script'; ?>
>
	<?php echo '<script'; ?>
 src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"><?php echo '</script'; ?>
>
        <!--Custom JS-->
        <?php echo '<script'; ?>
 src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/assets/js/custom.js"><?php echo '</script'; ?>
>
<?php }
}
