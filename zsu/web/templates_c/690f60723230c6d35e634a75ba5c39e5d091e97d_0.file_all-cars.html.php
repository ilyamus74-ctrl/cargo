<?php
/* Smarty version 5.3.1, created on 2025-03-16 07:57:21
  from 'file:all-cars.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_67d68461ec5fc5_46588666',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '690f60723230c6d35e634a75ba5c39e5d091e97d' => 
    array (
      0 => 'all-cars.html',
      1 => 1742111835,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_67d68461ec5fc5_46588666 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>			<div class="container">



				<div class="welcome-hero-txt">
					<h1  class="animated fadeInUp"><?php echo $_smarty_tpl->getValue('data')['main_text_h1'];?>
</h1>
					<p>
					<?php echo $_smarty_tpl->getValue('data')['main_text'];?>

					</p>
					<button class="welcome-btn" onclick="myModalOpen();" id="myBtn">Зв'яжись з нами!</button>
				</div>

				<div class="row">
					<div class="col-md-12">
						<div class="model-search-content">
							<div class="row">
								<div class="col-md-offset-1 col-md-2 col-sm-12">
									<div class="single-model-search">
										<h2>Тип трансопрту</h2>
										<div class="model-select-icon">
											<select class="form-control" id="type" name="type">
											  	<option value="all">Всі</option><!-- /.option-->
											  	<option value="bus">Буси</option><!-- /.option-->
											  	<option value="jeep">Джипи</option><!-- /.option-->
											  	<option value="cargo">Вантажний</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
									<div class="single-model-search">
										<h2>Технічний стан</h2>
										<div class="model-select-icon">
											<select class="form-control" id="stan" name="stan">
											  	<option value="all">Будь який</option><!-- /.option-->
											  	<option value="ready">Повністью справний</option><!-- /.option-->
											  	<option value="uradey">Умовно справний</option><!-- /.option-->
											  	<!--<option value="roadster">roadster</option>--><!-- /.option-->
											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
								</div>
								<div class="col-md-offset-1 col-md-2 col-sm-12">
									<div class="single-model-search">
										<h2>Виробник</h2>
										<div class="model-select-icon">
											<select class="form-control" id="marke" name="marke">
											  	<option value="all">Будь який</option><!-- /.option-->
											  	<option value="Volkswagen">Volkswagen</option><!-- /.option-->
											  	<option value="Mitsubishi">Mitsubishi</option><!-- /.option-->
											  	<option value="Nissan">Nissan</option><!-- /.option-->
											  	<option value="Toyota">Toyota</option><!-- /.option-->
											  	<option value="Kia">Kia</option><!-- /.option-->
											  	<option value="BMW">BMW</option><!-- /.option-->
											  	<option value="Suzuki">Suzuki</option><!-- /.option-->
											  	<option value="Peugeot">Peugeot</option><!-- /.option-->
											  	<option value="Citroen">Citroen</option><!-- /.option-->
											  	<option value="Renault">Renault</option><!-- /.option-->
											  	<option value="Mercedes Benz">Mercedes Benz</option><!-- /.option-->
											  	<option value="Ford">Ford</option><!-- /.option-->
											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
									<div class="single-model-search" style="display:none">
										<h2>car condition</h2>
										<div class="model-select-icon">
											<select class="form-control">

											  	<option value="default">condition</option><!-- /.option-->

											  	<option value="something">something</option><!-- /.option-->

											  	<option value="something">something</option><!-- /.option-->
											  	<option value="something">something</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
								</div>
								<div class="col-md-offset-1 col-md-2 col-sm-12" >
									<div class="single-model-search" style="display:none">
										<h2>select model</h2>
										<div class="model-select-icon">
											<select class="form-control" id="model" name="model">

											  	<option value="default">model</option><!-- /.option-->

											  	<option value="kia-rio">kia-rio</option><!-- /.option-->

											  	<option value="mitsubishi">Mitsubishi</option><!-- /.option-->
											  	<option value="ford">ford</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
									<div class="single-model-search">
										<h2>Вибір ціни</h2>
										<div class="model-select-icon">
											<select class="form-control" id="price" name="price">
											  	<option value="all"  <?php if ($_smarty_tpl->getValue('S')) {?>  selected <?php }?>>Всі кошти світу</option><!-- /.option-->
											  	<option value="2000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 2000 Евро</option><!-- /.option-->
											  	<option value="3000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 3000 Евро</option><!-- /.option-->
											  	<option value="4000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 4000 Евро</option><!-- /.option-->
											  	<option value="5000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 5000 Евро</option><!-- /.option-->
											  	<option value="6000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 6000 Евро</option><!-- /.option-->
											  	<option value="7000" <?php if ($_smarty_tpl->getValue('S')) {?> selected <?php }?>>до 7000 Евро</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
								</div>
								<div class="col-md-2 col-sm-12">
									<div class="single-model-search text-center">
										<button class="welcome-btn model-search-btn" onclick="searchAllCars();">
											Показати
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

		</section><!--/.welcome-hero-->

		</section><!--/.service-->
		<!--service end-->


		<!--featured-cars start -->
		<section id="featured-cars" class="featured-cars">
			<div class="container" id="listAllCars">
			
				<div class="section-header">
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
									<!--<h3><?php echo $_smarty_tpl->getValue('v')['price'];?>
 Евро</h3>-->
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
				</div>
				<?php if ($_smarty_tpl->getValue('allCarsLeft')) {?>
				<div class="section-header" id="showMoreButton">
				<button class="welcome-btn model-search-btn" onclick="searchMoreAllCars();">Показати ще</button>
				</div>
				<?php }?>
			</div><!--/.container-->

				<div id="nextCars" style="display:none">
			<!--	<input type="hidden" value="<?php
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
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>" id="lastIdCar">-->
				<input type="hidden" value="<?php echo $_smarty_tpl->getValue('v')['id'];?>
" id="lastIdCar">
				</div>
		</section><!--/.featured-cars-->
		<!--featured-cars end -->
<?php }
}
