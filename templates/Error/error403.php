<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 */
$title = $message ?? 'Accès refusé';
$this->assign('title', $title);
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
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
        <?php
        $identity = $this->request->getAttribute('identity');
        $canAdmin = $identity && method_exists($identity, 'can')
            && $identity->can('admin', new \App\Resource\PagesResource());
        if ($canAdmin): ?>
            <?= $this->Html->link(
                'Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary']
            ) ?>
        <?php endif; ?>
    </div>
</div>
