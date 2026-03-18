<?php
/* Smarty version 5.3.1, created on 2026-03-18 11:46:02
  from 'file:cells_NA_API_departures.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69ba907abe5994_81944747',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '0f6acec5f8cd1dbfcef00308f07d4fd68a44caf7' => 
    array (
      0 => 'cells_NA_API_departures.html',
      1 => 1773832464,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69ba907abe5994_81944747 (\Smarty\Template $_smarty_tpl) {
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
