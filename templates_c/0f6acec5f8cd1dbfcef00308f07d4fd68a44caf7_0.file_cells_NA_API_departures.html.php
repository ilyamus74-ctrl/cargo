<?php
/* Smarty version 5.3.1, created on 2026-03-18 13:59:49
  from 'file:cells_NA_API_departures.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69baafd54f1a21_50466761',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '0f6acec5f8cd1dbfcef00308f07d4fd68a44caf7' => 
    array (
      0 => 'cells_NA_API_departures.html',
      1 => 1773842383,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69baafd54f1a21_50466761 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="pagetitle">
  <h1>Отправления</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.html">Main</a></li>
      <li class="breadcrumb-item">Connectors</li>
      <li class="breadcrumb-item active">Отправления</li>
    </ol>
  </nav>
</div>

<section class="section" data-page-init="departures">
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">Рейсы форвардов</h5>
            <div class="small text-muted">
              Найдено рейсов: <span id="departures-total">0</span>
            </div>
          </div>

          <div id="departures-page" class="mt-3">
            <div class="row g-2 align-items-end mb-3">
              <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="departures-forwarder-filter">Форвард</label>
                <select id="departures-forwarder-filter" class="form-select form-select-sm">
                  <option value="ALL" selected>Все форварды</option>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('departure_forwarders'), 'forwarder');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('forwarder')->value) {
$foreach0DoElse = false;
?>
                    <option value="<?php echo $_smarty_tpl->getValue('forwarder')['id'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder')['name'], ENT_QUOTES, 'UTF-8', true);
if ($_smarty_tpl->getValue('forwarder')['countries']) {?> — <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('forwarder')['countries'], ENT_QUOTES, 'UTF-8', true);
}?></option>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="departures-status-filter">Статус рейса</label>
                <select id="departures-status-filter" class="form-select form-select-sm">
                  <option value="ALL" selected>Все статусы</option>
                  <option value="OPEN">open</option>
                  <option value="CLOSED">closed</option>
                </select>
              </div>

              <div class="col-12 col-md-auto">
                <button type="button"
                        class="btn btn-sm btn-primary js-departure-action-toggle"
                        data-target="departures-add-flight-panel"
                        data-open="0"
                        aria-expanded="false">
                  Добавить рейс
                </button>
              </div>
            </div>

            <div id="departures-add-flight-panel" class="card border border-dashed bg-light d-none mb-3">
              <div class="card-body py-3">

                <div class="alert alert-info py-2 small mb-3">
                  Для добавления рейса выберите одного форварда, укажите дату рейса и AWB без префикса <code>AWB</code>.
                </div>
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-3">
                    <label class="form-label small mb-1" for="departures-add-flight-date">Дата рейса</label>
                    <input type="date"
                           id="departures-add-flight-date"
                           class="form-control form-control-sm">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label small mb-1" for="departures-add-flight-awb">AWB</label>
                    <input type="text"
                           id="departures-add-flight-awb"
                           class="form-control form-control-sm"
                           inputmode="numeric"
                           autocomplete="off"
                           placeholder="50118620604">
                  </div>
                  <div class="col-12 col-md-auto">
                    <button type="button"
                            class="btn btn-sm btn-success js-departure-placeholder-action d-inline-flex align-items-center gap-2 px-3 shadow-sm"
                            data-operation="add_flight"
                            data-input="#departures-add-flight-awb"
                            data-date-input="#departures-add-flight-date"
                            data-refresh-operation="flight_list"
                            data-label="Добавить рейс"
                            data-busy-label="Добавляю рейс..."
                            style="min-width: 180px;">
                      <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>
                      <span class="js-departure-placeholder-label">Добавить рейс</span>
                    </button>
                  </div>
                </div>
                <div id="departures-add-flight-status" class="small text-muted mt-2" aria-live="polite"></div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th scope="col" style="width: 52px;"></th>
                    <th scope="col">Рейс</th>
                    <th scope="col">Форвард</th>
                    <th scope="col">Маршрут</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Контейнеры</th>
                    <th scope="col">Синхронизация контейнеров</th>
                    <th scope="col">Обновлено</th>
                  </tr>
                </thead>
                <tbody id="departures-tbody">
                  <tr>
                    <td colspan="8" class="text-center text-muted">Загрузка...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php }
}
