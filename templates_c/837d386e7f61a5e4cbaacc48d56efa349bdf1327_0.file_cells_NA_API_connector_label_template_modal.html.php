<?php
/* Smarty version 5.3.1, created on 2026-04-08 16:11:45
  from 'file:cells_NA_API_connector_label_template_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69d67e41b45a03_21195514',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '837d386e7f61a5e4cbaacc48d56efa349bdf1327' => 
    array (
      0 => 'cells_NA_API_connector_label_template_modal.html',
      1 => 1775664468,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69d67e41b45a03_21195514 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><form id="connector-label-template-form">
  <input type="hidden" name="connector_id" value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), 'htmlattr');?>
">
  <input type="hidden" name="template_code" value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('template')['template_code'] ?? null)===null||$tmp==='' ? 'default' ?? null : $tmp), 'htmlattr');?>
">

  <div class="container-fluid">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="mb-2">
          <label class="form-label">Template code</label>
          <input type="text"
                 class="form-control"
                 value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['template_code'] ?? null)===null||$tmp==='' ? 'default' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"
                 readonly>
        </div>
        <div class="mb-2">
          <label for="connector_label_template_body" class="form-label">Тело шаблона</label>
          <textarea id="connector_label_template_body"
                    name="template_body"
                    class="form-control font-monospace"
                    rows="18"
                    placeholder="<!doctype html>..."><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['template_body'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</textarea>
          <div class="form-text">Лимит: 100KB. Доступны плейсхолдеры вида <code>{{track}}</code>.</div>
        </div>
        <div class="alert alert-light border small mb-2">
          <div class="fw-semibold mb-1">Плейсхолдеры (минимум)</div>
          <div class="d-flex flex-wrap gap-2">
            <code>{{track}}</code>
            <code>{{client_name}}</code>
            <code>{{client}}</code>
            <code>{{client_code}}</code>
            <code>{{client_id}}</code>
            <code>{{internal_id}}</code>
            <code>{{weight}}</code>
            <code>{{amount}}</code>
            <code>{{forward_name}}</code>
            <code>{{country_dest}}</code>
            <code>{{flight_departure}}</code>
            <code>{{flight_destination}}</code>
            <code>{{flight_name}}</code>
            <code>{{barcode_url}}</code>
            <code>{{qr_img_html}}</code>
          </div>
        </div>
        <div id="connector-label-template-validation" class="small text-muted">Валидация ещё не запускалась.</div>
      </div>

      <div class="col-lg-6">
        <div class="mb-2 small text-muted">
          Коннектор: <strong><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['name'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</strong> (#<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
)
        </div>
        <div class="mb-2 small text-muted">
          Test track: <code><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['test_track'] ?? null)===null||$tmp==='' ? 'TEST-TRACK-0001' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</code>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-sm-6">
            <label for="connector_label_template_test_track" class="form-label form-label-sm">Test track</label>
            <input id="connector_label_template_test_track"
                   type="text"
                   class="form-control form-control-sm"
                   name="test_track"
                   value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('template')['test_track'] ?? null)===null||$tmp==='' ? 'TEST-TRACK-0001' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
          </div>
          <div class="col-sm-6">
            <label for="connector_label_template_print_device" class="form-label form-label-sm">Print device key</label>
            <select id="connector_label_template_print_device"
                    class="form-select form-select-sm"
                    name="print_device_key">
              <option value="">Не использовать принтер (только preview)</option>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('print_devices'), 'device');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('device')->value) {
$foreach0DoElse = false;
?>
                <option value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')($_smarty_tpl->getValue('device')['device_uid'], 'htmlattr');?>
" <?php if ($_smarty_tpl->getValue('print_devices_count') === 1) {?>selected<?php }?>>
                  <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('device')['name'] ?? null)===null||$tmp==='' ? 'Printer' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
 (<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_uid'], ENT_QUOTES, 'UTF-8', true);?>
)
                </option>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            </select>
          </div>

          <div class="col-sm-6">
            <label for="connector_label_template_print_rotate" class="form-label form-label-sm">Rotate (test print)</label>
            <select id="connector_label_template_print_rotate"
                    class="form-select form-select-sm"
                    name="print_rotate">
              <option value="0" <?php if ((int)((($tmp = $_smarty_tpl->getValue('template')['print_rotate'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) === 0) {?>selected<?php }?>>0°</option>
              <option value="90" <?php if ((int)((($tmp = $_smarty_tpl->getValue('template')['print_rotate'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) === 90) {?>selected<?php }?>>90°</option>
              <option value="180" <?php if ((int)((($tmp = $_smarty_tpl->getValue('template')['print_rotate'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) === 180) {?>selected<?php }?>>180°</option>
              <option value="270" <?php if ((int)((($tmp = $_smarty_tpl->getValue('template')['print_rotate'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp)) === 270) {?>selected<?php }?>>270°</option>
            </select>
          </div>
          <div class="col-sm-3">
            <label for="connector_label_template_label_width_cm" class="form-label form-label-sm">Label width (cm)</label>
            <input id="connector_label_template_label_width_cm"
                   type="number"
                   min="2"
                   max="30"
                   step="0.1"
                   class="form-control form-control-sm"
                   name="label_width_cm"
                   value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('template')['label_width_cm'] ?? null)===null||$tmp==='' ? 10 ?? null : $tmp), 'htmlattr');?>
">
          </div>
          <div class="col-sm-3">
            <label for="connector_label_template_label_height_cm" class="form-label form-label-sm">Label height (cm)</label>
            <input id="connector_label_template_label_height_cm"
                   type="number"
                   min="2"
                   max="30"
                   step="0.1"
                   class="form-control form-control-sm"
                   name="label_height_cm"
                   value="<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('escape')((($tmp = $_smarty_tpl->getValue('template')['label_height_cm'] ?? null)===null||$tmp==='' ? 15 ?? null : $tmp), 'htmlattr');?>
">
          </div>
        </div>
        <div class="border rounded p-2 bg-light" style="min-height: 420px; max-height: 620px; overflow: auto;">
          <div id="connector-label-template-preview" class="small"><?php echo (($tmp = $_smarty_tpl->getValue('template')['preview_html'] ?? null)===null||$tmp==='' ? '<span class="text-muted">Предпросмотр пока пуст</span>' ?? null : $tmp);?>
</div>
        </div>
      </div>
    </div>
  </div>

    <div class="d-flex justify-content-between mt-3 pt-2 border-top gap-2 flex-wrap">
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-outline-secondary js-core-link" data-core-action="validate_connector_label_template">
        Предпросмотр
      </button>
      <button type="button" class="btn btn-outline-secondary js-core-link" data-core-action="validate_connector_label_template">
        Проверить шаблон
      </button>
      <button type="button" class="btn btn-outline-info js-core-link" data-core-action="test_print_connector_label_template">
        Тест печати шаблона
      </button>
    </div>
    <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector_label_template">
      Сохранить шаблон
    </button>
  </div>
</form>
<?php }
}
