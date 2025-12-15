<?php
/* Smarty version 5.3.1, created on 2025-12-15 09:22:40
  from 'file:cells_NA_API_tools_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_693fd36034d284_83694648',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7c780fb2eca0e4916b5d0e2c80c6661f7e49fa56' => 
    array (
      0 => 'cells_NA_API_tools_stock.html',
      1 => 1765789998,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_693fd36034d284_83694648 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Инструменты обортные</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Ресурсы</li>
          <li class="breadcrumb-item active">Инструменты</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">


          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Добавление / Редактирования инструмента</h5>
              <button type="button" class="btn btn-primary js-core-link"  data-bs-toggle="modal" data-bs-target="#fullscreenModal" data-core-action="form_new_tool_stock">Добавить</button>
              <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
              <!--<button type="button" class="btn btn-outline-secondary btn-sm js-core-link" data-core-action="users_regen_qr" style="margin-left: 10px;">Обновить QR для всех </button>
              -->
              <?php }?>
              <!--class="js-core-link" data-core-action="view_users"-->
              <!--<p>Highlight a table row or cell by adding a <code>.table-active</code> class.</p>-->
            </div>
          </div>


          <div class="card table-responsive users-table-wrapper">
            <div class="card-body">
              <h5 class="card-title">Инструменты</h5>
              <!-- Default Table -->
              <input type="hidden" id="userListDirty" value="0">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">Название</th>
                    <th scope="col">Серийный/н</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Add Date</th>
                  </tr>
                </thead>
                <tbody>

                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('tools'), 'tool');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('tool')->value) {
$foreach0DoElse = false;
?>
                  <tr>
                    <th scope="row"><?php echo $_smarty_tpl->getValue('tool')['id'];?>
</th>
                    <td> <a href="#" class="js-core-link" data-core-action="form_edit_tool_stock" data-tool-id="<?php echo $_smarty_tpl->getValue('tool')['id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('tool')['name'], ENT_QUOTES, 'UTF-8', true);?>
</a></td>
                    <td><?php echo $_smarty_tpl->getValue('tool')['serial_number'];?>
</td>
                    <td><?php echo (($tmp = $_smarty_tpl->getValue('tool')['status'] ?? null)===null||$tmp==='' ? 'active' ?? null : $tmp);?>
</td>
                    <td><?php echo $_smarty_tpl->getValue('tool')['registered_at'];?>
</td>
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
                      <h5 class="modal-title">Добавление / Редактировния инструмента</h5>
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
