<?php
/* Smarty version 5.3.1, created on 2026-03-30 13:45:34
  from 'file:cells_NA_API_departures_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69ca7e7e284197_11283676',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '04916a2971ae525b97127d32f509a85cd4ad4d24' => 
    array (
      0 => 'cells_NA_API_departures_rows.html',
      1 => 1774878066,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69ca7e7e284197_11283676 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('departure_rows')) > 0) {?>
  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('departure_rows'), 'flight');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('flight')->value) {
$foreach0DoElse = false;
?>
    <tr>
      <td class="text-center">
        <button type="button"
                class="btn btn-sm btn-outline-secondary js-departure-toggle"
                data-target="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['row_uid'], ENT_QUOTES, 'UTF-8', true);?>
"
                data-open="0"
                title="Показать контейнеры">
          <i class="bi bi-chevron-down"></i>
        </button>
      </td>
      <td>
        <div class="fw-semibold"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="small text-muted">
          <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

          <?php if ($_smarty_tpl->getValue('flight')['flight_number']) {?> · <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['flight_number'], ENT_QUOTES, 'UTF-8', true);
}?>
          <?php if ($_smarty_tpl->getValue('flight')['awb']) {?> · AWB <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['awb'], ENT_QUOTES, 'UTF-8', true);
}?>
        </div>
      </td>
      <td>
        <div class="fw-semibold"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['forwarder_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="small text-muted"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['forwarder_countries'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
      </td>
      <td>
        <div><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['departure'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 → <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['destination'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="small text-muted"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['route'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
      </td>
      <td>
        <span class="badge bg-<?php echo (($tmp = $_smarty_tpl->getValue('flight')['status_badge_class'] ?? null)===null||$tmp==='' ? 'secondary' ?? null : $tmp);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['status'], ENT_QUOTES, 'UTF-8', true);?>
</span>
        <?php if ($_smarty_tpl->getValue('flight')['closed_at']) {?>
          <div class="small text-muted mt-1">Closed: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['closed_at'], ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php }?>
      </td>
      <td>
        <div class="fw-semibold"><?php echo (($tmp = $_smarty_tpl->getValue('flight')['containers_total'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div>
        <div class="small text-muted">
          мест: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['packages_count'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
,
          вес: <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['total_weight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

        </div>
      </td>
      <td>
        <?php $_smarty_tpl->assign('syncStatus', mb_strtolower((string) (($tmp = $_smarty_tpl->getValue('flight')['containers_sync_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp), 'UTF-8'), false, NULL);?>
        <span class="badge <?php if ($_smarty_tpl->getValue('syncStatus') == "synced" || $_smarty_tpl->getValue('syncStatus') == "matched") {?>bg-success<?php } elseif ($_smarty_tpl->getValue('syncStatus') == "mismatch" || $_smarty_tpl->getValue('syncStatus') == "error") {?>bg-warning text-dark<?php } else { ?>bg-light text-dark border<?php }?>"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['containers_sync_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span>
        <?php if ($_smarty_tpl->getValue('flight')['containers_synced_at']) {?>
          <div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['containers_synced_at'], ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php }?>
        <?php if ($_smarty_tpl->getValue('flight')['containers_sync_error']) {?>
          <div class="small text-danger mt-1"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['containers_sync_error'], ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php }?>
      </td>
      <td>
        <div><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['updated_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php if ($_smarty_tpl->getValue('flight')['flight_time']) {?>
          <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['flight_time'], ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php }?>
      </td>
    </tr>
    <tr id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['row_uid'], ENT_QUOTES, 'UTF-8', true);?>
" class="d-none table-light">
      <td colspan="8">
        <div class="p-2">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold">Контейнеры рейса <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <div class="small text-muted">Всего: <?php echo (($tmp = $_smarty_tpl->getValue('flight')['containers_total'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
</div>
              <?php if (mb_strtolower((string) $_smarty_tpl->getValue('flight')['status'], 'UTF-8') == 'open') {?>
                <button type="button"
                        class="btn btn-sm btn-outline-primary js-departure-placeholder-action"
                        data-operation="add_container_to_flight_php"
                        data-refresh-operation="flight_list_php"
                        data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('flight')['flight_no'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-container-name="NEW"
                        data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                        data-busy-label="Добавляю контейнер..."
                        data-success-message="Контейнер добавлен, рейс синхронизирован и список обновлён.">
                  <span class="js-departure-placeholder-label">Добавить контейнер</span>
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary js-departure-edit-toggle"
                        data-target="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_edit"
                        data-open="0"
                        aria-expanded="false">
                  Редактировать рейс
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-warning js-departure-placeholder-action"
                        data-operation="close_flight"
                        data-refresh-operation="flight_list"
                        data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('flight')['flight_no'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                        data-busy-label="Закрываю рейс..."
                        data-success-message="Рейс закрыт, список рейсов обновлён.">
                  <span class="js-departure-placeholder-label">Закрыть рейс</span>
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-danger js-departure-placeholder-action"
                        data-operation="delete_flight_php"
                        data-refresh-operation="flight_list_php"
                        data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('flight')['flight_no'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                        data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                        data-busy-label="Удаляю рейс..."
                        data-success-message="Рейс удалён, список рейсов обновлён."
                        <?php if ((($tmp = $_smarty_tpl->getValue('flight')['containers_total'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>disabled title="Удаление доступно только для рейса без контейнеров."<?php }?>>
                  <span class="js-departure-placeholder-label">Удалить рейс</span>
                </button>
              <?php }?>
            </div>
          </div>
          <div id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['row_uid'], ENT_QUOTES, 'UTF-8', true);?>
_status" class="small text-muted mb-2" aria-live="polite"></div>

          <?php if (mb_strtolower((string) $_smarty_tpl->getValue('flight')['status'], 'UTF-8') == 'open') {?>
            <div id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['row_uid'], ENT_QUOTES, 'UTF-8', true);?>
_edit" class="card border border-dashed bg-white d-none mb-3">
              <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-3">
                    <label class="form-label small mb-1" for="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_date">Новая дата</label>
                    <input type="date"
                           id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_date"
                           class="form-control form-control-sm js-departure-edit-date"
                           value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_number'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label small mb-1" for="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_awb">Новый AWB</label>
                    <input type="text"
                           id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_awb"
                           class="form-control form-control-sm js-departure-edit-awb"
                           inputmode="numeric"
                           autocomplete="off"
                           placeholder="50118620604"
                           value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['awb'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
">
                  </div>
                  <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                    <button type="button"
                            class="btn btn-sm btn-success js-departure-placeholder-action"
                            data-operation="edit_flight"
                            data-refresh-operation="flight_list_php"
                            data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                            data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('flight')['flight_no'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                            data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                            data-awb-input="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_awb"
                            data-date-input="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_date"
                            data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                            data-busy-label="Сохраняю изменения..."
                            data-success-message="Изменения рейса сохранены, список рейсов обновлён.">
                      <span class="js-departure-placeholder-label">Сохранить</span>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary js-departure-edit-toggle"
                            data-target="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_edit"
                            data-open="1"
                            aria-expanded="true">
                      Отмена
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php }?>

          <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('flight')['containers']) > 0) {?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Контейнер</th>
                    <th scope="col">Рейс</th>
                    <th scope="col">Маршрут</th>
                    <th scope="col">AWB</th>
                    <th scope="col">Склад (мест/вес)</th>
                    <th scope="col">Форвард (мест/вес)</th>
                    <th scope="col" class="text-end">Действие</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('flight')['containers'], 'container');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('container')->value) {
$foreach1DoElse = false;
?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)(($tmp = (($tmp = $_smarty_tpl->getValue('container')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('container')['container_external_id'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php if ($_smarty_tpl->getValue('container')['container_external_id']) {?>
                          <div class="small text-muted">ID: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['container_external_id'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php }?>
                        <button type="button"
                                class="btn btn-link btn-sm p-0 mt-1 js-departure-compare-open"
                                data-compare-payload="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['compare_modal_payload_json'] ?? null)===null||$tmp==='' ? '{}' ?? null : $tmp), 'htmlattr');?>
"
                                data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                                data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                data-container-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('container')['container_external_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
">
                          Сверка позиций
                        </button>
                      </td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['flight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['departure'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 → <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['destination'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['awb'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td class="<?php if ((($tmp = $_smarty_tpl->getValue('container')['compare_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp) == 'matched') {?>table-success<?php } elseif ((($tmp = $_smarty_tpl->getValue('container')['compare_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp) == 'mismatch') {?>table-warning<?php }?>">
                        <div><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['warehouse_packages_count'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 / <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['warehouse_total_weight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                      </td>
                      <td class="<?php if ((($tmp = $_smarty_tpl->getValue('container')['compare_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp) == 'matched') {?>table-success<?php } elseif ((($tmp = $_smarty_tpl->getValue('container')['compare_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp) == 'mismatch') {?>table-warning<?php }?>">
                        <div><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['packages_count'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 / <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['total_weight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php if ($_smarty_tpl->getValue('container')['forwarder_packages_synced_at']) {?>
                          <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['forwarder_packages_synced_at'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php }?>
                        <?php if ($_smarty_tpl->getValue('container')['compared_at']) {?>
                          <div class="small text-muted">Сверка: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['compared_at'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php }?>
                        <?php if ($_smarty_tpl->getValue('container')['compare_error'] && (($tmp = $_smarty_tpl->getValue('container')['compare_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp) == 'mismatch') {?>
                          <div class="small text-warning-emphasis"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('container')['compare_error'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                        <?php }?>
                      </td>

                      <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-1 me-2">
                          <button type="button"
                                  class="btn btn-sm btn-outline-primary js-departure-container-action"
                                  data-operation="sync_forwarder"
                                  data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                  data-container-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('container')['container_external_id'], 'htmlattr');?>
"
                                  data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                                  title="Запросить данные у форварда по контейнеру">
                            <i class="bi bi-cloud-arrow-down"></i>
                          </button>
                        </div>
                        <?php if (mb_strtolower((string) $_smarty_tpl->getValue('flight')['status'], 'UTF-8') == 'open' && $_smarty_tpl->getValue('container')['can_delete_placeholder']) {?>
                          <button type="button"
                                  class="btn btn-sm btn-outline-danger js-departure-placeholder-action"
                                  data-operation="delete_container_php"
                                  data-refresh-operation="flight_list_php"
                                  data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('flight')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('flight')['flight_no'] ?? null : $tmp) ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_id'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
"
                                  data-flight-record-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_record_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                                  data-container-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('container')['container_external_id'], 'htmlattr');?>
"
                                  data-container-name="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = (($tmp = $_smarty_tpl->getValue('container')['name'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('container')['container_external_id'] ?? null : $tmp) ?? null)===null||$tmp==='' ? 'container' ?? null : $tmp), 'htmlattr');?>
"
                                  data-status-target="#<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('flight')['row_uid'], 'htmlattr');?>
_status"
                                  data-busy-label="Удаляю контейнер..."
                                  data-success-message="Пустой контейнер удалён, рейс синхронизирован и список обновлён.">
                            <span class="js-departure-placeholder-label">Удалить контейнер</span>
                          </button>
                        <?php } else { ?>
                          <span class="text-muted">—</span>
                        <?php }?>
                      </td>
                    </tr>
                  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="text-muted small">Для этого рейса контейнеры пока не найдены.</div>
          <?php }?>
        </div>
      </td>
    </tr>
  <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
} else { ?>
  <tr>
    <td colspan="8" class="text-center text-muted">Рейсы не найдены по выбранным фильтрам.</td>
  </tr>
<?php }
}
}
