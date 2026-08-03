<?php
/**
 * @var \App\View\AppView $this
 * @var array $users
 * @var array $offers  
 * @var array $sites
 * @var array $day_ranges
 * @var array|null $searchUrl
 */

echo $this->Form->create(null, [
    'url' => $searchUrl ?? ['action' => 'index'],
    'type' => 'get',
    'valueSources' => ['query', 'context'],
    'class' => 'w-100'
]);
// Mode iframe workspace : conserver embed=1 dans les filtres GET
if (!empty($embedMode)) {
    echo $this->Form->hidden('embed', ['value' => '1']);
}
?>
<div class="form-row align-items-center justify-content-center w-100">
    <div class="col-auto">
        <label class="sr-only" for="date-start">Date Start</label>
        <div class="input-group input-group-sm mt-2 mb-2">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="bi bi-calendar-event mr-1"></i> Début
                </div>
            </div>
            <?php echo $this->Form->text('date_start', [
                'id' => 'date-start',
                'class' => 'form-control form-control-sm',
                'style' => 'width: 120px;',
                'placeholder' => 'jj/mm/aaaa',
                'value' => is_array($day_ranges) && isset($day_ranges[0]) ? (string)$day_ranges[0] : null,
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <label class="sr-only" for="date-end">Date End</label>
        <div class="input-group input-group-sm mt-2 mb-2">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="bi bi-calendar-x mr-1"></i> Fin
                </div>
            </div>
            <?php echo $this->Form->text('date_end', [
                'id' => 'date-end',
                'class' => 'form-control form-control-sm',
                'style' => 'width: 120px;',
                'readonly' => true,
                'placeholder' => 'jj/mm/aaaa',
                'value' => is_array($day_ranges) && isset($day_ranges[1]) ? (string)$day_ranges[1] : null,
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <div class="input-group input-group-sm mt-2 mb-2">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="bi bi-building mr-1"></i> Site
                </div>
            </div>
            <?php echo $this->Form->select('site_id', $sites, [
                'empty' => 'Tous',
                'class' => 'form-control form-control-sm',
                'style' => 'width: 140px;',
                'id' => 'site-filter'
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <div class="input-group input-group-sm mt-2 mb-2">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="bi bi-person mr-1"></i> Agent
                </div>
            </div>
            <?php echo $this->Form->select('user_id', $users, [
                'empty' => 'Tous',
                'class' => 'form-control form-control-sm',
                'style' => 'width: 160px;',
                'id' => 'user-filter'
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <?php
        $offerIdParam = $this->request->getQuery('offer_id');
        $selectedOfferIds = is_array($offerIdParam)
            ? array_values(array_filter(array_map('intval', $offerIdParam)))
            : ((int)$offerIdParam > 0 ? [(int)$offerIdParam] : []);
        $offerLabel = count($selectedOfferIds) === 0 ? 'Toutes' : (count($selectedOfferIds) === 1 ? (string)($offers[$selectedOfferIds[0]] ?? '1 offre') : count($selectedOfferIds) . ' offres');
        ?>
        <div class="input-group input-group-sm mt-2 mb-2">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="bi bi-basket mr-1"></i> Offre
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="form-control form-control-sm dropdown-toggle text-left d-flex align-items-center" data-toggle="dropdown" id="offer-filter-toggle" style="width: 140px; min-width: 140px;" aria-haspopup="true" aria-expanded="false">
                    <span class="offer-filter-label text-truncate"><?= h($offerLabel) ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right py-1 px-2" style="max-height: 280px; overflow-y: auto; min-width: 200px;">
                <div class="small mb-1 px-2">
                    <a href="#" class="offer-filter-check-all">Tout</a> /
                    <a href="#" class="offer-filter-uncheck-all">Rien</a>
                </div>
                <?php foreach ($offers as $offerId => $offerName) : ?>
                    <div class="form-check dropdown-item-text py-0">
                        <?php echo $this->Form->checkbox('offer_id[]', [
                            'value' => $offerId,
                            'id' => 'offer-filter-' . (int)$offerId,
                            'class' => 'form-check-input offer-filter-cb',
                            'checked' => in_array((int)$offerId, $selectedOfferIds, true),
                            'hiddenField' => false,
                        ]); ?>
                        <label class="form-check-label small w-100" for="offer-filter-<?= (int)$offerId ?>"><?= h($offerName) ?></label>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-auto">
        <?php echo $this->Form->button('<i class="bi bi-search mr-1"></i> Rechercher', [
            'type' => 'submit',
            'class' => 'btn btn-primary btn-sm mt-2 mb-2',
            'escapeTitle' => false
        ]); ?>
        <?php
        // Bouton Réinitialiser
        // On récupère l'action courante pour savoir si on est sur 'index' ou 'draft'
        $currentAction = $this->request->getParam('action');
        $resetUrl = ['action' => $currentAction];
        
        // Si on est sur 'draft', il faut conserver l'ID du job qui est dans 'pass'
        if ($currentAction === 'draft') {
            $pass = $this->request->getParam('pass');
            if (!empty($pass)) {
                $resetUrl[] = $pass[0];
            }
        }
        
        echo $this->Html->link(
            '<i class="bi bi-arrow-counterclockwise"></i>',
            $resetUrl,
            [
                'class' => 'btn btn-outline-secondary btn-sm ml-1 mt-2 mb-2',
                'escape' => false,
                'title' => 'Réinitialiser les filtres'
            ]
        );
        ?>
    </div>
</div>
<?php echo $this->Form->end();

// JavaScript pour le filtrage dynamique des agents par site
$this->Html->scriptStart(['block' => true]);
?>
document.addEventListener('DOMContentLoaded', function() {
    const siteSelect = document.getElementById('site-filter');
    const userSelect = document.getElementById('user-filter');
    const ajaxUrl = '<?= $this->Url->build(['controller' => 'Grids', 'action' => 'getUsersBySite', '_ext' => 'json']); ?>';
    
    // Sauvegarder la valeur initiale de l'agent sélectionné
    const initialUserId = userSelect.value;
    
    // Fonction pour charger les agents
    function loadUsers(siteId) {
        const url = siteId ? `${ajaxUrl}?site_id=${encodeURIComponent(siteId)}` : ajaxUrl;
        
        fetch(url, {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users) {
                // Sauvegarder la valeur actuelle
                const currentValue = userSelect.value;
                
                // Vider le select
                userSelect.innerHTML = '<option value=""> </option>';
                
                // Ajouter les nouveaux utilisateurs
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    userSelect.appendChild(option);
                });
                
                // Restaurer la sélection si l'utilisateur existe toujours dans la liste
                if (currentValue) {
                    const optionExists = Array.from(userSelect.options).some(opt => opt.value === currentValue);
                    if (optionExists) {
                        userSelect.value = currentValue;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des agents:', error);
        });
    }
    
    // Écouter les changements du select Site
    if (siteSelect && userSelect) {
        siteSelect.addEventListener('change', function() {
            loadUsers(this.value);
            // Soumission automatique du formulaire lors du changement de site
            this.form.submit();
        });
        
        // Charger les agents filtrés si un site est déjà sélectionné au chargement
        if (siteSelect.value) {
            loadUsers(siteSelect.value);
        }
    }

    // Soumission automatique pour les autres filtres (Agent, Offres)
    const userFilter = document.getElementById('user-filter');
    if (userFilter) {
        userFilter.addEventListener('change', function() {
            this.form.submit();
        });
    }
    const offerDropdownMenu = document.querySelector('#offer-filter-toggle') && document.querySelector('#offer-filter-toggle').nextElementSibling;
    if (offerDropdownMenu && offerDropdownMenu.classList.contains('dropdown-menu')) {
        offerDropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    document.querySelectorAll('input[name="offer_id[]"]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Tout cocher / Tout décocher pour les offres
    const checkAll = document.querySelector('.offer-filter-check-all');
    const uncheckAll = document.querySelector('.offer-filter-uncheck-all');
    if (checkAll) {
        checkAll.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.offer-filter-cb').forEach(function(c) { c.checked = true; });
            checkAll.closest('form').submit();
        });
    }
    if (uncheckAll) {
        uncheckAll.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.offer-filter-cb').forEach(function(c) { c.checked = false; });
            uncheckAll.closest('form').submit();
        });
    }

    // Soumission automatique pour les dates (via DateRangePicker ou changement direct)
    const dateInputs = document.querySelectorAll('#date-start, #date-end');
    dateInputs.forEach(input => {
        // Écouter l'événement 'apply.daterangepicker' spécifique à la librairie utilisée
        $(input).on('apply.daterangepicker', function(ev, picker) {
            $(this).closest('form').submit();
        });
        
        // Fallback pour changement manuel (si pas de daterangepicker ou saisie clavier)
        input.addEventListener('change', function() {
            // Petit délai pour éviter double soumission si le daterangepicker déclenche aussi change
            setTimeout(() => {
                this.form.submit();
            }, 100);
        });
    });
});
<?php
$this->Html->scriptEnd();

