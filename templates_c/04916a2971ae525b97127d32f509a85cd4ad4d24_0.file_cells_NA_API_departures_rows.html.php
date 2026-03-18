<?php
/* Smarty version 5.3.1, created on 2026-03-18 18:53:00
  from 'file:cells_NA_API_departures_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69baf48c1ba750_09915518',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '04916a2971ae525b97127d32f509a85cd4ad4d24' => 
    array (
      0 => 'cells_NA_API_departures_rows.html',
      1 => 1773859836,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69baf48c1ba750_09915518 (\Smarty\Template $_smarty_tpl) {
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
        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('flight')['containers_sync_status'] ?? null)===null||$tmp==='' ? 'pending' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
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
                        data-operation="add_container_to_flight"
                        data-refresh-operation="add_flight"
                        data-connector-id="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['connector_id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
"
                        data-flight="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('flight')['flight_no'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), 'htmlattr');?>
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
              <?php }?>
            </div>
          </div>
          <div id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('flight')['row_uid'], ENT_QUOTES, 'UTF-8', true);?>
_status" class="small text-muted mb-2" aria-live="polite"></div>

          <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('flight')['containers']) > 0) {?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Контейнер</th>
                    <th scope="col">Рейс</th>
                    <th scope="col">Маршрут</th>
                    <th scope="col">AWB</th>
                    <th scope="col">Мест</th>
                    <th scope="col">Вес</th>
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
                      </td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['flight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['departure'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 → <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['destination'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['awb'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['packages_count'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                      <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('container')['total_weight'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
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
