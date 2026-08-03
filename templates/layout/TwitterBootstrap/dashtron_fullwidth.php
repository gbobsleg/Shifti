<?php
/**
 * @var \Cake\View\View $this
 * Layout Dashtron sans sidebar - Pleine largeur
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

// CSS pour limiter la largeur sur grands écrans
$this->append('css', '<style>
.content-wrapper-fullwidth {
    max-width: 1400px;
    margin: 0 auto;
}
@media (min-width: 1920px) {
    .content-wrapper-fullwidth {
        max-width: 1400px;
    }
}
@media (max-width: 1400px) {
    .content-wrapper-fullwidth {
        max-width: 100%;
    }
}
</style>');

$this->prepend('tb_body_attrs', ' class="' . implode(' ', [$this->request->getParam('controller'), $this->request->getParam('action')]) . '" ');
$this->start('tb_body_start');
?>
<body <?= $this->fetch('tb_body_attrs') ?>>
<?= $this->element('nav-sidebar'); ?>

    <div class="container-fluid">
        <main role="main" class="col-12 pt-3 px-4 content-wrapper-fullwidth">
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

$this->append('content', '</main></div>');
echo $this->fetch('content');
