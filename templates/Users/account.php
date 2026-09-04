<?php $this->assign('title', 'Mon compte'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app users view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-person-circle"></i>
            Mon Profil
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-key me-1"></i> Changer mon mot de passe',
                ['action' => 'changePassword'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations personnelles</h2>
        <dl class="crud-fields">
            <div>
                <dt>Nom complet</dt>
                <dd><?= h($user->last_name . ' ' . $user->first_name) ?></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>
                    <?php if ($user->email): ?>
                        <a href="mailto:<?= h($user->email) ?>"><?= h($user->email) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Code utilisateur</dt>
                <dd><?= h($user->user_code ?? '—') ?></dd>
            </div>
            <div>
                <dt>Rôle</dt>
                <dd><?= h($user->role->name ?? '—') ?></dd>
            </div>
            <div>
                <dt>Site</dt>
                <dd><?= isset($user->site->name) ? h($user->site->name) : '—' ?></dd>
            </div>
        </dl>
    </section>
</div>
