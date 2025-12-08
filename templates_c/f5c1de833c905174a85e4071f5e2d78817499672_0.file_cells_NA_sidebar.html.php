<?php
/* Smarty version 5.3.1, created on 2025-12-05 09:55:41
  from 'file:cells_NA_sidebar.html' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.3.1',
  'unifunc' => 'content_6932ac1d0a67c9_63256390',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'f5c1de833c905174a85e4071f5e2d78817499672' => 
    array (
      0 => 'cells_NA_sidebar.html',
      1 => 1764928533,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_6932ac1d0a67c9_63256390 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/home/cells/web/templates';
?>
  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('menu'), 'group');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('group')->value) {
$foreach0DoElse = false;
?>
  <li class="nav-item">
    <a class="nav-link collapsed"
       data-bs-target="#nav-<?php echo $_smarty_tpl->getValue('group')['code'];?>
"
       data-bs-toggle="collapse"
       href="#">
      <i class="<?php echo $_smarty_tpl->getValue('group')['icon'];?>
"></i>
      <span><?php echo $_smarty_tpl->getValue('group')['title'];?>
</span>
      <i class="bi bi-chevron-down ms-auto"></i>
    </a>
    <ul id="nav-<?php echo $_smarty_tpl->getValue('group')['code'];?>
" class="nav-content collapse" data-bs-parent="#sidebar-nav">
      <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('group')['items'], 'item');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('item')->value) {
$foreach1DoElse = false;
?>
        <li>
          <a href="#"
             class="js-core-link"
             data-core-action="<?php echo $_smarty_tpl->getValue('item')['action'];?>
">
            <i class="<?php echo $_smarty_tpl->getValue('item')['icon'];?>
"></i>
            <span><?php echo $_smarty_tpl->getValue('item')['title'];?>
</span>
          </a>
        </li>
      <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </ul>
  </li>
<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
    </ul>

  </aside><!-- End Sidebar-->
<?php }
}
