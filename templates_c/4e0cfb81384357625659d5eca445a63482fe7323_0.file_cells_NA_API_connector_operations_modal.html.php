<?php
/* Smarty version 5.3.1, created on 2026-02-23 12:29:52
  from 'file:cells_NA_API_connector_operations_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_699c4840cb8121_37783665',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4e0cfb81384357625659d5eca445a63482fe7323' => 
    array (
      0 => 'cells_NA_API_connector_operations_modal.html',
      1 => 1771849355,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_699c4840cb8121_37783665 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<section class="section">
  <div class="row">
    <div class="col-lg-10">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Операции коннектора: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
</h5>

          <form id="connector-operations-form" autocomplete="off">
            <input type="hidden" name="connector_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">

            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Операция #1</label>
              <div class="col-md-8 col-lg-9">
                <div class="form-text mt-2">Получение отчета с сайта форварда (XLSX).</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_enabled" class="col-md-4 col-lg-3 col-form-label">Включено</label>
              <div class="col-md-8 col-lg-9">
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         id="report_enabled"
                         name="report_enabled"
                         value="1"
                         <?php if ($_smarty_tpl->getValue('operations')['report']['enabled'] == 1) {?>checked<?php }?>>
                  <label class="form-check-label" for="report_enabled">Использовать операцию отчета</label>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_page_url" class="col-md-4 col-lg-3 col-form-label">Страница отчета</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="report_page_url"
                       name="report_page_url"
                       value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['page_url'], ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="https://portal.forwarder.com/reports">
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_file_extension" class="col-md-4 col-lg-3 col-form-label">Расширение файла</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="report_file_extension"
                       name="report_file_extension"
                       value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('operations')['report']['file_extension'] ?? null)===null||$tmp==='' ? 'xlsx' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="xlsx">
                <div class="form-text">Сейчас основной формат: xlsx.</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_download_mode" class="col-md-4 col-lg-3 col-form-label">Режим загрузки</label>
              <div class="col-md-8 col-lg-9">
                <select class="form-select" id="report_download_mode" name="report_download_mode">
                  <option value="browser" <?php if ($_smarty_tpl->getValue('operations')['report']['download_mode'] == 'browser') {?>selected<?php }?>>Через браузерные шаги</option>
                  <option value="curl" <?php if ($_smarty_tpl->getValue('operations')['report']['download_mode'] == 'curl') {?>selected<?php }?>>Через PHP cURL</option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_steps_json" class="col-md-4 col-lg-3 col-form-label">Шаги формы/кнопок</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control"
                          id="report_steps_json"
                          name="report_steps_json"
                          rows="9"
                          placeholder="JSON шагов browser-автоматизации"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['steps_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                <div class="form-text">JSON-массив шагов: goto/fill/click/wait_for/download.</div>
                <pre class="form-text mb-0">[{"action":"goto","url":"https://portal.example.com/reports"},{"action":"click","selector":"#period_today"},{"action":"click","selector":"button.export-xlsx"}]</pre>
              </div>
            </div>

            <div class="row mb-3">
              <label for="report_curl_config_json" class="col-md-4 col-lg-3 col-form-label">PHP cURL конфиг</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control"
                          id="report_curl_config_json"
                          name="report_curl_config_json"
                          rows="8"
                          placeholder="JSON конфиг для PHP cURL режима"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['curl_config_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                <div class="form-text">Используется, если выбран режим "Через PHP cURL".</div>
                <pre class="form-text mb-0">{"url":"https://portal.example.com/export","method":"POST","headers":{"Accept":"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"},"body":{"date_from":"${date_from}","date_to":"${date_to}"}}</pre>
              </div>
            </div>


            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Период теста</label>
              <div class="col-md-8 col-lg-9">
                <div class="row g-2">
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_from" id="test_period_from">
                    <div class="form-text">Дата начала (например, 2025-01-01)</div>
                  </div>
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_to" id="test_period_to">
                    <div class="form-text">Дата окончания (например, 2026-02-22)</div>
                  </div>
                </div>
              </div>
            </div>


            <div class="row mb-3">
              <label for="report_field_mapping_json" class="col-md-4 col-lg-3 col-form-label">Маппинг полей</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control"
                          id="report_field_mapping_json"
                          name="report_field_mapping_json"
                          rows="6"
                          placeholder="JSON маппинга полей (для CSV авто-импорта)"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['field_mapping_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                <div class="form-text">Где указывать, какие поля куда кладем. Формат: target_field: csv_column_name.</div>
                <pre class="form-text mb-0">{"tracking_number":"Tracking Number","shipment_status":"Status","updated_at":"Updated At"}</pre>
              </div>
            </div>


            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Период теста</label>
              <div class="col-md-8 col-lg-9">
                <div class="row g-2">
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_from" id="test_period_from">
                    <div class="form-text">Дата начала (например, 2025-01-01)</div>
                  </div>
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_to" id="test_period_to">
                    <div class="form-text">Дата окончания (например, 2026-02-22)</div>
                  </div>
                </div>
              </div>
            </div>


            <div class="row mb-3">
              <label for="report_target_table" class="col-md-4 col-lg-3 col-form-label">Целевая таблица</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="report_target_table"
                       name="report_target_table"
                       value="connector_report_<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['target_table'], ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="forward_company_us">
                <div class="form-text">Например: forward_name_country (латиница, цифры, underscore).</div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector_operations">
                Сохранить операции
              </button>
              <button type="button" class="btn btn-outline-secondary js-core-link" data-core-action="test_connector_operations" data-connector-id="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
">
                Тест операции
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php }
}
