<?php
/* Smarty version 5.3.1, created on 2026-03-19 15:16:05
  from 'file:cells_NA_API_warehouse_item_out.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69bc1335f08990_89931073',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '1e9799e9c3e87d128e25a93d4c0cb584ee55eee0' => 
    array (
      0 => 'cells_NA_API_warehouse_item_out.html',
      1 => 1773933272,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69bc1335f08990_89931073 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="pagetitle">
  <h1>Отгрузка</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.html">Main</a></li>
      <li class="breadcrumb-item">Warehouse</li>
      <li class="breadcrumb-item active">Отгрузка</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="row">
    <div class="col-lg-10">
      <div class="card table-responsive warehouse-sync-table-wrapper">
        <div class="card-body">
          <h5 class="card-title">Посылки к отгрузке (to_send)</h5>

          <div id="warehouse-item-out" class="tab-pane fade show active" role="tabpanel" aria-labelledby="warehouse-sync-missing-tab">
            <p class="small text-muted mb-2">
              Найдено to_send: <span id="warehouse-item-out-total">0</span>
            </p>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="warehouse-item-out-forwarder">Форвард</label>
                <select id="warehouse-item-out-forwarder" class="form-select form-select-sm">
                  <option value="ALL" selected>Все</option>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('item_out_forwarders'), 'forwarder');
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

              <div class="col-12 col-md-5">
                <label class="form-label small mb-1" for="warehouse-item-out-container">Контейнер из open рейса</label>
                <select id="warehouse-item-out-container" class="form-select form-select-sm">
                  <option value="" selected>Выберите контейнер</option>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('item_out_open_containers'), 'container');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('container')->value) {
$foreach1DoElse = false;
?>
                    <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['value'], ENT_QUOTES, 'UTF-8', true);?>
"
                            data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-no="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['flight_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-container-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['container_external_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-container-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['container_name'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['label'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </select>
              </div>
              <div class="col-12 col-md-2">
                <label class="form-label small mb-1" for="warehouse-item-out-search">Трекномер</label>
                <input type="text" id="warehouse-item-out-search" class="form-control form-control-sm" placeholder="Введите трекномер">
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="warehouse-item-out-limit">Вывод строк</label>
                <select id="warehouse-item-out-limit" class="form-select form-select-sm">
                  <option value="20">20</option>
                  <option value="50" selected>50</option>
                  <option value="100">100</option>
                  <option value="all">Все</option>
                </select>
              </div>
              <div class="col-12">
                <div class="form-text">Список контейнеров собирается из рейсов со статусом open в формате: рейс HHN02092025 - CONTAINER23896.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle users-table">
                <thead>
                  <tr>
                    <th scope="col">Посылка</th>
                    <th scope="col">Трекномер</th>
                    <th scope="col">Форвард</th>
                    <th scope="col">Страна</th>
                    <th scope="col">Ячейка</th>
                    <th scope="col">Статус</th>
                  </tr>
                </thead>
                <tbody id="warehouse-item-out-tbody">
                  <tr>
                    <td colspan="6" class="text-center text-muted">Загрузка...</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div id="warehouse-item-out-sentinel" class="py-2"></div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>
<?php }
}
