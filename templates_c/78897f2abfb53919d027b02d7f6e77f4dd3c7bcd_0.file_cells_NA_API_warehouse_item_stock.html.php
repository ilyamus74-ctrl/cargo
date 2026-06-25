<?php
/* Smarty version 5.3.1, created on 2026-06-23 13:05:01
  from 'file:cells_NA_API_warehouse_item_stock.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a3a847da000f7_80112992',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '78897f2abfb53919d027b02d7f6e77f4dd3c7bcd' => 
    array (
      0 => 'cells_NA_API_warehouse_item_stock.html',
      1 => 1782219833,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a3a847da000f7_80112992 (\Smarty\Template $_smarty_tpl) {
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
                  <label class="form-label small mb-1" for="warehouse-items-registry-forwarder">Форвард</label>
                  <select id="warehouse-items-registry-forwarder" class="form-select form-select-sm">
                    <option value="ALL" selected>Все форварды</option>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('warehouse_stock_forwarders'), 'forwarder');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('forwarder')->value) {
$foreach0DoElse = false;
?>
                      <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder')['id'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder')['name'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
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

                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-sort-by">Дата сортировки</label>
                  <select id="warehouse-items-registry-sort-by" class="form-select form-select-sm">
                    <option value="created_at_local" selected>Создана у нас</option>
                    <option value="forwarder_date">Дата у форварда</option>
                  </select>
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                  <label class="form-label small mb-1" for="warehouse-items-registry-sort">Сортировка</label>
                  <select id="warehouse-items-registry-sort" class="form-select form-select-sm">
                    <option value="DESC" selected>DESC</option>
                    <option value="ASC">ASC</option>
                  </select>
                </div>
                <div class="col-12 col-md-4 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-date-type">Фильтр даты</label>
                  <select id="warehouse-items-registry-date-type" class="form-select form-select-sm">
                    <option value="" selected>Не выбран</option>
                    <option value="created_at_local">Создана у нас</option>
                    <option value="forwarder_date">Дата у форварда</option>
                    <option value="forwarder_synced_at">Синхронизация с форвардом</option>
                  </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-date-from">Дата с</label>
                  <input type="date" id="warehouse-items-registry-date-from" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                  <label class="form-label small mb-1" for="warehouse-items-registry-date-to">Дата по</label>
                  <input type="date" id="warehouse-items-registry-date-to" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-5 col-xl-2 d-flex gap-2">
                  <button type="button" id="warehouse-items-registry-date-apply" class="btn btn-primary btn-sm flex-fill">Применить</button>
                  <button type="button" id="warehouse-items-registry-date-reset" class="btn btn-outline-secondary btn-sm flex-fill">Сбросить</button>
                </div>
              </div>
              <div id="warehouse-items-registry-date-summary" class="small text-muted mb-2 d-none"></div>

              <div class="table-responsive">
                <table class="table table-sm align-middle users-table">
                  <thead>
                    <tr>
                      <th scope="col">Источник</th>
                      <th scope="col">Состояние</th>
                      <th scope="col">Трек</th>
                      <th scope="col">Получатель</th>
                      <th scope="col">Форвард</th>
                      <th scope="col">Ячейка/Контейнер</th>
                      <th scope="col">Статус отгрузки</th>
                      <th scope="col">Регистрация у форварда</th>
                      <th scope="col">Статус у форварда</th>
                      <th scope="col">Дата у форварда</th>
                      <th scope="col">Сообщение</th>
                      <th scope="col" class="text-end">⋮</th>
                      <th scope="col">Создана у нас</th>
                    </tr>
                  </thead>
                  <tbody id="warehouse-items-registry-tbody">
                    <tr>
                      <td colspan="13" class="text-center text-muted">Загрузка...</td>
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
