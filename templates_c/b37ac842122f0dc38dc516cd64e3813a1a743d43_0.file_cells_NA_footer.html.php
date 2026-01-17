<?php
/* Smarty version 5.3.1, created on 2026-01-17 20:40:32
  from 'file:cells_NA_footer.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_696bf3c07ea478_21848294',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'b37ac842122f0dc38dc516cd64e3813a1a743d43' => 
    array (
      0 => 'cells_NA_footer.html',
      1 => 1768682425,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_696bf3c07ea478_21848294 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Cargo Cells</span></strong>
    </div>
    <div class="credits">
      <!-- All the links in the footer should remain intact. -->
      <!-- You can delete the links only if you purchased the pro version. -->
      <!-- Licensing information: https://bootstrapmade.com/license/ -->
      <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
      <!--Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>-->
    </div>
  </footer><!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <?php echo '<script'; ?>
 src="assets/vendor/apexcharts/apexcharts.min.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/chart.js/chart.umd.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/echarts/echarts.min.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/quill/quill.min.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/simple-datatables/simple-datatables.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/tinymce/tinymce.min.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="assets/vendor/php-email-form/validate.js"><?php echo '</script'; ?>
>

  <!-- Template Main JS File -->
  <?php echo '<script'; ?>
 src="assets/js/main.js"><?php echo '</script'; ?>
>
  <?php echo '<script'; ?>
 src="js/core_api.js"><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
>
document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.js-print-qr');
    if (!trigger) {
      return;
    }
    event.preventDefault();
    const payload = {
      src: trigger.getAttribute('data-qr-src'),
      title: trigger.getAttribute('data-qr-title') || 'QR',
      name: trigger.getAttribute('data-qr-name'),
      serial: trigger.getAttribute('data-qr-serial'),
      domain: trigger.getAttribute('data-qr-domain'),
      code: trigger.getAttribute('data-qr-code'),
    };

    openQrPrintDesigner(payload);
  });
});

function printUserQr() {
  const qrImage = document.getElementById('user-qr-image');
  if (!qrImage) {
    return;
  }

  openQrPrintDesigner({
    src: qrImage.src,
    title: 'QR',
  });
}

function openQrPrintDesigner(payload) {
  if (!payload || !payload.src) {
    return;
  }

  const params = new URLSearchParams();
  params.set('src', payload.src);
  params.set('title', payload.title || 'QR');

  if (payload.name) {
    params.set('name', payload.name);
  }
  if (payload.serial) {
    params.set('serial', payload.serial);
  }
  if (payload.code) {
    params.set('code', payload.code);
  }
  params.set('domain', payload.domain || window.location.hostname);

  const url = '/prepare_print?' + params.toString();
  const printWindow = window.open(url, 'print-qr');
  if (!printWindow) {
    return;
  }
  printWindow.focus();
}
<?php echo '</script'; ?>
>
<?php }
}
