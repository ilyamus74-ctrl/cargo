<?php
/* Smarty version 5.3.1, created on 2025-12-22 19:27:10
  from 'file:cells_NA_API_devices_profile.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69499b8ebdad28_34693778',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '66c932c2de09ea981a94eea94e66fa27ebd20013' => 
    array (
      0 => 'cells_NA_API_devices_profile.html',
      1 => 1765985650,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69499b8ebdad28_34693778 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<section class="section profile">
  <div class="row">
   <div class="col-xl-4">


      <div class="card">
        <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">

          <!--<img src="assets/img/profile-img.jpg" alt="Profile" class="rounded-circle">-->
          <h2><?php echo $_smarty_tpl->getValue('device')['name'];?>
</h2>
          <h3><?php echo $_smarty_tpl->getValue('device')['model'];?>
</h3>
        </div>
      </div>
    </div>


    <div class="col-xl-8">

      <div class="card">
        <div class="card-body pt-3">

          <!-- ОДИН общий form на все вкладки -->
          <form id="device-profile-form">
            <input type="hidden" name="device_id" id="device_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('device')['id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
">

            <!-- Bordered Tabs -->
            <ul class="nav nav-tabs nav-tabs-bordered">
              <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview" type="button">Overview</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit" type="button">All log</button>
              </li>

              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-settings" type="button">Settings</button>
              </li>

            </ul>

            <div class="tab-content pt-2">

              <!-- OVERVIEW -->
              <div class="tab-pane fade show active profile-overview" id="profile-overview">

                <h5 class="card-title">Device details</h5>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label ">Name</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['name'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Serial</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['serial'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Model</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['model'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">App version</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['app_version'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">token</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['device_token'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Статус</div>
                  <div class="col-lg-9 col-md-8"><?php if ($_smarty_tpl->getValue('device')['is_active'] == 1) {?>Actived<?php } else { ?>NOT Actived<?php }?></div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Дата активации</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['activated_at'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Last created</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['last_created_at'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Last seent</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['last_seen_at'];?>
</div>
                </div>

                <div class="row">
                  <div class="col-lg-3 col-md-4 label">Last IP</div>
                  <div class="col-lg-9 col-md-8"><?php echo $_smarty_tpl->getValue('device')['last_ip'];?>
</div>
                </div>
              </div>
              

              <!-- EDIT PROFILE -->
              <div class="tab-pane fade profile-edit pt-3" id="profile-edit">
                <div class="row mb-3">
                <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('device')['logs'])) {?>
                <h5>Последние события по устройству</h5>
                <table class="table table-sm">
                <thead>
                      <tr>
                      <th>Время</th>
                      <th>User ID</th>
                      <th>Тип</th>
                      <th>Entity ID</th>
                      <th>IP</th>
                      <th>User-Agent</th>
                      <th>Описание</th>
                      </tr>
               </thead>
               <tbody>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('device')['logs'], 'log');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('log')->value) {
$foreach0DoElse = false;
?>
                      <tr>
                      <td><?php echo $_smarty_tpl->getValue('log')['event_time'];?>
</td>
                      <td><?php echo $_smarty_tpl->getValue('log')['user_id'];?>
</td>
                      <td><?php echo $_smarty_tpl->getValue('log')['event_type'];?>
</td>
                      <td><?php echo $_smarty_tpl->getValue('log')['entity_id'];?>
</td>
                      <td><?php echo $_smarty_tpl->getValue('log')['ip_address'];?>
</td>
                      <td class="small"><?php echo $_smarty_tpl->getValue('log')['user_agent'];?>
</td>
                      <td><?php echo $_smarty_tpl->getValue('log')['description'];?>
</td>
                      </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
               </tbody>
               </table>
               <?php }?>
               </div>
              </div>

              <!-- SETTINGS -->
              <div class="tab-pane fade pt-3" id="profile-settings">
                <div class="row mb-3">

                    <div class="mt-3">

                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="delete" name="delete">
                  <label class="form-check-label" for="securityNotify">
                    Удалить устройство
                  </label>
                </div>


                  <div class="form-check"">
                  <input class="form-check-input" type="checkbox" id="status" name="is_active" value="1" <?php if ($_smarty_tpl->getValue('device')['is_active'] == 1) {?>checked<?php }?>>  <label class="form-check-label" for="status">
                  Статус регистрации в системе
                  </label>
                  </div>



                  </div>
                  </div>

                <div class="row mb-3">
                  <label for="about" class="col-md-4 col-lg-3 col-form-label">Notes</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea name="notes" class="form-control" id="notes" style="height: 100px"><?php echo $_smarty_tpl->getValue('device')['notes'];?>
</textarea>
                  </div>
                </div>

                  <div class="text-center">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_device">Save Changes</button>
                  </div>

                </div>

              </div>


              </div>

            </div><!-- /.tab-content -->
          </form><!-- /#device-profile-form -->

        </div>
      </div>

    </div>
  </div>

</section>
<?php }
}
