<?php
/* Smarty version 5.3.1, created on 2026-02-24 12:55:36
  from 'file:cells_NA_API_warehouse_item_in_batch.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_699d9fc8d710c7_96774629',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7e8c2c62a4cb34a58255b0e76fcea2eec9328b7b' => 
    array (
      0 => 'cells_NA_API_warehouse_item_in_batch.html',
      1 => 1771937721,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_699d9fc8d710c7_96774629 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>


<form id="item-in-modal-form" class="row g-3">
  <input type="hidden" name="batch_uid" value="<?php echo $_smarty_tpl->getValue('batch_uid');?>
">
  <input type="hidden" name="carrierCode" value="">

  <div class="col-md-4">
    <label for="tuid" class="form-label">
      TUID
      <span id="ocrCarrierInfo" class="text-muted"></span>
    </label>
    <input type="text" class="form-control" id="tuid" name="tuid" required>
  </div>

  <div class="col-md-4">
    <label for="trackingNo" class="form-label">Трек-номер</label>
    <input type="text" class="form-control" id="trackingNo" name="tracking_no" required>
  </div>

  <div class="col-md-4">
     <label for="carrierName" class="form-label">Перевозчик</label>
     <select class="form-select" id="carrierName" name="carrier_name">
       <option value="">— выберите —</option>
       <option value="DHL">DHL</option>
       <option value="GLS">GLS</option>
       <option value="HERMES">HERMES</option>
       <option value="UPS">UPS</option>
       <option value="AMAZON">AMAZON</option>
       <option value="DPD">DPD</option>
       <option value="OTHER">OTHER</option>
     </select>
     <input type="hidden" id="senderName" name="carrier_code">
  </div>

  <div class="col-md-4">
    <label class="form-label" for="receiverCountry">Страна получателя</label>
    <select class="form-select"
            id="receiverCountry"
            name="receiver_country_code">
              <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('dest_country'), 'b');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('b')->value) {
$foreach0DoElse = false;
?>
              <option value="<?php echo $_smarty_tpl->getValue('b')['code_iso2'];?>
"><?php echo $_smarty_tpl->getValue('b')['name_en'];?>
</option>
              <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
      <!-- опции будем заполнять JS-ом -->
    </select>
  </div>

  <div class="col-md-6">
    <label for="receiverName" class="form-label">Получатель</label>
    <input type="text" class="form-control" id="receiverName" name="receiver_name">
  </div>

  <div class="col-md-6">
    <label for="receiverAddress" class="form-label">Ячейка</label>
    <input type="text" class="form-control" id="receiverAddress" name="receiver_address">
    <div id="receiverAddressQuickCells" class="mt-2 d-flex flex-wrap gap-2"></div>
  </div>


  <div class="col-md-6">
    <label class="form-label" for="receiverCompany">Компания форвард</label>
    <select class="form-select"
            id="receiverCompany"
            name="receiver_company">
             <option value="COLIBRI">COLIBRI</option>
             <option value="KOLLI">KOLLI</option>
             <option value="ASER">ASER</option>
             <option value="CAMEX">CAMEX</option>
             <option value="KARGOFLEX">KARGOFLEX</option>
             <option value="CAMARATC">CAMARATC</option>
             <option value="POSTLINK">POSTLINK</option>

                  </select>
  </div>

<!--  <div class="col-md-6">
    <label for="receiverCompany" class="form-label">Компания форвард</label>
    <input type="text" class="form-control" id="receiverCompany" name="receiver_company">
  </div>-->

  <div class="col-md-6">
    <label for="senderName" class="form-label">Форвард CODE</label>
    <input type="text" class="form-control" id="carrierCode" name="sender_code">
  </div>


  <div class="col-md-6">
    <label for="standDevice" class="form-label">Устройство измерения</label>
    <select class="form-select" id="standDevice" name="stand_device_uid">
      <option value="">— выберите устройство —</option>
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('stand_devices'), 'device');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('device')->value) {
$foreach1DoElse = false;
?>
      <option value="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_uid'], ENT_QUOTES, 'UTF-8', true);?>
" data-device-token="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_token'], ENT_QUOTES, 'UTF-8', true);?>
">
        <?php if ($_smarty_tpl->getValue('device')['name']) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['name'], ENT_QUOTES, 'UTF-8', true);
} else {
echo htmlspecialchars((string)$_smarty_tpl->getValue('device')['device_uid'], ENT_QUOTES, 'UTF-8', true);
}?>
      </option>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </select>
  </div>

  <div class="col-md-6 d-flex align-items-end">
    <button type="button" class="btn btn-outline-secondary" id="standMeasureBtn">
      Получить измерения
    </button>
  </div>

  <div class="col-md-3">
    <label for="weightKg" class="form-label">Вес, кг</label>
    <input type="number" step="0.001" class="form-control" id="weightKg" name="weight_kg">
  </div>

  <div class="col-md-3">
    <label for="sizeL" class="form-label">Длина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeL" name="size_l_cm">
  </div>

  <div class="col-md-3">
    <label for="sizeW" class="form-label">Ширина, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeW" name="size_w_cm">
  </div>

  <div class="col-md-3">
    <label for="sizeH" class="form-label">Высота, см</label>
    <input type="number" step="0.1" class="form-control" id="sizeH" name="size_h_cm">
  </div>

  <div class="col-12">
    <button type="button"
            class="btn btn-primary js-core-link"
            data-core-action="add_new_item_in">
      Добавить посылку
    </button>
  </div>
</form>

<hr>

<h5>Посылки в партии <?php echo $_smarty_tpl->getValue('batch_uid');?>
</h5>

<table class="table table-sm align-middle">
  <thead>
    <tr>
      <th>#</th>
      <th>Трек</th>
      <th>Получатель</th>
      <th>Вес</th>
      <th></th>
      <th>Габариты</th>
      <th>Создано</th>
    </tr>
  </thead>
  <tbody>
    <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('items'), 'p');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('p')->value) {
$foreach2DoElse = false;
?>
      <tr>
        <td><?php echo $_smarty_tpl->getValue('p')['id'];?>
</td>
        <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('p')['tracking_no'], ENT_QUOTES, 'UTF-8', true);?>
</td>
        <td><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('p')['receiver_name'], ENT_QUOTES, 'UTF-8', true);?>
</td>
        <td><?php if ($_smarty_tpl->getValue('p')['weight_kg']) {
echo $_smarty_tpl->getValue('p')['weight_kg'];?>
 кг<?php }?></td>
        <td class="text-end">
          <button type="button"
                  class="btn btn-sm btn-outline-danger js-core-link"
                  data-core-action="delete_item_in"
                  data-item-id="<?php echo $_smarty_tpl->getValue('p')['id'];?>
">
            Удалить
          </button>
        </td>
        <td>
          <?php if ($_smarty_tpl->getValue('p')['size_l_cm'] || $_smarty_tpl->getValue('p')['size_w_cm'] || $_smarty_tpl->getValue('p')['size_h_cm']) {?>
            <?php echo $_smarty_tpl->getValue('p')['size_l_cm'];?>
×<?php echo $_smarty_tpl->getValue('p')['size_w_cm'];?>
×<?php echo $_smarty_tpl->getValue('p')['size_h_cm'];?>
 см
          <?php }?>
        </td>
        <td><?php echo $_smarty_tpl->getValue('p')['created_at'];?>
</td>
      </tr>
    <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    <?php if (!$_smarty_tpl->getValue('items')) {?>
      <tr>
        <td colspan="6" class="text-center text-muted">
          В этой партии ещё нет посылок
        </td>
      </tr>
    <?php }?>
  </tbody>
</table>

<div class="mt-3 text-end">
  <button type="button"
          class="btn btn-success js-core-link"
          data-core-action="commit_item_in_batch"
          data-batch-uid="<?php echo $_smarty_tpl->getValue('batch_uid');?>
">
    Завершить партию (на склад)
  </button>
</div>

<?php echo '<script'; ?>
 id="device-scan-config" type="application/json">
{
  "task_id": "warehouse_in",
  "default_mode": "barcode",
  "modes": ["barcode","ocr"],

  "barcode": {
    "action": "fill_field",
    "field_ids": ["tuid","trackingNo","senderName"]
  },
  "qr": {
    "action": "api_check",
    "endpoint": "/api/qr_check.php"
  },

  "cell_null_default_forwrad": {
    "CAMEX_AZB": "A99",
    "CAMARATC_TBS": "C99",
    "CAMARATC_KG":  "K99",
    "COLIBRI_AZB": "C0",
    "KOLLI_AZB": "379761",
    "POSTLINK_AZB": "00069",
    "ASER_AZB": "AS0"
  },

  "buttons": {
    "vol_down_single": "scan",
    "vol_down_double": "confirm",
    "vol_up_single":   "clear",
    "vol_up_double":   "reset"
  },

  "ui": {
    "title": "Приёмка",
    "step_labels": {
      "barcode": "Штрихкод",
      "ocr":     "OCR",
      "measure": "Замер",
      "submit":  "Подтвердить"
    }
  },

  "flow": {
    "start": "barcode",
    "steps": {
      "barcode": {
        "next_on_scan": "ocr",
        "on_action": {
          "scan":    [ { "op":"open_scanner", "mode":"barcode" } ],
          "clear":   [ { "op":"web", "name":"clear_tracking" } ],
          "reset":   [ { "op":"web", "name":"clear_all" }, { "op":"set_step", "to":"barcode" } ],
          "confirm": [ { "op":"noop" } ]
        }
      },

      "ocr": {
        "next_on_scan": "measure",
        "on_action": {
          "scan":    [ { "op":"open_scanner", "mode":"ocr" }, { "op":"set_step", "to":"measure" } ],
          "clear":   [ { "op":"web", "name":"clear_except_track" }, { "op":"set_step", "to":"barcode" } ],
          "reset":   [ { "op":"web", "name":"clear_all" }, { "op":"set_step", "to":"barcode" } ],
          "confirm": [ { "op":"noop" } ]
        }
      },

      "measure": {
        "on_action": {
          "scan":    [ { "op":"web","name":"measure_request" }, { "op":"set_step","to":"submit" } ],
          "clear":   [ { "op":"web", "name":"clear_measurements" }, { "op":"set_step", "to":"ocr" } ],
          "reset":   [ { "op":"web", "name":"clear_all" }, { "op":"set_step", "to":"barcode" } ],
          "confirm": [ { "op":"noop" } ]
        }
      },

  
      "submit": {
        "on_action": {
          "scan":    [ { "op":"web","name":"add_new_item" }, { "op":"set_step","to":"barcode" } ],
          "confirm": [ { "op":"web","name":"add_new_item" }, { "op":"set_step","to":"barcode" } ],
          "clear":   [ { "op":"web","name":"clear_measurements" }, { "op":"set_step", "to":"measure" } ],
          "reset":   [ { "op":"web","name":"clear_all" }, { "op":"set_step", "to":"barcode" } ]
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
<?php }
}
