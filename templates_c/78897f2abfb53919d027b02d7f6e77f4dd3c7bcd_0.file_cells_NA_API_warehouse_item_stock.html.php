<?php
/* Smarty version 5.3.1, created on 2026-02-26 09:30:31
  from 'file:cells_NA_API_warehouse_item_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a012b7e94145_97706142',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '78897f2abfb53919d027b02d7f6e77f4dd3c7bcd' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock.html',
      1 => 1772098016,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a012b7e94145_97706142 (\Smarty\Template $_smarty_tpl) {
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
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-without-addons-tab" data-bs-toggle="tab" data-bs-target="#warehouse-without-addons" type="button" role="tab" aria-controls="warehouse-without-addons" aria-selected="false" tabindex="-1">
                    Посылки без ДопИнфо
                  </button>
                </li>
              </ul>
              <div class="tab-content pt-3" id="warehouseTabsContent">
                <div class="tab-pane fade show active" id="warehouse-without-cells" role="tabpanel" aria-labelledby="warehouse-without-cells-tab">
                  <p class="small text-muted mb-2">
                    Всего посылок: <span id="warehouse-without-cells-total">0</span>
                  </p>

                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-without-cells-search">Быстрый поиск</label>
                      <input type="text" id="warehouse-without-cells-search" class="form-control form-control-sm" placeholder="ФИО или трекномер">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-without-cells-limit">Вывод строк</label>
                      <select id="warehouse-without-cells-limit" class="form-select form-select-sm">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Все</option>
                      </select>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-without-cells-sort">Сортировка</label>
                      <select id="warehouse-without-cells-sort" class="form-select form-select-sm">
                        <option value="DESC" selected>DESC</option>
                        <option value="ASC">ASC</option>
                      </select>
                    </div>
                  </div>
                  <div class="table-responsive">
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

                    <tbody id="warehouse-without-cells-tbody">
                      <tr>
                        <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>3<?php } else { ?>2<?php }?>" class="text-center text-muted">
                          Загрузка...
                        </td>
                      </tr>
                    </tbody>
                    </table>
                  </div>
                  <div id="warehouse-without-cells-sentinel" class="py-2"></div>
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
                    Всего посылок: <span id="warehouse-in-storage-total">0</span>
                    </p>

                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-in-storage-search">Быстрый поиск</label>
                      <input type="text" id="warehouse-in-storage-search" class="form-control form-control-sm" placeholder="ФИО, трекномер или ячейка">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-in-storage-limit">Вывод строк</label>
                      <select id="warehouse-in-storage-limit" class="form-select form-select-sm">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Все</option>
                      </select>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-in-storage-sort">Сортировка</label>
                      <select id="warehouse-in-storage-sort" class="form-select form-select-sm">
                        <option value="DESC" selected>DESC</option>
                        <option value="ASC">ASC</option>
                      </select>
                    </div>
                  </div>
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


                    <tbody id="warehouse-in-storage-tbody">
                      <tr>
                        <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>4<?php } else { ?>3<?php }?>" class="text-center text-muted">
                          Загрузка...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div id="warehouse-in-storage-sentinel" class="py-2"></div>
                </div>
                <div class="tab-pane fade" id="warehouse-without-addons" role="tabpanel" aria-labelledby="warehouse-without-addons-tab">
                  <p class="small text-muted mb-2">
                    Всего посылок: <span id="warehouse-without-addons-total">0</span>
                  </p>

                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-without-addons-search">Быстрый поиск</label>
                      <input type="text" id="warehouse-without-addons-search" class="form-control form-control-sm" placeholder="ФИО или трекномер">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-without-addons-limit">Вывод строк</label>
                      <select id="warehouse-without-addons-limit" class="form-select form-select-sm">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Все</option>
                      </select>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small mb-1" for="warehouse-without-addons-sort">Сортировка</label>
                      <select id="warehouse-without-addons-sort" class="form-select form-select-sm">
                        <option value="DESC" selected>DESC</option>
                        <option value="ASC">ASC</option>
                      </select>
                    </div>
                  </div>
                  <div class="table-responsive">
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

                      <tbody id="warehouse-without-addons-tbody">
                        <tr>
                          <td colspan="<?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>3<?php } else { ?>2<?php }?>" class="text-center text-muted">
                            Загрузка...
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div id="warehouse-without-addons-sentinel" class="py-2"></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Full Screen Modal -->
    <div class="modal fade" id="fullscreenModal" tabindex="-1">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Просмотр посылки</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Загрузка...
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div><!-- End Full Screen Modal-->
<?php }
}
