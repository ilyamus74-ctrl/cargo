<?php
/* Smarty version 5.3.1, created on 2025-12-15 18:23:38
  from 'file:cells_NA_API_warehouse_item_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6940522a9d10d6_56943914',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '78897f2abfb53919d027b02d7f6e77f4dd3c7bcd' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock.html',
      1 => 1765822967,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6940522a9d10d6_56943914 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
    <div class="pagetitle">
      <h1>Warehouse Item Stock</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Warehouse</li>
          <li class="breadcrumb-item active">Item Stock</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-lg-6">

          <div class="card table-responsive warehouse-item-stock-table-wrapper">
            <div class="card-body">
              <h5 class="card-title">Склад</h5>
              <!-- Default Table -->
              <input type="hidden" id="userListDirty" value="0">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                    <th scope="col">Партия</th>
                    <th scope="col">Посылок</th>
                    <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                      <th scope="col">Пользователь</th>
                    <?php }?>
                    <th scope="col">Принята</th>
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
                      <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                        <td><?php echo (($tmp = $_smarty_tpl->getValue('b')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                      <?php }?>
                      <td><?php echo $_smarty_tpl->getValue('b')['started_at'];?>
</td>
                    </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  <?php if (!$_smarty_tpl->getValue('batches')) {?>
                    <tr>
                      <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>4<?php } else { ?>3<?php }?>" class="text-center text-muted">
                        Нет товаров на складе
                      </td>
                    </tr>
                  <?php }?>
                </tbody>
              </table>
              <!-- End Default Table Example -->
            </div>
          </div>

        </div>
      </div>
    </section><?php }
}
