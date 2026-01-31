<?php
/* Smarty version 5.3.1, created on 2026-01-31 11:13:38
  from 'file:cells_NA_API_tools_management.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_697de3e241a963_09925621',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'd3b2cc55e44ccac55c216a4345cae548aa3ba653' => 
    array (
      0 => 'cells_NA_API_tools_management.html',
      1 => 1769857774,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_697de3e241a963_09925621 (\Smarty\Template $_smarty_tpl) {
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
    <div data-page-init="tools_management" style="display:none"></div>
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
                  <p class="text-muted mb-1">
                    Перемещайте инструменты между ячейками хранения, чтобы фиксировать текущее место
                    нахождения и историю перемещений.
                  </p>

                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-8">
                      <label class="form-label small mb-1" for="tools-storage-move-search">Поиск</label>
                      <input type="text"
                             id="tools-storage-move-search"
                             class="form-control form-control-sm"
                             placeholder="QR код / название / серийный номер">
                    </div>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    Найдено: <span id="tools-storage-move-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Инструмент</th>
                          <th scope="col">Пользователь</th>
                          <th scope="col">Ячейка</th>
                          <th scope="col">Дата</th>
                        </tr>
                      </thead>
                      <tbody id="tools-storage-move-results-tbody"></tbody>
                    </table>
                  </div>
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


    <!-- Full Screen Modal -->
    <div class="modal fade" id="fullscreenModal" tabindex="-1">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Назначение инструмента</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Загрузка...
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div><!-- End Full Screen Modal-->





    <?php echo '<script'; ?>
 id="device-scan-config" type="application/json">
    {
      "task_id": "tools_management",
      "default_mode": "qr",
      "modes": ["qr", "barcode"],

      "buttons": {
        "vol_down_single": "scan",
        "vol_down_double": "confirm",
        "vol_up_single":   "clear",
        "vol_up_double":   "reset"
      },

      "active_context": "tools_storage_move",

      "context_switch": {
        "tabs": {
          "#tools-storage-move-tab": "tools_storage_move"
        },
        "modals": {
          "#fullscreenModal[data-tools-modal=\"user\"]": { "shown": "tools_user_modal", "hidden": "tools_storage_move" },
          "#fullscreenModal[data-tools-modal=\"cell\"]": { "shown": "tools_cell_modal", "hidden": "tools_storage_move" }
        }
      },

      "contexts": {
        "tools_storage_move": {
          "active_tab_selector": "#tools-storage-move-tab.nav-link.active",

          "qr": {
            "action": "fill_field",
            "field_id": "tools-storage-move-search"
          },
          "barcode": {
            "action": "fill_field",
            "field_id": "tools-storage-move-search"
          },

          "flow": {
            "start": "scan_tool",
            "steps": {
              "scan_tool": {
                "mode": "qr",
                "next_on_scan": "scan_tool",
                "on_action": {
                  "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
                  "confirm": [ { "op": "noop" } ],
                  "clear":   [ { "op": "web", "name": "clearToolsStorageMoveSearch" } ],
                  "reset":   [ { "op": "web", "name": "clearToolsStorageMoveSearch" } ]
                }
              }
            }
          }
        },

        "tools_user_modal": {
          "flow": {
            "start": "scan_user_qr",
            "steps": {
              "scan_user_qr": {
                "mode": "qr",
                "next_on_scan": "wait_for_confirm",
                "qr": {
                  "action": "web_callback",
                  "callback": "setToolsUserFromQR"
                },
                "barcode": {
                  "action": "web_callback",
                  "callback": "setToolsUserFromQR"
                },
                "on_action": {
                  "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
                  "confirm": [ { "op": "web", "name": "triggerToolsManagementSave" } ],
                  "clear":   [ { "op": "web", "name": "resetToolsUserSelection" } ],
                  "reset":   [ { "op": "web", "name": "resetToolsUserSelection" } ]
                }
              },
              "wait_for_confirm": {
                "mode": "none",
                "qr": {
                  "action": "web_callback",
                  "callback": "setToolsUserFromQR"
                },
                "barcode": {
                  "action": "web_callback",
                  "callback": "setToolsUserFromQR"
                },
                "on_action": {
                  "scan":    [ { "op": "set_step", "to": "scan_user_qr" }, { "op": "open_scanner", "mode": "qr" } ],
                  "confirm": [ { "op": "web", "name": "triggerToolsManagementSave" } ],
                  "clear":   [ { "op": "web", "name": "resetToolsUserSelection" }, { "op": "set_step", "to": "scan_user_qr" } ],
                  "reset":   [ { "op": "web", "name": "resetToolsUserSelection" }, { "op": "set_step", "to": "scan_user_qr" } ]
                }
              }
            }
          }
        },

        "tools_cell_modal": {
          "flow": {
            "start": "scan_cell_qr",
            "steps": {
              "scan_cell_qr": {
                "mode": "qr",
                "next_on_scan": "wait_for_confirm",
                "qr": {
                  "action": "web_callback",
                  "callback": "setToolsCellFromQR"
                },
                "barcode": {
                  "action": "web_callback",
                  "callback": "setToolsCellFromQR"
                },
                "on_action": {
                  "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
                  "confirm": [ { "op": "web", "name": "triggerToolsManagementSave" } ],
                  "clear":   [ { "op": "web", "name": "resetToolsCellSelection" } ],
                  "reset":   [ { "op": "web", "name": "resetToolsCellSelection" } ]
                }
              },
              "wait_for_confirm": {
                "mode": "none",
                "qr": {
                  "action": "web_callback",
                  "callback": "setToolsCellFromQR"
                },
                "barcode": {
                  "action": "web_callback",
                  "callback": "setToolsCellFromQR"
                },
                "on_action": {
                  "scan":    [ { "op": "set_step", "to": "scan_cell_qr" }, { "op": "open_scanner", "mode": "qr" } ],
                  "confirm": [ { "op": "web", "name": "triggerToolsManagementSave" } ],
                  "clear":   [ { "op": "web", "name": "resetToolsCellSelection" }, { "op": "set_step", "to": "scan_cell_qr" } ],
                  "reset":   [ { "op": "web", "name": "resetToolsCellSelection" }, { "op": "set_step", "to": "scan_cell_qr" } ]
                }
              }
            }
          }
        }
      }
    }
    <?php echo '</script'; ?>
>
<?php }
}
