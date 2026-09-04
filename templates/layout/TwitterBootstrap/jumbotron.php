<?php
/**
 * @var \Cake\View\View $this
 */
use Cake\Core\Configure;

$this->setLayout('planning');

$this->Html->css("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css", ['block' => true]);

$this->prepend('tb_body_attrs', ' class="' . implode(' ', [$this->request->getParam('controller'), $this->request->getParam('action')]) . '" ');
$this->start('tb_body_start');
?>
<body <?= $this->fetch('tb_body_attrs') ?>>
<?= $this->element('nav-sidebar'); ?>

    <main role="main">
        <?php
        /**
         * Default `flash` block.
         */
        if (!$this->fetch('tb_flash')) {
            $this->start('tb_flash');
            echo $this->Flash->render();
            $this->end();
        }
        ?>
        <div class="shell-inner">

<?php
$this->end();

$this->start('tb_body_end');
echo '</body>';
$this->end();

$this->append('content', '</div></main>');
echo $this->fetch('content');
