<?php
/* Smarty version 5.3.1, created on 2026-03-16 19:49:32
  from 'file:cells_NA_API_connector_operations_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69b85ecc78bae6_31760934',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4e0cfb81384357625659d5eca445a63482fe7323' => 
    array (
      0 => 'cells_NA_API_connector_operations_modal.html',
      1 => 1773690566,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69b85ecc78bae6_31760934 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><section class="section">
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


          <form id="connector-operations-form" autocomplete="off">
            <input type="hidden" name="connector_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">
            <textarea class="d-none" id="operations_v3_json" name="operations_v3_json"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('operations_v3_json') ?? null)===null||$tmp==='' ? '{"schema_version":3,"operations":[]}' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</textarea>

            <ul class="nav nav-tabs mb-3" id="connector-operations-tabs" role="tablist"></ul>

            <div class="tab-content" id="connector-operations-tab-content"></div>

            <div class="d-flex gap-2 mb-3">

              <div class="input-group" style="max-width: 420px;">
                <label class="input-group-text" for="operation-template-select">Создать из шаблона</label>
                <select class="form-select" id="operation-template-select">
                  <option value="">Выберите шаблон…</option>
                  <option value="flights_list_fetch">Получить список рейсов</option>
                  <option value="flight_upsert">Создать/обновить рейс</option>
                  <option value="flight_containers_create">Создать контейнеры рейса</option>
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
              <label for="addon_node_mapping_json" class="col-md-4 col-lg-3 col-form-label">Node mapping</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control" id="addon_node_mapping_json" name="addon_node_mapping_json" rows="6"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('addons')['node_mapping_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
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
  var textarea = document.getElementById('operations_v3_json');
  var tabs = document.getElementById('connector-operations-tabs');
  var content = document.getElementById('connector-operations-tab-content');
  if (!textarea || !tabs || !content) return;

  function toArray(v) { return Array.isArray(v) ? v : []; }
  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  var payload;
  try {
    payload = JSON.parse(textarea.value || '{"schema_version":3,"operations":[]}');
  } catch (e) {
    payload = { schema_version: 3, operations: [] };
  }
  if (!payload || typeof payload !== 'object') payload = { schema_version: 3, operations: [] };
  if (!Array.isArray(payload.operations)) payload.operations = [];


  var operationTemplates = {
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

  var actionRegistry = { generic: [] };

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

  function parseJsonSafe(s, fallback) {
    if (!String(s || '').trim()) return fallback;
    try { return JSON.parse(s); } catch (e) { return fallback; }
  }

  function collectPayloadFromUi() {
    var errors = [];
    var ids = {};
    var rows = content.querySelectorAll('.js-operation-card');
    var operations = [];

    rows.forEach(function(row, idx) {
      var op = {
        operation_id: row.querySelector('.js-op-id').value.trim(),
        display_name: row.querySelector('.js-op-display-name').value.trim(),
        module: row.querySelector('.js-op-module').value.trim().toLowerCase(),
        kind: row.querySelector('.js-op-kind').value.trim().toLowerCase(),
        action: row.querySelector('.js-op-action').value.trim(),
        enabled: row.querySelector('.js-op-enabled').checked ? 1 : 0,
        entrypoint: row.querySelector('.js-op-entrypoint').checked ? 1 : 0,
        on_dependency_fail: row.querySelector('.js-op-on-dependency-fail').value,
        run_after: parseJsonSafe(row.querySelector('.js-op-run-after').value, []),
        run_with: parseJsonSafe(row.querySelector('.js-op-run-with').value, []),
        run_finally: parseJsonSafe(row.querySelector('.js-op-run-finally').value, []),
        config: parseJsonSafe(row.querySelector('.js-op-config').value, {})
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
        errors.push('Операция ' + op.operation_id + ': config должен быть JSON-объектом');
        op.config = {};
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

    payload.operations.forEach(function(op, idx) {
      var tabId = 'dyn-op-tab-' + idx;
      var paneId = 'dyn-op-pane-' + idx;
      var active = idx === 0 ? 'active' : '';
      var show = idx === 0 ? 'show active' : '';

      var li = document.createElement('li');
      li.className = 'nav-item';
      li.setAttribute('role', 'presentation');
      li.innerHTML = '<button class="nav-link ' + active + '" id="' + tabId + '" data-bs-toggle="tab" data-bs-target="#' + paneId + '" type="button" role="tab" aria-controls="' + paneId + '" aria-selected="' + (idx === 0 ? 'true' : 'false') + '">' + esc(op.display_name || op.operation_id || ('Операция #' + (idx + 1))) + '</button>';
      tabs.appendChild(li);

      var pane = document.createElement('div');
      pane.className = 'tab-pane fade ' + show;
      pane.id = paneId;
      pane.setAttribute('role', 'tabpanel');
      pane.setAttribute('aria-labelledby', tabId);
      pane.innerHTML = '\
        <div class="js-operation-card border rounded p-3 mt-2">\
          <h6 class="mb-3">Основные</h6>\
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
        </div>';
      content.appendChild(pane);
      initOperationCardControls(pane.querySelector('.js-operation-card'), op);
    });

    var plusLi = document.createElement('li');
    plusLi.className = 'nav-item';
    plusLi.setAttribute('role', 'presentation');
    plusLi.innerHTML = '<button class="nav-link" type="button" id="add-operation-tab">+ Добавить операцию</button>';
    tabs.appendChild(plusLi);

    var addBtn = document.getElementById('add-operation-tab');
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

    var createFromTemplateBtn = document.getElementById('create-from-template-btn');
    var templateSelect = document.getElementById('operation-template-select');
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

    updateTextareaFromUi();
  }
  var saveBtn = document.querySelector('.js-core-link[data-core-action="save_connector_operations"]');
  if (saveBtn) {
    saveBtn.addEventListener('click', function(e) {
      if (!updateTextareaFromUi()) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  }
  loadActionRegistry().finally(render);
})();

<?php echo '</script'; ?>
>
</section>
<?php }
}
