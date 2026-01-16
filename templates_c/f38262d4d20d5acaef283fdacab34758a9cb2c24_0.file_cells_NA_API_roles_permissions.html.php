<?php
/* Smarty version 5.3.1, created on 2026-01-16 21:07:45
  from 'file:cells_NA_API_roles_permissions.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696aa8a1cb0630_57058006',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f38262d4d20d5acaef283fdacab34758a9cb2c24' => 
    array (
      0 => 'cells_NA_API_roles_permissions.html',
      1 => 1768597565,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696aa8a1cb0630_57058006 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Settings roles & permissions</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Settings</li>
          <li class="breadcrumb-item active">Roles & permissions</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-xl-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Роли</h5>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th scope="col">Code</th>
                      <th scope="col">Name</th>
                      <th scope="col">Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('roles')) > 0) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('roles'), 'role');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('role')->value) {
$foreach0DoElse = false;
?>
                        <tr>
                          <td><span class="badge bg-secondary"><?php echo $_smarty_tpl->getValue('role')['code'];?>
</span></td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('role')['name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('role')['description'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="3" class="text-muted">Роли не найдены</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Добавить / обновить право</h5>
              <form id="permission-form" autocomplete="off">
                <input type="hidden" name="permission_id" id="permission_id" value="">
                <div class="mb-3">
                  <label for="permission_code" class="form-label">Code</label>
                  <input type="text" class="form-control" name="code" id="permission_code" placeholder="warehouse.in.view_all">
                </div>
                <div class="mb-3">
                  <label for="permission_name" class="form-label">Название</label>
                  <input type="text" class="form-control" name="name" id="permission_name" placeholder="Просмотр всех приходов">
                </div>
                <div class="mb-3">
                  <label for="permission_description" class="form-label">Описание</label>
                  <textarea class="form-control" name="description" id="permission_description" rows="3"></textarea>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_permission">Сохранить</button>
                  <button type="button" class="btn btn-outline-secondary js-permission-reset">Очистить</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Добавить / обновить пункт меню</h5>
              <form id="menu-item-form" autocomplete="off">
                <input type="hidden" name="menu_item_id" id="menu_item_id" value="">
                <div class="mb-3">
                  <label for="menu_item_key" class="form-label">Menu key</label>
                  <input type="text" class="form-control" name="menu_key" id="menu_item_key" placeholder="warehouse_item_in">
                </div>
                <div class="mb-3">
                  <label for="menu_item_group" class="form-label">Группа</label>
                  <select class="form-select" name="group_code" id="menu_item_group">
                    <option value="">Выберите группу</option>
                    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('menu_groups'), 'group');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('group')->value) {
$foreach1DoElse = false;
?>
                      <option value="<?php echo $_smarty_tpl->getValue('group')['code'];?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('group')['title'], ENT_QUOTES, 'UTF-8', true);?>
 (<?php echo $_smarty_tpl->getValue('group')['code'];?>
)</option>
                    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="menu_item_title" class="form-label">Название</label>
                  <input type="text" class="form-control" name="title" id="menu_item_title" placeholder="Приход на склад">
                </div>
                <div class="mb-3">
                  <label for="menu_item_icon" class="form-label">Иконка</label>
                  <input type="text" class="form-control" name="icon" id="menu_item_icon" placeholder="bi bi-box">
                </div>
                <div class="mb-3">
                  <label for="menu_item_action" class="form-label">Action</label>
                  <input type="text" class="form-control" name="action_code" id="menu_item_action" placeholder="warehouse_item_in">
                </div>
                <div class="mb-3">
                  <label for="menu_item_sort" class="form-label">Порядок</label>
                  <input type="number" class="form-control" name="sort_order" id="menu_item_sort" value="0">
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="is_active" id="menu_item_active" checked>
                  <label class="form-check-label" for="menu_item_active">Активен</label>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary js-core-link" data-core-action="save_menu_item">Сохранить</button>
                  <button type="button" class="btn btn-outline-secondary js-menu-item-reset">Очистить</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="card">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <h5 class="card-title mb-0">Права</h5>
                <span class="text-muted small">Изменения применятся при следующем входе</span>
              </div>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th scope="col">Code</th>
                      <th scope="col">Название</th>
                      <th scope="col">Описание</th>
                      <th scope="col" class="text-end">Действия</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('permissions')) > 0) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('permissions'), 'permission');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('permission')->value) {
$foreach2DoElse = false;
?>
                        <tr>
                          <td><code><?php echo $_smarty_tpl->getValue('permission')['code'];?>
</code></td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['description'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary js-permission-edit"
                                    data-permission-id="<?php echo $_smarty_tpl->getValue('permission')['id'];?>
"
                                    data-permission-code="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['code'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-permission-name="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['name'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-permission-description="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['description'], ENT_QUOTES, 'UTF-8', true);?>
">
                              Изменить
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger js-core-link"
                                    data-core-action="delete_permission"
                                    data-permission-code="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('permission')['code'], ENT_QUOTES, 'UTF-8', true);?>
">
                              Удалить
                            </button>
                          </td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="4" class="text-muted">Права не найдены</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Матрица роль ↔ право</h5>
              <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center">
                  <thead>
                    <tr>
                      <th scope="col" class="text-start">Право</th>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('roles'), 'role');
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('role')->value) {
$foreach3DoElse = false;
?>
                        <th scope="col"><?php echo $_smarty_tpl->getValue('role')['code'];?>
</th>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('permissions')) > 0) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('permissions'), 'permission');
$foreach4DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('permission')->value) {
$foreach4DoElse = false;
?>
                        <tr>
                          <td class="text-start"><?php echo $_smarty_tpl->getValue('permission')['code'];?>
</td>
                          <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('roles'), 'role');
$foreach5DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('role')->value) {
$foreach5DoElse = false;
?>
                            <?php $_smarty_tpl->assign('roleCode', $_smarty_tpl->getValue('role')['code'], false, NULL);?>
                            <?php $_smarty_tpl->assign('permCode', $_smarty_tpl->getValue('permission')['code'], false, NULL);?>
                            <?php $_smarty_tpl->assign('isChecked', false, false, NULL);?>
                            <?php if ((null !== ($_smarty_tpl->getValue('role_permissions')[$_smarty_tpl->getValue('roleCode')] ?? null)) && (null !== ($_smarty_tpl->getValue('role_permissions')[$_smarty_tpl->getValue('roleCode')][$_smarty_tpl->getValue('permCode')] ?? null))) {?>
                              <?php $_smarty_tpl->assign('isChecked', true, false, NULL);?>
                            <?php }?>
                            <td>
                              <input type="checkbox"
                                     class="form-check-input js-role-permission-toggle"
                                     data-role-code="<?php echo $_smarty_tpl->getValue('role')['code'];?>
"
                                     data-permission-code="<?php echo $_smarty_tpl->getValue('permission')['code'];?>
"
                                     <?php if ($_smarty_tpl->getValue('isChecked')) {?>checked<?php }?>>
                            </td>
                          <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="<?php echo $_smarty_tpl->getSmarty()->getFunctionHandler('math')->handle(array('equation'=>"x+1",'x'=>$_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('roles'))), $_smarty_tpl);?>
" class="text-muted">Нет прав для отображения</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>


          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Пункты меню</h5>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th scope="col">Key</th>
                      <th scope="col">Группа</th>
                      <th scope="col">Название</th>
                      <th scope="col">Action</th>
                      <th scope="col">Статус</th>
                      <th scope="col" class="text-end">Действия</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('menu_items')) > 0) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('menu_items'), 'item');
$foreach6DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach6DoElse = false;
?>
                        <?php $_smarty_tpl->assign('groupTitle', '', false, NULL);?>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('menu_groups'), 'group');
$foreach7DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('group')->value) {
$foreach7DoElse = false;
?>
                          <?php if ($_smarty_tpl->getValue('group')['code'] == $_smarty_tpl->getValue('item')['group_code']) {?>
                            <?php $_smarty_tpl->assign('groupTitle', $_smarty_tpl->getValue('group')['title'], false, NULL);?>
                          <?php }?>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                        <tr>
                          <td><code><?php echo $_smarty_tpl->getValue('item')['menu_key'];?>
</code></td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('groupTitle'), ENT_QUOTES, 'UTF-8', true);?>
 <span class="text-muted small">(<?php echo $_smarty_tpl->getValue('item')['group_code'];?>
)</span></td>
                          <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['title'], ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td><code><?php echo $_smarty_tpl->getValue('item')['action'];?>
</code></td>
                          <td>
                            <?php if ($_smarty_tpl->getValue('item')['is_active']) {?>
                              <span class="badge bg-success">Активен</span>
                            <?php } else { ?>
                              <span class="badge bg-secondary">Выключен</span>
                            <?php }?>
                          </td>
                          <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary js-menu-item-edit"
                                    data-menu-item-id="<?php echo $_smarty_tpl->getValue('item')['id'];?>
"
                                    data-menu-item-key="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['menu_key'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-menu-item-group="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['group_code'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-menu-item-title="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['title'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-menu-item-icon="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['icon'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-menu-item-action="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['action'], ENT_QUOTES, 'UTF-8', true);?>
"
                                    data-menu-item-sort="<?php echo $_smarty_tpl->getValue('item')['sort_order'];?>
"
                                    data-menu-item-active="<?php echo $_smarty_tpl->getValue('item')['is_active'];?>
">
                              Изменить
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger js-core-link"
                                    data-core-action="delete_menu_item"
                                    data-menu-item-id="<?php echo $_smarty_tpl->getValue('item')['id'];?>
"
                                    data-menu-item-key="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('item')['menu_key'], ENT_QUOTES, 'UTF-8', true);?>
">
                              Удалить
                            </button>
                          </td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="6" class="text-muted">Пункты меню не найдены</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php }
}
