<?php
/**
 * Layout minimal pour iframe (brouillon embed dans le workspace).
 * Charge CSS/JS sans sidebar ni footer.
 *
 * @var \Cake\View\View $this
 */
use Cake\Core\Configure;

if (!$this->fetch('html')) {
    $this->start('html');
    if (Configure::check('App.language')) {
        printf('<html lang="%s">', Configure::read('App.language'));
    } else {
        echo '<html>';
    }
    $this->end();
}

if (!$this->fetch('title')) {
    $this->start('title');
    echo Configure::read('App.title');
    $this->end();
}

$this->prepend('css', $this->Html->css('bootstrap.min', ['block' => false]));
$this->append('css', $this->Html->css('app/shell', ['block' => false, 'timestamp' => 'force']));
$this->prepend('script', $this->Html->script('jquery-3.6.0.min', ['block' => false]));
$this->append('script', $this->Html->script('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', ['block' => false]));
$this->append('script', $this->Html->script('app/tooltips', ['block' => false]));
$this->append('script', $this->Html->script('flash-auto-dismiss', ['block' => false, 'timestamp' => 'force']));
$this->Html->css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', ['block' => true]);
?>
<!doctype html>
<?= $this->fetch('html') ?>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($this->fetch('title')) ?></title>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
        body { margin: 0; padding: 0.5rem; background: #fff; }
        body.embed-layout .grids-app {
            width: 100%;
            max-width: none;
            margin: 0;
        }
        body.embed-layout .grids-app .grids-save-btn-floating {
            right: 0.75rem;
            bottom: 0.75rem;
        }
    </style>
</head>
<body class="embed-layout <?= h((string)$this->request->getParam('controller')) ?> <?= h((string)$this->request->getParam('action')) ?>">
    <div class="flash-container flash-container--embed">
        <?= $this->Flash->render() ?>
    </div>
    <?= $this->fetch('content') ?>
    <?= $this->fetch('script') ?>
</body>
</html>
