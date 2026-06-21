<?php
/* Smarty version 5.3.1, created on 2024-07-12 08:59:58
  from 'file:service.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6690f08eb566e0_92339883',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '5d711f03e2e373c1f08a97d583169a852d8de544' => 
    array (
      0 => 'service.html',
      1 => 1720294055,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6690f08eb566e0_92339883 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>
			<div class="container">
				<div class="welcome-hero-txt">
					<h1 class="animated fadeInUp"><?php echo $_smarty_tpl->getValue('data')['main_text_h1'];?>
</h1>
					<p><?php echo $_smarty_tpl->getValue('data')['main_text'];?>

					</p>
					<button class="welcome-btn" onclick="myModalOpen();" id="myBtn">Зв'яжись з нами!</button>
					<br><br><p>або переходь</p>
					<button class="welcome-btn " onclick="window.location.href='//zsuauto.info/all-cars'">
					    Дивитись всі варіанти авто
					</button>
				</div>
			</div>






<?php }
}
