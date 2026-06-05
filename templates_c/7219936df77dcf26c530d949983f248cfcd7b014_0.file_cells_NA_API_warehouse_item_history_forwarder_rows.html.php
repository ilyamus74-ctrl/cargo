<?php
/* Smarty version 5.3.1, created on 2026-06-05 15:32:24
  from 'file:cells_NA_API_warehouse_item_history_forwarder_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a22ec0885cff2_66750769',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7219936df77dcf26c530d949983f248cfcd7b014' => 
    array (
      0 => 'cells_NA_API_warehouse_item_history_forwarder_rows.html',
      1 => 1780671449,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a22ec0885cff2_66750769 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
if (!$_smarty_tpl->getValue('events')) {?>
  <div class="alert alert-secondary mb-3">
    Репорты форварда не найдены<?php if ($_smarty_tpl->getValue('report_table')) {?> в <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('report_table'), ENT_QUOTES, 'UTF-8', true);
}?>.
    Проверено строк: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('scanned_rows'), ENT_QUOTES, 'UTF-8', true);?>
.
  </div>
<?php } else { ?>
  <div class="mb-2 small text-muted">
    Репорты форварда<?php if ($_smarty_tpl->getValue('report_table')) {?> из <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('report_table'), ENT_QUOTES, 'UTF-8', true);
}?>. Проверено строк: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('scanned_rows'), ENT_QUOTES, 'UTF-8', true);?>
.
  </div>
  <div class="list-group mb-3">
    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('events'), 'event');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('event')->value) {
$foreach0DoElse = false;
?>
      <div class="list-group-item">
        <div class="d-flex justify-content-between gap-2">
          <div>
            <div class="fw-semibold">
              <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('event')['title'] ?? null)===null||$tmp==='' ? 'Репорт форварда' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

            </div>
            <div class="small text-muted">
              <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['source_label'], ENT_QUOTES, 'UTF-8', true);?>

              <?php if ($_smarty_tpl->getValue('event')['source_file']) {?> · <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['source_file'], ENT_QUOTES, 'UTF-8', true);
}?>
              <?php if ($_smarty_tpl->getValue('event')['report_row_id']) {?> · #<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('event')['report_row_id'], ENT_QUOTES, 'UTF-8', true);
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
<?php }
}
}
