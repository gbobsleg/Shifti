<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;

// Si c'est un 403, on affiche la page stylée de l'app, même en debug
$status = $this->getResponse() ? $this->getResponse()->getStatusCode() : null;
if ($status === 403) {
    $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
    $this->assign('title', $message ?? 'Accès refusé');
    ?>
    <div class="crud-app">
        <div class="crud-header">
            <div>
                <h1>Accès refusé</h1>
                <p class="crud-header-meta">
                    <?= h($message ?? "Vous n'êtes pas autorisé à accéder à cette page.") ?>
                </p>
            </div>
        </div>
        <div class="crud-actions-bar">
            <?= $this->Html->link(
                'Accueil',
                '/',
                ['class' => 'btn btn-primary']
            ) ?>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">Retour</a>
        </div>
    </div>
    <?php
    return;
}

// Sinon, fallback Cake par défaut
$this->setLayout('error');

if (Configure::read('debug')) :
    $this->setLayout('dev_error');

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
    echo $this->element('auto_table_warning');
    $this->end();
endif;
?>
<div class="crud-header">
    <div>
        <h1><?= h($message) ?></h1>
        <p class="crud-header-meta">Cette adresse n'existe pas.</p>
    </div>
</div>
