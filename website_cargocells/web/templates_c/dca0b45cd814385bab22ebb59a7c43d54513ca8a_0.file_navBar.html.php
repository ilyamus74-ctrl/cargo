<?php
/* Smarty version 5.3.1, created on 2026-01-27 19:41:12
  from 'file:navBar.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697914d8f014e1_26745187',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'dca0b45cd814385bab22ebb59a7c43d54513ca8a' => 
    array (
      0 => 'navBar.html',
      1 => 1760943752,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697914d8f014e1_26745187 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cellscargo/web/templates';
?>        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
            <div class="container">
		<a class="navbar-brand" href="/">
		<svg xmlns="http://www.w3.org/2000/svg" width="220" height="40" viewBox="0 0 220 40" role="img" aria-label="EASY TRADE"><defs>
			<!-- Сине-голубой для графика -->
			<linearGradient id="lineGrad" x1="0" y1="0" x2="1" y2="0">
    			<stop offset="0" stop-color="#0ea5e9"/>
    			<stop offset="1" stop-color="#1f6feb"/>
			</linearGradient>
			<!-- Золотой градиент для текста -->
			<linearGradient id="goldGrad" x1="0" y1="0" x2="0" y2="1">
    			<stop offset="0" stop-color="#f59e0b"/>  <!-- amber-500 -->
    			<stop offset="1" stop-color="#f59e0b"/>  <!-- amber-200 -->
			</linearGradient>
			    </defs>

			  <!-- Иконка: тёмный квадратик + цветной спарклайн и точки вход/выход -->
			    <g transform="translate(4,6)">
			<!-- Фон-карточка -->
			    <rect x="0" y="0" width="28" height="28" rx="6" fill="#444444" opacity="0.85"/>
			<!-- Спарклайн -->
			    <path d="M3,20 L8,16 L12,18 L16,12 L20,14 L24,8" fill="none" stroke="url(#lineGrad)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		        <!-- Вход (зелёная точка) -->
			    <circle cx="8" cy="16" r="2.6" fill="#10b981"/>
		        <!-- Выход (красная точка) -->
			    <circle cx="24" cy="8" r="2.6" fill="#ef4444"/>
			    </g>

			  <!-- Вордмарк: золотой градиент (хорошо читается и на тёмной, и на светлой шапке) -->
			      <g transform="translate(40,26)">
			    <!-- Тонкий тёмный обвод для контраста на светлом фоне -->
	<text x="0" y="0" font-family="Inter, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="18" font-weight="800" fill="url(#goldGrad)" stroke="#1f2937" stroke-width="0.5" paint-order="stroke"> Easy </text>
        <text x="55" y="0" font-family="Inter, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="18" font-weight="800" fill="url(#goldGrad)" stroke="#1f2937" stroke-width="0.5" paint-order="stroke"> Trade </text>
	</g></svg></a>


                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars ms-1"></i>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                        <!--<li class="nav-item"><a class="nav-link" href="#services"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainNavBarService");?>
</a></li>-->
                        <li class="nav-item"><a class="nav-link" href="predictions.html"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainNavBarPredict");?>
</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.html"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainNavBarAbout");?>
</a></li>
                        <li class="nav-item"><a class="nav-link" href="team.html"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainNavBarTeam");?>
</a></li>
                        <li class="nav-item"><a class="nav-link" href="contact.html"><?php echo $_smarty_tpl->getSmarty()->getModifierCallback('__')("mainNavBarContact");?>
</a></li>

                    </ul>
                    
<nav class="lang-switch">
  <a href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/uk/<?php echo (($tmp = $_smarty_tpl->getValue('currentPath') ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
" class="<?php if ($_smarty_tpl->getValue('xlang') == 'uk') {?>active<?php }?>">UA</a>
  <a href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/de/<?php echo (($tmp = $_smarty_tpl->getValue('currentPath') ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
" class="<?php if ($_smarty_tpl->getValue('xlang') == 'de') {?>active<?php }?>">DE</a>
  <a href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/en/<?php echo (($tmp = $_smarty_tpl->getValue('currentPath') ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
" class="<?php if ($_smarty_tpl->getValue('xlang') == 'en') {?>active<?php }?>">EN</a>
  <a href="https://<?php echo $_smarty_tpl->getValue('domainName');?>
/ru/<?php echo (($tmp = $_smarty_tpl->getValue('currentPath') ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
" class="<?php if ($_smarty_tpl->getValue('xlang') == 'ru') {?>active<?php }?>">RU</a>
</nav>
                </div>
            </div>
        </nav>
<?php }
}
