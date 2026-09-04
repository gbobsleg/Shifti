<?php
/**
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;
$appName = (string)Configure::read('App.name', 'Shifti');
$pageTitle = (string)($this->fetch('title') ?: 'Erreur');
?>
<!doctype html>
<html lang="fr">
  <head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($appName . ' - ' . $pageTitle) ?></title>
    <?= $this->Html->meta(['link' => '/favicon.svg', 'rel' => 'icon', 'type' => 'image/svg+xml']) ?>
    <?= $this->Html->meta(['link' => '/favicon-32.png', 'rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32']) ?>
    <?= $this->Html->meta('icon', '/favicon.ico') ?>
    <?= $this->Html->css('bootstrap.min') ?>
    <?= $this->Html->css('app/shell') ?>
    <?= $this->Html->css('app/crud') ?>
    <?= $this->Html->css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
  </head>
  <body>
    <?= $this->element('nav-sidebar'); ?>

    <main role="main" class="shell-inner py-4">
        <?= $this->Flash->render() ?>
        <div class="crud-app">
          <?= $this->fetch('content') ?>
          <div class="crud-actions-bar">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">Retour</a>
            <?= $this->Html->link('Accueil', '/', ['class' => 'btn btn-primary']) ?>
          </div>
        </div>
    </main>

    <?= $this->fetch('script') ?>
  </body>
</html>
