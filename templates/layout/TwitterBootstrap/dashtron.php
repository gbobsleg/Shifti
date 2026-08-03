<?php
/**
 * @var \Cake\View\View $this
 */
use Cake\Core\Configure;

$this->setLayout('planning');

$this->Html->css('dashtron', ['block' => true]);
$this->Html->css("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css", ['block' => true]);
$this->Html->scriptStart(['block' => true]);
echo  "$(function () {
    $('[data-toggle=\"tooltip\"]').tooltip()
})";
$this->Html->scriptEnd();

$this->prepend('tb_body_attrs', ' class="' . implode(' ', [$this->request->getParam('controller'), $this->request->getParam('action')]) . '" ');
$this->start('tb_body_start');
?>
<body <?= $this->fetch('tb_body_attrs') ?>>
<?= $this->element('nav-sidebar'); ?>

    <div class="container-fluid">
        <div class="row">
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <?= $this->fetch('tb_sidebar') ?>
            </div>
        </nav>
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">
        <?php
            if (!$this->fetch('tb_flash')) {
                $this->start('tb_flash');
                echo $this->Flash->render();
                $this->end();
            }
        ?>

<?php
$this->end();

$this->start('tb_body_end');
echo '</body>';
$this->end();

$this->append('content', '</main></div></div>');
echo $this->fetch('content');
