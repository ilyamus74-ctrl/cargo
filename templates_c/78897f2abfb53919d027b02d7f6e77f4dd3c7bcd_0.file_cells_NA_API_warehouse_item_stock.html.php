<?php
/* Smarty version 5.3.1, created on 2026-01-15 12:49:59
  from 'file:cells_NA_API_warehouse_item_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6968e277e91333_12804946',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '78897f2abfb53919d027b02d7f6e77f4dd3c7bcd' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock.html',
      1 => 1768481393,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6968e277e91333_12804946 (\Smarty\Template $_smarty_tpl) {
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


              <ul class="nav nav-tabs d-flex" id="warehouseTabs" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100 active" id="warehouse-without-cells-tab" data-bs-toggle="tab" data-bs-target="#warehouse-without-cells" type="button" role="tab" aria-controls="warehouse-without-cells" aria-selected="true">
                    Посылки без ячеек
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-for-shipment-tab" data-bs-toggle="tab" data-bs-target="#warehouse-for-shipment" type="button" role="tab" aria-controls="warehouse-for-shipment" aria-selected="false" tabindex="-1">
                    Посылки на отгрузку
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-in-storage-tab" data-bs-toggle="tab" data-bs-target="#warehouse-in-storage" type="button" role="tab" aria-controls="warehouse-in-storage" aria-selected="false" tabindex="-1">
                    Посылки на складе
                  </button>
                </li>
              </ul>
              <div class="tab-content pt-3" id="warehouseTabsContent">
                <div class="tab-pane fade show active" id="warehouse-without-cells" role="tabpanel" aria-labelledby="warehouse-without-cells-tab">
                  <p class="small text-muted mb-2">
                    Всего посылок: <?php if ($_smarty_tpl->getValue('parcels_without_cells')) {
echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('parcels_without_cells'));
} else { ?>0<?php }?>
                  </p>
                  <input type="hidden" id="userListDirty" value="0">
                  <table class="table table-sm align-middle users-table">
                    <thead>
                      <tr>
                        <th scope="col">Посылка</th>
                        <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                          <th scope="col">Пользователь</th>
                        <?php }?>
                        <th scope="col">Создана</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('parcels_without_cells'), 'parcel');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('parcel')->value) {
$foreach0DoElse = false;
?>
                        <tr>
                          <td><?php echo $_smarty_tpl->getValue('parcel')['parcel_uid'];?>
</td>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <?php }?>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['created_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      <?php if (!$_smarty_tpl->getValue('parcels_without_cells')) {?>
                        <tr>
                          <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>3<?php } else { ?>2<?php }?>" class="text-center text-muted">
                            Нет посылок без ячеек
                          </td>
                        </tr>
                      <?php }?>

                    </tbody>
                  </table>
                </div>
                <div class="tab-pane fade" id="warehouse-for-shipment" role="tabpanel" aria-labelledby="warehouse-for-shipment-tab">
                  <p class="small text-muted mb-2">
                    Всего посылок: <?php if ($_smarty_tpl->getValue('parcels_for_shipment')) {
echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('parcels_for_shipment'));
} else { ?>0<?php }?>
                  </p>
                  <table class="table table-sm align-middle users-table">
                    <thead>
                      <tr>
                        <th scope="col">Посылка</th>
                        <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                          <th scope="col">Пользователь</th>
                        <?php }?>
                        <th scope="col">Статус</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('parcels_for_shipment'), 'parcel');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('parcel')->value) {
$foreach1DoElse = false;
?>
                        <tr>
                          <td><?php echo $_smarty_tpl->getValue('parcel')['parcel_uid'];?>
</td>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <?php }?>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['status'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      <?php if (!$_smarty_tpl->getValue('parcels_for_shipment')) {?>
                        <tr>
                          <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>3<?php } else { ?>2<?php }?>" class="text-center text-muted">
                            Нет посылок на отгрузку
                          </td>
                        </tr>
                      <?php }?>
                    </tbody>
                  </table>
                </div>
                <div class="tab-pane fade" id="warehouse-in-storage" role="tabpanel" aria-labelledby="warehouse-in-storage-tab">
                  <p class="small text-muted mb-2">
                    Всего посылок: <?php if ($_smarty_tpl->getValue('parcels_in_storage')) {
echo $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('parcels_in_storage'));
} else { ?>0<?php }?>
                  </p>
                  <table class="table table-sm align-middle users-table">
                    <thead>
                      <tr>
                        <th scope="col">Посылка</th>
                        <th scope="col">Ячейка</th>
                        <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                          <th scope="col">Пользователь</th>
                        <?php }?>
                        <th scope="col">Размещена</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('parcels_in_storage'), 'parcel');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('parcel')->value) {
$foreach2DoElse = false;
?>
                        <tr>
                          <td><?php echo $_smarty_tpl->getValue('parcel')['parcel_uid'];?>
</td>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['cell_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['user_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <?php }?>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('parcel')['stored_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      <?php if (!$_smarty_tpl->getValue('parcels_in_storage')) {?>
                        <tr>
                          <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>4<?php } else { ?>3<?php }?>" class="text-center text-muted">
                            Нет посылок на складе
                          </td>
                        </tr>
                      <?php }?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section><?php }
}
