<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\FixedActivityRule $rule */
?>
<?php $this->assign('title', 'Éditer activité fixe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            <?= $rule->isNew() ? 'Nouvelle règle d\'activité fixe' : 'Éditer règle #' . h($rule->id) ?>
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($rule) ?>
        
        <?php // --- Section Informations générales --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations générales
            </div>
            <div class="card-body">
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
                                ], ['hiddenField' => false, 'escape' => false]) ?>
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
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label"><i class="bi bi-building"></i> Sites concernés</label>
                        <?= $this->Form->control('sites._ids', [
                            'type' => 'select',
                            'multiple' => 'multiple',
                            'options' => $sites,
                            'label' => false,
                            'class' => 'form-control',
                            'id' => 'sitesSelect'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Mode global: aucun site à sélectionner. Modes par site/mutualisé: sélectionner les sites.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Section Horaires et quantité --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-clock"></i> Horaires et quantité
            </div>
            <div class="card-body">
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
            </div>
        </div>

        <?php // --- Section Jours de la semaine --- ?>
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-calendar-week"></i> Jours de la semaine
            </div>
            <div class="card-body">
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

        <?php // --- Section Options --- ?>
        <div class="card border-secondary mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-sliders"></i> Options
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="bi bi-exclamation-triangle"></i> Priorité (Poids 0-100)</label>
                        <?= $this->Form->control('priority', [
                            'type' => 'number',
                            'min' => 0,
                            'max' => 100,
                            'label' => false,
                            'class' => 'form-control',
                            'default' => 0
                        ]) ?>
                        <small class="text-muted d-block">Plus la valeur est élevée, plus cette règle sera traitée en priorité</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-check">
                            <?= $this->Form->control('active', [
                                'type' => 'checkbox',
                                'label' => '<i class="bi bi-power"></i> Actif',
                                'escape' => false
                            ]) ?>
                            <small class="text-muted d-block">Activer ou désactiver cette règle</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-check">
                            <?= $this->Form->control('is_splittable', [
                                'type' => 'checkbox',
                                'label' => '<i class="bi bi-scissors"></i> Scindable (relais autorisés)',
                                'escape' => false
                            ]) ?>
                            <small class="text-muted d-block">Permet les relais entre agents au sein de la journée</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="bi bi-people"></i> Équité (sur la période)</label>
                        <?php
                        $equityValue = '';
                        if ($rule->equity_enabled === true) {
                            $equityValue = '1';
                        } elseif ($rule->equity_enabled === false) {
                            $equityValue = '0';
                        }
                        ?>
                        <?= $this->Form->select('equity_enabled', [
                            '' => 'Hériter de l’offre',
                            '1' => 'Activée',
                            '0' => 'Désactivée',
                        ], [
                            'class' => 'form-control',
                            'value' => $equityValue,
                        ]) ?>
                        <small class="text-muted d-block">“Hériter” utilise le paramètre de l’offre sélectionnée.</small>
                    </div>
                    <div class="col-md-3 mb-3">
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
                        <label class="form-label"><i class="bi bi-speedometer2"></i> Force de l'équité (0-100%)</label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range flex-grow-1 mr-3" min="0" max="100" step="10" id="equity-strength-range" 
                                   name="equity_strength" value="<?= $rule->equity_strength ?? 0 ?>">
                            <span class="badge bg-primary" id="equity-strength-val"><?= $rule->equity_strength ?? 0 ?>%</span>
                        </div>
                        <small class="text-muted d-block">
                            Plus cette valeur est élevée, plus le solveur cherchera à répartir cette activité équitablement, quitte à ne pas planifier d'autres activités moins prioritaires.
                        </small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-check">
                            <?= $this->Form->control('lunch_overlap_allowed', [
                                'type' => 'checkbox',
                                'label' => '<i class="bi bi-cup-hot"></i> Autoriser le repas à recouvrir cette activité',
                                'escape' => false,
                                'default' => true,
                            ]) ?>
                            <small class="text-muted d-block">
                                Si décoché, le repas devra être planifié en dehors de cette activité fixe.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="row">
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
                            Si activée, la préférence est traitée comme forte : le solveur collera le repas
                            avant/après cette activité dès que la couverture le permet.
                        </small>
                    </div>
                </div>
                <div id="blocks-container" class="mt-3" style="display:none;" data-next-index="<?= isset($rule->fixed_activity_blocks) ? count($rule->fixed_activity_blocks) : 0 ?>">
                    <h6><i class="bi bi-layout-three-columns"></i> Blocs intra-journée (optionnels)</h6>
                    <small class="text-muted d-block mb-2">
                        Définissez des sous-plages horaires (par ex. 09:00-12:00 et 14:00-17:00) pour favoriser la répartition entre agents.
                    </small>
                    <div id="blocks-rows">
                        <?php $existingBlocks = $rule->fixed_activity_blocks ?? []; ?>
                        <?php $idx = 0; ?>
                        <?php foreach ($existingBlocks as $b): ?>
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <?= $this->Form->control("fixed_activity_blocks.$idx.start_time", [
                                        'type' => 'time',
                                        'label' => 'Début bloc',
                                        'class' => 'form-control',
                                        'empty' => true,
                                        'required' => false,
                                        'value' => $b->start_time,
                                    ]) ?>
                                </div>
                                <div class="col-md-3">
                                    <?= $this->Form->control("fixed_activity_blocks.$idx.end_time", [
                                        'type' => 'time',
                                        'label' => 'Fin bloc',
                                        'class' => 'form-control',
                                        'empty' => true,
                                        'required' => false,
                                        'value' => $b->end_time,
                                    ]) ?>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-block-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php $idx++; ?>
                        <?php endforeach; ?>
                    </div>
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
            </div>
        </div>

        <?php // --- Section Incompatibilités --- ?>
        <div class="card border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-x-octagon"></i> Incompatibilités
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
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
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Enregistrer', [
                'class' => 'btn btn-primary mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
document.addEventListener('DOMContentLoaded', function() {
  const radios = document.querySelectorAll('input[name="site_mode"]');
  const sel = document.getElementById('sitesSelect');
  const splitCheckbox = document.getElementById('is-splittable');
  const blocksContainer = document.getElementById('blocks-container');
  const blocksRows = document.getElementById('blocks-rows');
  const blockTemplate = document.getElementById('block-row-template');
  const addBlockBtn = document.getElementById('add-block-btn');
  if (!sel || radios.length === 0) return;
  
  const sync = () => {
    const mode = document.querySelector('input[name="site_mode"]:checked')?.value;
    if (mode === 'global') {
      for (const opt of sel.options) opt.selected = false;
      sel.setAttribute('disabled', 'disabled');
    } else {
      sel.removeAttribute('disabled');
    }
  };
  
  sync(); // Init on load
  radios.forEach(r => r.addEventListener('change', sync));

  if (splitCheckbox && blocksContainer) {
    const syncBlocks = () => {
      blocksContainer.style.display = splitCheckbox.checked ? 'block' : 'none';
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

  const range = document.getElementById('equity-strength-range');
  const valDisplay = document.getElementById('equity-strength-val');
  if (range && valDisplay) {
      range.addEventListener('input', function() {
          valDisplay.textContent = this.value + '%';
      });
  }
});
<?php $this->Html->scriptEnd(); ?>
