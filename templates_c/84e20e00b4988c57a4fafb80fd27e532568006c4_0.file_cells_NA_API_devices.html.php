<?php
/* Smarty version 5.3.1, created on 2026-01-03 19:17:56
  from 'file:cells_NA_API_devices.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69596b641a9bf1_50699499',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '84e20e00b4988c57a4fafb80fd27e532568006c4' => 
    array (
      0 => 'cells_NA_API_devices.html',
      1 => 1767467566,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69596b641a9bf1_50699499 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Settings devices</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Settings</li>
          <li class="breadcrumb-item active">Devices</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">


         <!-- <div class="card">
            <div class="card-body">
              <h5 class="card-title">Добавление / Редактирования устройств</h5>
              <button type="button" class="btn btn-primary js-core-link"  data-bs-toggle="modal" data-bs-target="#fullscreenModal" data-core-action="form_new_user">Добавить</button>
              <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
              <button type="button" class="btn btn-outline-secondary btn-sm js-core-link" data-core-action="users_regen_qr" style="margin-left: 10px;">Обновить QR для всех </button>
              <?php }?>
            </div>
          </div>
          -->

          <div class="card table-responsive users-table-wrapper">
            <div class="card-body">
              <h5 class="card-title">Устройства</h5>
              <!-- Default Table -->
              <a class="btn btn-secondary" href="/download/OcrScanner.apk" download>Скачать APP OcrScanner</a>
              <a class="btn btn-secondary" href="/download/Stand.apk" download>Скачать APP Stand</a>
              <input type="hidden" id="userListDirty" value="0">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">NameDevice</th>
                    <th scope="col">Model</th>
                    <th scope="col">Ver APP</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>

                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('devices'), 'value');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('value')->value) {
$foreach0DoElse = false;
?>
                  <tr>
                    <th scope="row"><?php echo $_smarty_tpl->getValue('value')['id'];?>
</th>
                    <td> <a href="#" class="js-core-link" data-core-action="form_edit_device" data-device-id="<?php echo $_smarty_tpl->getValue('value')['id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('value')['name'], ENT_QUOTES, 'UTF-8', true);?>
</a></td>
                    <td><?php echo $_smarty_tpl->getValue('value')['model'];?>
</td>
                    <td><?php echo $_smarty_tpl->getValue('value')['app_version'];?>
</td>
                    <td><?php if ($_smarty_tpl->getValue('value')['is_active'] == 0) {?>Not active<?php } else { ?>Actived<?php }?></td>
                  </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </tbody>
              </table>
              <!-- End Default Table Example -->
            </div>
          </div>

              <!-- Full Screen Modal -->
              <div class="modal fade" id="fullscreenModal" tabindex="-1">
                <div class="modal-dialog modal-fullscreen">
                  <div class="modal-content">
                 
                    <div class="modal-header">
                      <h5 class="modal-title">Просмотр устройства</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      Тут не должно быть приведения, если ты его видешь значить что то пошло не так или не туда :)
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      <!--<button type="button" class="btn btn-primary">Save changes</button>
-->
                    </div>
                  </div>
                </div>
              </div><!-- End Full Screen Modal-->


        </div>
      </div>
    </section>

<?php }
}
