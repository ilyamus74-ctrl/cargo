<?php
/* Smarty version 5.3.1, created on 2026-01-15 09:32:43
  from 'file:cells_NA_API_warehouse_cells.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6968b43baa7401_21636775',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4f833ca3ad796719bf284807db20101fe49bc3c4' => 
    array (
      0 => 'cells_NA_API_warehouse_cells.html',
      1 => 1768469393,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6968b43baa7401_21636775 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Warehouse Cells</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Warehouse</li>
          <li class="breadcrumb-item active">Cells</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">


          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Добавление / Редактирования ячейки</h5>
              <form class="row g-3" id="cells-form">
  <div class="col-md-4">
    <label for="firstCell" class="form-label">Первая ячейка</label>
    <input type="text"
           class="form-control"
           id="firstCell"
           name="first_code"
           placeholder="A10"
           required>
  </div>

  <div class="col-md-4">
    <label for="lastCell" class="form-label">Последняя ячейка</label>
    <input type="text"
           class="form-control"
           id="lastCell"
           name="last_code"
           placeholder="A99"
           required>
  </div>

  <div class="col-md-6">
    <label for="cellsDesc" class="form-label">Описание для всех новых ячеек</label>
    <input type="text"
           class="form-control"
           id="cellsDesc"
           name="description">
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="add_new_cells">
      Добавить
    </button>
  </div>
              </form>
            </div>
          </div>


          <div class="card table-responsive warehouse-cells-table-wrapper">
            <div class="card-body">
              <h5 class="card-title">Ячейки</h5>
              <!-- Default Table -->
              <input type="hidden" id="userListDirty" value="0">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                    <th scope="col">#</th>
                    <th scope="col">Code</th>
                    <th scope="col"></th>
                    <th scope="col">QR_payload</th>
                    <th scope="col">Description</th>
                    <th scope="col"></th>
                  </tr>
                </thead>
                <tbody>

                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'value');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('value')->value) {
$foreach0DoElse = false;
?>
                  <tr>
                    <th scope="row"><?php echo $_smarty_tpl->getValue('value')['id'];?>
</th>
                    <td> <a href="#" class="js-core-link" data-core-action="form_edit_cell" data-cell-id="<?php echo $_smarty_tpl->getValue('value')['id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('value')['code'], ENT_QUOTES, 'UTF-8', true);?>
</a></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-danger js-core-link" data-core-action="delete_cell" data-cell-id="<?php echo $_smarty_tpl->getValue('value')['id'];?>
">
                        <i class="bi bi-exclamation-octagon"></i>
                        </button>
                     </td>
                    <td><?php echo $_smarty_tpl->getValue('value')['qr_payload'];?>
</td>
                    <td><?php echo $_smarty_tpl->getValue('value')['description'];?>
</td>
                    <td>
                      <?php if ($_smarty_tpl->getValue('value')['qr_file']) {?>
                        <button type="button"
                                class="btn btn-link p-0 js-print-qr"
                                data-qr-src="img/cells/<?php echo $_smarty_tpl->getValue('value')['qr_file'];?>
"
                                data-qr-title="QR <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('value')['code'], ENT_QUOTES, 'UTF-8', true);?>
">
                          <img src="img/cells/<?php echo $_smarty_tpl->getValue('value')['qr_file'];?>
"
                               alt="QR <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('value')['code'], ENT_QUOTES, 'UTF-8', true);?>
"
                               style="height:40px;">
                        </button>
                      <?php }?>
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
                      <h5 class="modal-title">Редактирование ячейки</h5>
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
