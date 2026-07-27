<?php
/* Smarty version 5.3.1, created on 2024-07-12 18:41:39
  from 'file:prigon-avto-dlya-viyskovih.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_669178e38dcc94_68499823',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '81cfd6b3f736ae361714b819a4a4081bd0d8a2b9' => 
    array (
      0 => 'prigon-avto-dlya-viyskovih.html',
      1 => 1720294028,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_669178e38dcc94_68499823 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?>			<div class="container">
				<div class="welcome-hero-txt">
					<h1 class="animated fadeInUp"><?php echo $_smarty_tpl->getValue('data')['main_text_h1'];?>
</h1>
					<p>
					<?php echo $_smarty_tpl->getValue('data')['main_text'];?>

					</p>
					<button class="welcome-btn" onclick="window.location.href='//zsuauto.info/all-cars'">
					    Дивитись всі варіанти авто
					</button>
					<br><br><p>або переходь</p>
					<button class="welcome-btn " onclick="window.location.href='//zsuauto.info/all-cars'">
					    Дивитись всі варіанти авто
					</button>
				</div>
			</div>




		<!--service start -->
		<section id="service" class="service">
			<div class="container" style="display:none">
				<div class="service-content">
					<div class="row">
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car"></i>
								</div>
								<h2><a href="#">Впевниність підбору <span> авто </span> </a></h2>
								<p>
									Ми шукаємо тільки ті авто які технічно справні і можоть бути використанні на фронті без глабальних фінансових вкладень. Зайвий клопіт нікому не треба!
								</p>
							</div>
						</div>
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car-repair"></i>
								</div>
								<h2><a href="#">Тільки технічно справні авто</a></h2>
								<p>Авто яки ми пропонуємо відповідають опису та діляться на дві категоріі.
								 <br>
								 <br><b>1. Повністью справні до використання на дорогах ЄС.
								 <br>2.Умовно справні, справні та не придатні до використання на дороах ЕС.</b></p>
								
							</div>
						</div>
						<div class="col-md-4 col-sm-6">
							<div class="single-service-item">
								<div class="single-service-icon">
									<i class="flaticon-car-1"></i>
								</div>
								<h2><a href="#">Повний звіт з послуги доставки авто</a></h2>
								<p>
								    Незалежно від того хто замовляє авто, ми робимо повний звіт всіх фінансових витрат перед військовослужбовцем! Довіра та мета перемоги України понад усе!
								</p>
							</div>
						</div>
					</div>
				</div>
			</div><!--/.container-->

		</section><!--/.service-->
		<!--service end-->

<?php }
}
