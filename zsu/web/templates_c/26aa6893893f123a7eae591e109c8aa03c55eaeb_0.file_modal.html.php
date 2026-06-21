<?php
/* Smarty version 5.3.1, created on 2024-07-12 08:59:38
  from 'file:modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6690f07a399926_96449972',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '26aa6893893f123a7eae591e109c8aa03c55eaeb' => 
    array (
      0 => 'modal.html',
      1 => 1720294345,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6690f07a399926_96449972 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/zsuauto/web/templates';
?><!-- The Modal -->
<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close" onclick="closeModal();">&times;</span>
		<div id="reaSuccessful" style="display:none">Запит успішно надісланно, чекайте зворотнього зв'язку!</div>
		<div id="reqText" style="display:block">
              <h5 class="card-title">Віконце запиту</h5>
              <p>Залиште будьласка ваше ім'я  та контактний номер телефону. Як буде час у волонтера він вийде з вами на зв'язок!</p>

              <!-- Browser Default Validation -->
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="validationDefault01" class="form-label">Ваше ім'я</label>
                  <input type="text" class="form-control" id="validationDefault01" value="" required="" placeholder="Кракен" onkeyup="checkThisForm();">
                </div>
                <div class="col-md-4">
                  <label for="validationDefault02" class="form-label">Ваш контактний номер телеону</label>
                  <input type="text" class="form-control" id="validationDefault02" value="" required="" placeholder="+380XXXXXXX" onkeyup="checkThisForm();">
                </div>
                <div class="col-md-4">
                  <div class="input-group">
                  </div>
                </div>
                <div class="col-md-6">
            	    <?php if (!empty($_smarty_tpl->getValue('viewCar')['name_announce'])) {?>
            	    <div id="detalCar" style="display:block">
            	    Запит стосовно машини <b><?php echo $_smarty_tpl->getValue('viewCar')['name_announce'];?>
</b>
            	    <input type="hidden" value="" name="car" id="car">
            	    </div>
            	    <?php } else { ?>
            	    <div id="detalCar" style="display:none">
            	    Запит стосовно машини 
            	    <input type="hidden" value="" name="car" id="car">
            	    </div>
            	    <?php }?>
            	    
                </div>
                <div class="col-md-3">
                </div>
                <div class="col-md-3">
                
                </div>
                <div class="col-12">
                  <div class="form-check">
                  <input type="hidden" value="<?php echo $_smarty_tpl->getValue('secCode');?>
" name="secCode" id="secCode" >
                  </div>
                </div>
                <div class="col-12">
                  <button class="welcome-btn btn btn-warning btn-warning-lg" id="sendRequestButton" type="submit" disabled onclick="sendReqestUser();">Надіслати запит</button>
                </div>
              </div>
              <!-- End Browser Default Validation -->
	    </div>

            
  </div>

</div>
<!-- The Modal end-->
<?php }
}
