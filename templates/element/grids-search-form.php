<?php
/**
 * @var \App\View\AppView $this
 * @var array $users
 * @var array $offers
 * @var array $sites
 * @var array $day_ranges
 * @var array|null $searchUrl
 */
$zoom = $zoom ?? '15';
$sortBy = $sortBy ?? (string)$this->request->getQuery('sort_by', 'site_name');

echo $this->Form->create(null, [
    'url' => $searchUrl ?? ['action' => 'index'],
    'type' => 'get',
    'valueSources' => ['query', 'context'],
    'class' => 'grids-filters-form',
]);
if (!empty($embedMode)) {
    echo $this->Form->hidden('embed', ['value' => '1']);
}
if ($zoom === 'hour') {
    echo $this->Form->hidden('zoom', ['value' => 'hour']);
}
if ($sortBy !== 'site_name') {
    echo $this->Form->hidden('sort_by', ['value' => $sortBy]);
}

$offerIdParam = $this->request->getQuery('offer_id');
$selectedOfferIds = is_array($offerIdParam)
    ? array_values(array_filter(array_map('intval', $offerIdParam)))
    : ((int)$offerIdParam > 0 ? [(int)$offerIdParam] : []);
$offerLabel = count($selectedOfferIds) === 0
    ? 'Toutes'
    : (count($selectedOfferIds) === 1 ? (string)($offers[$selectedOfferIds[0]] ?? '1 offre') : count($selectedOfferIds) . ' offres');

$currentAction = $this->request->getParam('action');
$zoomBase = ['action' => $currentAction];
if ($currentAction === 'draft') {
    $pass = $this->request->getParam('pass');
    if (!empty($pass)) {
        $zoomBase[] = $pass[0];
    }
}
?>
<div class="grids-chrome-row">
    <label class="visually-hidden" for="date-start">Période</label>
    <?= $this->Form->text('date_start', [
        'id' => 'date-start',
        'class' => 'form-control form-control-sm',
        'style' => 'width: 118px;',
        'placeholder' => 'Début',
        'value' => is_array($day_ranges) && isset($day_ranges[0]) ? (string)$day_ranges[0] : null,
    ]) ?>
    <?= $this->Form->text('date_end', [
        'id' => 'date-end',
        'class' => 'form-control form-control-sm',
        'style' => 'width: 118px;',
        'readonly' => true,
        'placeholder' => 'Fin',
        'value' => is_array($day_ranges) && isset($day_ranges[1]) ? (string)$day_ranges[1] : null,
    ]) ?>
    <?= $this->Form->select('site_id', $sites, [
        'empty' => 'Site',
        'class' => 'form-control form-control-sm',
        'style' => 'width: 140px;',
        'id' => 'site-filter',
    ]) ?>
    <?= $this->Form->select('user_id', $users, [
        'empty' => 'Agent',
        'class' => 'form-control form-control-sm',
        'style' => 'width: 160px;',
        'id' => 'user-filter',
    ]) ?>
    <div class="dropdown">
        <button type="button" class="form-control form-control-sm dropdown-toggle text-start" data-bs-toggle="dropdown" id="offer-filter-toggle" style="width: 140px;">
            <span class="offer-filter-label text-truncate"><?= h($offerLabel) ?></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end py-1 px-2" style="max-height: 280px; overflow-y: auto; min-width: 200px;">
            <div class="small mb-1 px-2">
                <a href="#" class="offer-filter-check-all">Tout</a> /
                <a href="#" class="offer-filter-uncheck-all">Rien</a>
            </div>
            <?php foreach ($offers as $offerId => $offerName) : ?>
                <div class="form-check dropdown-item-text py-0">
                    <?= $this->Form->checkbox('offer_id[]', [
                        'value' => $offerId,
                        'id' => 'offer-filter-' . (int)$offerId,
                        'class' => 'form-check-input offer-filter-cb',
                        'checked' => in_array((int)$offerId, $selectedOfferIds, true),
                        'hiddenField' => false,
                    ]) ?>
                    <label class="form-check-label small w-100" for="offer-filter-<?= (int)$offerId ?>"><?= h($offerName) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?= $this->Form->button('Rechercher', [
        'type' => 'submit',
        'class' => 'btn btn-grids-primary btn-sm',
    ]) ?>
    <?php
    $resetUrl = ['action' => $currentAction];
    if ($currentAction === 'draft') {
        $pass = $this->request->getParam('pass');
        if (!empty($pass)) {
            $resetUrl[] = $pass[0];
            $resetUrl['?'] = ['embed' => '1'];
        }
    }
    echo $this->Html->link(
        '<i class="bi bi-arrow-counterclockwise"></i>',
        $resetUrl,
        ['class' => 'btn btn-grids-ghost btn-sm', 'escape' => false, 'title' => 'Réinitialiser']
    );
    ?>

    <!-- Conteneur poussé à droite -->
    <div style="margin-left: auto; display: flex; align-items: center; gap: 0.75rem;">
        <select id="sort-select" class="form-control form-control-sm" style="width: 130px;" title="Tri">
            <option value="site_name" <?= $sortBy === 'site_name' ? 'selected' : '' ?>>Site (A-Z)</option>
            <option value="last_name" <?= $sortBy === 'last_name' ? 'selected' : '' ?>>Nom (A-Z)</option>
            <option value="user_code" <?= $sortBy === 'user_code' ? 'selected' : '' ?>>Code agent</option>
        </select>
        <button type="button" id="toggle-site-column" class="btn btn-grids-ghost btn-sm" title="Afficher ou masquer la colonne Site">
            <span id="toggle-site-text">Masquer site</span>
        </button>
        <?php if (isset($canAlertsAdd) && $canAlertsAdd): ?>
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#alertAddModal" style="padding: 0 0.75rem;">
                <i class="bi bi-bell-fill pe-1"></i> Ajouter une alerte
            </button>
        <?php endif; ?>
    </div>
</div>
<?php
$chips = [];
$chipBase = $this->request->getQueryParams();
if (!empty($chipBase['site_id']) && isset($sites[(int)$chipBase['site_id']])) {
    $next = $chipBase;
    unset($next['site_id']);
    $chips[] = ['label' => 'Site : ' . $sites[(int)$chipBase['site_id']], 'url' => $zoomBase + ['?' => $next]];
}
if (!empty($chipBase['user_id']) && isset($users[(int)$chipBase['user_id']])) {
    $next = $chipBase;
    unset($next['user_id']);
    $chips[] = ['label' => 'Agent : ' . $users[(int)$chipBase['user_id']], 'url' => $zoomBase + ['?' => $next]];
}
if (count($selectedOfferIds) > 0) {
    $next = $chipBase;
    unset($next['offer_id']);
    $chips[] = ['label' => 'Offre : ' . $offerLabel, 'url' => $zoomBase + ['?' => $next]];
}
if ($chips):
?>
<div class="grids-chips">
    <?php foreach ($chips as $chip): ?>
        <?= $this->Html->link(h($chip['label']) . ' ×', $chip['url'], ['class' => 'grids-chip', 'escape' => false]) ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php echo $this->Form->end(); ?>

<?php
$this->Html->scriptStart(['block' => true]);
?>
document.addEventListener('DOMContentLoaded', function() {
    const siteSelect = document.getElementById('site-filter');
    const userSelect = document.getElementById('user-filter');
    const form = document.querySelector('.grids-filters-form');
    const ajaxUrl = <?= json_encode($this->Url->build(['controller' => 'Grids', 'action' => 'getUsersBySite', '_ext' => 'json'])) ?>;

    function requestSubmitForm() {
        if (form && typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else if (form) {
            form.submit();
        }
    }

    function loadUsers(siteId) {
        const url = siteId ? ajaxUrl + '?site_id=' + encodeURIComponent(siteId) : ajaxUrl;
        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !data.users || !userSelect) {
                    return;
                }
                const currentValue = userSelect.value;
                userSelect.innerHTML = '<option value=""></option>';
                data.users.forEach((user) => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    userSelect.appendChild(option);
                });
                if (currentValue && Array.from(userSelect.options).some((opt) => opt.value === currentValue)) {
                    userSelect.value = currentValue;
                }
            });
    }

    if (siteSelect && userSelect) {
        siteSelect.addEventListener('change', function () {
            loadUsers(this.value);
            requestSubmitForm();
        });
        if (siteSelect.value) {
            loadUsers(siteSelect.value);
        }
    }
    if (userSelect) {
        userSelect.addEventListener('change', function () {
            requestSubmitForm();
        });
    }
    const offerToggle = document.querySelector('#offer-filter-toggle');
    const offerDropdownMenu = offerToggle ? offerToggle.nextElementSibling : null;
    if (offerDropdownMenu && offerDropdownMenu.classList.contains('dropdown-menu')) {
        offerDropdownMenu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }
    document.querySelectorAll('input[name="offer_id[]"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            requestSubmitForm();
        });
    });
    const checkAll = document.querySelector('.offer-filter-check-all');
    const uncheckAll = document.querySelector('.offer-filter-uncheck-all');
    if (checkAll) {
        checkAll.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.offer-filter-cb').forEach(function (c) { c.checked = true; });
            requestSubmitForm();
        });
    }
    if (uncheckAll) {
        uncheckAll.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.offer-filter-cb').forEach(function (c) { c.checked = false; });
            requestSubmitForm();
        });
    }
});
<?php
$this->Html->scriptEnd();
