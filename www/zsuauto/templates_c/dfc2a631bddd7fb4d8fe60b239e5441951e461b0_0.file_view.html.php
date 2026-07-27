<?php
/* Smarty version 5.3.1, created on 2025-10-29 10:37:53
  from 'file:view.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6901ee811e5256_76508315',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'dfc2a631bddd7fb4d8fe60b239e5441951e461b0' => 
    array (
      0 => 'view.html',
      1 => 1761734270,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6901ee811e5256_76508315 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>			<div class="container">
				<div class="welcome-hero-txt">
					<h1 class="animated fadeInUp"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['h1'], ENT_QUOTES, 'UTF-8', true);?>
</h1>
					<p>
										<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('meta')['intro'], ENT_QUOTES, 'UTF-8', true);?>

					</p>
				</div>
			</div>


			<div class="container">
				<div class="row">
				

				</div>
			</div>

		</section><!--/.welcome-hero-->
		<!--welcome-hero end -->

		<!--new-cars start -->
		<section id="new-cars" class="new-cars">
			<div class="container">
				<div class="section-header">
					<!--<p>Список нових надходжен на автомобільний ринок  <span></span></p>-->
					<h2 id="name_announce"><?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>
</h2>
				</div><!--/.section-header-->
				<div class="new-cars-content">
					<div class="owl-carousel owl-theme" id="new-cars-carousel">
												<div class="new-cars-item">
							<div class="single-new-cars-item">
								<div class="row">
									<div class="col-md-7 col-sm-12">
										<div class="new-cars-img">
											<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('viewCar')['img_announce'], 'v', false, 'k');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('k')->value => $_smarty_tpl->getVariable('v')->value) {
$foreach0DoElse = false;
?>
											<!--<img src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/img/announce/<?php echo $_smarty_tpl->getValue('viewCar')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('viewCar')['img_announce'][0];?>
" alt="img"/>
-->
											<img src="//<?php echo $_smarty_tpl->getValue('domainName');?>
/img/announce/<?php echo $_smarty_tpl->getValue('viewCar')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('v');?>
" alt="<?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>
 - Фото <?php echo $_smarty_tpl->getValue('k');?>
"/>
											<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
										</div><!--/.new-cars-img-->
									</div>
									<div class="col-md-5 col-sm-12">
										<div class="new-cars-txt">
											<h2><a href="#"> <?php echo $_smarty_tpl->getValue('viewCar')['marke'];?>
 <?php echo $_smarty_tpl->getValue('viewCar')['modell'];?>
 ціна:<span> <?php echo $_smarty_tpl->getValue('viewCar')['price'];?>
</span> Euro</a></h2>
											<p>
												<?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>

												 											</p>
											<p class="new-cars-para2"><?php echo $_smarty_tpl->getValue('vviewCar')['fahrzeugzustand'];?>
</p>
											<p class="new-cars-para2">Пробіг: <?php echo $_smarty_tpl->getValue('viewCar')['kilometerstand'];?>
	 км.
											<br>Рік випуску: <?php echo $_smarty_tpl->getValue('viewCar')['erstzulassung'];?>

											<br>Коробка передач: <?php echo $_smarty_tpl->getValue('viewCar')['Getriebe'];?>

											<br>Колір: <?php echo $_smarty_tpl->getValue('viewCar')['farbe'];?>

											<br>Тип: <?php echo $_smarty_tpl->getValue('viewCar')['fahrzeugtyp'];?>

											<br>Двері: <?php echo $_smarty_tpl->getValue('viewCar')['anzahl_turen'];?>

											<br>
											</p>
											<p class="new-cars-para2">
											<?php echo $_smarty_tpl->getValue('viewCar')['text_full_announce'];?>

											</p>
											<input type="hidden" id="car" value="<?php echo $_smarty_tpl->getValue('viewCar')['img_dir'];?>
">
											<button class="welcome-btn new-cars-btn" onclick="myModalOpen();" id="myBtn">
												Замовити консультацію
											</button>
										</div><!--/.new-cars-txt-->	
									</div><!--/.col-->
								</div><!--/.row-->
							</div><!--/.single-new-cars-item-->
						</div><!--/.new-cars-item-->
											</div><!--/#new-cars-carousel-->
				</div><!--/.new-cars-content-->
			</div><!--/.container-->

		</section><!--/.new-cars-->
		<!--new-cars end -->

<?php }
}
