<?php
/* Smarty version 5.3.1, created on 2026-06-05 15:03:20
  from 'file:cells_NA_API_warehouse_item_history_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a22e538ca0fb0_18436041',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'be0cbb74b8c1d132a4dac14f336e8ea184569ed7' => 
    array (
      0 => 'cells_NA_API_warehouse_item_history_modal.html',
      1 => 1780671506,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a22e538ca0fb0_18436041 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="warehouse-item-history">
  <div class="mb-3">
    <h5 class="mb-1">История посылки</h5>
    <div class="small text-muted">
      <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['tracking_no'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('item')['tuid'] ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

      · <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

      · <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_company'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

      · <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row g-2 small">
        <div class="col-6 col-md-3"><span class="text-muted">ID:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['id'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-6 col-md-3"><span class="text-muted">TUID:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['tuid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12 col-md-6"><span class="text-muted">Трек:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['tracking_no'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12 col-md-6"><span class="text-muted">Получатель:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12 col-md-6"><span class="text-muted">Форвард:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_company'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12"><span class="text-muted">Адрес:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_address'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12 col-md-6"><span class="text-muted">Статус регистрации у форварда:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_registration_status'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <div class="col-12 col-md-6"><span class="text-muted">Дата регистрации:</span> <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_registered_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php if ($_smarty_tpl->getValue('item')['forwarder_registration_message']) {?>
          <div class="col-12"><span class="text-muted">Сообщение:</span> <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['forwarder_registration_message'], ENT_QUOTES, 'UTF-8', true);?>
</div>
        <?php }?>
      </div>
    </div>
  </div>

  <div class="mb-3">
    <button type="button"
            class="btn btn-sm btn-outline-primary js-core-link"
            data-core-action="warehouse_stock_history_forwarder_reports"
            data-item-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['id'], ENT_QUOTES, 'UTF-8', true);?>
">
      Загрузить репорты форварда
    </button>
    <div class="form-text">
      Репорты форварда загружаются отдельно, чтобы не тормозить открытие истории.
    </div>
  </div>

  <div id="warehouse-stock-history-forwarder-reports"></div>

  <?php if (!$_smarty_tpl->getValue('timeline')) {?>
    <div class="alert alert-secondary">История по посылке не найдена.</div>
  <?php } else { ?>
    <div class="list-group">
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('timeline'), 'event');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('event')->value) {
$foreach0DoElse = false;
?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between gap-2">
            <div>
              <div class="fw-semibold">
                <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('event')['title'] ?? null)===null||$tmp==='' ? 'Событие' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

              </div>
              <div class="small text-muted">
                <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['source_label'], ENT_QUOTES, 'UTF-8', true);?>

                <?php if ($_smarty_tpl->getValue('event')['actor_name']) {?> · <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['actor_name'], ENT_QUOTES, 'UTF-8', true);
}?>
              </div>
            </div>
            <div class="small text-muted text-nowrap">
              <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('event')['event_time'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

            </div>
          </div>

          <?php if ($_smarty_tpl->getValue('event')['description']) {?>
            <div class="mt-2"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['description'], ENT_QUOTES, 'UTF-8', true);?>
</div>
          <?php }?>

          <?php if ($_smarty_tpl->getValue('event')['details_json']) {?>
            <details class="mt-2">
              <summary class="small text-muted">JSON / детали</summary>
              <pre class="small bg-light border rounded p-2 mt-2 mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['details_json'], ENT_QUOTES, 'UTF-8', true);?>
</pre>
            </details>
          <?php }?>
        </div>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </div>
  <?php }?>
</div>
<?php }
}
