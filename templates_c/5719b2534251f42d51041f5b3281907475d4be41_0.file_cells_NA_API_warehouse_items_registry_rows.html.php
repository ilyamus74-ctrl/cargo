<?php
/* Smarty version 5.3.1, created on 2026-06-05 14:01:55
  from 'file:cells_NA_API_warehouse_items_registry_rows.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6a22d6d32ca7c8_11155654',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '5719b2534251f42d51041f5b3281907475d4be41' => 
    array (
      0 => 'cells_NA_API_warehouse_items_registry_rows.html',
      1 => 1780667920,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6a22d6d32ca7c8_11155654 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('warehouse_items_registry'), 'item');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach0DoElse = false;
?>
  <tr>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['source_table'] == 'in') {?>
        <span class="badge bg-info text-dark">Приёмка</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['source_table'] == 'stock') {?>
        <span class="badge bg-primary">Склад</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['source_table'] == 'out') {?>
        <span class="badge bg-secondary">Отгрузка</span>
      <?php } else { ?>
        <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['source_table'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span>
      <?php }?>
    </td>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['warehouse_state'] == 'without_cells') {?>
        <span class="badge bg-warning text-dark">Без ячейки</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['warehouse_state'] == 'to_send') {?>
        <span class="badge bg-info text-dark">На отгрузку</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['warehouse_state'] == 'in_storage') {?>
        <span class="badge bg-success">На складе</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['warehouse_state'] == 'sended') {?>
        <span class="badge bg-dark">Отправлена</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['warehouse_state'] == 'in_progress') {?>
        <span class="badge bg-light text-dark">Приёмка</span>
      <?php } else { ?>
        <span class="badge bg-light text-dark"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['warehouse_state'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</span>
      <?php }?>
    </td>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['stock_item_id']) {?>
        <button type="button"
                class="btn btn-link p-0 js-core-link"
                data-core-action="open_item_stock_modal"
                data-item-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['stock_item_id'], ENT_QUOTES, 'UTF-8', true);?>
">
          <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

        </button>
      <?php } else { ?>
        <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['parcel_uid'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

      <?php }?>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['tracking_no'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td>
      <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['receiver_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

      <?php if ($_smarty_tpl->getValue('item')['receiver_company']) {?>
        <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['receiver_company'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
      <?php if ($_smarty_tpl->getValue('item')['user_name']) {?>
        <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['user_name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['cell_address']) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['cell_address'], ENT_QUOTES, 'UTF-8', true);
} else { ?>—<?php }?>
      <?php if ($_smarty_tpl->getValue('item')['container_name']) {?>
        <div class="small text-muted"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['container_name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
      <?php }?>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['out_status'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td>
      <?php if ($_smarty_tpl->getValue('item')['forwarder_registration_status'] == 'ok') {?>
        <span class="badge bg-success">ok</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['forwarder_registration_status'] == 'validation_error') {?>
        <span class="badge bg-warning text-dark">validation_error</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['forwarder_registration_status'] == 'error') {?>
        <span class="badge bg-danger">error</span>
      <?php } elseif ($_smarty_tpl->getValue('item')['forwarder_registration_status'] == 'skipped') {?>
        <span class="badge bg-secondary">skipped</span>
      <?php } else { ?>
        <span class="badge bg-secondary">—</span>
      <?php }?>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_registered_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
    <td title="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_registration_message'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
      <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['forwarder_registration_message_short'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

    </td>
    <td class="text-end">
      <?php if ($_smarty_tpl->getValue('item')['stock_item_id'] && $_smarty_tpl->getValue('item')['source_table'] != 'in') {?>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary"
                  type="button"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                  title="Действия">
            <i class="bi bi-three-dots-vertical"></i>
          </button>

          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <button type="button"
                      class="dropdown-item js-core-link"
                      data-core-action="open_item_stock_modal"
                      data-item-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['stock_item_id'], ENT_QUOTES, 'UTF-8', true);?>
">
                Открыть карточку
              </button>
            </li>

            <li>
              <button type="button"
                      class="dropdown-item js-core-link"
                      data-core-action="warehouse_stock_register_forwarder"
                      data-item-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['stock_item_id'], ENT_QUOTES, 'UTF-8', true);?>
"
                      data-force="1">
                Регистрация у форварда
              </button>
            </li>

            <li><hr class="dropdown-divider"></li>

            <li>
              <button type="button"
                      class="dropdown-item js-core-link"
                      data-core-action="warehouse_stock_history_modal"
                      data-item-id="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['stock_item_id'], ENT_QUOTES, 'UTF-8', true);?>
">
                История
              </button>
            </li>
          </ul>
        </div>
      <?php } else { ?>
        —
      <?php }?>
    </td>
    <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('item')['created_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
  </tr>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);
if (!$_smarty_tpl->getValue('warehouse_items_registry') && $_smarty_tpl->getValue('show_empty')) {?>
  <tr>
    <td colspan="13" class="text-center text-muted">Нет посылок по выбранным фильтрам</td>
  </tr>
<?php }
}
}
