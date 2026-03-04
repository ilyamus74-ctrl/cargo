<?php
/* Smarty version 5.3.1, created on 2026-03-04 13:21:54
  from 'file:cells_NA_API_warehouse_sync.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a831f217eba6_42165242',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '29b257235e81d55e0df3e385612344f078c386a9' => 
    array (
      0 => 'cells_NA_API_warehouse_sync.html',
      1 => 1772630169,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a831f217eba6_42165242 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="pagetitle">
  <h1>Синхронизация</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.html">Main</a></li>
      <li class="breadcrumb-item">Warehouse</li>
      <li class="breadcrumb-item active">Синхронизация с форвардами</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row">
    <div class="col-lg-10">
      <div class="card table-responsive warehouse-sync-table-wrapper">
        <div class="card-body">
          <h5 class="card-title">Синхронизация с форвардами</h5>

          <ul class="nav nav-tabs d-flex" id="warehouseSyncTabs" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
              <button class="nav-link w-100 active" id="warehouse-sync-missing-tab" data-bs-toggle="tab" data-bs-target="#warehouse-sync-missing" type="button" role="tab" aria-controls="warehouse-sync-missing" aria-selected="true">
                Синхронизация
              </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
              <button class="nav-link w-100" id="warehouse-sync-reports-tab" data-bs-toggle="tab" data-bs-target="#warehouse-sync-reports" type="button" role="tab" aria-controls="warehouse-sync-reports" aria-selected="false" tabindex="-1">
                Отчеты форвардов
              </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
              <button class="nav-link w-100" id="warehouse-sync-history-tab" data-bs-toggle="tab" data-bs-target="#warehouse-sync-history" type="button" role="tab" aria-controls="warehouse-sync-history" aria-selected="false" tabindex="-1">
                История сверки
              </button>
            </li>
          </ul>

          <div class="tab-content pt-3" id="warehouseSyncTabsContent">
            <div class="tab-pane fade show active" id="warehouse-sync-missing" role="tabpanel" aria-labelledby="warehouse-sync-missing-tab">
              <p class="small text-muted mb-2">
                Нет в отчетах форварда: <span id="warehouse-sync-missing-total">0</span>
              </p>

              <div class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-3">
                  <label class="form-label small mb-1" for="warehouse-sync-forwarder">Форвард</label>
                  <select id="warehouse-sync-forwarder" class="form-select form-select-sm">
                    <option value="ALL" selected>Все</option>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('sync_forwarders'), 'forwarder');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('forwarder')->value) {
$foreach0DoElse = false;
?>
                      <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder'), ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder'), ENT_QUOTES, 'UTF-8', true);?>
</option>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </select>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small mb-1" for="warehouse-sync-search">Поиск</label>
                  <input type="text" id="warehouse-sync-search" class="form-control form-control-sm" placeholder="Трек, TUID или получатель">
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label small mb-1" for="warehouse-sync-limit">Вывод строк</label>
                  <select id="warehouse-sync-limit" class="form-select form-select-sm">
                    <option value="20">20</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="all">Все</option>
                  </select>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm align-middle users-table">
                  <thead>
                    <tr>
                      <th scope="col">Посылка</th>
                      <th scope="col">Форвард</th>
                      <th scope="col">Страна</th>
                      <th scope="col">Ячейка</th>
                      <th scope="col">Таблица отчета</th>
                    </tr>
                  </thead>
                  <tbody id="warehouse-sync-missing-tbody">
                    <tr>
                      <td colspan="5" class="text-center text-muted">Загрузка...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div id="warehouse-sync-missing-sentinel" class="py-2"></div>
            </div>

            <div class="tab-pane fade" id="warehouse-sync-reports" role="tabpanel" aria-labelledby="warehouse-sync-reports-tab">
              <p class="text-muted mb-0">Раздел под список загруженных отчетов форвардов (следующий шаг).</p>
            </div>
            <div class="tab-pane fade" id="warehouse-sync-history" role="tabpanel" aria-labelledby="warehouse-sync-history-tab">
              <p class="text-muted mb-0">Раздел под историю запусков сверки (следующий шаг).</p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>
<?php }
}
