<?php
/**
 * @var \Cake\View\View $this
 */

use Cake\Core\Configure;

/**
 * Default `html` block.
 */
if (!$this->fetch('html')) {
    $this->start('html');
    if (Configure::check('App.language')) {
        printf('<html lang="%s">', Configure::read('App.language'));
    } else {
        echo '<html>';
    }
    $this->end();
}

/**
 * Default `title` block.
 */
if (!$this->fetch('title')) {
    $this->start('title');
    echo Configure::read('App.title');
    $this->end();
}

/**
 * Default `footer` block.
 */
if (!$this->fetch('tb_footer')) {
    $this->start('tb_footer');
    if (Configure::check('App.title')) {
        printf('&copy;%s %s', date('Y'), Configure::read('App.title'));
    } else {
        printf('&copy;%s', date('Y'));
    }
    $this->end();
}

/**
 * Default `body` block.
 */
$this->prepend('tb_body_attrs', ' class="' . implode(' ', [$this->request->getParam('controller'), $this->request->getParam('action')]) . '" ');
if (!$this->fetch('tb_body_start')) {
    $this->start('tb_body_start');
    echo '<body' . $this->fetch('tb_body_attrs') . '>';
    $this->end();
}
/**
 * Default `flash` block.
 */
if (!$this->fetch('tb_flash')) {
    $this->start('tb_flash');
    if (isset($this->Flash))
        echo $this->Flash->render();
    $this->end();
}
if (!$this->fetch('tb_body_end')) {
    $this->start('tb_body_end');
    echo '</body>';
    $this->end();
}

/**
 * Prepend `meta` block with `author` and `favicon`.
 */
if (Configure::check('App.author')) {
    $this->prepend('meta', $this->Html->meta('author', null, ['name' => 'author', 'content' => Configure::read('App.author')]));
}
$this->prepend('meta', $this->Html->meta('icon', '/favicon.ico'));
$this->prepend('meta', $this->Html->meta([
    'link' => '/favicon-32.png',
    'rel' => 'icon',
    'type' => 'image/png',
    'sizes' => '32x32',
]));
$this->prepend('meta', $this->Html->meta([
    'link' => '/favicon.svg',
    'rel' => 'icon',
    'type' => 'image/svg+xml',
]));

/**
 * Bootstrap 5 (CSS local, JS CDN) + jQuery (daterangepicker / DragSelect)
 */
$this->prepend('css', $this->Html->css('bootstrap.min', ['block' => false]));
$this->append('css', $this->Html->css('app/shell', ['block' => false, 'timestamp' => 'force']));
$this->append('css', $this->Html->css('cake', ['block' => false]));
$this->append('css', $this->Html->css('dropdown-actions-body', ['block' => false]));

$this->prepend('script', $this->Html->script('jquery-3.6.0.min', ['block' => false]));
$this->append('script', $this->Html->script('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', ['block' => false]));
$this->append('script', $this->Html->script('app/tooltips', ['block' => false]));
$this->append('script', $this->Html->script('flash-auto-dismiss', ['block' => false, 'timestamp' => 'force']));
$this->append('script', $this->Html->script('dropdown-actions-body', ['block' => false]));

?>
<!doctype html>
<?= $this->fetch('html') ?>
    <head>
        <?= $this->Html->charset() ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <?php
        $appName = (string)Configure::read('App.name', 'Shifti');
        $pageTitle = trim((string)$this->fetch('title'));
        $documentTitle = $pageTitle !== '' ? ($appName . ' - ' . $pageTitle) : $appName;
        ?>
        <title><?= h($documentTitle) ?></title>
        <?= $this->fetch('meta') ?>
        <?= $this->Html->css('navbar') ?>
        <?= $this->Html->css('footer') ?>
        <?= $this->fetch('css') ?>
    </head>

    <?php
    echo $this->fetch('tb_body_start');
    ?>
    <div class="flash-container">
        <?php echo $this->fetch('tb_flash'); ?>
    </div>
    <?= $this->fetch('content'); ?>
    <footer class="navbar navbar-dark bg-primary app-footer">
        <div class="app-footer-content shell-inner">
            <?= $this->fetch('tb_footer'); ?>
        </div>
    </footer>

    <?= $this->fetch('script') ?>
    <?= $this->fetch('tb_body_end'); ?>

</html>
