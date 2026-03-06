<?php
/* Smarty version 5.3.1, created on 2026-03-06 17:05:27
  from 'file:cells_NA_API_system_tasks.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69ab09576c5ff1_98557153',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7ee614622c6abfb7a3fa0b52b9d386478b25d76c' => 
    array (
      0 => 'cells_NA_API_system_tasks.html',
      1 => 1772815823,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69ab09576c5ff1_98557153 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Задания системы</h4>
    <button type="button" class="btn btn-sm btn-outline-primary js-core-link" data-core-action="run_system_tasks_now">Выполнить due сейчас</button>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-4">
      <div class="card">
        <div class="card-header">Добавить / редактировать</div>
        <div class="card-body">
          <form id="system-task-form" autocomplete="off">
            <input type="hidden" name="task_id" id="system_task_id" value="">
            <div class="mb-2">
              <label class="form-label" for="system_task_code">Code</label>
              <input type="text" class="form-control form-control-sm" name="code" id="system_task_code" placeholder="warehouse_sync_batch_worker">
            </div>
            <div class="mb-2">
              <label class="form-label" for="system_task_name">Название</label>
              <input type="text" class="form-control form-control-sm" name="name" id="system_task_name" placeholder="Обработчик синхронизации">
            </div>
            <div class="mb-2">
              <label class="form-label" for="system_task_endpoint">Endpoint action</label>
              <input type="text" class="form-control form-control-sm" name="endpoint_action" id="system_task_endpoint" placeholder="warehouse_sync_batch_worker">
            </div>
            <div class="mb-2">
              <label class="form-label" for="system_task_interval">Интервал (мин.)</label>
              <input type="number" min="1" class="form-control form-control-sm" name="interval_minutes" id="system_task_interval" value="60">
            </div>
            <div class="mb-2">
              <label class="form-label" for="system_task_description">Описание</label>
              <textarea class="form-control form-control-sm" name="description" id="system_task_description" rows="3"></textarea>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_enabled" id="system_task_enabled" checked>
              <label class="form-check-label" for="system_task_enabled">Разрешено к выполнению</label>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-primary js-core-link" data-core-action="save_system_task">Сохранить</button>
              <button type="button" class="btn btn-sm btn-outline-secondary js-system-task-reset">Очистить</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-8">
      <div class="card mb-3">
        <div class="card-header">Список заданий</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
              <th>ID</th>
              <th>Code</th>
              <th>Endpoint</th>
              <th>Every</th>
              <th>Enabled</th>
              <th>Last</th>
              <th>Next</th>
              <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('tasks')) > 0) {?>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('tasks'), 'task');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('task')->value) {
$foreach0DoElse = false;
?>
                <tr>
                  <td><?php echo $_smarty_tpl->getValue('task')['id'];?>
</td>
                  <td><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['code'], ENT_QUOTES, 'UTF-8', true);?>
</code><br><small><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['name'], ENT_QUOTES, 'UTF-8', true);?>
</small></td>
                  <td><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['endpoint_action'], ENT_QUOTES, 'UTF-8', true);?>
</code></td>
                  <td><?php echo $_smarty_tpl->getValue('task')['interval_minutes'];?>
m</td>
                  <td><?php if ($_smarty_tpl->getValue('task')['is_enabled'] == 1) {?><span class="badge bg-success">yes</span><?php } else { ?><span class="badge bg-secondary">no</span><?php }?></td>
                  <td><small><?php echo (($tmp = $_smarty_tpl->getValue('task')['last_run_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp);?>
</small><br><small><?php echo (($tmp = $_smarty_tpl->getValue('task')['last_status'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp);?>
</small></td>
                  <td><small><?php echo (($tmp = $_smarty_tpl->getValue('task')['next_run_at'] ?? null)===null||$tmp==='' ? '-' ?? null : $tmp);?>
</small></td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary js-system-task-edit"
                            data-task-id="<?php echo $_smarty_tpl->getValue('task')['id'];?>
"
                            data-task-code="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['code'], ENT_QUOTES, 'UTF-8', true);?>
"
                            data-task-name="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['name'], ENT_QUOTES, 'UTF-8', true);?>
"
                            data-task-description="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['description'], ENT_QUOTES, 'UTF-8', true);?>
"
                            data-task-endpoint="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['endpoint_action'], ENT_QUOTES, 'UTF-8', true);?>
"
                            data-task-interval="<?php echo $_smarty_tpl->getValue('task')['interval_minutes'];?>
"
                            data-task-enabled="<?php echo $_smarty_tpl->getValue('task')['is_enabled'];?>
">Edit</button>
                    <button type="button" class="btn btn-sm btn-outline-danger js-core-link"
                            data-core-action="delete_system_task"
                            data-task-id="<?php echo $_smarty_tpl->getValue('task')['id'];?>
"
                            data-confirm="Удалить задание <?php echo htmlspecialchars((string)$_smarty_tpl->getValue('task')['code'], ENT_QUOTES, 'UTF-8', true);?>
?">Del</button>
                  </td>
                </tr>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            <?php } else { ?>
              <tr><td colspan="8" class="text-center text-muted py-3">Заданий пока нет</td></tr>
            <?php }?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Последние запуски</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
              <th>ID</th>
              <th>Task</th>
              <th>Status</th>
              <th>Message</th>
              <th>At</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('task_runs')) > 0) {?>
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('task_runs'), 'run');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('run')->value) {
$foreach1DoElse = false;
?>
                <tr>
                  <td><?php echo $_smarty_tpl->getValue('run')['id'];?>
</td>
                  <td><code><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('run')['task_code'], ENT_QUOTES, 'UTF-8', true);?>
</code></td>
                  <td><?php if ($_smarty_tpl->getValue('run')['status'] == 'ok') {?><span class="badge bg-success">ok</span><?php } else { ?><span class="badge bg-danger">error</span><?php }?></td>
                  <td><small><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('run')['message'], ENT_QUOTES, 'UTF-8', true);?>
</small></td>
                  <td><small><?php echo (($tmp = $_smarty_tpl->getValue('run')['finished_at'] ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('run')['started_at'] ?? null : $tmp);?>
</small></td>
                </tr>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            <?php } else { ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Логов пока нет</td></tr>
            <?php }?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php }
}
