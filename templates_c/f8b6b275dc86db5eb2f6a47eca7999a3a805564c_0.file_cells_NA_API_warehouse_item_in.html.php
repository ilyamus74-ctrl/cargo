<?php
/* Smarty version 5.3.1, created on 2025-12-09 10:09:54
  from 'file:cells_NA_API_warehouse_item_in.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6937f5724e89c6_24478984',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f8b6b275dc86db5eb2f6a47eca7999a3a805564c' => 
    array (
      0 => 'cells_NA_API_warehouse_item_in.html',
      1 => 1765274989,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6937f5724e89c6_24478984 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Warehouse Item In</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Warehouse</li>
          <li class="breadcrumb-item active">Item In</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">


          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Приход — незавершённые партии</h5>
                        <form class="row g-3" id="warehouse-item_in-form">
            <div class="col-12">
              <label for="inNote" class="form-label">Комментарий (необязательно)</label>
              <input type="text"
                     class="form-control"
                     id="inNote"
                     name="note"
                     placeholder="Например: Приход от постачальника X">
            </div>

            <div class="col-12">
            <button type="button"
                    class="btn btn-primary js-core-link"
                    data-core-action="open_item_in_batch"
                    data-batch-uid="">
              Начать новую партию
            </button>
            </div>
          </form>
              
              <!--<form class="row g-3" id="warehouse-item_in-form">

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="add_new_item_in">
      Добавить
    </button>
  </div>
              </form>-->
            </div>
          </div>


          <div class="card table-responsive warehouse-item-in-table-wrapper">
            <div class="card-body">
              <h5 class="card-title">Приход</h5>
              <!-- Default Table -->
              <input type="hidden" id="userListDirty" value="0">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                <th scope="col">Партия</th>
                <th scope="col">Посылок</th>
                <th scope="col"></th>
                <th scope="col">Начата</th>
                 </tr>
                </thead>
            <tbody>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('batches'), 'b');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('b')->value) {
$foreach0DoElse = false;
?>
                <tr>
                  <td><?php echo $_smarty_tpl->getValue('b')['batch_uid'];?>
</td>
                  <td><?php echo $_smarty_tpl->getValue('b')['parcel_count'];?>
</td>
                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-secondary js-core-link"
                            data-core-action="open_item_in_batch"
                            data-batch-uid="<?php echo $_smarty_tpl->getValue('b')['batch_uid'];?>
">
                      Открыть
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-success js-core-link"
                            data-core-action="commit_item_in_batch"
                            data-batch-uid="<?php echo $_smarty_tpl->getValue('b')['batch_uid'];?>
">
                      Готово
                    </button>
                  </td>
                  <td><?php echo $_smarty_tpl->getValue('b')['started_at'];?>
</td>
                </tr>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
              <?php if (!$_smarty_tpl->getValue('batches')) {?>
                <tr><td colspan="4" class="text-center text-muted">Нет незавершённых партий</td></tr>
              <?php }?>
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
                      <h5 class="modal-title">Просмотр прихода</h5>
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
