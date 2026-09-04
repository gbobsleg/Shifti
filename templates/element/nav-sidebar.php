<style>
#background-jobs-badge.bj-badge-idle [data-bj-count] {
    opacity: 0.55;
}
#background-jobs-badge.bj-badge-active [data-bj-count] {
    opacity: 1;
}
#background-jobs-badge .dropdown-menu {
    max-height: 70vh;
    overflow-y: auto;
}
</style>
<nav class="navbar navbar-expand-md navbar-dark bg-primary fixed-top">
    <div class="shell-inner">
    <?= $this->Html->link(
        $this->element('logo', ['class' => 'navbar-logo']),
        '/',
        ['class' => 'navbar-brand', 'escape' => false]) ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarsExampleDefault">
        <?php
        $identityObj = $this->request->getAttribute('identity');
        $loggedIn = (bool)$identityObj;
        $canAdmin = $loggedIn && method_exists($identityObj, 'can')
            && $identityObj->can('admin', new \App\Resource\PagesResource());
        $roleId = 0;
        if ($identityObj) {
            if (method_exists($identityObj, 'get')) {
                $roleId = (int)($identityObj->get('role_id') ?? 0);
            }
            if (!$roleId && method_exists($identityObj, 'getOriginalData')) {
                $orig = $identityObj->getOriginalData();
                if (is_object($orig) && isset($orig->role_id)) {
                    $roleId = (int)$orig->role_id;
                }
            }
        }
        $navController = (string)$this->request->getParam('controller');
        $navAction = (string)$this->request->getParam('action');
        $isHome = $navController === 'Grids';
        $isAccount = $navController === 'Users' && in_array($navAction, ['account', 'changePassword'], true);
        $isAdminNav = !$isHome && !$isAccount;
        ?>
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <?= $this->Html->link(
                    $isHome
                        ? 'Accueil <span class="visually-hidden">(page courante)</span>'
                        : 'Accueil',
                    '/',
                    [
                        'class' => 'nav-link' . ($isHome ? ' active' : ''),
                        'escape' => false,
                        'aria-current' => $isHome ? 'page' : false,
                    ]
                ) ?>
            </li>
            <?php if ($loggedIn && ($roleId === 2 || $canAdmin)): ?>
                <li class="nav-item">
                    <?= $this->Html->link(
                        $isAdminNav
                            ? 'Administration <span class="visually-hidden">(page courante)</span>'
                            : 'Administration',
                        ['controller' => 'Pages', 'action' => 'display', 'admin'],
                        [
                            'class' => 'nav-link' . ($isAdminNav ? ' active' : ''),
                            'escape' => false,
                            'aria-current' => $isAdminNav ? 'page' : false,
                        ]
                    ) ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <ul class="navbar-nav ms-auto">
        <?php if ($loggedIn && ($roleId === 1 || $roleId === 2 || $canAdmin)): ?>
            <?php
            $bjStatusUrl = $this->Url->build(['controller' => 'BackgroundJobs', 'action' => 'status', '_ext' => 'json']);
            $bjIndexUrl = $this->Url->build(['controller' => 'BackgroundJobs', 'action' => 'index']);
            $this->Html->script('background-jobs-badge', [
                'block' => true,
                'timestamp' => 'force',
            ]);
            ?>
            <li class="nav-item dropdown bj-badge-idle"
                id="background-jobs-badge"
                data-url-status="<?= h($bjStatusUrl) ?>"
                data-url-index="<?= h($bjIndexUrl) ?>">
                <a class="nav-link dropdown-toggle"
                   href="#"
                   id="backgroundJobsDropdown"
                   role="button"
                   data-bs-toggle="dropdown"
                   aria-expanded="false"
                   title="Jobs en file / en cours">
                    <i class="bi bi-list-task me-1"></i>
                    <span class="badge bg-secondary" data-bj-count>0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow"
                     aria-labelledby="backgroundJobsDropdown"
                     data-bj-menu
                     style="min-width: 280px; max-width: 320px;">
                    <span class="dropdown-item-text text-muted small">Chargement…</span>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-primary" href="<?= h($bjIndexUrl) ?>">
                        <i class="bi bi-arrow-right-circle me-1"></i> Voir tout
                    </a>
                </div>
            </li>
        <?php endif; ?>
        <li class="nav-item text-nowrap">
            <?= $this->Html->link(
                '<i class="bi bi-person-square me-1"></i> Mon profil',
                ['controller' => 'Users', 'action' => 'account'],
                [
                    'class' => 'nav-link' . ($isAccount ? ' active' : ''),
                    'escape' => false,
                    'title' => 'Mon compte',
                    'aria-current' => $isAccount ? 'page' : false,
                ]
            ) ?>
        </li>
        <li class="nav-item text-nowrap">
            <?= $this->Html->link(
                '<i class="bi bi-box-arrow-right me-1"></i> Déconnexion',
                ['controller' => 'Users', 'action' => 'logout'],
                ['class' => 'nav-link', 'escape' => false, 'title' => 'Déconnexion']
            ) ?>
        </li>
    </ul>
    </div>
</nav>
