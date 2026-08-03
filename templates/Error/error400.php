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
    $this->extend('/layout/TwitterBootstrap/jumbotron');
    $this->assign('title', $message ?? 'Accès refusé');
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
                        </div>
                    </div>
                </div>
            </div>
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
<h2><?= h($message) ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= __d('cake', 'The requested address {0} was not found on this server.', "<strong>'{$url}'</strong>") ?>
</p>
