<?php
/* Smarty version 5.3.1, created on 2025-12-15 08:42:20
  from 'file:cells_NA_API_tools_stock_form.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_693fc9ec60bc51_92322003',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '6d55e47b00793e3a8ae1867fa293107185d64564' => 
    array (
      0 => 'cells_NA_API_tools_stock_form.html',
      1 => 1765788131,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_693fc9ec60bc51_92322003 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<section class="section profile">
  <div class="row">
    <div class="col-xl-4">

      <div class="card">
        <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">

          <h2><?php echo $_smarty_tpl->getValue('edit_tool')['NameTool'];?>
</h2>
          <h3><?php echo $_smarty_tpl->getValue('edit_user')['SerialNumber'];?>
</h3>
          <div class="social-links mt-2">
          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('edit_tool')['img_patch'], 'r');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('r')->value) {
$foreach0DoElse = false;
?>
          <img src="/img/tool/<?php echo $_smarty_tpl->getValue('edit_tool')['id'];?>
/r.png" alt="Profile" class="rounded-circle">
          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            <!--<a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>-->
          </div>
        </div>
      </div>

    </div>

    <div class="col-xl-8">

      <div class="card">
        <div class="card-body pt-3">

          <!-- ОДИН общий form на все вкладки -->
          <form id="tool-profile-form">
            <input type="hidden" name="tool_id" id="tool_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('edit_tool')['id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
">

            <!-- Bordered Tabs -->
            <ul class="nav nav-tabs nav-tabs-bordered">
              <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview" type="button">Overview</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit" type="button">Edit Tool</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-settings" type="button">Remark</button>
              </li>
<!--
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password" type="button">Change Password</button>
              </li>
-->
            </ul>

            <div class="tab-content pt-2">

              <!-- OVERVIEW -->
              <div class="tab-pane fade show active profile-overview" id="profile-overview">
                <h5 class="card-title">About</h5>
                <p class="small fst-italic"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['about'];?>
</p>

                <h5 class="card-title">Tool Details</h5>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label ">Name Tool</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['NameTool'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Serial number</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['SerialNumber'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Waranty / days</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['WarantyDays'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Price buy</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['PriceBuy'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Date buy</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['DateBuy'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Add in system date</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['AddInSystem'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Resource / days</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['ResourceDays'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Resurce end date</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_tool')['ResourceEndDate'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">QR Code tool</div>
                  <img src="/img/tool/<?php echo $_smarty_tpl->getValue('edit_tool')['id'];?>
/qr.png" alt="Profile" class="rounded-circle">
                  <!--<div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['email'];?>
</div>-->
                </div>
              </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">
                  <label for="Email" class="col-md-4 col-lg-3 col-form-label">Статус</label>
                  </div>
                </div>


              <!-- EDIT PROFILE -->
              <div class="tab-pane fade profile-edit pt-3" id="profile-edit">

                <div class="row mb-3">
                  <label for="profileImage" class="col-md-4 col-lg-3 col-form-label">Tool Image</label>
                  <div class="col-md-8 col-lg-9">
                    <!--<img src="assets/img/profile-img.jpg" alt="Profile">-->
                    <div class="pt-2" id="uploadPhoto" display="none">
                      <a href="#" class="btn btn-primary btn-sm" title="Upload new profile image"><i class="bi bi-upload"></i></a>
                      <a href="#" class="btn btn-danger btn-sm" title="Remove my profile image"><i class="bi bi-trash"></i></a>
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Name Tool</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="NameTool" type="text" class="form-control" id="NameTool"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['NameTool'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                </div>

                <div class="row mb-3">
                  <label for="company" class="col-md-4 col-lg-3 col-form-label">Serial number</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="SerialNumber" type="text" class="form-control" id="SerialNumber"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['SerialNumber'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Job" class="col-md-4 col-lg-3 col-form-label">Waranty / days</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="WarantyDays" type="numer" class="form-control" id="WarantyDays"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['WarantyDays'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Country" class="col-md-4 col-lg-3 col-form-label">Price buy</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="PriceBuy" type="text" class="form-control" id="PriceBuy"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['PriceBuy'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Address" class="col-md-4 col-lg-3 col-form-label">Date buy</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="DateBuy" type="date" class="form-control" id="DateBuy"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['DateBuy'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Phone" class="col-md-4 col-lg-3 col-form-label">Add in system</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="AddInSystem" type="date" class="form-control" id="AddInSystem"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['AddInSystem'];?>
" disabled>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Email" class="col-md-4 col-lg-3 col-form-label">Resource / days</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="ResourceDays" type="nuber" class="form-control" id="ResourceDays"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['ResourceDays'];?>
">
                  </div>
                </div>


                <div class="row mb-3">
                  <label for="Email" class="col-md-4 col-lg-3 col-form-label">Resource end date</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="ResourceEndDate" type="date" class="form-control" id="ResourceEndDate"
                           value="<?php echo $_smarty_tpl->getValue('edit_tool')['ResourceEndDate'];?>
" disabled>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Email" class="col-md-4 col-lg-3 col-form-label">Статус</label>
                  <div class="col-md-8 col-lg-9">
                  <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="statusCheck" 
                      <?php if ($_smarty_tpl->getValue('edit_tool')['status'] == '1') {?> checked="true" <?php } else { ?> checked ="false" <?php }?>> 
                      <label class="form-check-label" for="statusCheck">Если активный инструмент он доступен для работы</label>
                  </div>
                  </div>
                </div>

                <div class="text-center">
                  <button type="button"
                          class="btn btn-primary js-core-link"
                          data-core-action="save_tool">Save Changes</button>
                </div>
              </div>

              <!-- SETTINGS -->
              <div class="tab-pane fade pt-3" id="profile-settings">

                <div class="row mb-3">
                      <label class="col-sm-2 col-form-label">Привязать к пользователю</label>
                      <div class="col-sm-10">
                        <select class="form-select" name="role" aria-label="Default select example">
                          <option value="0" selected="">Выбрать один из</option>
                          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('roles'), 'r');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('r')->value) {
$foreach1DoElse = false;
?>
                            <option value="<?php echo $_smarty_tpl->getValue('r')['code'];?>
" <?php if ((($tmp = $_smarty_tpl->getValue('edit_user')['role'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == $_smarty_tpl->getValue('r')['code']) {?>selected<?php }?>><?php echo $_smarty_tpl->getValue('r')['code'];?>
</option>
                          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                        </select>
                      </div>
                    </div>
                  <div class="row mt-3">
                  <label for="about" class="col-md-4 col-lg-3 col-form-label">Notes</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea name="notes" class="form-control" id="about" style="height: 100px"><?php echo $_smarty_tpl->getValue('edit_tool')['notes'];?>
</textarea>
                 </div>
                 </div>
                                 <!--
                  <label class="col-md-4 col-lg-3 col-form-label">Email Notifications</label>
                  <div class="col-md-8 col-lg-9">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="changesMade" id="changesMade" checked>
                      <label class="form-check-label" for="changesMade">
                        Changes made to your account
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="newProducts" id="newProducts" checked>
                      <label class="form-check-label" for="newProducts">
                        Information on new products and services
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proOffers" id="proOffers">
                      <label class="form-check-label" for="proOffers">
                        Marketing and promo offers
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="securityNotify" id="securityNotify" checked disabled>
                      <label class="form-check-label" for="securityNotify">
                        Security alerts
                      </label>
                    </div>-->



                    <div class="row mt-3">
                       <div class="col-lg-3 col-md-4 label">История изминений</div>
                          <div class="col-lg-9 col-md-8">
                          </div>
                       </div>
                  </div>

<!--                  <div class="text-center">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_user">Save Changes</button>
                  </div>
-->
                </div>

              </div>
              </div>

              <!-- CHANGE PASSWORD -->
              <div class="tab-pane fade pt-3" id="profile-change-password">
                <div class="row mb-3">
                  <label for="newPassword" class="col-md-4 col-lg-3 col-form-label">New Password</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="newpassword" type="password" class="form-control" id="newPassword">
                  </div>
                </div>

                <div class="text-center">
                  <button type="button"  class="btn btn-primary js-core-link"  data-core-action="save_user">Change Password</button>
                </div>
              </div>

            </div><!-- /.tab-content -->
          </form><!-- /#user-profile-form -->

        </div>
      </div>

    </div>
  </div>
</section>
<?php }
}
