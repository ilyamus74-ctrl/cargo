<?php
/* Smarty version 5.3.1, created on 2026-01-29 13:27:21
  from 'file:cells_NA_API_tools_management.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697b6039104465_39070657',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd3b2cc55e44ccac55c216a4345cae548aa3ba653' => 
    array (
      0 => 'cells_NA_API_tools_management.html',
      1 => 1769693075,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697b6039104465_39070657 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Управление инструментами</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Ресурсы</li>
          <li class="breadcrumb-item active">Управление инструментами</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Модуль управления</h5>
              <ul class="nav nav-tabs nav-tabs-bordered d-flex" id="tools-management-tabs" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link active w-100"
                          id="tools-storage-move-tab"
                          data-bs-toggle="tab"
                          data-bs-target="#tools-storage-move"
                          type="button"
                          role="tab"
                          aria-controls="tools-storage-move"
                          aria-selected="true">
                    Перемещение хранения
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100"
                          id="tools-owner-assignment-tab"
                          data-bs-toggle="tab"
                          data-bs-target="#tools-owner-assignment"
                          type="button"
                          role="tab"
                          aria-controls="tools-owner-assignment"
                          aria-selected="false">
                    Назначения владельца
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100"
                          id="tools-reporting-tab"
                          data-bs-toggle="tab"
                          data-bs-target="#tools-reporting"
                          type="button"
                          role="tab"
                          aria-controls="tools-reporting"
                          aria-selected="false">
                    Отчетность
                  </button>
                </li>
              </ul>
              <div class="tab-content pt-3" id="tools-management-tab-content">
                <div class="tab-pane fade show active"
                     id="tools-storage-move"
                     role="tabpanel"
                     aria-labelledby="tools-storage-move-tab">
                  <p class="mb-0">
                    Перемещайте инструменты между ячейками хранения, чтобы фиксировать текущее место
                    нахождения и историю перемещений.
                  </p>
                </div>
                <div class="tab-pane fade"
                     id="tools-owner-assignment"
                     role="tabpanel"
                     aria-labelledby="tools-owner-assignment-tab">
                  <p class="mb-0">
                    Назначайте ответственного сотрудника за инструмент, чтобы видеть актуального владельца
                    и контролировать выдачу.
                  </p>
                </div>
                <div class="tab-pane fade"
                     id="tools-reporting"
                     role="tabpanel"
                     aria-labelledby="tools-reporting-tab">
                  <p class="mb-0">
                    Просматривайте принадлежность инструментов к персоналу и местам хранения для
                    оперативной отчетности.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php }
}
