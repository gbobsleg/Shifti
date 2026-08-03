<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 */
use Cake\Core\Configure;

// Utilise le design courant (navbar, styles Bootstrap UI)
$this->extend('/layout/TwitterBootstrap/jumbotron');

$title = $message ?? 'Accès refusé';
$this->assign('title', $title);
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <div class="mr-3" style="font-size: 2rem; line-height: 1;">
                            <i class="bi bi-shield-lock text-danger"></i>
                        </div>
                        <div>
                            <h3 class="card-title mb-2">Accès refusé</h3>
                            <p class="mb-0 text-muted">
                                <?= h($message ?? "Vous n'êtes pas autorisé à accéder à cette page.") ?>
                            </p>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex flex-wrap gap-2">
                        <?= $this->Html->link(
                            '<i class="bi bi-house"></i> Accueil',
                            '/',
                            ['class' => 'btn btn-primary', 'escape' => false]
                        ) ?>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                        <?php
                        $identity = $this->request->getAttribute('identity');
                        $canAdmin = $identity && method_exists($identity, 'can')
                            && $identity->can('admin', new \App\Resource\PagesResource());
                        if ($canAdmin): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-gear"></i> Administration',
                                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                                ['class' => 'btn btn-outline-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


