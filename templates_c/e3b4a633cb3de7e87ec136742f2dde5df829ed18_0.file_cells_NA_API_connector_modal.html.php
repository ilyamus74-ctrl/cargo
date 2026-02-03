<?php
/* Smarty version 5.3.1, created on 2026-02-03 13:57:24
  from 'file:cells_NA_API_connector_modal.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6981fec4afd484_55320249',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'e3b4a633cb3de7e87ec136742f2dde5df829ed18' => 
    array (
      0 => 'cells_NA_API_connector_modal.html',
      1 => 1770126069,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6981fec4afd484_55320249 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?><section class="section">
  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body pt-3">
          <h5 class="card-title">Коннектор форварда</h5>
          <form id="connector-form" autocomplete="off">
            <input type="hidden" name="connector_id" value="<?php echo (($tmp = $_smarty_tpl->getValue('connector')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">

            <div class="row mb-3">
              <label for="connector_name" class="col-md-4 col-lg-3 col-form-label">Название</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="connector_name"
                       name="name"
                       value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['name'], ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="Forwarder Ltd">
              </div>
            </div>

            <div class="row mb-3">
              <label for="connector_countries" class="col-md-4 col-lg-3 col-form-label">Страны</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="connector_countries"
                       name="countries"
                       value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['countries'], ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="US, CA, MX">
                <div class="form-text">Коды стран через запятую.</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="connector_base_url" class="col-md-4 col-lg-3 col-form-label">Base URL</label>
              <div class="col-md-8 col-lg-9">
                <input type="text"
                       class="form-control"
                       id="connector_base_url"
                       name="base_url"
                       value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['base_url'], ENT_QUOTES, 'UTF-8', true);?>
"
                       placeholder="https://portal.forwarder.com/api">
              </div>
            </div>

            <div class="row mb-3">
              <label for="connector_auth_type" class="col-md-4 col-lg-3 col-form-label">Тип доступа</label>
              <div class="col-md-8 col-lg-9">
                <select class="form-select" id="connector_auth_type" name="auth_type">
                  <option value="login" <?php if ($_smarty_tpl->getValue('connector')['auth_type'] == 'login') {?>selected<?php }?>>Логин / пароль</option>
                  <option value="token" <?php if ($_smarty_tpl->getValue('connector')['auth_type'] == 'token') {?>selected<?php }?>>API токен</option>
                </select>
              </div>
            </div>

            <div data-connector-auth="login">
              <div class="row mb-3">
                <label for="connector_username" class="col-md-4 col-lg-3 col-form-label">Логин</label>
                <div class="col-md-8 col-lg-9">
                  <input type="text"
                         class="form-control"
                         id="connector_username"
                         name="auth_username"
                         value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['auth_username'], ENT_QUOTES, 'UTF-8', true);?>
"
                         placeholder="login@example.com">
                </div>
              </div>

              <div class="row mb-3">
                <label for="connector_password" class="col-md-4 col-lg-3 col-form-label">Пароль</label>
                <div class="col-md-8 col-lg-9">
                  <input type="password"
                         class="form-control"
                         id="connector_password"
                         name="auth_password"
                         value=""
                         placeholder="••••••••">
                  <?php if ($_smarty_tpl->getValue('connector')['id']) {?>
                    <div class="form-text">Оставьте пустым, чтобы не менять пароль.</div>
                  <?php }?>
                </div>
              </div>
            </div>

            <div data-connector-auth="token">
              <div class="row mb-3">
                <label for="connector_token" class="col-md-4 col-lg-3 col-form-label">API токен</label>
                <div class="col-md-8 col-lg-9">
                  <input type="password"
                         class="form-control"
                         id="connector_token"
                         name="api_token"
                         value=""
                         placeholder="token">
                  <?php if ($_smarty_tpl->getValue('connector')['id']) {?>
                    <div class="form-text">Оставьте пустым, чтобы не менять токен.</div>
                  <?php }?>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="connector_notes" class="col-md-4 col-lg-3 col-form-label">Комментарий</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control"
                          id="connector_notes"
                          name="notes"
                          rows="3"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['notes'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
              </div>
            </div>


            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Ручное подтверждение</label>
              <div class="col-md-8 col-lg-9">
                <div class="alert alert-light border small mb-2">
                  <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['manual_instruction'] ?? null)===null||$tmp==='' ? 'При необходимости вручную пройдите капчу и вставьте токен/куки.' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                </div>
                <div class="mb-2">
                  <label for="connector_auth_token" class="form-label small">Токен</label>
                  <textarea class="form-control"
                            id="connector_auth_token"
                            name="auth_token"
                            rows="2"
                            placeholder="Bearer token..."><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['auth_token'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                </div>
                <div class="mb-2">
                  <label for="connector_auth_token_expires" class="form-label small">Срок токена (YYYY-MM-DD HH:MM:SS)</label>
                  <input type="text"
                         class="form-control"
                         id="connector_auth_token_expires"
                         name="auth_token_expires_at"
                         value="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['auth_token_expires_at'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                </div>
                <div class="mb-2">
                  <label for="connector_auth_cookies" class="form-label small">Cookies</label>
                  <textarea class="form-control"
                            id="connector_auth_cookies"
                            name="auth_cookies"
                            rows="3"
                            placeholder="cookie1=value1; cookie2=value2"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['auth_cookies'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                </div>
                <div class="small text-muted">
                  Последнее подтверждение: <?php echo (($tmp = $_smarty_tpl->getValue('connector')['last_manual_confirm_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>

                </div>
                <?php if ($_smarty_tpl->getValue('connector')['id']) {?>
                  <button type="button"
                          class="btn btn-outline-secondary btn-sm mt-2 js-core-link"
                          data-core-action="manual_confirm_connector"
                          data-connector-id="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
">
                    Обновить токен
                  </button>
                <?php }?>
              </div>
            </div>

            <div class="row mb-3">
              <label for="connector_scenario" class="col-md-4 col-lg-3 col-form-label">Сценарий входа</label>
              <div class="col-md-8 col-lg-9">
                <textarea class="form-control"
                          id="connector_scenario"
                          name="scenario_json"
                          rows="10"
                          placeholder="JSON сценарий входа"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('connector')['scenario_json'], ENT_QUOTES, 'UTF-8', true);?>
</textarea>
                <div class="form-text">
                  Укажите URL входа, параметры формы и критерий успеха (селектор/текст). Для API шагов используйте steps с expect.
                </div>
                <pre class="form-text mb-0">{"manual_confirm":{"required":true,"instruction":"Оператор проходит капчу и нажимает \"Обновить токен\""},"login":{"url":"https://portal.example.com/login","method":"POST","fields":{"username":"${login}","password":"${password}"}},"success":{"selector":"a[href*=\"logout\"]","text":"Log out"},"steps":[{"name":"Dashboard","url":"https://portal.example.com/dashboard","method":"GET","success":{"selector":".user-profile"}},{"name":"Balance API","url":"https://portal.example.com/api/balance","method":"GET","expect":{"json_path":"data.balance","operator":">","value":0}}]}</pre>
              </div>
            </div>

            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Активен</label>
              <div class="col-md-8 col-lg-9">
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         id="connector_is_active"
                         name="is_active"
                         value="1"
                         <?php if ($_smarty_tpl->getValue('connector')['is_active'] == 1) {?>checked<?php }?>>
                  <label class="form-check-label" for="connector_is_active">Использовать для синхронизации</label>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">SSL ignored</label>
              <div class="col-md-8 col-lg-9">
                <div class="form-check">
                  <input class="form-check-input"
                         type="checkbox"
                         id="connector_ssl_ignore"
                         name="ssl_ignore"
                         value="1"
                         <?php if ($_smarty_tpl->getValue('connector')['ssl_ignore'] == 1) {?>checked<?php }?>>
                  <label class="form-check-label" for="connector_ssl_ignore">Игнорировать проверку SSL</label>
                </div>
                <div class="form-text">Используйте для самоподписных сертификатов. Опасно для продакшна.</div>
              </div>
            </div>

            <div class="row mb-3">
              <label class="col-md-4 col-lg-3 col-form-label">Состояние</label>
              <div class="col-md-8 col-lg-9">
                <div class="small text-muted">Последний обмен: <?php echo (($tmp = $_smarty_tpl->getValue('connector')['last_sync_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</div>
                <div class="small text-muted">Успешное обновление: <?php echo (($tmp = $_smarty_tpl->getValue('connector')['last_success_at'] ?? null)===null||$tmp==='' ? '—' ?? null : $tmp);?>
</div>
                <div class="small text-danger"><?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('connector')['last_error'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
</div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary js-core-link" data-core-action="save_connector">
                Сохранить
              </button>

              <?php if ($_smarty_tpl->getValue('connector')['id']) {?>
                <button type="button"
                        class="btn btn-outline-secondary js-core-link"
                        data-core-action="test_connector"
                        data-connector-id="<?php echo $_smarty_tpl->getValue('connector')['id'];?>
">
                  Проверить
                </button>
              <?php }?>

              <?php if ($_smarty_tpl->getValue('connector')['id']) {?>
                <button type="button"
                        class="btn btn-outline-danger js-core-link"
                        data-core-action="save_connector"
                        data-delete="1">
                  Удалить
                </button>
              <?php }?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php }
}
