<?php
/* Smarty version 5.3.1, created on 2026-04-15 16:04:11
  from 'file:cells_NA_API_connector_operations_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69dfb6fbbd40f7_39589081',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4e0cfb81384357625659d5eca445a63482fe7323' => 
    array (
      0 => 'cells_NA_API_connector_operations_modal.html',
      1 => 1774947860,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69dfb6fbbd40f7_39589081 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><section class="section">
  <style>
    #connector-operations-tabs .nav-link {
      color: var(--bs-body-color, #212529);
      border-bottom-width: 2px;
    }

    #connector-operations-tabs .nav-link.active {
      color: #0d6efd;
      background: #eef4ff;
      border-color: #86b7fe #86b7fe #eef4ff;
      box-shadow: inset 0 -2px 0 #0d6efd;
      font-weight: 600;
    }

    #connector-operations-tabs .nav-link.active .badge {
      box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.15);
    }
  </style>
  <div class="row">
    <div class="col-lg-10">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Операции коннектора: <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
</h5>

          <details class="mb-3">
            <summary><strong>Памятка</strong></summary>
            <div class="small mt-2 text-muted">
              Вкладки операций теперь формируются динамически из payload v3. Добавляйте операции кнопкой <strong>+</strong>.
            </div>
          </details>

          <?php if (!$_smarty_tpl->getValue('node_runtime_available')) {?>
          <div class="alert alert-warning py-2 small mb-3">
            Node-пути отключены/недоступны. Для новых операций используйте PHP-варианты (`php_report` или `script` + `interpreter=php`).
          </div>
          <?php }?>

          <form id="connector-operations-form" autocomplete="off" data-node-enabled="<?php if ($_smarty_tpl->getValue('node_runtime_available')) {?>1<?php } else { ?>0<?php }?>">
            <input type="hidden" name="connector_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">
            <input type="hidden" id="operations_v3_json" name="operations_v3_json" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('operations_v3_json') ?? null)===null||$tmp==='' ? '{"schema_version":3,"operations":[]}' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
            <input type="hidden" id="operations_last_status_json" value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('operations_last_status_json') ?? null)===null||$tmp==='' ? '{}' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
            <div id="operations-existing-summary" class="alert alert-secondary py-2 small mb-3 d-none"></div>

            <ul class="nav nav-tabs mb-3" id="connector-operations-tabs" role="tablist"></ul>

            <div class="tab-content" id="connector-operations-tab-content"></div>

            <div class="d-flex gap-2 mb-3">

              <div class="input-group" style="max-width: 420px;">
                <label class="input-group-text" for="operation-template-select">Создать из шаблона</label>
                <select class="form-select" id="operation-template-select">
                  <option value="">Выберите шаблон…</option>
                </select>
                <button type="button" class="btn btn-outline-secondary" id="create-from-template-btn">Добавить</button>
              </div>
              <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector_operations">Сохранить операции</button>
 
            </div>

            <hr>
            <h6 class="mb-3">addons ДопИнфо</h6>

            <div class="row mb-3">
              <label for="addon_extra_json" class="col-md-4 col-lg-3 col-form-label">Дополнения (extra)</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control" id="addon_extra_json" name="addon_extra_json" rows="6"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['extra_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              </div>
            </div>

            <div class="row mb-3">
              <label for="addon_node_mapping_json" class="col-md-4 col-lg-3 col-form-label">Legacy mapping (optional)</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control" id="addon_node_mapping_json" name="addon_node_mapping_json" rows="6"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['node_mapping_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                <div class="form-text">Историческое поле совместимости для старых node-сценариев.</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="addon_status_targets_json" class="col-md-4 col-lg-3 col-form-label">Routing статусов</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control" id="addon_status_targets_json" name="addon_status_targets_json" rows="5"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['status_targets_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              </div>
            </div>

            <div class="row mb-3">
              <label for="addon_report_out_statuses_json" class="col-md-4 col-lg-3 col-form-label">Статусы репорта -> warehouse_item_out.status</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control" id="addon_report_out_statuses_json" name="addon_report_out_statuses_json" rows="5"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['report_out_statuses_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              </div>
            </div>


            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector_addons">Сохранить ДопИнфо</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php echo '<script'; ?>
>

(function() {
  var scriptEl = document.currentScript;
  var root = scriptEl && scriptEl.closest ? scriptEl.closest('section.section') : null;
  var textarea = root ? root.querySelector('#operations_v3_json') : document.getElementById('operations_v3_json');
  var tabs = root ? root.querySelector('#connector-operations-tabs') : document.getElementById('connector-operations-tabs');
  var content = root ? root.querySelector('#connector-operations-tab-content') : document.getElementById('connector-operations-tab-content');
  var summary = root ? root.querySelector('#operations-existing-summary') : document.getElementById('operations-existing-summary');
  var statusJsonEl = root ? root.querySelector('#operations_last_status_json') : document.getElementById('operations_last_status_json');
  var formEl = root ? root.querySelector('#connector-operations-form') : document.getElementById('connector-operations-form');
  var nodeEnabled = formEl && formEl.dataset ? String(formEl.dataset.nodeEnabled || '1') === '1' : true;
  if (!textarea || !tabs || !content) return;

  function toArray(v) { return Array.isArray(v) ? v : []; }
  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isPlainObject(v) {
    return Object.prototype.toString.call(v) === '[object Object]';
  }
  function normalizeOperation(rawOp, fallbackId) {
    var op = (rawOp && typeof rawOp === 'object') ? Object.assign({}, rawOp) : {};
    if (!op.operation_id) op.operation_id = String(fallbackId || '').trim();
    if (!op.display_name) op.display_name = String(op.operation_id || '').trim();
    if (!op.module) op.module = 'generic';
    if (!op.kind && String(op.module).toLowerCase() === 'generic') op.kind = 'browser_steps';
    if (!Array.isArray(op.run_after)) op.run_after = [];
    if (!Array.isArray(op.run_with)) op.run_with = [];
    if (!Array.isArray(op.run_finally)) op.run_finally = [];
    if (!isPlainObject(op.config)) op.config = {};
    return op;
  }
  function normalizePayload(rawPayload) {
    if (Array.isArray(rawPayload)) {
      return {
        schema_version: 3,
        operations: rawPayload.map(function(op, idx) {
          return normalizeOperation(op, 'op_' + (idx + 1));
        })
      };
    }

    if (!rawPayload || typeof rawPayload !== 'object') {
      return { schema_version: 3, operations: [] };
    }

    if (Array.isArray(rawPayload.operations)) {
      return {
        schema_version: 3,
        operations: rawPayload.operations.map(function(op, idx) {
          return normalizeOperation(op, 'op_' + (idx + 1));
        })
      };
    }

    if (rawPayload.operations && typeof rawPayload.operations === 'object') {
      var mapped = [];
      Object.keys(rawPayload.operations).forEach(function(opKey) {
        var candidate = rawPayload.operations[opKey];
        if (!candidate || typeof candidate !== 'object') return;
        mapped.push(normalizeOperation(candidate, opKey));
      });
      return { schema_version: 3, operations: mapped };
    }

    var legacyOps = [];
    Object.keys(rawPayload).forEach(function(opKey) {
      if (opKey === 'schema_version' || opKey === 'operations') return;
      var candidate = rawPayload[opKey];
      if (!candidate || typeof candidate !== 'object' || Array.isArray(candidate)) return;
      if (candidate.operation_id || candidate.display_name || candidate.module || candidate.kind || candidate.action || candidate.config) {
        legacyOps.push(normalizeOperation(candidate, opKey));
      }
    });

    return { schema_version: 3, operations: legacyOps };
  }

  function ensureReportPhpOperation(payloadLike) {
    if (!payloadLike || !Array.isArray(payloadLike.operations)) return payloadLike;

    var hasReport = false;
    var hasReportPhp = false;
    var reportConfig = {};

    payloadLike.operations.forEach(function(op) {
      var opId = String(op && op.operation_id || '').trim().toLowerCase();
      if (opId === 'report') {
        hasReport = true;
        if (op && typeof op.config === 'object' && op.config) {
          reportConfig = op.config;
        }
      }
      if (opId === 'report_php') {
        hasReportPhp = true;
      }
    });

    if (!hasReport || hasReportPhp) return payloadLike;

    var inheritedConfig = {};
    if (reportConfig && typeof reportConfig === 'object') {
      ['target_table', 'field_mapping', 'file_extension'].forEach(function(key) {
        if (Object.prototype.hasOwnProperty.call(reportConfig, key)) {
          inheritedConfig[key] = reportConfig[key];
        }
      });
    }

    payloadLike.operations.push({
      operation_id: 'report_php',
      display_name: 'Операция report_php',
      module: 'generic',
      action: '',
      kind: 'script',
      enabled: 0,
      entrypoint: 0,
      on_dependency_fail: 'stop',
      run_after: [],
      run_with: [],
      run_finally: [],
      config: Object.assign({
        interpreter: 'php',
        script_path: '',
        args: [],
        timeout_sec: 90
      }, inheritedConfig)
    });

    return payloadLike;
  }

  function ensureFlightListPhpOperation(payloadLike) {
    if (!payloadLike || !Array.isArray(payloadLike.operations)) return payloadLike;

    var hasFlightList = false;
    var hasFlightListPhp = false;
    var inheritedTargetTable = '';

    payloadLike.operations.forEach(function(op) {
      var opId = String(op && op.operation_id || '').trim().toLowerCase();
      if (opId === 'flight_list') {
        hasFlightList = true;
        var cfg = op && typeof op.config === 'object' && op.config ? op.config : {};
        inheritedTargetTable = String(cfg.target_table || '').trim();
      }
      if (opId === 'flight_list_php') {
        hasFlightListPhp = true;
      }
    });

    if (!hasFlightList || hasFlightListPhp) return payloadLike;

    payloadLike.operations.push({
      operation_id: 'flight_list_php',
      display_name: 'Операция flight_list_php',
      module: 'connectors',
      action: '',
      kind: 'script',
      enabled: 1,
      entrypoint: 0,
      on_dependency_fail: 'stop',
      run_after: [],
      run_with: [],
      run_finally: [],
      config: {
        interpreter: 'php',
        script_path: 'www/scripts/mvp/app/Forwarder/run_flight_list.php',
        timeout_sec: 180,
        target_table: inheritedTargetTable || 'connector_dev_colibri_operation_flight_list',
        args: [
          '--base-url={{base_url}}',
          '--login={{auth_username}}',
          '--password={{auth_password}}',
          '--page-path=/collector/flights',
          '--connector-id={{connector_id}}',
          '--target-table={{target_table}}',
          '--write-mode=upsert'
        ]
      }
    });

    return payloadLike;
  }

  var payload;
  var operationLastStatus = parseJsonSafe(statusJsonEl && statusJsonEl.value ? statusJsonEl.value : '{}', {});
  var backendOperationTemplates = <?php echo (($tmp = $_smarty_tpl->getValue('operation_templates_json') ?? null)===null||$tmp==='' ? '{}' ?? null : $tmp);?>
;
  try {
    payload = JSON.parse(textarea.value || '{"schema_version":3,"operations":[]}');
  } catch (e) {
    payload = { schema_version: 3, operations: [] };
  }
  payload = normalizePayload(payload);
  payload = ensureReportPhpOperation(payload);
  payload = ensureFlightListPhpOperation(payload);

  var operationTemplates = {
    php_report: {
      operation_id_prefix: 'report_php',
      display_name: 'Report (PHP runtime)',
      module: 'connectors',
      action: '',
      kind: 'php_report',
      config: {
        from: '<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('date_format')(time(),"%Y-%m-01");?>
',
        to: '<?php echo $_smarty_tpl->getSmarty()->getModifierCallback('date_format')(time(),"%Y-%m-%d");?>
',
        target_table: 'connector_report_table',
        download: {
          url: 'https://example.com/report',
          method: 'GET',
          timeout_sec: 120
        },
        import: {
          enabled: 1,
          file_extension: 'xlsx',
          field_mapping: {}
        }
      }
    },
    script_php_forwarder: {
      operation_id_prefix: 'report_script_php',
      display_name: 'Report (Forwarder script+php)',
      module: 'connectors',
      action: '',
      kind: 'script',
      config: {
        interpreter: 'php',
        script_path: 'www/scripts/mvp/app/Forwarder/run_report.php',
        timeout_sec: 180,
        args: [
          '--from={{from}}',
          '--to={{to}}',
          '--target_table={{target_table}}'
        ]
      }
    },
    flight_list_php: {
      operation_id_prefix: 'flight_list_php',
      display_name: 'Flight list (script+php upsert)',
      module: 'connectors',
      action: '',
      kind: 'script',
      config: {
        interpreter: 'php',
        script_path: 'www/scripts/mvp/app/Forwarder/run_flight_list.php',
        timeout_sec: 180,
        target_table: 'connector_dev_colibri_operation_flight_list',
        args: [
          '--base-url={{base_url}}',
          '--login={{auth_username}}',
          '--password={{auth_password}}',
          '--page-path=/collector/flights',
          '--connector-id={{connector_id}}',
          '--target-table={{target_table}}',
          '--write-mode=upsert'
        ]
      }
    },
    flights_list_fetch: {
      operation_id_prefix: 'flights_list_fetch',
      display_name: 'Получить список рейсов',
      module: 'warehouse',
      action: 'warehouse_sync_reports',
      kind: 'api_call',
      config: {
        source: 'flights',
        mode: 'list_fetch',
        limit: 100,
        filters: {
          status: ['planned', 'active']
        }
      }
    },
    flight_upsert: {
      operation_id_prefix: 'flight_upsert',
      display_name: 'Создать/обновить рейс',
      module: 'warehouse',
      action: 'warehouse_sync_item',
      kind: 'api_call',
      config: {
        source: 'flights',
        mode: 'upsert',
        unique_key: 'flight_number',
        payload_map: {
          flight_number: '{ldelim}{ldelim}flight_number{rdelim}{rdelim}',
          departure_date: '{ldelim}{ldelim}departure_date{rdelim}{rdelim}'
        }
      }
    },
    flight_containers_create: {
      operation_id_prefix: 'flight_containers_create',
      display_name: 'Создать контейнеры рейса',
      module: 'warehouse',
      action: 'warehouse_sync_batch_enqueue',
      kind: 'api_call',
      config: {
        source: 'flights',
        mode: 'containers_create',
        container_type: 'BOX',
        quantity: 1
      }
    }
  };


  function mapBackendTemplateToUi(templatePayload) {
    var op = templatePayload && typeof templatePayload.operation === 'object' ? templatePayload.operation : null;
    if (!op) return null;
    return {
      operation_id_prefix: op.operation_id || 'op',
      display_name: op.display_name || op.operation_id || 'Новая операция',
      module: op.module || 'generic',
      action: op.action || '',
      kind: op.kind || 'browser_steps',
      config: op.config && typeof op.config === 'object' ? op.config : {}
    };
  }

  function mergeOperationTemplates(baseTemplates, templatesFromBackend) {
    var merged = Object.assign({}, baseTemplates || {});
    if (!templatesFromBackend || typeof templatesFromBackend !== 'object') {
      return merged;
    }
    Object.keys(templatesFromBackend).forEach(function(key) {
      var mapped = mapBackendTemplateToUi(templatesFromBackend[key]);
      if (!mapped) return;
      merged[key] = mapped;
    });
    return merged;
  }

  operationTemplates = mergeOperationTemplates(operationTemplates, backendOperationTemplates);

  function buildTemplateLabel(templateKey, template) {
    var opIdPrefix = String(template && template.operation_id_prefix || '').trim();
    var title = String(template && template.display_name || '').trim();
    if (title) return title;
    if (opIdPrefix) return opIdPrefix;
    return templateKey;
  }

  function renderTemplateOptions() {
    var templateSelect = root ? root.querySelector('#operation-template-select') : document.getElementById('operation-template-select');
    if (!templateSelect) return;
    var keys = Object.keys(operationTemplates || {});
    keys.sort();
    var html = '<option value="">Выберите шаблон…</option>';
    keys.forEach(function(templateKey) {
      var template = operationTemplates[templateKey];
      if (!nodeEnabled && templateRequiresNode(template)) return;
      html += '<option value="' + esc(templateKey) + '">' + esc(buildTemplateLabel(templateKey, template)) + '</option>';
    });
    templateSelect.innerHTML = html;
  }


  function operationRequiresNode(op) {
    if (!op || typeof op !== 'object') return false;
    var kind = String(op.kind || '').toLowerCase().trim();
    if (kind === 'browser_steps') return true;
    if (kind !== 'script') return false;
    var config = op.config && typeof op.config === 'object' ? op.config : {};
    var interpreter = String(config.interpreter || '').toLowerCase().trim();
    if (!interpreter) {
      var scriptPath = String(config.script_path || '').toLowerCase().trim();
      if (/\.js$/i.test(scriptPath)) interpreter = 'node';
    }
    return interpreter === 'node';
  }

  function templateRequiresNode(template) {
    if (!template || typeof template !== 'object') return false;
    return operationRequiresNode({
      kind: template.kind || '',
      config: template.config && typeof template.config === 'object' ? template.config : {}
    });
  }
  var actionRegistry = { generic: [] };

  function statusMeta(status) {
    var normalized = String(status || '').toLowerCase();
    if (normalized === 'ok' || normalized === 'success') {
      return { cls: 'success', title: 'OK' };
    }
    if (normalized === 'error' || normalized === 'failed' || normalized === 'fail') {
      return { cls: 'danger', title: 'ERR' };
    }
    return { cls: 'secondary', title: 'N/A' };
  }
  function nextOperationId() {
    var maxN = 0;
    payload.operations.forEach(function(op) {
      var m = String(op && op.operation_id || '').match(/^op_(\d+)$/);
      if (m) maxN = Math.max(maxN, parseInt(m[1], 10) || 0);
    });
    return 'op_' + (maxN + 1);
  }

  function nextTemplateOperationId(prefix) {
    var normalizedPrefix = String(prefix || 'op').trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
    if (!normalizedPrefix) normalizedPrefix = 'op';
    var occupied = {};
    payload.operations.forEach(function(op) {
      var id = String(op && op.operation_id || '').trim();
      if (id) occupied[id] = true;
    });
    if (!occupied[normalizedPrefix]) return normalizedPrefix;
    var n = 2;
    while (occupied[normalizedPrefix + '_' + n]) n += 1;
    return normalizedPrefix + '_' + n;
  }


  function findOperationIndexById(operationId) {
    var opId = String(operationId || '').trim();
    if (!opId || !Array.isArray(payload.operations)) return -1;
    for (var i = 0; i < payload.operations.length; i += 1) {
      if (String(payload.operations[i] && payload.operations[i].operation_id || '').trim() === opId) {
        return i;
      }
    }
    return -1;
  }


  function parseJsonSafe(s, fallback) {
    if (!String(s || '').trim()) return fallback;
    try { return JSON.parse(s); } catch (e) { return fallback; }
  }

  function parseJsonStrict(raw, fallback, errorMessage, errors) {
    if (!String(raw || '').trim()) return fallback;
    try {
      return JSON.parse(raw);
    } catch (e) {
      errors.push(errorMessage + ' (' + (e && e.message ? e.message : 'ошибка парсинга') + ')');
      return fallback;
    }
  }

  function collectPayloadFromUi() {
    var errors = [];
    var ids = {};
    var rows = content.querySelectorAll('.js-operation-card');
    var operations = [];

    rows.forEach(function(row, idx) {

      var operationLabel = 'Операция #' + (idx + 1);
      var opIdRaw = row.querySelector('.js-op-id').value.trim();
      var op = {
        operation_id: opIdRaw,
        display_name: row.querySelector('.js-op-display-name').value.trim(),
        module: row.querySelector('.js-op-module').value.trim().toLowerCase(),
        kind: row.querySelector('.js-op-kind').value.trim().toLowerCase(),
        action: row.querySelector('.js-op-action').value.trim(),
        enabled: row.querySelector('.js-op-enabled').checked ? 1 : 0,
        entrypoint: row.querySelector('.js-op-entrypoint').checked ? 1 : 0,
        on_dependency_fail: row.querySelector('.js-op-on-dependency-fail').value,

        run_after: parseJsonStrict(row.querySelector('.js-op-run-after').value, [], operationLabel + ': некорректный JSON в run_after', errors),
        run_with: parseJsonStrict(row.querySelector('.js-op-run-with').value, [], operationLabel + ': некорректный JSON в run_with', errors),
        run_finally: parseJsonStrict(row.querySelector('.js-op-run-finally').value, [], operationLabel + ': некорректный JSON в run_finally', errors),
        config: parseJsonStrict(row.querySelector('.js-op-config').value, {}, operationLabel + ': некорректный JSON в config', errors)
      };

      if (!op.operation_id) errors.push('Операция #' + (idx + 1) + ': operation_id обязателен');
      if (!op.display_name) errors.push('Операция ' + (op.operation_id || '#' + (idx + 1)) + ': display_name обязателен');
      if (ids[op.operation_id]) errors.push('operation_id должен быть уникальным: ' + op.operation_id);
      ids[op.operation_id] = 1;

      if (!op.module) {
        op.module = 'generic';
        op.kind = 'browser_steps';
      } else if (!op.kind && op.module === 'generic') {
        op.kind = 'browser_steps';
      }
      if (op.module !== 'generic' && op.kind === 'api_call' && !op.action) {
        errors.push('Операция ' + op.operation_id + ': action обязателен для module != generic + kind=api_call');
      }

      ['run_after','run_with','run_finally'].forEach(function(key){
        if (!Array.isArray(op[key])) {
          errors.push('Операция ' + op.operation_id + ': ' + key + ' должен быть JSON-массивом');
          op[key] = [];
          return;
        }
        op[key] = op[key].map(function(v){ return String(v || '').trim(); }).filter(Boolean);
      });

      if (Object.prototype.toString.call(op.config) !== '[object Object]') {
        if (Array.isArray(op.config) && op.config.length === 0) {
          op.config = {};
        } else {
          errors.push('Операция ' + op.operation_id + ': config должен быть JSON-объектом');
          op.config = {};
        }
      }

      operations.push(op);
    });

    var allIds = operations.map(function(x){ return x.operation_id; });
    operations.forEach(function(op){
      ['run_after','run_with','run_finally'].forEach(function(key){
        op[key].forEach(function(dep){
          if (allIds.indexOf(dep) === -1) {
            errors.push('Операция ' + op.operation_id + ': ссылка ' + key + ' содержит несуществующий operation_id: ' + dep);
          }
        });
      });
    });

    if (errors.length) {
      alert(errors.join('\n'));
      return null;
    }
    return { schema_version: 3, operations: operations };
  }


  function updateTextareaFromUi() {
    var data = collectPayloadFromUi();
    if (!data) return false;
    payload = data;
    textarea.value = JSON.stringify(payload, null, 2);
    return true;
  }


  function removeOperationAt(indexToRemove) {
    var latest = collectPayloadFromUi();
    if (latest && Array.isArray(latest.operations)) {
      payload = latest;
    } else {
      var fromTextarea = parseJsonSafe(textarea.value, payload);
      payload = normalizePayload(fromTextarea);
    }

    if (!Array.isArray(payload.operations)) payload.operations = [];
    if (indexToRemove < 0 || indexToRemove >= payload.operations.length) return;

    var removedOperation = payload.operations[indexToRemove] || {};
    var removedOperationId = String(removedOperation.operation_id || '').trim();
    payload.operations.splice(indexToRemove, 1);

    if (removedOperationId) {
      payload.operations.forEach(function(op) {
        ['run_after', 'run_with', 'run_finally'].forEach(function(key) {
          if (!Array.isArray(op[key])) {
            op[key] = [];
            return;
          }
          op[key] = op[key].filter(function(depId) {
            return String(depId || '').trim() !== removedOperationId;
          });
        });
      });
    }

    textarea.value = JSON.stringify(payload, null, 2);
    render();
  }




  function buildModuleOptions(currentModule) {
    var modules = Object.keys(actionRegistry || {});
    if (modules.indexOf('generic') === -1) modules.unshift('generic');
    if (currentModule && modules.indexOf(currentModule) === -1) modules.push(currentModule);
    modules = modules.filter(Boolean).sort();
    if (modules.indexOf('generic') !== -1) {
      modules.splice(modules.indexOf('generic'), 1);
      modules.unshift('generic');
    }
    return modules;
  }

  function refillActionSelect(actionSelect, module, currentAction) {
    if (!actionSelect) return;
    var actions = toArray((actionRegistry && actionRegistry[module]) || []);
    if (currentAction && actions.indexOf(currentAction) === -1) actions.unshift(currentAction);

    var html = '<option value=""></option>';
    actions.forEach(function(actionValue) {
      html += '<option value="' + esc(actionValue) + '">' + esc(actionValue) + '</option>';
    });
    actionSelect.innerHTML = html;
    actionSelect.value = currentAction || '';
  }

  function initOperationCardControls(card, op) {
    if (!card) return;
    var moduleSelect = card.querySelector('.js-op-module');
    var actionSelect = card.querySelector('.js-op-action');
    if (!moduleSelect || !actionSelect) return;

    var moduleValue = (op.module || 'generic').toLowerCase();
    var actionValue = op.action || '';
    var moduleOptions = buildModuleOptions(moduleValue);
    moduleSelect.innerHTML = moduleOptions.map(function(moduleName) {
      return '<option value="' + esc(moduleName) + '">' + esc(moduleName) + '</option>';
    }).join('');
    moduleSelect.value = moduleValue;
    if (!moduleSelect.value) moduleSelect.value = 'generic';

    refillActionSelect(actionSelect, moduleSelect.value || 'generic', actionValue);

    moduleSelect.addEventListener('change', function() {
      var selectedModule = (moduleSelect.value || 'generic').toLowerCase();
      refillActionSelect(actionSelect, selectedModule, '');
      updateTextareaFromUi();
    });

    actionSelect.addEventListener('change', updateTextareaFromUi);
  }

  function loadActionRegistry() {
    if (typeof fetch !== 'function') {
      actionRegistry = actionRegistry || {};
      if (!actionRegistry.generic || !Array.isArray(actionRegistry.generic)) {
        actionRegistry.generic = [];
      }
      return Promise.resolve();
    }

    return fetch('/core_api.php?action=get_module_actions_registry', {
      credentials: 'same-origin'
    })
      .then(function(resp) { return resp.json(); })
      .then(function(data) {
        if (!data || data.status !== 'ok' || !data.registry || typeof data.registry !== 'object') {
          throw new Error('registry payload invalid');
        }
        actionRegistry = data.registry;
        if (!actionRegistry.generic || !Array.isArray(actionRegistry.generic)) {
          actionRegistry.generic = [];
        }
      })
      .catch(function() {
        actionRegistry = actionRegistry || {};
        if (!actionRegistry.generic) actionRegistry.generic = [];
      });
  }

  function render() {
    tabs.innerHTML = '';
    content.innerHTML = '';



    if (summary) {
      var ops = Array.isArray(payload.operations) ? payload.operations : [];
      if (ops.length > 0) {
        var parts = ops.map(function(op, idx) {
          var opId = String(op && op.operation_id || '').trim() || ('#' + (idx + 1));
          var opName = String(op && op.display_name || '').trim();
          return opName ? (opId + ' — ' + opName) : opId;
        });
        summary.classList.remove('d-none');
        summary.innerHTML = '<strong>Найдено операций:</strong> ' + ops.length + '<br><span class="text-muted">' + esc(parts.join(' | ')) + '</span>';
      } else {
        summary.classList.remove('d-none');
        summary.innerHTML = '<strong>Найдено операций:</strong> 0<br><span class="text-muted">Пустой payload. Можно добавить операцию кнопкой + или через шаблон.</span>';
      }
    }


    payload.operations.forEach(function(op, idx) {
      var opId = String(op && op.operation_id || '').trim();
      var opIdLower = opId.toLowerCase();
      if (/_php$/i.test(opId)) {
        var baseId = opId.replace(/_php$/i, '');
        if (findOperationIndexById(baseId) !== -1) {
          return;
        }
      }
      var tabId = 'dyn-op-tab-' + idx;
      var paneId = 'dyn-op-pane-' + idx;
      var active = idx === 0 ? 'active' : '';
      var show = idx === 0 ? 'show active' : '';

      var li = document.createElement('li');
      li.className = 'nav-item';
      li.setAttribute('role', 'presentation');
      var opId = String(op.operation_id || '').trim();
      var statusData = opId && operationLastStatus && operationLastStatus[opId] ? operationLastStatus[opId] : null;
      var badge = statusMeta(statusData && typeof statusData === 'object' ? (statusData.status || '') : '');
      var statusTitle = statusData && typeof statusData === 'object'
        ? String(statusData.finished_at || statusData.message || statusData.status || '').trim()
        : 'Статус теста отсутствует';
      var statusBadge = ' <span class="badge text-bg-' + badge.cls + ' ms-1" data-op-status-for="' + esc(opId) + '" title="' + esc(statusTitle) + '">' + badge.title + '</span>';
      li.innerHTML = '<button class="nav-link ' + active + '" id="' + tabId + '" data-bs-toggle="tab" data-bs-target="#' + paneId + '" type="button" role="tab" aria-controls="' + paneId + '" aria-selected="' + (idx === 0 ? 'true' : 'false') + '">' + esc(op.display_name || op.operation_id || ('Операция #' + (idx + 1))) + statusBadge + '</button>';
      tabs.appendChild(li);

      var pane = document.createElement('div');
      pane.className = 'tab-pane fade ' + show;
      pane.id = paneId;
      pane.setAttribute('role', 'tabpanel');
      pane.setAttribute('aria-labelledby', tabId);
      var nodeWarningHtml = '';
      if (!nodeEnabled && operationRequiresNode(op)) {
        nodeWarningHtml = '<div class="alert alert-warning py-2 small">Для этой операции нужен Node (kind=' + esc(op.kind || '') + '). Node сейчас отключен: переключите на PHP-вариант и выставьте entrypoint.</div>';
      }

      pane.innerHTML = '\
        <div class="js-operation-card border rounded p-3 mt-2">\
          <div class="d-flex justify-content-between align-items-center mb-3">\
            <h6 class="mb-0">Основные</h6>\
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-operation" data-op-index="' + idx + '">Удалить операцию</button>\
          </div>\
          ' + nodeWarningHtml + '\
          <div class="row mb-3">\
            <div class="col-md-6">\
              <label class="form-label">display_name</label>\
              <input type="text" class="form-control js-op-display-name" value="' + esc(op.display_name || '') + '">\
            </div>\
            <div class="col-md-6">\
              <label class="form-label">operation_id</label>\
              <input type="text" class="form-control js-op-id" value="' + esc(op.operation_id || '') + '">\
            </div>\
          </div>\
          <div class="row mb-4">\
            <div class="col-md-4 form-check mt-4"><input class="form-check-input js-op-enabled" type="checkbox" ' + (op.enabled ? 'checked' : '') + '> <label class="form-check-label">enabled</label></div>\
            <div class="col-md-4 form-check mt-4"><input class="form-check-input js-op-entrypoint" type="checkbox" ' + (op.entrypoint ? 'checked' : '') + '> <label class="form-check-label">entrypoint</label></div>\
            <div class="col-md-4"><label class="form-label">on_dependency_fail</label><select class="form-select js-op-on-dependency-fail"><option value="stop" ' + (op.on_dependency_fail === 'stop' ? 'selected' : '') + '>stop</option><option value="skip" ' + (op.on_dependency_fail === 'skip' ? 'selected' : '') + '>skip</option><option value="continue" ' + (op.on_dependency_fail === 'continue' ? 'selected' : '') + '>continue</option></select></div>\
          </div>\
          <h6 class="mb-3">Связь с системой</h6>\
          <div class="row mb-4">\
            <div class="col-md-4"><label class="form-label">module</label><select class="form-select js-op-module"></select></div>\
            <div class="col-md-4"><label class="form-label">kind</label><input type="text" class="form-control js-op-kind" value="' + esc(op.kind || 'browser_steps') + '"></div>\
            <div class="col-md-4"><label class="form-label">action</label><select class="form-select js-op-action"></select><div class="form-text">Для module=generic поле action можно оставить пустым.</div></div>\
          </div>\
          <h6 class="mb-3">Зависимости (JSON-массив operation_id)</h6>\
          <div class="row mb-4">\
            <div class="col-md-4"><label class="form-label">run_after</label><textarea class="form-control js-op-run-after" rows="3">' + esc(JSON.stringify(toArray(op.run_after), null, 2)) + '</textarea></div>\
            <div class="col-md-4"><label class="form-label">run_with</label><textarea class="form-control js-op-run-with" rows="3">' + esc(JSON.stringify(toArray(op.run_with), null, 2)) + '</textarea></div>\
            <div class="col-md-4"><label class="form-label">run_finally</label><textarea class="form-control js-op-run-finally" rows="3">' + esc(JSON.stringify(toArray(op.run_finally), null, 2)) + '</textarea></div>\
          </div>\
          <h6 class="mb-3">Параметры</h6>\
          <div class="row">\
            <div class="col-12"><label class="form-label">config (JSON object)</label><textarea class="form-control js-op-config" rows="8">' + esc(JSON.stringify(op.config && typeof op.config === 'object' ? op.config : {}, null, 2)) + '</textarea></div>\
          </div>\
          <div class="d-flex gap-2 mt-3">\
            <button type="button" class="btn btn-outline-primary js-core-link" data-core-action="test_connector_operations" data-test-operation="' + esc(op.operation_id || '') + '">Проверить операцию</button>\
          </div>\
          <div class="alert alert-light border mt-3 py-2 small d-none" data-op-report-for="' + esc(op.operation_id || '') + '"></div>\
        </div>';

      var pairedPhpId = opId + '_php';
      var pairedPhpIdx = (!/_php$/i.test(opId) && opId) ? findOperationIndexById(pairedPhpId) : -1;
      if (pairedPhpIdx !== -1) {
          var phpOp = payload.operations[pairedPhpIdx] || {};
          pane.innerHTML += '\
        <div class="js-operation-card border rounded p-3 mt-3 bg-light-subtle">\
          <div class="d-flex justify-content-between align-items-center mb-3">\
            <h6 class="mb-0">PHP-вариант ' + esc(opId || 'operation') + ' (' + esc(phpOp.operation_id || pairedPhpId) + ')</h6>\
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-operation" data-op-index="' + pairedPhpIdx + '">Удалить PHP-операцию</button>\
          </div>\
          <div class="row mb-3">\
            <div class="col-md-6">\
              <label class="form-label">display_name</label>\
              <input type="text" class="form-control js-op-display-name" value="' + esc(phpOp.display_name || '') + '">\
            </div>\
            <div class="col-md-6">\
              <label class="form-label">operation_id</label>\
              <input type="text" class="form-control js-op-id" value="' + esc(phpOp.operation_id || pairedPhpId) + '">\
            </div>\
          </div>\
          <div class="row mb-4">\
            <div class="col-md-4 form-check mt-4"><input class="form-check-input js-op-enabled" type="checkbox" ' + (phpOp.enabled ? 'checked' : '') + '> <label class="form-check-label">enabled</label></div>\
            <div class="col-md-4 form-check mt-4"><input class="form-check-input js-op-entrypoint" type="checkbox" ' + (phpOp.entrypoint ? 'checked' : '') + '> <label class="form-check-label">entrypoint</label></div>\
            <div class="col-md-4"><label class="form-label">on_dependency_fail</label><select class="form-select js-op-on-dependency-fail"><option value="stop" ' + (phpOp.on_dependency_fail === 'stop' ? 'selected' : '') + '>stop</option><option value="skip" ' + (phpOp.on_dependency_fail === 'skip' ? 'selected' : '') + '>skip</option><option value="continue" ' + (phpOp.on_dependency_fail === 'continue' ? 'selected' : '') + '>continue</option></select></div>\
          </div>\
          <h6 class="mb-3">Связь с системой</h6>\
          <div class="row mb-4">\
            <div class="col-md-4"><label class="form-label">module</label><select class="form-select js-op-module"></select></div>\
            <div class="col-md-4"><label class="form-label">kind</label><input type="text" class="form-control js-op-kind" value="' + esc(phpOp.kind || 'script') + '"></div>\
            <div class="col-md-4"><label class="form-label">action</label><select class="form-select js-op-action"></select><div class="form-text">Для module=generic поле action можно оставить пустым.</div></div>\
          </div>\
          <h6 class="mb-3">Зависимости (JSON-массив operation_id)</h6>\
          <div class="row mb-4">\
            <div class="col-md-4"><label class="form-label">run_after</label><textarea class="form-control js-op-run-after" rows="3">' + esc(JSON.stringify(toArray(phpOp.run_after), null, 2)) + '</textarea></div>\
            <div class="col-md-4"><label class="form-label">run_with</label><textarea class="form-control js-op-run-with" rows="3">' + esc(JSON.stringify(toArray(phpOp.run_with), null, 2)) + '</textarea></div>\
            <div class="col-md-4"><label class="form-label">run_finally</label><textarea class="form-control js-op-run-finally" rows="3">' + esc(JSON.stringify(toArray(phpOp.run_finally), null, 2)) + '</textarea></div>\
          </div>\
          <h6 class="mb-3">Параметры</h6>\
          <div class="row">\
            <div class="col-12"><label class="form-label">config (JSON object)</label><textarea class="form-control js-op-config" rows="8">' + esc(JSON.stringify(phpOp.config && typeof phpOp.config === 'object' ? phpOp.config : {}, null, 2)) + '</textarea></div>\
          </div>\
          <div class="d-flex gap-2 mt-3">\
            <button type="button" class="btn btn-outline-secondary js-core-link" data-core-action="test_connector_operations" data-test-operation="' + esc(phpOp.operation_id || pairedPhpId) + '" data-entrypoint-mode="entrypoint_php">Проверить операцию PHP</button>\
          </div>\
          <div class="alert alert-light border mt-3 py-2 small d-none" data-op-report-for="' + esc(phpOp.operation_id || pairedPhpId) + '"></div>\
        </div>';
      }
      content.appendChild(pane);
      pane.querySelectorAll('.js-operation-card').forEach(function(cardEl) {
        var cardOpId = (cardEl.querySelector('.js-op-id') && cardEl.querySelector('.js-op-id').value) || '';
        var cardOp = payload.operations.find(function(candidate) {
          return String(candidate && candidate.operation_id || '').trim() === String(cardOpId || '').trim();
        }) || op;
        initOperationCardControls(cardEl, cardOp);
      });
    });

    var plusLi = document.createElement('li');
    plusLi.className = 'nav-item';
    plusLi.setAttribute('role', 'presentation');
    plusLi.innerHTML = '<button class="nav-link" type="button" id="add-operation-tab">+ Добавить операцию</button>';
    tabs.appendChild(plusLi);

    var addBtn = tabs.querySelector('#add-operation-tab');
    if (addBtn) {
      addBtn.addEventListener('click', function() {
        if (!updateTextareaFromUi()) return;
        payload.operations.push({
          operation_id: nextOperationId(),
          display_name: 'Новая операция',
          module: 'generic',
          action: '',
          kind: 'browser_steps',
          enabled: 0,
          entrypoint: 0,
          on_dependency_fail: 'stop',
          run_after: [],
          run_with: [],
          run_finally: [],
          config: {}
        });
        render();
      });
    }

    var createFromTemplateBtn = root ? root.querySelector('#create-from-template-btn') : document.getElementById('create-from-template-btn');
    var templateSelect = root ? root.querySelector('#operation-template-select') : document.getElementById('operation-template-select');
    if (createFromTemplateBtn && templateSelect) {
      createFromTemplateBtn.addEventListener('click', function() {
        if (!updateTextareaFromUi()) return;
        var templateKey = String(templateSelect.value || '').trim();
        var template = operationTemplates[templateKey];
        if (!template) {
          alert('Выберите шаблон операции.');
          return;
        }
        payload.operations.push({
          operation_id: nextTemplateOperationId(template.operation_id_prefix),
          display_name: template.display_name,
          module: template.module,
          action: template.action,
          kind: template.kind,
          enabled: 0,
          entrypoint: 0,
          on_dependency_fail: 'stop',
          run_after: [],
          run_with: [],
          run_finally: [],
          config: template.config && typeof template.config === 'object' ? JSON.parse(JSON.stringify(template.config)) : {}
        });
        templateSelect.value = '';
        render();
      });
    }

    content.querySelectorAll('input,textarea,select').forEach(function(el){
      el.addEventListener('change', updateTextareaFromUi);
      el.addEventListener('blur', updateTextareaFromUi);
    });

    content.querySelectorAll('.js-remove-operation').forEach(function(btn){
      btn.addEventListener('click', function() {
        var idx = parseInt(btn.getAttribute('data-op-index') || '-1', 10);
        if (isNaN(idx) || idx < 0) return;
        var row = content.querySelectorAll('.js-operation-card')[idx];
        var opIdEl = row ? row.querySelector('.js-op-id') : null;
        var opLabel = opIdEl && opIdEl.value ? opIdEl.value : ('#' + (idx + 1));
        if (!confirm('Удалить операцию ' + opLabel + '?')) return;
        removeOperationAt(idx);
      });
    });


    updateTextareaFromUi();
  }
  var saveButtons = root
    ? root.querySelectorAll('.js-core-link[data-core-action="save_connector_operations"]')
    : document.querySelectorAll('.js-core-link[data-core-action="save_connector_operations"]');
  saveButtons.forEach(function(saveBtn) {
    saveBtn.addEventListener('click', function(e) {
      if (!updateTextareaFromUi()) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);

  });
  var testButtons = root
    ? root.querySelectorAll('.js-core-link[data-core-action="test_connector_operations"]')
    : document.querySelectorAll('.js-core-link[data-core-action="test_connector_operations"]');
  testButtons.forEach(function(testBtn) {
    testBtn.addEventListener('click', function(e) {
      if (!updateTextareaFromUi()) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  });
  renderTemplateOptions();
  render();
  loadActionRegistry().then(render, function(){});
})();

<?php echo '</script'; ?>
>
</section>
<?php }
}
