<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\FixedActivityRule $rule */
?>
<?php $this->assign('title', 'Nouvelle activité fixe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="crud-app fixed-activity-rules form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouvelle règle d'activité fixe
        </h1>
        <div class="crud-header-actions">
            <input type="hidden" name="active" value="0" form="rule-form">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" id="active-switch" name="active" value="1" form="rule-form" checked>
                <label class="form-check-label" for="active-switch">Actif</label>
            </div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
        <?= $this->Form->create($rule, ['id' => 'rule-form']) ?>

        <ul class="nav nav-tabs mb-4" id="rule-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-scope-tab" data-bs-toggle="tab" href="#tab-scope" role="tab" aria-controls="tab-scope" aria-selected="true">
                    <i class="bi bi-info-circle"></i> Portée &amp; activité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-schedule-tab" data-bs-toggle="tab" href="#tab-schedule" role="tab" aria-controls="tab-schedule" aria-selected="false">
                    <i class="bi bi-clock"></i> Horaires &amp; fréquence
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-equity-tab" data-bs-toggle="tab" href="#tab-equity" role="tab" aria-controls="tab-equity" aria-selected="false">
                    <i class="bi bi-people"></i> Couverture &amp; équité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-planning-tab" data-bs-toggle="tab" href="#tab-planning" role="tab" aria-controls="tab-planning" aria-selected="false">
                    <i class="bi bi-calendar2-range"></i> Planification &amp; repas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-incompat-tab" data-bs-toggle="tab" href="#tab-incompat" role="tab" aria-controls="tab-incompat" aria-selected="false">
                    <i class="bi bi-x-octagon"></i> Incompatibilités
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <?php // --- Onglet 1 : Portée & activité --- ?>
            <div class="tab-pane fade show active" id="tab-scope" role="tabpanel" aria-labelledby="tab-scope-tab">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-tag"></i> Offre</label>
                        <?= $this->Form->control('offer_id', [
                            'type' => 'select',
                            'options' => $offers,
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-diagram-3"></i> Mode site</label>
                        <div class="mb-2">
                            <div class="form-check">
                                <?= $this->Form->radio('site_mode', [
                                    ['value' => 'per_site', 'text' => '<i class="bi bi-building"></i> Par site (une offre par site sélectionné)', 'id' => 'sitemode-per']
                                ], ['default' => 'per_site', 'hiddenField' => false, 'escape' => false]) ?>
                            </div>
                            <div class="form-check">
                                <?= $this->Form->radio('site_mode', [
                                    ['value' => 'pooled', 'text' => '<i class="bi bi-diagram-3"></i> Mutualisé (sites sélectionnés regroupés)', 'id' => 'sitemode-pooled']
                                ], ['hiddenField' => false, 'escape' => false]) ?>
                            </div>
                            <div class="form-check">
                                <?= $this->Form->radio('site_mode', [
                                    ['value' => 'global', 'text' => '<i class="bi bi-globe"></i> Global (tous sites)', 'id' => 'sitemode-global']
                                ], ['hiddenField' => false, 'escape' => false]) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Bon à savoir</strong> : l'offre sélectionnée sert aussi de critère d'équité.
                    Les agents compétents sur cette offre seront répartis équitablement
                    <strong>par site</strong> en mode « Par site », ou <strong>sur l'ensemble des sites</strong>
                    en mode « Mutualisé » / « Global ».
                    Si deux équipes traitent des flux réellement différents (compétences ou besoins distincts),
                    créez deux offres et deux règles distinctes pour ne pas les mélanger.
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-building"></i> Sites concernés</label>
                    <div class="p-3 border rounded bg-light" style="max-height: 220px; overflow-y: auto;">
                        <?php
                            $selectedSiteIds = [];
                            if (!empty($rule->sites)) {
                                $selectedSiteIds = array_map(fn($s) => $s->id, (array)$rule->sites);
                            }
                        ?>
                        <div class="row">
                            <?php foreach ($sites as $id => $name): ?>
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               name="sites[_ids][]" 
                                               value="<?= h($id) ?>" 
                                               class="form-check-input site-checkbox" 
                                               id="site-<?= h($id) ?>"
                                               <?= in_array($id, $selectedSiteIds) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="site-<?= h($id) ?>">
                                            <?= h($name) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <input type="hidden" name="sites[_ids][]" value="">
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle"></i>
                        Mode global: aucun site à sélectionner. Modes par site/mutualisé: sélectionner les sites.
                    </small>
                </div>
            </div>

            <?php // --- Onglet 2 : Horaires & fréquence --- ?>
            <div class="tab-pane fade" id="tab-schedule" role="tabpanel" aria-labelledby="tab-schedule-tab">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-history"></i> Heure de début</label>
                        <?= $this->Form->control('start_time', [
                            'type' => 'time',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-fill"></i> Heure de fin</label>
                        <?= $this->Form->control('end_time', [
                            'type' => 'time',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hash"></i> Quantité</label>
                        <?= $this->Form->control('quantity', [
                            'type' => 'number',
                            'min' => 1,
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-calendar-week"></i> Jours de la semaine</label>
                    <div class="d-flex flex-wrap" style="gap: 2rem;">
                        <?php foreach ($daysOptions as $val => $label): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="days_of_week_selected[]" id="dow-<?= (int)$val ?>" value="<?= (int)$val ?>" <?= in_array($val, (array)$selectedDays, true) ? 'checked' : '' ?> style="margin-right: 0.5rem;">
                                <label class="form-check-label" for="dow-<?= (int)$val ?>"><?= h($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="bi bi-info-circle"></i>
                        Si aucun jour n'est sélectionné, la règle s'appliquera tous les jours.
                    </small>
                </div>
            </div>

            <?php // --- Onglet 3 : Couverture & équité --- ?>
            <div class="tab-pane fade" id="tab-equity" role="tabpanel" aria-labelledby="tab-equity-tab">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->control('allow_shortfall', [
                                'type' => 'checkbox',
                                'label' => '<i class="bi bi-exclamation-triangle"></i> Accepter le shortfall',
                                'escape' => false,
                            ]) ?>
                            <small class="text-muted d-block">
                                Si coché, cette activité pourra ne pas être couverte pour préserver l'équité des activités prioritaires.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-people"></i> Équité (sur la période)</label>
                        <?= $this->Form->select('equity_enabled', [
                            '' => 'Hériter de l’offre',
                            '1' => 'Activée',
                            '0' => 'Désactivée',
                        ], [
                            'class' => 'form-control',
                            'value' => '',
                        ]) ?>
                        <small class="text-muted d-block">“Hériter” utilise le paramètre de l’offre sélectionnée.</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-diagram-2"></i> Groupe d'équité (ID partagé)</label>
                        <?= $this->Form->control('equity_group_id', [
                            'type' => 'text',
                            'label' => false,
                            'class' => 'form-control',
                            'placeholder' => 'ex: FRONT',
                        ]) ?>
                        <small class="text-muted d-block" title="Mettre le même identifiant (ex: FRONT) sur plusieurs activités pour qu'elles partagent le même compteur d'équité.">
                            <i class="bi bi-info-circle"></i>
                            Mettre le même identifiant (ex: FRONT) sur plusieurs activités pour qu'elles partagent le même compteur d'équité.
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-sort-numeric-down"></i> Ordre de résolution</label>
                        <?= $this->Form->control('sort_order', [
                            'type' => 'number',
                            'min' => 0,
                            'label' => false,
                            'class' => 'form-control',
                            'default' => 0,
                        ]) ?>
                        <small class="text-muted d-block" title="Ordre de résolution : les activités de même groupe (même equity_group_id) sont résolues ensemble. 1 = priorité la plus haute, puis 2, 3…">
                            <i class="bi bi-info-circle"></i>
                            Ordre de résolution : les activités de même groupe (même equity_group_id) sont résolues ensemble. 1 = priorité la plus haute, puis 2, 3…
                        </small>
                    </div>
                </div>
            </div>

            <?php // --- Onglet 4 : Planification & repas --- ?>
            <div class="tab-pane fade" id="tab-planning" role="tabpanel" aria-labelledby="tab-planning-tab">
                <div class="form-check mb-3">
                    <?= $this->Form->control('is_splittable', [
                        'type' => 'checkbox',
                        'label' => '<i class="bi bi-scissors"></i> Scindable (relais autorisés)',
                        'escape' => false
                    ]) ?>
                    <small class="text-muted d-block">Permet les relais entre agents au sein de la journée</small>
                </div>
                <div id="blocks-container" class="mt-3" style="display:none;" data-next-index="0">
                    <h6><i class="bi bi-layout-three-columns"></i> Blocs intra-journée (optionnels)</h6>
                    <small class="text-muted d-block mb-2">
                        Définissez des sous-plages horaires (par ex. 09:00-12:00 et 14:00-17:00) pour favoriser la répartition entre agents.
                    </small>
                    <div id="blocks-rows"></div>
                    <button type="button" id="add-block-btn" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Ajouter un bloc
                    </button>
                    <!-- Template de ligne de bloc -->
                    <div id="block-row-template" class="row mb-2 d-none">
                        <div class="col-md-3">
                            <?= $this->Form->control('fixed_activity_blocks.__INDEX__.start_time', [
                                'type' => 'time',
                                'label' => 'Début bloc',
                                'class' => 'form-control',
                                'empty' => true,
                                'required' => false,
                            ]) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $this->Form->control('fixed_activity_blocks.__INDEX__.end_time', [
                                'type' => 'time',
                                'label' => 'Fin bloc',
                                'class' => 'form-control',
                                'empty' => true,
                                'required' => false,
                            ]) ?>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-block-btn">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->control('lunch_overlap_allowed', [
                                'type' => 'checkbox',
                                'label' => '<i class="bi bi-cup-hot"></i> Autoriser le repas à recouvrir cette activité',
                                'escape' => false,
                                'checked' => true,
                            ]) ?>
                            <small class="text-muted d-block">
                                Si décoché, le repas devra être planifié en dehors de cette activité fixe.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="bi bi-arrow-left-right"></i> Préférence de position du repas
                        </label>
                        <?= $this->Form->control('lunch_attach_mode', [
                            'type' => 'select',
                            'label' => false,
                            'options' => [
                                'none' => 'Aucune préférence particulière',
                                'before' => 'De préférence juste AVANT cette activité',
                                'after' => 'De préférence juste APRÈS cette activité',
                            ],
                            'empty' => false,
                            'default' => 'none',
                            'class' => 'form-control',
                        ]) ?>
                        <small class="text-muted d-block">
                            Si activée, la préférence est traitée comme forte : le solveur collera le repas
                            avant/après cette activité dès que la couverture le permet.
                        </small>
                    </div>
                </div>
            </div>

            <?php // --- Onglet 5 : Incompatibilités --- ?>
            <div class="tab-pane fade" id="tab-incompat" role="tabpanel" aria-labelledby="tab-incompat-tab">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-slash-circle"></i> Offres incompatibles ce jour-là</label>
                    <div class="p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;">
                        <?php 
                            // On récupère les IDs déjà sélectionnés pour pré-cocher
                            $selectedIds = [];
                            if (!empty($rule->incompatible_offers)) {
                                $selectedIds = array_map(function($o) { return $o->id; }, $rule->incompatible_offers);
                            }
                        ?>
                        <div class="row">
                            <?php foreach ($offers as $id => $name): ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               name="incompatible_offers[_ids][]" 
                                               value="<?= h($id) ?>" 
                                               class="form-check-input" 
                                               id="inc-offer-<?= h($id) ?>"
                                               <?= in_array($id, $selectedIds) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="inc-offer-<?= h($id) ?>">
                                            <?= h($name) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <input type="hidden" name="incompatible_offers[_ids][]" value="">
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block">
                        <i class="bi bi-info-circle"></i>
                        Si un agent est planifié sur cette activité fixe, il ne pourra PAS être planifié sur les offres cochées le même jour.
                    </small>
                </div>
            </div>
        </div>

        <div class="crud-actions-bar">
            <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer', [
                'class' => 'btn btn-primary',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>

        <?= $this->Form->end() ?>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
document.addEventListener('DOMContentLoaded', function() {
  const radios = document.querySelectorAll('input[name="site_mode"]');
  const siteCheckboxes = document.querySelectorAll('input.site-checkbox');
  const splitCheckbox = document.getElementById('is-splittable');
  const blocksContainer = document.getElementById('blocks-container');
  const blocksRows = document.getElementById('blocks-rows');
  const blockTemplate = document.getElementById('block-row-template');
  const addBlockBtn = document.getElementById('add-block-btn');
  if (radios.length === 0) return;
  
  const sync = () => {
    const mode = document.querySelector('input[name="site_mode"]:checked')?.value;
    if (mode === 'global') {
      siteCheckboxes.forEach(cb => { cb.checked = false; cb.disabled = true; });
    } else {
      siteCheckboxes.forEach(cb => { cb.disabled = false; });
    }
  };
  
  sync(); // Init on load
  radios.forEach(r => r.addEventListener('change', sync));

  if (splitCheckbox && blocksContainer) {
    const syncBlocks = () => {
      blocksContainer.style.display = splitCheckbox.checked ? 'block' : 'none';
      if (splitCheckbox.checked && blocksRows && blocksRows.children.length === 0) {
        // Ajouter un premier bloc automatiquement
        addBlockRow();
      }
    };
    syncBlocks();
    splitCheckbox.addEventListener('change', syncBlocks);
  }

  function addBlockRow() {
    if (!blockTemplate || !blocksContainer || !blocksRows) return;
    const nextIndex = parseInt(blocksContainer.getAttribute('data-next-index') || '0', 10);
    const clone = blockTemplate.cloneNode(true);
    clone.id = '';
    clone.classList.remove('d-none');
    // Remplacer __INDEX__ dans les noms des champs
    clone.querySelectorAll('input').forEach((input) => {
      const name = input.getAttribute('name');
      if (!name) return;
      input.setAttribute('name', name.replace('__INDEX__', String(nextIndex)));
      const id = input.getAttribute('id');
      if (id) {
        input.setAttribute('id', id.replace('__INDEX__', String(nextIndex)));
      }
    });
    blocksRows.appendChild(clone);
    blocksContainer.setAttribute('data-next-index', String(nextIndex + 1));
  }

  if (addBlockBtn) {
    addBlockBtn.addEventListener('click', addBlockRow);
  }

  if (blocksRows) {
    blocksRows.addEventListener('click', function (e) {
      var target = e.target;
      if (!target) return;
      var btn = target.closest ? target.closest('.remove-block-btn') : null;
      if (btn) {
        var row = btn.closest ? btn.closest('.row') : null;
        if (row && blocksRows.contains(row)) {
          blocksRows.removeChild(row);
        }
      }
    });
  }

  // Ouverture automatique de l'onglet contenant une erreur de validation
  const invalid = document.querySelector('.tab-pane .is-invalid, .tab-pane .invalid-feedback');
  if (invalid) {
    const pane = invalid.closest('.tab-pane');
    if (pane) {
      const id = pane.id;
      document.querySelectorAll('.nav-tabs a').forEach(a => a.classList.remove('active'));
      document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
      const link = document.querySelector('.nav-tabs a[href="#' + id + '"]');
      if (link) link.classList.add('active');
      pane.classList.add('show', 'active');
    }
  }
});
<?php $this->Html->scriptEnd(); ?>
