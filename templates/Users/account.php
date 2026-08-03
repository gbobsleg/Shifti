<?php $this->assign('title', 'Mon compte'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-person-circle text-primary"></i>
            Mon Profil
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-key mr-1"></i> Changer mon mot de passe',
                ['action' => 'changePassword'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <?php // --- Informations personnelles --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-person-badge"></i> Informations personnelles
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-person"></i> Nom complet</label>
                            <div><strong><?= h($user->last_name . ' ' . $user->first_name) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-envelope"></i> Email</label>
                            <div>
                                <a href="mailto:<?= h($user->email) ?>"><?= h($user->email) ?></a>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-person-badge"></i> Code Utilisateur</label>
                            <div><span class="badge badge-secondary" style="font-size: 1rem;"><?= h($user->user_code ?? 'N/A') ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Rôle et Site --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-building"></i> Affectation
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-shield-lock"></i> Rôle</label>
                            <div>
                                <span class="badge badge-info" style="font-size: 1rem;">
                                    <?= h($user->role->name ?? 'Non défini') ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-geo-alt"></i> Site</label>
                            <div>
                                <?php if (isset($user->site->name)): ?>
                                    <span class="badge badge-success" style="font-size: 1rem;">
                                        <i class="bi bi-building"></i> <?= h($user->site->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Actions --- ?>
        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-key mr-2"></i> Changer mon mot de passe',
                ['action' => 'changePassword'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>
</div>
