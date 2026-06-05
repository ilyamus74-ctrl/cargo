<?php
/* Smarty version 5.3.1, created on 2026-06-05 15:21:09
  from 'file:cells_NA_API_warehouse_item_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a22e965c32ad6_39460564',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '78897f2abfb53919d027b02d7f6e77f4dd3c7bcd' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock.html',
      1 => 1780672668,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a22e965c32ad6_39460564 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
    <div class="pagetitle">
      <h1>Склад</h1>
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
        <div class="col-lg-12">

          <div class="card table-responsive warehouse-item-stock-table-wrapper" id="warehouse-items-registry">
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h5 class="card-title mb-0">Склад</h5>
                <p class="small text-muted mb-0">
                  Всего посылок: <span id="warehouse-items-registry-total">0</span>
                </p>
              </div>
              <div class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="form-label small mb-1" for="warehouse-items-registry-state">Состояние</label>
                  <select id="warehouse-items-registry-state" class="form-select form-select-sm">
                    <option value="all" selected>Все</option>
                    <option value="without_cells">Посылки без ячеек</option>
                    <option value="to_send">Посылки на отгрузку</option>
                    <option value="in_storage">Посылки на складе</option>
                    <option value="without_addons">Посылки без ДопИнфо</option>
                    <option value="registration_errors">Ошибки регистрации у форварда</option>
                    <option value="not_registered">Не зарегистрированы у форварда</option>
                    <option value="registered">Зарегистрированы у форварда</option>
                    <option value="sended">Отправленные</option>
                  </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-source">Таблица</label>
                  <select id="warehouse-items-registry-source" class="form-select form-select-sm">
                    <option value="all" selected>Все таблицы</option>
                    <option value="in">Приёмка</option>
                    <option value="stock">Склад</option>
                    <option value="out">Отгрузка</option>
                  </select>

                </div>
                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-forwarder-status">Статус форварда</label>
                  <select id="warehouse-items-registry-forwarder-status" class="form-select form-select-sm">
                    <option value="all" selected>all</option>
                    <option value="empty">empty</option>
                    <option value="ok">ok</option>
                    <option value="validation_error">validation_error</option>
                    <option value="error">error</option>
                    <option value="skipped">skipped</option>
                  </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-forwarder-registered">Дата регистрации</label>
                  <select id="warehouse-items-registry-forwarder-registered" class="form-select form-select-sm">
                    <option value="all" selected>all</option>
                    <option value="filled">filled</option>
                    <option value="empty">empty</option>
                  </select>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                  <label class="form-label small mb-1" for="warehouse-items-registry-search">Поиск</label>
                  <input type="text" id="warehouse-items-registry-search" class="form-control form-control-sm" placeholder="Трек, TUID, получатель, партия, сообщение">

                </div>
                <div class="col-6 col-md-3 col-xl-1">
                  <label class="form-label small mb-1" for="warehouse-items-registry-limit">Лимит</label>
                  <select id="warehouse-items-registry-limit" class="form-select form-select-sm">
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="all">all</option>
                  </select>
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                  <label class="form-label small mb-1" for="warehouse-items-registry-sort">Сортировка</label>
                  <select id="warehouse-items-registry-sort" class="form-select form-select-sm">
                    <option value="DESC" selected>DESC</option>
                    <option value="ASC">ASC</option>
                  </select>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm align-middle users-table">
                  <thead>
                    <tr>
                      <th scope="col">Источник</th>
                      <th scope="col">Состояние</th>
                      <th scope="col">Посылка</th>
                      <th scope="col">Трек</th>
                      <th scope="col">Получатель</th>
                      <th scope="col">Форвард</th>
                      <th scope="col">Ячейка/Контейнер</th>
                      <th scope="col">Статус отгрузки</th>
                      <th scope="col">Регистрация у форварда</th>
                      <th scope="col">Сообщение</th>
                      <th scope="col" class="text-end">⋮</th>
                      <th scope="col">Создана</th>
                    </tr>
                  </thead>
                  <tbody id="warehouse-items-registry-tbody">
                    <tr>
                      <td colspan="12" class="text-center text-muted">Загрузка...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div id="warehouse-items-registry-sentinel" class="py-2"></div>
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
</div>
<!-- End Full Screen Modal -->
<?php }
}
