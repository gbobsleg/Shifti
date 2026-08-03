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

$this->prepend('css', $this->Html->css('bootstrap.spacelab.min', ['block' => false]));
$this->append('css', $this->Html->css('cake', ['block' => false]));
$this->prepend('script', $this->Html->script('jquery-3.6.0.min', ['block' => false]));
$this->append('script', $this->Html->script('https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js', ['block' => false]));
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
        .flash-container { position: relative; z-index: 2000; }
        /* Pas de barre fixe dans l'iframe workspace (évite le chevauchement) */
        body.embed-layout .grids-search-bar-sticky {
            position: relative;
            top: auto;
            left: auto;
            right: auto;
            z-index: auto;
        }
        body.embed-layout .grids-search-spacer { display: none; height: 0; }
        body.embed-layout .grids-alerts-bar-sticky {
            margin-top: 0;
            margin-left: 0;
            margin-right: 0;
        }

        /* Sidebar légende figée à gauche (scroll vertical iframe + scroll horizontal grille) */
        /* Même logique que le planning principal :
           sidebar 200px + écart ~35px avant la grille (margin-left 250px - left 15px). */
        body.embed-layout .grids-embed-body {
            display: flex;
            align-items: flex-start;
            gap: 35px;
        }
        body.embed-layout .grids-embed-sidebar {
            position: sticky;
            top: 0.5rem;
            flex: 0 0 200px;
            width: 200px;
            z-index: 40;
            max-height: calc(100vh - 1rem);
            overflow-y: auto;
        }
        body.embed-layout .grids-embed-sidebar .grids-sort-card,
        body.embed-layout .grids-embed-sidebar .grids-site-toggle-card,
        body.embed-layout .grids-embed-sidebar .offers-legend-card {
            position: relative;
            left: auto;
            top: auto;
            width: 100%;
            max-height: none;
            overflow: visible;
        }
        body.embed-layout .grids-embed-main {
            flex: 1 1 auto;
            min-width: 0;
        }
        body.embed-layout .grids-embed-main .main-content-wrapper {
            margin-left: 0;
            padding-right: 0.25rem;
        }
    </style>
</head>
<body class="embed-layout <?= h((string)$this->request->getParam('controller')) ?> <?= h((string)$this->request->getParam('action')) ?>">
    <div class="flash-container">
        <?= $this->Flash->render() ?>
    </div>
    <?= $this->fetch('content') ?>
    <?= $this->fetch('script') ?>
</body>
</html>
