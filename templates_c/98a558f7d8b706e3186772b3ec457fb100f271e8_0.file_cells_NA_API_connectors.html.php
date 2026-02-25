<?php
/* Smarty version 5.3.1, created on 2026-02-25 11:34:43
  from 'file:cells_NA_API_connectors.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_699ede53422df6_74642281',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '98a558f7d8b706e3186772b3ec457fb100f271e8' => 
    array (
      0 => 'cells_NA_API_connectors.html',
      1 => 1772019280,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_699ede53422df6_74642281 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Settings connectors</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Settings</li>
          <li class="breadcrumb-item active">Connectors</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section" data-page-init="connectors">
      <div class="row">
        <div class="col-xl-12">
          <div class="card">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <h5 class="card-title mb-0">Коннекторы форвардов</h5>

                <a class="btn btn-secondary" href="https://addons.mozilla.org/developers/addon/2976563/versions" download>Плагин Firefox</a>
                <button type="button"
                        class="btn btn-primary js-core-link"
                        data-core-action="form_new_connector">
                  Добавить коннектор
                </button>
              </div>
              <div class="table-responsive mt-3">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th scope="col">Название</th>
                      <th scope="col">Страны</th>
                      <th scope="col">Тип доступа</th>
                      <th scope="col">Статус</th>
                      <th scope="col">Последний обмен</th>
                      <th scope="col">Успешное обновление</th>
                      <th scope="col">Ошибка</th>
                      <th scope="col" class="text-end">Операции</th>
                      <th scope="col" class="text-end">Действия</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('connectors')) > 0) {?>
                      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('connectors'), 'connector');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('connector')->value) {
$foreach0DoElse = false;
?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                            <?php if ($_smarty_tpl->getValue('connector')['base_url']) {?>
                              <div class="text-muted small"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['base_url'], ENT_QUOTES, 'UTF-8', true);?>
</div>
                            <?php }?>
                          </td>
                          <td><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['countries'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</td>
                          <td>
                            <?php if ($_smarty_tpl->getValue('connector')['auth_type'] == 'token') {?>
                              API Token
                            <?php } else { ?>
                              Логин/пароль
                            <?php }?>
                          </td>
                          <td>
                            <span class="badge bg-<?php echo (($tmp = $_smarty_tpl->getValue('connector')['status_class'] ?? null)===null||$tmp==='' ? 'secondary' ?? null : $tmp);?>
">
                              <?php echo (($tmp = $_smarty_tpl->getValue('connector')['status_label'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

                            </span>
                          </td>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('connector')['last_sync_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <td><?php echo (($tmp = $_smarty_tpl->getValue('connector')['last_success_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</td>
                          <td class="text-truncate" style="max-width: 160px;">
                            <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['last_error'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                          </td>
                          <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary js-core-link"
                                    data-core-action="form_connector_operations"
                                    data-connector-id="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
">
                              Операции
                            </button>
                          </td>
                          <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary js-core-link"
                                    data-core-action="form_edit_connector"
                                    data-connector-id="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
">
                              Редактировать
                            </button>
                          </td>
                        </tr>
                      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                    <?php } else { ?>
                      <tr>
                        <td colspan="9" class="text-muted text-center">Коннекторы не настроены</td>
                      </tr>
                    <?php }?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="modal fade" id="fullscreenModal" tabindex="-1">
            <div class="modal-dialog modal-fullscreen">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Коннектор</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  Контент коннектора загрузится сюда после выбора.
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
              </div>
            </div>
          </div><!-- End Full Screen Modal -->
        </div>
      </div>
    </section>
<?php }
}
