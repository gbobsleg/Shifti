<?php
/**
 * @var \App\View\AppView $this
 * @var string $icon
 * @var string $title
 * @var string $text
 */
$identity = $this->request->getAttribute('identity');
$canAdmin = false;
if ($identity && method_exists($identity, 'can')) {
    try {
        $canAdmin = (bool)$identity->can('admin', new \App\Resource\PagesResource());
    } catch (\Throwable) {
        $canAdmin = false;
    }
}
?>
<div class="crud-app crud-error">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi <?= h($icon) ?>" aria-hidden="true"></i>
                <?= h($title) ?>
            </h1>
            <p class="crud-header-meta"><?= h($text) ?></p>
        </div>
    </div>
    <div class="crud-actions-bar">
        <?= $this->Html->link('Accueil', '/', ['class' => 'btn btn-primary']) ?>
        <a href="javascript:history.back()" class="btn btn-outline-secondary">Retour</a>
        <?php if ($canAdmin): ?>
            <?= $this->Html->link(
                'Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary']
            ) ?>
        <?php endif; ?>
    </div>
</div>
