<?php
/* Smarty version 5.3.1, created on 2026-01-18 16:57:37
  from 'file:cells_NA_API_warehouse_move.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696d1101d84908_32757412',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '9041cbe8efcd5e4b6c57b0a4462d7d776f0b5774' => 
    array (
      0 => 'cells_NA_API_warehouse_move.html',
      1 => 1768754786,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696d1101d84908_32757412 (\Smarty\Template $_smarty_tpl) {
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
              </ul>

              <div class="tab-content pt-3" id="warehouseMoveTabsContent">
                <div class="tab-pane fade show active" id="warehouse-move-scanner" role="tabpanel" aria-labelledby="warehouse-move-scanner-tab">
                  <p class="text-muted mb-1">Содержимое вкладки будет добавлено позже.</p>
                  <small class="text-muted">Цель: присвоение новых значений <code>warehouse_item_stock.cell_id</code> для товаров на складе.</small>
                  <input type="hidden" id="warehouseMoveTracking" value="">
                </div>
                <div class="tab-pane fade" id="warehouse-move-batch" role="tabpanel" aria-labelledby="warehouse-move-batch-tab">
                  <p class="text-muted mb-1">Содержимое вкладки будет добавлено позже.</p>
                  <small class="text-muted">Цель: пакетное присвоение новых значений <code>warehouse_item_stock.cell_id</code>.</small>
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
  "default_mode": "ocr",
  "modes": ["ocr"],
  "barcode": { "action": "fill_field", "field_id": "warehouseMoveTracking" },
  "qr":      { "action": "api_check",  "endpoint": "/api/qr_check.php" }
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
<?php }
}
