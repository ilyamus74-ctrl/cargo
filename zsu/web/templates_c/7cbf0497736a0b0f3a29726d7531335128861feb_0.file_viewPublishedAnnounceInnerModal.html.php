<?php
/* Smarty version 5.3.1, created on 2024-07-16 06:49:01
  from 'file:NiceAdmin/viewPublishedAnnounceInnerModal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_669617dd76ca59_35463017',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7cbf0497736a0b0f3a29726d7531335128861feb' => 
    array (
      0 => 'NiceAdmin/viewPublishedAnnounceInnerModal.html',
      1 => 1720774490,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_669617dd76ca59_35463017 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates/NiceAdmin';
?>                
                <div class="card">
                <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                  <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="" aria-label="Slide 1"></button>
                  <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2" class="active" aria-current="true"></button>
                  <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3" class=""></button>
                </div>
                <div class="carousel-inner w-50">
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('viewCar')['img_announce'], 'v', false, 'k');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('k')->value => $_smarty_tpl->getVariable('v')->value) {
$foreach0DoElse = false;
?>
                  <?php if ($_smarty_tpl->getValue('k') <= 2) {?>
                  <div class="carousel-item <?php if ($_smarty_tpl->getValue('k') == 0) {?> active <?php }?>">
                    <img src="imgLink/announce/<?php echo $_smarty_tpl->getValue('viewCar')['img_dir'];?>
/<?php echo $_smarty_tpl->getValue('v');?>
" class="d-block w-50" alt="...">
                  </div>
                  <?php }?>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </div>
                
                </div>

                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                  <span class="carousel-control-next-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Next</span>
                </button>

              </div>
            	    <input type="hidden" id="idAnnounce" value="<?php echo $_smarty_tpl->getValue('viewCar')['id'];?>
">
            	    <div class="card-body">Назва об'яви, <?php echo $_smarty_tpl->getValue('viewCar')['img_dir'];?>
 зроби привабливим!
            	    <div class="row mb-3">
                  <label for="inputText" class="col-sm-2 col-form-label">
                  <input type="hidden" id="pname" value="<?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>
">
                  <?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>

                  </label>
                  <div class="col-sm-10">
                    <input type="text" id="NewName" value="<?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>
"  class="form-control">
                  </div>
                </div>
            	    </div>
                    <div class="card-body">
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['marke'];?>
');">Марка: <?php echo $_smarty_tpl->getValue('viewCar')['marke'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['modell'];?>
');">Модель: <?php echo $_smarty_tpl->getValue('viewCar')['modell'];?>
</div>
                    <div onclick="makeNewName('пробіг: ','<?php echo $_smarty_tpl->getValue('viewCar')['kilometerstand'];?>
');">Пробіг: <?php echo $_smarty_tpl->getValue('viewCar')['kilometerstand'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['fahrzeugzustand'];?>
');">Ушкодження: <?php echo $_smarty_tpl->getValue('viewCar')['fahrzeugzustand'];?>
</div>
                    <div onclick="makeNewName('рік: ','<?php echo $_smarty_tpl->getValue('viewCar')['erstzulassung'];?>
');">Рік випуску: <?php echo $_smarty_tpl->getValue('viewCar')['erstzulassung'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['kraftstoffart'];?>
');">Паливо: <?php echo $_smarty_tpl->getValue('viewCar')['kraftstoffart'];?>
</div>
                    <div onclick="makeNewName('потужність:','<?php echo $_smarty_tpl->getValue('viewCar')['Leistung'];?>
');">Потужність: <?php echo $_smarty_tpl->getValue('viewCar')['Leistung'];?>
</div>
                    <div onclick="makeNewName('коробка: ','<?php echo $_smarty_tpl->getValue('viewCar')['Getriebe'];?>
');">Коробка: <?php echo $_smarty_tpl->getValue('viewCar')['Getriebe'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['fahrzeugtyp'];?>
');">Тип авто: <?php echo $_smarty_tpl->getValue('viewCar')['fahrzeugtyp'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['anzahl_turen'];?>
');">Двері: <?php echo $_smarty_tpl->getValue('viewCar')['anzahl_turen'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['hu_bis'];?>
');">Техогляд: <?php echo $_smarty_tpl->getValue('viewCar')['hu_bis'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['umweltplakette'];?>
');">Значок Єкологія: <?php echo $_smarty_tpl->getValue('viewCar')['umweltplakette'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['schadstoffklasse'];?>
');">Єкологія: <?php echo $_smarty_tpl->getValue('viewCar')['schadstoffklasse'];?>
</div>
                    <div onclick="makeNewName('ціна: ','<?php echo $_smarty_tpl->getValue('viewCar')['price'];?>
');">Ціна: <?php echo $_smarty_tpl->getValue('viewCar')['price'];?>
</div>
                    <div onclick="makeNewName('','<?php echo $_smarty_tpl->getValue('viewCar')['farbe'];?>
');">Колір: <?php echo $_smarty_tpl->getValue('viewCar')['farbe'];?>
</div>
                    </div>
                    <div class="card-body">
                    <?php echo $_smarty_tpl->getValue('viewCar')['text_full_announce'];?>

                    </div>
                    </div>
<?php }
}
