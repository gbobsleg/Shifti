<?php
/**
 * Layout pour la page de login (minimal, sans navbar/footer)
 */
use Cake\Core\Configure;
$appName = (string)Configure::read('App.name', 'Shifti');
?>
<!doctype html>
<html lang="fr">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($appName . ' - Connexion') ?></title>
    <?= $this->Html->meta(['link' => '/favicon.svg', 'rel' => 'icon', 'type' => 'image/svg+xml']) ?>
    <?= $this->Html->meta(['link' => '/favicon-32.png', 'rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32']) ?>
    <?= $this->Html->meta('icon', '/favicon.ico') ?>
    <?= $this->Html->css('bootstrap.spacelab.min') ?>
    <?= $this->Html->css('cake') ?>
    <?= $this->Html->css('navbar') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 24px;
        }
        .flash-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            max-width: 520px;
            width: calc(100% - 40px);
        }
    </style>
</head>
<body>
    <div class="flash-container">
        <?= $this->Flash->render() ?>
    </div>

    <div class="login-container">
        <?= $this->fetch('content'); ?>
    </div>

    <?= $this->Html->script('jquery-3.6.0.min') ?>
    <?= $this->Html->script('https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js') ?>

    <?php $this->Html->scriptStart(['block' => 'script']); ?>
    setTimeout(function(){
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(a){ a.style.transition='opacity .5s'; a.style.opacity='0'; });
    }, 5000);
    <?php $this->Html->scriptEnd(); ?>

    <?= $this->fetch('script') ?>
</body>
</html>

