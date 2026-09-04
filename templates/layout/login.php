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
    <?= $this->Html->css('bootstrap.min') ?>
    <?= $this->Html->css('app/shell', ['timestamp' => 'force']) ?>
    <?= $this->Html->css('app/crud') ?>
    <?= $this->Html->css('cake') ?>
    <?= $this->Html->css('navbar') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
        body {
            background-color: #f4f6f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-logo {
            animation: none;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 24px;
        }
    </style>
</head>
<body>
    <div class="flash-container flash-container--login">
        <?= $this->Flash->render() ?>
    </div>

    <div class="login-container">
        <?= $this->fetch('content'); ?>
    </div>

    <?= $this->Html->script('jquery-3.6.0.min') ?>
    <?= $this->Html->script('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js') ?>
    <?= $this->Html->script('app/tooltips') ?>
    <?= $this->Html->script('flash-auto-dismiss', ['timestamp' => 'force']) ?>

    <?= $this->fetch('script') ?>
</body>
</html>

