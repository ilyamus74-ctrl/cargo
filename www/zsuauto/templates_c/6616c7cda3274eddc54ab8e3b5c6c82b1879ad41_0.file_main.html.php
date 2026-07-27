<?php
/* Smarty version 5.3.1, created on 2025-04-05 08:04:14
  from 'file:main.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_67f0e3fe7ce633_58363280',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6616c7cda3274eddc54ab8e3b5c6c82b1879ad41' => 
    array (
      0 => 'main.html',
      1 => 1743840252,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_67f0e3fe7ce633_58363280 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>

			<div class="container">
				<div class="welcome-hero-txt">
					<h1  class="animated fadeInUp"><?php echo $_smarty_tpl->getValue('data')['main_text_h1'];?>
</h1>
					<p>
					<?php echo $_smarty_tpl->getValue('data')['main_text'];?>
	
					</p>
					<button class="welcome-btn" onclick="myModalOpen();" id="myBtn">Зв'яжись з нами!</button>
				</div>
			</div>

			<div class="container">
				<div class="row" style="display:none">
					<div class="col-md-12">
						<div class="model-search-content">
							<div class="row">
								<div class="col-md-offset-1 col-md-2 col-sm-12">
									<div class="single-model-search">
										<h2>Тип трансопрту</h2>
										<div class="model-select-icon">
											<select class="form-control">

											  	<option value="default">Всі</option><!-- /.option-->

											  	<option value="2018">Буси</option><!-- /.option-->

											  	<option value="2017">Джипи</option><!-- /.option-->
											  	<option value="2016">Вантажний</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
									<div class="single-model-search">
										<h2>Технічний стан</h2>
										<div class="model-select-icon">
											<select class="form-control">

											  	<option value="default">Будь який</option><!-- /.option-->

											  	<option value="sedan">Повністью справний</option><!-- /.option-->

											  	<option value="van">Умовно справний</option><!-- /.option-->
											  	<!--<option value="roadster">roadster</option>--><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
								</div>
								<div class="col-md-offset-1 col-md-2 col-sm-12">
									<div class="single-model-search">
										<h2>Виробник</h2>
										<div class="model-select-icon">
											<select class="form-control">

											  	<option value="default">Будь який</option><!-- /.option-->

											  	<option value="toyota">VW</option><!-- /.option-->

											  	<option value="holden">Mercedes</option><!-- /.option-->
											  	<option value="maecedes-benz">Hyndai</option><!-- /.option-->

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
											<select class="form-control">

											  	<option value="default">model</option><!-- /.option-->

											  	<option value="kia-rio">kia-rio</option><!-- /.option-->

											  	<option value="mitsubishi">mitsubishi</option><!-- /.option-->
											  	<option value="ford">ford</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
									<div class="single-model-search">
										<h2>Вибір ціни</h2>
										<div class="model-select-icon">
											<select class="form-control">

											  	<option value="default">Всі кошти світу</option><!-- /.option-->

											  	<option value="$0.00">до 2000 Евро</option><!-- /.option-->
											  	<option value="$0.00">до 3000 Евро</option><!-- /.option-->
											  	<option value="$0.00">до 4000 Евро</option><!-- /.option-->
											  	<option value="$0.00">до 5000 Евро</option><!-- /.option-->
											  	<option value="$0.00">до 6000 Евро</option><!-- /.option-->
											  	<option value="$0.00">до 7000 Евро</option><!-- /.option-->

											</select><!-- /.select-->
										</div><!-- /.model-select-icon -->
									</div>
								</div>
								<div class="col-md-2 col-sm-12">
									<div class="single-model-search text-center">
										<button class="welcome-btn model-search-btn" onclick="window.location.href='#'">
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
		<!--welcome-hero end -->

		<!--service start -->
		<section id="service" class="service">
			<div class="container">
				<div class="service-content">
					<div class="row">
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car"></i>
								</div>
								<h2><a href="/service" target="blank">Впевненість підбору <span> авто </span> </a></h2>
								<p>
								<a href="/service" target="blank">
									Ми шукаємо тільки ті авто які технічно справні і можуть бути використані на фронті без глобальних фінансових вкладень. Зайвий клопіт нікому не треба!
								</a>
								</p>
							</div>
						</div>
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car-repair"></i>
								</div>
								<h2><a href="/service" target="blank">Тільки технічно справні авто</a></h2>
								<a href="/service" target="blank">
								<p>Авто яки ми пропонуємо відповідають опису та діляться на дві категорії.
								 <br>
								 <br><b>1. Повністю справні до використання на дорогах ЄС.
								 <br>2.Умовно справні, справні та не придатні до використання на дорогах ЕС.</b></p>
								</a>
							</div>
						</div>
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car-1"></i>
								</div>
								<h2><a href="/service" target="blank">Повний звіт з послуги доставлення авто</a></h2>
								<p><a href="/service" target="blank">
								    Незалежно від того хто замовляє авто, ми робимо повний звіт всіх фінансових витрат перед військовослужбовцем! Довіра та мета перемоги України понад усе!
								    </a>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div><!--/.container-->

		</section><!--/.service-->
		<!--service end-->

		<!--new-cars start -->
		<section id="new-cars" class="new-cars">
			<div class="container">
				<div class="section-header">
					<p>Список нових надходжень на автомобільний ринок  <span></span></p>
					<h2>Нові пропозиції з ринку</h2>
				</div><!--/.section-header-->
				<div class="new-cars-content">
					<div class="owl-carousel owl-theme" id="new-cars-carousel">
						<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('newCars'), 'v', false, 'k');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('k')->value => $_smarty_tpl->getVariable('v')->value) {
$foreach0DoElse = false;
?>
						<div class="new-cars-item">
							<div class="single-new-cars-item">
								<div class="row">
									<div class="col-md-7 col-sm-12">
										<div class="new-cars-img">
											<a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
"><img src="img/thumb/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('v')['img_thumb'];?>
" alt="<?php echo $_smarty_tpl->getValue('v')['name_announce'];?>
"/></a>
										</div><!--/.new-cars-img-->
									</div>
									<div class="col-md-5 col-sm-12">
										<div class="new-cars-txt">
											<h2><a href="#"> <?php echo $_smarty_tpl->getValue('v')['marke'];?>
 <?php echo $_smarty_tpl->getValue('v')['modell'];?>
 ціна:<span> <?php echo $_smarty_tpl->getValue('v')['price'];?>
</span> Euro</a></h2>
											<p>
												<?php echo $_smarty_tpl->getValue('v')['name_announce'];?>

												 											</p>
											<p class="new-cars-para2"><?php echo $_smarty_tpl->getValue('v')['fahrzeugzustand'];?>
</p>
											<p class="new-cars-para2">Пробіг: <?php echo $_smarty_tpl->getValue('v')['kilometerstand'];?>
	 км.
											<br>Рік випуску: <?php echo $_smarty_tpl->getValue('v')['erstzulassung'];?>

											<br>Коробка передач: <?php echo $_smarty_tpl->getValue('v')['Getriebe'];?>

											<br>
											</p>
											<button class="welcome-btn model-search-btn" onclick="window.location.href='//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
'">
											    Детально
											</button>
											<!--<a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
" class="btn btn-info">Детально</a>-->
										</div><!--/.new-cars-txt-->	
									</div><!--/.col-->
								</div><!--/.row-->
							</div><!--/.single-new-cars-item-->
						</div><!--/.new-cars-item-->
						<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
					</div><!--/#new-cars-carousel-->
				</div><!--/.new-cars-content-->
			</div><!--/.container-->

		</section><!--/.new-cars-->
		<!--new-cars end -->

		<!--featured-cars start -->
		<section id="featured-cars" class="featured-cars">
			<div class="container">
				<div class="section-header">
					<p>Актуальна інформація авто з Европейських ресурсів <span></span> </p>
					<h2>Доступні авто у продажу в Європі</h2>
				</div><!--/.section-header-->
				<div class="featured-cars-content">
					<div class="row">
						
						<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('allCars'), 'v', false, 'k');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('k')->value => $_smarty_tpl->getVariable('v')->value) {
$foreach1DoElse = false;
?>
						<div class="col-lg-3 col-md-4 col-sm-6">
							<div class="single-featured-cars">
								<div class="featured-img-box">
									<div class="featured-cars-img">
									<a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
"><img src="img/thumb/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('v')['img_thumb'];?>
" alt="<?php echo $_smarty_tpl->getValue('v')['name_announce'];?>
"></a>
									</div>
									<div class="featured-model-info">
										<p>
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
									<h2><a href="//<?php echo $_smarty_tpl->getValue('domainName');?>
/view/<?php echo $_smarty_tpl->getValue('v')['img_dir'];?>
-<?php echo $_smarty_tpl->getValue('v')['url_announce'];?>
"><?php echo $_smarty_tpl->getValue('v')['marke'];?>
  <?php echo $_smarty_tpl->getValue('v')['modell'];?>
</a></h2>
									<h3><?php echo $_smarty_tpl->getValue('v')['price'];?>
 Евро</h3>
									<p>
										<?php echo $_smarty_tpl->getValue('v')['name_announce'];?>

									</p>
								</div>
							</div>
						</div>
						<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
					
					</div>
				</div>
				<div class="section-header">
					<button class="welcome-btn model-search-btn" onclick="window.location.href='//zsuauto.info/all-cars'">
					    Дивитись всі варіанти авто
					</button>
				</div><!--/.section-header-->
			</div><!--/.container-->

		</section><!--/.featured-cars-->
		<!--featured-cars end -->




		<!--featured-cars start -->
		<section id="featured-cars" class="featured-cars">
			<div class="container">
				<div class="section-header">
				</div>
				
				<div class="section-header">
				    <!--<iframe width="420" height="315"
				    src="https://youtube.com/shorts/jM5FyVZvkTU?si=EEJytJCGCqB">
				    </iframe>-->
				</div><!--/.section-header-->
			</div><!--/.container-->

		</section>


		<!-- clients-say strat -->
		<section id="clients-say"  class="clients-say">
			<div class="container">
				<div class="section-header">
					<h2>Відгуки військових</h2>
				</div><!--/.section-header-->
				<div class="row">
					<div class="owl-carousel testimonial-carousel">
						<div class="col-sm-3 col-xs-12">
							<div class="single-testimonial-box">
								<div class="testimonial-description">
									<div class="testimonial-info">
										<div class="testimonial-img">
											<img src="assets/images/clients/c1.png" alt="image of clients person" />
										</div><!--/.testimonial-img-->
									</div><!--/.testimonial-info-->
									<div class="testimonial-comment">
										<p>
											Страшна на погляд, але справна в середині, працьє навід круіз контрол. Класного буса привезли нам в підрозділ! Дуже дякуємо!
										</p>
									</div><!--/.testimonial-comment-->
									<div class="testimonial-person">
										<h2><a href="#">Андрій Ко</a></h2>
										<h4>Авідійвка</h4>
									</div><!--/.testimonial-person-->
								</div><!--/.testimonial-description-->
							</div><!--/.single-testimonial-box-->
						</div><!--/.col-->
						<div class="col-sm-3 col-xs-12">
							<div class="single-testimonial-box">
								<div class="testimonial-description">
									<div class="testimonial-info">
										<div class="testimonial-img">
											<img src="assets/images/clients/c2.png" alt="image of clients person" />
										</div><!--/.testimonial-img-->
									</div><!--/.testimonial-info-->
									<div class="testimonial-comment">
										<p>
											Неочікуванно швидко доставили та ще по грошах приємно вийшло. Так тримати!
										</p>
									</div><!--/.testimonial-comment-->
									<div class="testimonial-person">
										<h2><a href="#">Медична Лиска</a></h2>
										<h4>Харьківський напрямок</h4>
									</div><!--/.testimonial-person-->
								</div><!--/.testimonial-description-->
							</div><!--/.single-testimonial-box-->
						</div><!--/.col-->
						<div class="col-sm-3 col-xs-12">
							<div class="single-testimonial-box">
								<div class="testimonial-description">
									<div class="testimonial-info">
										<div class="testimonial-img">
											<img src="assets/images/clients/c3.png" alt="image of clients person" />
										</div><!--/.testimonial-img-->
									</div><!--/.testimonial-info-->
									<div class="testimonial-comment">
										<p>
											Розуміємо що не нове авто але ціна та зворотній зв'зок просто на висоті! Авто прийшло як по фото. Відео та фото звіт простий та прозорий!.
										</p>
									</div><!--/.testimonial-comment-->
									<div class="testimonial-person">
										<h2><a href="#">Джон Дир</a></h2>
										<h4>пре Бахмут</h4>
									</div><!--/.testimonial-person-->
								</div><!--/.testimonial-description-->
							</div><!--/.single-testimonial-box-->
						</div><!--/.col-->
					</div><!--/.testimonial-carousel-->
				</div><!--/.row-->
			</div><!--/.container-->

		</section><!--/.clients-say-->	
		<!-- clients-say end -->

		<!--brand strat -->
		<section id="brand"  class="brand">
			<div class="container">
				<div class="brand-area" style="display:none">
					<div class="owl-carousel owl-theme brand-item">
						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br1.png" alt="brand-image" />
							</a>
						</div><!--/.item-->
						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br2.png" alt="brand-image" />
							</a>
						</div><!--/.item-->
						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br3.png" alt="brand-image" />
							</a>
						</div><!--/.item-->
						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br4.png" alt="brand-image" />
							</a>
						</div><!--/.item-->

						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br5.png" alt="brand-image" />
							</a>
						</div><!--/.item-->

						<div class="item">
							<a href="#">
								<img src="assets/images/brand/br6.png" alt="brand-image" />
							</a>
						</div><!--/.item-->
					</div><!--/.owl-carousel-->
				</div><!--/.clients-area-->

			</div><!--/.container-->

		</section><!--/brand-->	
		<!--brand end -->
<?php }
}
