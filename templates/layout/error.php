<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
?>
<!doctype html>
<html lang="fr">
  <head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($this->fetch('title') ?: 'Erreur') ?></title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css('bootstrap.spacelab.min') ?>
    <?= $this->Html->css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css') ?>
    <?= $this->Html->css('cake') ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
  </head>
  <body class="bg-light">
    <?= $this->element('nav-sidebar'); ?>

    <main role="main" class="container-fluid" style="padding-top: 80px;">
      <div class="container">
        <?= $this->Flash->render() ?>
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="card shadow-sm border-0">
              <div class="card-body p-4">
                <?= $this->fetch('content') ?>
                <div class="mt-4">
                  <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
                  <?= $this->Html->link('<i class="bi bi-house"></i> Accueil', '/', ['class' => 'btn btn-primary ml-2', 'escape' => false]) ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <?= $this->fetch('script') ?>
  </body>
</html>
