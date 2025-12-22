<?php
/* Smarty version 5.3.1, created on 2025-12-17 15:30:47
  from 'file:cells_NA_API_profile.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6942cca76e1ad1_96206078',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '0d25ca146ef359be6b7ece6c255ef91c3f386b11' => 
    array (
      0 => 'cells_NA_API_profile.html',
      1 => 1765985441,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6942cca76e1ad1_96206078 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<section class="section profile">
  <div class="row">
    <div class="col-xl-4">

      <div class="card">
        <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">

          <img src="assets/img/profile-img.jpg" alt="Profile" class="rounded-circle">
          <h2><?php echo $_smarty_tpl->getValue('edit_user')['full_name'];?>
</h2>
          <h3><?php echo $_smarty_tpl->getValue('edit_user')['settings']['job'];?>
</h3>
          <div class="social-links mt-2">
            <a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
      </div>

    </div>

    <div class="col-xl-8">

      <div class="card">
        <div class="card-body pt-3">

          <!-- ОДИН общий form на все вкладки -->
          <form id="user-profile-form">
            <input type="hidden" name="user_id" id="user_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('edit_user')['id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
">

            <!-- Bordered Tabs -->
            <ul class="nav nav-tabs nav-tabs-bordered">
              <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview" type="button">Overview</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit" type="button">Edit Profile</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-settings" type="button">Settings</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password" type="button">Change Password</button>
              </li>
            </ul>

            <div class="tab-content pt-2">

              <!-- OVERVIEW -->
              <div class="tab-pane fade show active profile-overview" id="profile-overview">
                <h5 class="card-title">About</h5>
                <p class="small fst-italic"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['about'];?>
</p>

                <h5 class="card-title">Profile Details</h5>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label ">Full Name</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['full_name'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Company</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['company'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Job</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['job'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Country</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['country'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Address</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['address'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Phone</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['phone'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Email</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('edit_user')['email'];?>
</div>
                </div>
              </div>

              <!-- EDIT PROFILE -->
              <div class="tab-pane fade profile-edit pt-3" id="profile-edit">

                <div class="row mb-3">
                  <label for="profileImage" class="col-md-4 col-lg-3 col-form-label">Profile Image</label>
                  <div class="col-md-8 col-lg-9">
                    <img src="assets/img/profile-img.jpg" alt="Profile">
                    <div class="pt-2">
                      <a href="#" class="btn btn-primary btn-sm" title="Upload new profile image"><i class="bi bi-upload"></i></a>
                      <a href="#" class="btn btn-danger btn-sm" title="Remove my profile image"><i class="bi bi-trash"></i></a>
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Full Name</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="fullName" type="text" class="form-control" id="fullName"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['full_name'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="about" class="col-md-4 col-lg-3 col-form-label">About</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea name="about" class="form-control" id="about" style="height: 100px"><?php echo $_smarty_tpl->getValue('edit_user')['settings']['about'];?>
</textarea>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="company" class="col-md-4 col-lg-3 col-form-label">Company</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="company" type="text" class="form-control" id="company"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['settings']['company'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Job" class="col-md-4 col-lg-3 col-form-label">Job</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="job" type="text" class="form-control" id="Job"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['settings']['job'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Country" class="col-md-4 col-lg-3 col-form-label">Country</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="country" type="text" class="form-control" id="Country"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['settings']['country'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Address" class="col-md-4 col-lg-3 col-form-label">Address</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="address" type="text" class="form-control" id="Address"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['settings']['address'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Phone" class="col-md-4 col-lg-3 col-form-label">Phone</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="phone" type="text" class="form-control" id="Phone"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['settings']['phone'];?>
">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="Email" class="col-md-4 col-lg-3 col-form-label">Email</label>
                  <div class="col-md-8 col-lg-9">
                    <input name="email" type="email" class="form-control" id="Email"
                           value="<?php echo $_smarty_tpl->getValue('edit_user')['email'];?>
">
                  </div>
                </div>

                <div class="text-center">
                  <button type="button"
                          class="btn btn-primary js-core-link"
                          data-core-action="save_user">Save Changes</button>
                </div>
              </div>

              <!-- SETTINGS -->
              <div class="tab-pane fade pt-3" id="profile-settings">
                <div class="row mb-3"><!--
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

                    <div class="mt-3">

                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="delete" name="delete">
                          <label class="form-check-label" for="securityNotify">
                            Удалить пользователя
                          </label>
                        </div>

                      <label class="col-sm-2 col-form-label">Роль</label>
                      <div class="col-sm-10">
                        <select class="form-select" name="role" aria-label="Default select example">
                          <option value="0" selected="">Выбрать один из</option>
                          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('roles'), 'r');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('r')->value) {
$foreach0DoElse = false;
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
                      <div class="col-lg-3 col-md-4 label">QR для входа</div>
                      <div class="col-lg-9 col-md-8">
                        <?php if ($_smarty_tpl->getValue('edit_user')['qr_image_url']) {?>
                          <div class="d-flex flex-column flex-md-row align-items-start gap-3">
                            <img id="user-qr-image" src="<?php echo $_smarty_tpl->getValue('edit_user')['qr_image_url'];?>
" alt="QR для входа" class="img-fluid" style="max-width:220px;">
                            <div>
                              <div class="form-text">
                                Отсканируйте этот QR в приложении сканера.
                              </div>
                              <button type="button" class="btn btn-outline-primary mt-2" onclick="printUserQr()">Распечатать</button>
                            </div>
                          </div>
                        <?php } else { ?>
                          <span class="text-muted">QR ещё не сгенерирован. Нажмите «Обновить QR для всех».</span>
                        <?php }?>
                      </div>
                  </div>
                  </div>
                  <div class="text-center">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_user">Save Changes</button>
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
