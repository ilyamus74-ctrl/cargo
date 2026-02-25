<?php
/* Smarty version 5.3.1, created on 2026-02-25 12:15:18
  from 'file:cells_NA_API_connector_operations_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_699ee7d6ac5cc2_73331350',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4e0cfb81384357625659d5eca445a63482fe7323' => 
    array (
      0 => 'cells_NA_API_connector_operations_modal.html',
      1 => 1772021710,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_699ee7d6ac5cc2_73331350 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
<section class="section">
  <div class="row">
    <div class="col-lg-10">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Операции коннектора: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
</h5>


          <ul class="nav nav-tabs mb-3" id="connector-operations-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active"
                      id="op1-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#op1-pane"
                      type="button"
                      role="tab"
                      aria-controls="op1-pane"
                      aria-selected="true">Операция #1</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="op2-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#op2-pane"
                      type="button"
                      role="tab"
                      aria-controls="op2-pane"
                      aria-selected="false">Операция #2</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="op3-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#op3-pane"
                      type="button"
                      role="tab"
                      aria-controls="op3-pane"
                      aria-selected="false">Операция #3</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="addons-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#addons-pane"
                      type="button"
                      role="tab"
                      aria-controls="addons-pane"
                      aria-selected="false">addons ДопИнфа</button>
            </li>
          </ul>


          <form id="connector-operations-form" autocomplete="off">
            <input type="hidden" name="connector_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">

            <div class="tab-content" id="connector-operations-tab-content">
              <div class="tab-pane fade show active" id="op1-pane" role="tabpanel" aria-labelledby="op1-tab" tabindex="0">

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
                  <?php if (!$_smarty_tpl->getValue('node_runtime_available')) {?>
                  <option value="curl" <?php if ($_smarty_tpl->getValue('operations')['report']['download_mode'] == 'curl') {?>selected<?php }?>>Через PHP cURL</option>
                  <?php }?>
                </select>
              </div>
            </div>



            <div class="row mb-3">
              <label for="report_log_steps" class="col-md-4 col-lg-3 col-form-label">Лог шагов</label>
              <div class="col-md-8 col-lg-9">
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         id="report_log_steps"
                         name="report_log_steps"
                         value="1"
                         <?php if ($_smarty_tpl->getValue('operations')['report']['log_steps'] == 1) {?>checked<?php }?>>
                  <label class="form-check-label" for="report_log_steps">Писать в лог шаги сценария (только при включении)</label>
                </div>
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
                <div class="form-text">JSON-массив шагов: goto/fill/click/wait_for/download. Доступные переменные: ${date_from}/${test_period_from}, ${date_to}/${test_period_to}, ${today}, ${today_minus_2y}. Если период теста не задан — берется today_minus_2y → today. Перед шагами автоматически выполнятся login.browser_steps (или browser_login_steps) из scenario_json коннектора. Тайминги можно настраивать прямо в шагах (например: post_goto_wait_ms, before_click_wait_ms, before_export_click_wait_ms, timeout_ms), чтобы каждый сценарий жил со своими задержками.</div>
                <pre class="form-text mb-0">[{"action":"goto","url":"https://portal.example.com/reports"},{"action":"click","selector":"#period_today"},{"action":"click","selector":"button.export-xlsx"}]</pre>
              </div>
            </div>

            <?php if (!$_smarty_tpl->getValue('node_runtime_available')) {?>
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
                <pre class="form-text mb-0">{
  "login": {
    "url": "https://backend.colibri.az/login",
    "method": "POST",
    "fields": {
      "username": "${login}",
      "password": "${password}"
    }
  },
  "url": "https://dev-backend.colibri.az/collector/reports/all_packages",
  "method": "POST",
  "body": {
    "from_date": "${date_from}",
    "to_date": "${date_to}"
  }
}</pre>
              </div>
            </div>
            <?php } else { ?>
            <input type="hidden" name="report_curl_config_json" value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['curl_config_json'], ENT_QUOTES, 'UTF-8', true);?>
">
            <?php }?>

            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Период теста</label>
              <div class="col-md-8 col-lg-9">
                <div class="row g-2">
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_from" id="test_period_from">
                    <div class="form-text">Дата начала теста (подставляется в ${test_period_from}/${date_from}; если пусто — сегодня минус 2 года)</div>
                  </div>
                  <div class="col-md-6">
                    <input type="date" class="form-control" name="test_period_to" id="test_period_to">
                    <div class="form-text">Дата окончания теста (подставляется в ${test_period_to}/${date_to}; если пусто — сегодня)</div>
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
              <label for="report_target_table" class="col-md-4 col-lg-3 col-form-label">Целевая таблица</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="report_target_table"
                       name="report_target_table"
                       value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['report']['target_table'], ENT_QUOTES, 'UTF-8', true);?>
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
                Тест операции #1
              </button>
            </div>

            <div class="form-text mt-2">Кнопка теста сейчас запускает только сценарий операции #1 (скачивание отчета).</div>

              </div>

              <div class="tab-pane fade" id="op2-pane" role="tabpanel" aria-labelledby="op2-tab" tabindex="0">
                <div class="row mb-3 mt-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Операция #2</label>
                  <div class="col-md-8 col-lg-9">
                    <div class="form-text mt-2">Сценарий заполнения и отправки формы (например, https://dev-backend.colibri.az/collector).</div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_enabled" class="col-md-4 col-lg-3 col-form-label">Включено</label>
                  <div class="col-md-8 col-lg-9">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             id="submission_enabled"
                             name="submission_enabled"
                             value="1"
                             <?php if ($_smarty_tpl->getValue('operations')['submission']['enabled'] == 1) {?>checked<?php }?>>
                      <label class="form-check-label" for="submission_enabled">Использовать операцию отправки</label>
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_page_url" class="col-md-4 col-lg-3 col-form-label">Страница формы</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="text"
                           class="form-control"
                           id="submission_page_url"
                           name="submission_page_url"
                           value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['submission']['page_url'], ENT_QUOTES, 'UTF-8', true);?>
"
                           placeholder="https://dev-backend.colibri.az/collector">
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_log_steps" class="col-md-4 col-lg-3 col-form-label">Лог шагов</label>
                  <div class="col-md-8 col-lg-9">
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             id="submission_log_steps"
                             name="submission_log_steps"
                             value="1"
                             <?php if ($_smarty_tpl->getValue('operations')['submission']['log_steps'] == 1) {?>checked<?php }?>>
                      <label class="form-check-label" for="submission_log_steps">Писать в лог шаги сценария операции #2</label>
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_steps_json" class="col-md-4 col-lg-3 col-form-label">Шаги формы операции #2</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea class="form-control"
                              id="submission_steps_json"
                              name="submission_steps_json"
                              rows="10"
                              placeholder="JSON шагов browser-автоматизации"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['submission']['steps_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                    <div class="form-text">JSON-массив шагов для формы: goto/fill/click/wait_for. Можно использовать переменные из scenario_json: ${login}, ${password}, ${date_from}, ${date_to}.</div>
                    <pre class="form-text mb-0">[
  {"action":"goto","url":"https://dev-backend.colibri.az/collector"},
  {"action":"fill","selector":"input[name=\"tracking\"]","value":"${tracking_number}"},
  {"action":"click","selector":"button[type=\"submit\"]"},
  {"action":"wait_for","selector":".alert-success","timeout_ms":10000}
]</pre>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_request_config_json" class="col-md-4 col-lg-3 col-form-label">AJAX / Request конфиг</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea class="form-control"
                              id="submission_request_config_json"
                              name="submission_request_config_json"
                              rows="8"
                              placeholder="JSON для ajax/fetch запроса формы"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['submission']['request_config_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                    <div class="form-text">Опционально: параметры XHR/fetch, если нужно отправлять форму без клика по кнопке.</div>
                    <pre class="form-text mb-0">{
  "url": "https://dev-backend.colibri.az/collector/store",
  "method": "POST",
  "headers": {
    "X-Requested-With": "XMLHttpRequest"
  },
  "body": {
    "tracking": "${tracking_number}",
    "suite": "${suite}"
  }
}</pre>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="submission_success_selector" class="col-md-4 col-lg-3 col-form-label">Проверка успеха</label>
                  <div class="col-md-8 col-lg-9">
                    <input type="text"
                           class="form-control mb-2"
                           id="submission_success_selector"
                           name="submission_success_selector"
                           value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['submission']['success_selector'], ENT_QUOTES, 'UTF-8', true);?>
"
                           placeholder=".alert-success, table#changes tbody tr:first-child">
                    <input type="text"
                           class="form-control"
                           id="submission_success_text"
                           name="submission_success_text"
                           value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('operations')['submission']['success_text'], ENT_QUOTES, 'UTF-8', true);?>
"
                           placeholder="Expected success text (optional)">
                    <div class="form-text">Селектор и/или текст для валидации успешной отправки формы.</div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="op3-pane" role="tabpanel" aria-labelledby="op3-tab" tabindex="0">
                <div class="alert alert-info mb-0" role="alert">
                  <strong>Операция #3 (TrackAndLabelInfo)</strong><br>
                  Заготовка под сценарий проверки трека и загрузки label/документов.
                </div>
              </div>

              <div class="tab-pane fade" id="addons-pane" role="tabpanel" aria-labelledby="addons-tab" tabindex="0">
                <div class="row mb-3 mt-3">
                  <label class="col-md-4 col-lg-3 col-form-label">Дополнительная информация</label>
                  <div class="col-md-8 col-lg-9">
                    <div class="form-text mt-2">Данные о содержимом посылки для форварда. Хранится в таблице <code>connectors_addons</code>.</div>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="addon_extra_json" class="col-md-4 col-lg-3 col-form-label">Дополнения (extra)</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea class="form-control"
                              id="addon_extra_json"
                              name="addon_extra_json"
                              rows="6"
                              placeholder='[{"tariff_type":{"1":"General","2":"Liquid","3":"Promotions"}},{"category":{"50% school":"50% school","adapter (kompyuter)":"Adapter (Kompyuter)","animal accessories":"Animal Accessories"}}]'><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['extra_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                    <div class="form-text">JSON со справочниками по типу и категории. Пример:</div>
                    <pre class="form-text mb-0">[
  {
    "tariff_type": {
      "1": "General",
      "2": "Liquid",
      "3": "Promotions"
    }
  },
  {
    "category": {
      "50% school": "50% school",
      "adapter (kompyuter)": "Adapter (Kompyuter)",
      "animal accessories": "Animal Accessories"
    }
  }
]</pre>
                  </div>
                </div>

                <div class="row mb-3">
                  <label for="addon_node_mapping_json" class="col-md-4 col-lg-3 col-form-label">Node mapping</label>
                  <div class="col-md-8 col-lg-9">
                    <textarea class="form-control"
                              id="addon_node_mapping_json"
                              name="addon_node_mapping_json"
                              rows="6"
                              placeholder='{"forwarder_field":"node_field"}'><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['node_mapping_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                    <div class="form-text">Маппинг для заполнения через node-сценарий по конкретному форварду.</div>
                  </div>
                </div>

                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector_addons">
                    Сохранить ДопИнфо
                  </button>
                </div>
              </div>

            </div>
          </form>
        </div>
      </div>
    </div>
  </div>


<?php if ((($tmp = $_smarty_tpl->getValue('open_tab') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {
echo '<script'; ?>
>
  (function() {
    var tabTrigger = document.querySelector('[data-bs-target="#<?php echo strtr((string)$_smarty_tpl->getValue('open_tab'), array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
"]');
    if (!tabTrigger || !window.bootstrap || !window.bootstrap.Tab) {
      return;
    }
    window.bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
  })();
<?php echo '</script'; ?>
>
<?php }?>


</section>
<?php }
}
