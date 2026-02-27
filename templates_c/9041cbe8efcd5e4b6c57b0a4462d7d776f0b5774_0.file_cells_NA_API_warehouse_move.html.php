<?php
/* Smarty version 5.3.1, created on 2026-02-27 12:21:17
  from 'file:cells_NA_API_warehouse_move.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_69a18c3dc0f484_88911409',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9041cbe8efcd5e4b6c57b0a4462d7d776f0b5774' => 
    array (
      0 => 'cells_NA_API_warehouse_move.html',
      1 => 1772194707,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69a18c3dc0f484_88911409 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>    <div class="pagetitle">
      <h1>Warehouse Move</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.html">Main</a></li>
          <li class="breadcrumb-item">Warehouse</li>
          <li class="breadcrumb-item active">Перемещение</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section">
    <div data-page-init="warehouse_move" style="display:none"></div>
      <div class="row">
        <div class="col-lg-6">
          <div class="card table-responsive warehouse-move-wrapper">
            <div class="card-body">
              <h5 class="card-title">Перемещение</h5>

              <ul class="nav nav-tabs d-flex" id="warehouseMoveTabs" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100 active" id="warehouse-move-scanner-tab" data-bs-toggle="tab" data-bs-target="#warehouse-move-scanner" type="button" role="tab" aria-controls="warehouse-move-scanner" aria-selected="true">
                    Сканнер перемещение
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-move-batch-tab" data-bs-toggle="tab" data-bs-target="#warehouse-move-batch" type="button" role="tab" aria-controls="warehouse-move-batch" aria-selected="false" tabindex="-1">
                    Пакетное перемещение
                  </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                  <button class="nav-link w-100" id="warehouse-move-box-tab" data-bs-toggle="tab" data-bs-target="#warehouse-move-box" type="button" role="tab" aria-controls="warehouse-move-box" aria-selected="false" tabindex="-1">
                    Box перемещение
                  </button>
                </li>
              </ul>

              <div class="tab-content pt-3" id="warehouseMoveTabsContent">
                <div class="tab-pane fade show active" id="warehouse-move-scanner" role="tabpanel" aria-labelledby="warehouse-move-scanner-tab">
                  <p class="text-muted mb-1">Введите или отсканируйте TUID/трек-номер для поиска.</p>
                  <small class="text-muted">Цель: присвоение новых значений <code>warehouse_item_stock.cell_id</code> для товаров на складе.</small>


                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-8">
                      <label class="form-label small mb-1" for="warehouse-move-search">Поиск</label>
                      <input type="text" id="warehouse-move-search" class="form-control form-control-sm" placeholder="TUID или трек-номер">
                    </div>
                  </div>
                  <!-- Debug status indicator for device testing -->
                  <div id="scanner-debug-status" style="display:none; margin-top:10px; padding:8px; border-radius:4px; font-size:12px; background:#f8f9fa; border:1px solid #dee2e6;">
                    <strong>Debug:</strong> <span id="scanner-debug-text"></span>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    Найдено: <span id="warehouse-move-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Посылка</th>
                          <th scope="col">Источник</th>
                          <th scope="col">Ячейка</th>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <th scope="col">Пользователь</th>
                          <?php }?>
                          <th scope="col">Дата</th>
                        </tr>
                      </thead>
                      <tbody id="warehouse-move-results-tbody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="tab-pane fade" id="warehouse-move-batch" role="tabpanel" aria-labelledby="warehouse-move-batch-tab">
                  <p class="text-muted mb-1">Выберите ячейку и добавляйте посылки по трек-номеру.</p>
                  <small class="text-muted">Цель: пакетное присвоение новых значений <code>warehouse_item_stock.cell_id</code>.</small>

                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-move-batch-cell">Ячейка склада</label>
                      <select class="form-select form-select-sm" id="warehouse-move-batch-cell">
                        <option value="">— выберите ячейку —</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach0DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                    </div>
                    <div class="col-12 col-md-7">
                      <label class="form-label small mb-1" for="warehouse-move-batch-search">Трек-номер</label>
                      <input type="text" id="warehouse-move-batch-search" class="form-control form-control-sm" placeholder="TUID или трек-номер">
                    </div>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    Найдено: <span id="warehouse-move-batch-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Посылка</th>
                          <th scope="col">Источник</th>
                          <th scope="col">Ячейка</th>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <th scope="col">Пользователь</th>
                          <?php }?>
                          <th scope="col" class="d-none d-md-table-cell">Переместить</th>
                        </tr>
                      </thead>
                      <tbody id="warehouse-move-batch-results-tbody"></tbody>
                    </table>
                  </div>
                </div>

                <div class="tab-pane fade" id="warehouse-move-box" role="tabpanel" aria-labelledby="warehouse-move-box-tab">
                  <p class="text-muted mb-1">Выберите исходную и целевую ячейку для массового переноса.</p>
                  <small class="text-muted">Цель: переписать <code>warehouse_item_stock.cell_id</code> у всех посылок из выбранной ячейки.</small>

                  <div class="row g-2 align-items-end mt-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-move-box-from-cell">Из ячейки</label>
                      <select class="form-select form-select-sm" id="warehouse-move-box-from-cell" name="from_cell_id">
                        <option value="">— выберите ячейку —</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach1DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                    </div>
                    <div class="col-12 col-md-5">
                      <label class="form-label small mb-1" for="warehouse-move-box-to-cell">В ячейку</label>
                      <select class="form-select form-select-sm" id="warehouse-move-box-to-cell" name="to_cell_id">
                        <option value="">— выберите ячейку —</option>
                        <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('cells'), 'cell');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('cell')->value) {
$foreach2DoElse = false;
?>
                          <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['id'], ENT_QUOTES, 'UTF-8', true);?>
"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('cell')['code'], ENT_QUOTES, 'UTF-8', true);?>
</option>
                        <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                      </select>
                    </div>
                    <div class="col-12 col-md-2">
                      <button type="button" class="btn btn-sm btn-primary w-100 js-core-link" data-core-action="warehouse_move_box_assign">
                        Подтвердить
                      </button>
                    </div>
                  </div>

                  <p class="small text-muted mb-2 mt-3">
                    В исходной ячейке: <span id="warehouse-move-box-total">0</span>
                  </p>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle users-table">
                      <thead>
                        <tr>
                          <th scope="col">Посылка</th>
                          <th scope="col">Ячейка</th>
                          <?php if ($_smarty_tpl->getValue('current_user')['role'] == 'ADMIN') {?>
                            <th scope="col">Пользователь</th>
                          <?php }?>
                          <th scope="col">Размещена</th>
                        </tr>
                      </thead>
                      <tbody id="warehouse-move-box-items-tbody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>



<?php echo '<script'; ?>
 id="device-scan-config" type="application/json">
{
  "task_id": "warehouse_move",
  "default_mode": "barcode",
  "modes": ["barcode", "qr"],

  "buttons": {
    "vol_down_single": "scan",
    "vol_down_double": "confirm",
    "vol_up_single":   "clear",
    "vol_up_double":   "reset"
  },

  "api": {
    "move_apply": "/api/warehouse_move_apply.php"
  },

  "active_context": "scanner",

  "context_switch": {
    "tabs": {
      "#warehouse-move-scanner-tab": "scanner",
      "#warehouse-move-batch-tab":   "batch",
      "#warehouse-move-box-tab":     "box"
    },
    "modals": {
      "#fullscreenModal": { "shown": "scanner_modal", "hidden": "scanner" }
    }
  },

  "contexts": {
    "scanner": {
      "active_tab_selector": "#warehouse-move-scanner-tab.nav-link.active",

      "barcode": {
        "action": "fill_field",
        "field_id": "warehouse-move-search"
      },
      "qr": {
        "action": "api_check",
        "endpoint": "/api/qr_check.php"
      },

      "flow": {
        "start": "scan_parcel",
        "steps": {
          "scan_parcel": {
            "mode": "barcode",
            "next_on_scan": "wait_for_confirm",
            "on_action": {
              "scan":    [ { "op": "open_scanner", "mode": "barcode" } ],
              "confirm": [ { "op": "web", "name": "openMoveModal" }, { "op": "set_step", "to": "scan_cell_in_modal" } ],
              "clear":   [ { "op": "web", "name": "clear_search" } ],
              "reset":   [ { "op": "web", "name": "reset_form" }, { "op": "set_step", "to": "scan_parcel" } ]
            }
          },

          "wait_for_confirm": {
            "mode": "none",
            "on_action": {
              "scan":    [ { "op": "noop" } ],
              "confirm": [ { "op": "web", "name": "openMoveModal" }, { "op": "set_step", "to": "scan_cell_in_modal" } ],
              "clear":   [ { "op": "web", "name": "clear_search" }, { "op": "set_step", "to": "scan_parcel" } ],
              "reset":   [ { "op": "web", "name": "reset_form" }, { "op": "set_step", "to": "scan_parcel" } ]
            }
          },

          "scan_cell_in_modal": {
            "mode": "qr",
            "next_on_scan": "wait_for_save",
            "qr": {
              "action": "web_callback",
              "callback": "setCellFromQR"
            },
            "on_action": {
              "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
              "confirm": [ { "op": "web", "name":  "triggerSaveButton"  } ],
              "clear":   [ { "op": "set_step", "to": "scan_cell_in_modal" } ],
              "reset":   [ { "op": "web", "name": "reset_form" }, { "op": "set_step", "to": "scan_parcel" } ]
            }
          },

          "wait_for_save": {
            "mode": "none",
            "on_action": {
              "scan":    [ { "op": "noop" } ],
              "confirm": [ { "op": "web", "name": "triggerSaveButton" }, { "op": "set_step", "to": "scan_parcel" } ],
              "clear":   [ { "op": "set_step", "to": "scan_cell_in_modal" } ],
              "reset":   [ { "op": "web", "name": "reset_form" }, { "op": "set_step", "to": "scan_parcel" } ]
            }
          }
        }
      }
    },

    "scanner_modal": {
      "flow": {
        "start": "scan_cell_in_modal",
        "steps": {
          "scan_cell_in_modal": {
            "mode": "qr",
            "next_on_scan": "wait_for_save",
            "qr": {
              "action": "web_callback",
              "callback": "setCellFromQR"
            },
            "on_action": {
              "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
              "confirm": [ { "op": "web", "name": "triggerSaveButton" } ],
              "clear":   [ { "op": "noop" } ],
              "reset":   [ { "op": "web", "name": "reset_form" } ]
            }
          },
          "wait_for_save": {
            "mode": "none",
            "on_action": {
              "scan":    [ { "op": "noop" } ],
              "confirm": [ { "op": "web", "name": "triggerSaveButton" }, { "op": "set_step", "to": "scan_parcel" } ],
              "clear":   [ { "op": "noop" } ],
              "reset":   [ { "op": "web", "name": "reset_form" } ]
            }
          }
        }
      }
    },

    "batch": {
      "active_tab_selector": "#warehouse-move-batch-tab.nav-link.active",

      "barcode": {
        "action": "fill_field",
        "field_id": "warehouse-move-batch-search"
      },
      "qr": {
        "action": "api_check",
        "endpoint": "/api/qr_check.php",
        "apply_to_select_id": "warehouse-move-batch-cell"
      },

      "flow": {
        "start": "scan_cell",
        "steps": {
          "scan_cell": {
            "mode": "qr",
            "next_on_scan": "scan_parcel",
            "on_action": {
              "scan":    [ { "op": "open_scanner", "mode": "qr" } ],
              "clear":   [ { "op": "noop" } ],
              "reset":   [ { "op": "web", "name": "reset_form" } ],
              "confirm": [ { "op": "web", "name": "confirmBatchMove" } ]
            }
          },

          "scan_parcel": {
            "mode": "barcode",
            "next_on_scan": "wait_confirm",
            "on_action": {
              "scan":    [ { "op": "open_scanner", "mode": "barcode" } ],
              "clear":   [ { "op": "web", "name": "clear_search" } ],
              "reset":   [ { "op": "web", "name": "reset_form" } ],
              "confirm": [ { "op": "web", "name": "confirmBatchMove" } ]

            }
          },

          "wait_confirm": {
            "mode": "none",
            "on_action": {
              "scan":    [ { "op": "noop" } ],
              "confirm": [ { "op": "web", "name": "confirmBatchMove" } ],
              "clear":   [ { "op": "set_step", "to": "scan_parcel" } ],
              "reset":   [ { "op": "web", "name": "reset_form" }, { "op": "set_step", "to": "scan_cell" } ]
            }
          }
        }
      }
    },

    "box": {
      "active_tab_selector": "#warehouse-move-box-tab.nav-link.active",
      "flow": {
        "start": "idle",
        "steps": {
          "idle": {
            "mode": "none",
            "on_action": {
              "scan":    [ { "op": "noop" } ],
              "confirm": [ { "op": "web", "name": "confirmBoxMove" } ],
              "clear":   [ { "op": "noop" } ],
              "reset":   [ { "op": "web", "name": "reset_form" } ]
            }
          }
        }
      }
    }
  }
}
<?php echo '</script'; ?>
>





<div id="ocr-templates" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonOcrTemplates');?>

</div>
<div id="ocr-templates-destcountry" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonDestCountry');?>

</div>

<div id="ocr-dicts" style="display:none">
    <?php echo $_smarty_tpl->getValue('jsonOcrDicts');?>

</div>

    <!-- Full Screen Modal -->
    <div class="modal fade" id="fullscreenModal" tabindex="-1">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title">Просмотр посылки</h5>
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
<?php }
}
