<?php
/* Smarty version 5.3.1, created on 2025-03-24 09:21:56
  from 'file:singleAllCars.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_67e1243454e214_02926638',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '73d5eacd04b53659151d06140ef90d36ba7e0335' => 
    array (
      0 => 'singleAllCars.html',
      1 => 1742111611,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_67e1243454e214_02926638 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>				<div class="section-header">
				<br><br><br>
				<h3>Всього за запитом оголошень <?php echo $_smarty_tpl->getValue('allCarsCount');?>
 </h3>
					<!--<p>Актуальна інформація авто з Европейських ресурсів <span></span> </p>
					<h2>Доступні авто у продажу в Європі</h2>
-->
				</div><!--/.section-header-->
				<div class="featured-cars-content" >
					<div class="row">
						
						<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('allCars'), 'v', false, 'k');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('k')->value => $_smarty_tpl->getVariable('v')->value) {
$foreach0DoElse = false;
?>
						<div class="col-lg-3 col-md-4 col-sm-6">
							<div class="single-featured-cars">
								<div class="featured-img-box">
									<div class="featured-cars-img">
									<a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
" target="_blank"><img src="img/thumb/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('v')['img_thumb'];?>
" alt="<?php echo $_smarty_tpl->getValue('v')['name_announce'];?>
"></a>
									</div>
									<div class="featured-model-info">
										<p class="h5">
											модель: <?php echo $_smarty_tpl->getValue('v')['modell'];?>

											<span class="featured-mi-span"> <?php echo $_smarty_tpl->getValue('v')['kilometerstand'];?>
 км</span> 
											<span class="featured-hp-span"> <?php echo $_smarty_tpl->getValue('v')['Leistung'];?>
</span>
											коробка: <?php echo $_smarty_tpl->getValue('v')['Getriebe'];?>

										</p>
									</div>
								</div>
								<div class="featured-cars-txt">
									<h4><a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
" target="_blank"><?php echo $_smarty_tpl->getValue('v')['marke'];?>
  <?php echo $_smarty_tpl->getValue('v')['modell'];?>
</a></h4>
									<h3><?php echo $_smarty_tpl->getValue('v')['price'];?>
 Евро</h3>
									<p><?php echo $_smarty_tpl->getValue('v')['name_announce'];?>
</p>
								</div>
							</div>
						</div>
						<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
						
						
					</div>
					<div class="row" id="showNextCar"></div>
				<?php if ($_smarty_tpl->getValue('allCarsLeft')) {?>
				<div class="section-header" id="showMoreButton">
				<button class="welcome-btn model-search-btn" onclick="searchMoreAllCars();">Показати ще</button>
				</div>
				<?php }?>

				</div>
				<div id="nextCars" style="display:none">
				<input type="hidden" value="<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('allCars'), 'i', false, 'myId', 'foo', array (
  'last' => true,
  'iteration' => true,
  'total' => true,
));
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('myId')->value => $_smarty_tpl->getVariable('i')->value) {
$foreach1DoElse = false;
$_smarty_tpl->tpl_vars['__smarty_foreach_foo']->value['iteration']++;
$_smarty_tpl->tpl_vars['__smarty_foreach_foo']->value['last'] = $_smarty_tpl->tpl_vars['__smarty_foreach_foo']->value['iteration'] === $_smarty_tpl->tpl_vars['__smarty_foreach_foo']->value['total'];
if (($_smarty_tpl->getValue('__smarty_foreach_foo')['last'] ?? null)) {
echo $_smarty_tpl->getValue('myId');
}
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>" id="lastIdCar">
				</div><?php }
}
