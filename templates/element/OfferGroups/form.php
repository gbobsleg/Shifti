<?php
/**
 * Formulaire partagé add/edit groupes d'offres.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup $offerGroup
 * @var array<int, string> $mixedOffers
 * @var list<array{offer_id:int,name:string,selected:bool,split_ratio_percent:int|null,display_order:int}> $memberOfferRows
 */
use App\Model\Entity\OfferGroup;

$errors = $offerGroup->getErrors();
$memberErrors = $errors['offer_group_members'] ?? [];
?>

<?= $this->Form->create($offerGroup, ['id' => 'offer-group-form']) ?>

<section class="crud-section">
    <h2 class="crud-section-title">Paramètres du groupe</h2>
    <div class="mb-3">
        <label class="form-label">Nom du groupe</label>
        <?= $this->Form->control('name', [
            'label' => false,
            'class' => 'form-control',
            'required' => true,
            'placeholder' => 'Ex: TI-AE, C/P…',
        ]) ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Offre mixte (profil)</label>
        <?= $this->Form->control('mixed_offer_id', [
            'type' => 'select',
            'options' => $mixedOffers,
            'empty' => '— Choisir une offre normale —',
            'label' => false,
            'class' => 'form-control',
            'id' => 'mixed-offer-id',
            'required' => true,
        ]) ?>
        <div class="form-text text-muted">
            <p class="mb-1">
                C’est le <strong>profil planifiable</strong> du pool (compétence agent + libellé dans le planning),
                pas un doublon du nom du groupe. Ex.&nbsp;: groupe «&nbsp;C/P&nbsp;» → offre mixte
                <code>C/P</code> ; groupe «&nbsp;TI-AE&nbsp;» → offre <code>TI-AE</code>.
            </p>
            <p class="mb-1">
                <strong>Mode Membres</strong> (ex. CESU + PAJEMPLOI)&nbsp;: le besoin vient des membres ;
                l’offre mixte n’est pas calculée en prévision (need à 0) — elle sert seulement à affecter des agents
                sur le profil mixte.
            </p>
            <p class="mb-1">
                <strong>Mode Groupe</strong> (ex. TI-AE)&nbsp;: le besoin vient de l’offre mixte
                (histo global), puis est réparti vers les membres via les ratios.
            </p>
            <p class="mb-0">
                Pour l’instant, créez d’abord l’offre mixte dans
                <?= $this->Html->link('Offres', ['controller' => 'Offers', 'action' => 'index']) ?>
                (type normal), puis sélectionnez-la ici. Elle ne doit pas déjà être membre ou mixte
                d’un autre groupe.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Source de prévision</label>
            <?= $this->Form->control('forecast_source', [
                'type' => 'select',
                'options' => [
                    OfferGroup::FORECAST_SOURCE_MEMBERS => 'Membres (histo sur chaque offre membre)',
                    OfferGroup::FORECAST_SOURCE_GROUP => 'Groupe (histo sur le mixte + ratios manuels)',
                ],
                'label' => false,
                'class' => 'form-control',
                'id' => 'forecast-source',
                'required' => true,
            ]) ?>
            <div class="form-text text-muted">
                Détermine d’où vient le volume à couvrir. Les ratios (%) des membres ne s’appliquent
                qu’en mode <strong>Groupe</strong>.
            </div>
        </div>
        <div class="col-md-6 mb-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <?= $this->Form->control('prefer_mixed', [
                    'type' => 'checkbox',
                    'label' => 'Préférence souple pour le profil mixte (activé par défaut)',
                    'checked' => $offerGroup->prefer_mixed !== false,
                    'class' => 'form-check-input',
                    'templates' => [
                        'inputContainer' => '{{content}}',
                        'nestingLabel' => '{{hidden}}{{input}}<label class="form-check-label"{{attrs}}>{{text}}</label>',
                    ],
                ]) ?>
            </div>
        </div>
    </div>
</section>

<section class="crud-section">
    <h2 class="crud-section-title">Offres membres</h2>
    <?php if (!empty($memberErrors)): ?>
        <div class="alert alert-danger">
            <?php foreach ((array)$memberErrors as $msg): ?>
                <?php if (is_array($msg)): ?>
                    <?php foreach ($msg as $m): ?>
                        <div><?= h((string)$m) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div><?= h((string)$msg) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="text-muted small">
        Cochez au moins 2 offres membres (<strong>autres</strong> que l’offre mixte ci-dessus).
        Les ratios (%) ne s'appliquent qu'en mode <strong>Groupe</strong> et doivent
        totaliser exactement 100&nbsp;%.
    </p>

    <div class="table-responsive">
        <table class="table table-sm table-hover crud-table" id="members-table">
            <thead>
                <tr>
                    <th style="width: 3rem;"></th>
                    <th>Offre</th>
                    <th class="js-ratio-col" style="width: 8rem;">Ratio %</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($memberOfferRows as $i => $row): ?>
                    <tr class="member-row" data-offer-id="<?= (int)$row['offer_id'] ?>">
                        <td class="text-center align-middle">
                            <?= $this->Form->control("offer_group_members.$i._selected", [
                                'type' => 'checkbox',
                                'label' => false,
                                'checked' => !empty($row['selected']),
                                'class' => 'form-check-input js-member-selected',
                                'templates' => ['inputContainer' => '{{content}}'],
                            ]) ?>
                            <?= $this->Form->hidden("offer_group_members.$i.offer_id", [
                                'value' => $row['offer_id'],
                            ]) ?>
                            <?= $this->Form->hidden("offer_group_members.$i.display_order", [
                                'value' => $i,
                            ]) ?>
                        </td>
                        <td class="align-middle"><?= h($row['name']) ?></td>
                        <td class="js-ratio-col">
                            <?= $this->Form->control("offer_group_members.$i.split_ratio_percent", [
                                'type' => 'number',
                                'label' => false,
                                'class' => 'form-control form-control-sm js-ratio-input',
                                'min' => 0,
                                'max' => 100,
                                'step' => 1,
                                'value' => $row['split_ratio_percent'],
                                'templates' => ['inputContainer' => '{{content}}'],
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="js-ratio-col">
        <small class="text-muted">Somme des ratios cochés : <strong id="ratio-sum">0</strong> %</small>
    </div>
</section>

<div class="crud-actions-bar">
    <?= $this->Form->button(
        '<i class="bi bi-save me-2"></i> Enregistrer',
        ['escapeTitle' => false, 'class' => 'btn btn-primary']
    ) ?>
    <?= $this->Html->link(
        '<i class="bi bi-x-circle me-2"></i> Annuler',
        ['action' => 'index'],
        ['class' => 'btn btn-outline-secondary', 'escape' => false]
    ) ?>
</div>

<?= $this->Form->end() ?>

<?php
$this->Html->scriptStart(['block' => true]);
?>
(function () {
    function syncMixedOfferExcludedFromMembers() {
        var mixedSelect = document.getElementById('mixed-offer-id');
        var mixedId = mixedSelect && mixedSelect.value ? String(mixedSelect.value) : '';
        document.querySelectorAll('#members-table tbody tr.member-row').forEach(function (row) {
            var offerId = String(row.getAttribute('data-offer-id') || '');
            var isMixed = mixedId !== '' && offerId === mixedId;
            row.style.display = isMixed ? 'none' : '';
            if (isMixed) {
                var cb = row.querySelector('.js-member-selected');
                var ratio = row.querySelector('.js-ratio-input');
                if (cb) {
                    cb.checked = false;
                }
                if (ratio) {
                    ratio.value = '';
                    ratio.required = false;
                }
            }
        });
    }

    function syncRatioVisibility() {
        var source = document.getElementById('forecast-source');
        var isGroup = source && source.value === 'group';
        document.querySelectorAll('.js-ratio-col').forEach(function (el) {
            el.style.display = isGroup ? '' : 'none';
        });
        document.querySelectorAll('.js-ratio-input').forEach(function (input) {
            input.required = false;
            if (!isGroup) {
                return;
            }
            var row = input.closest('tr');
            if (row && row.style.display === 'none') {
                return;
            }
            var cb = row ? row.querySelector('.js-member-selected') : null;
            if (cb && cb.checked) {
                input.required = true;
            }
        });
        updateRatioSum();
    }

    function updateRatioSum() {
        var source = document.getElementById('forecast-source');
        if (!source || source.value !== 'group') {
            var sumEl = document.getElementById('ratio-sum');
            if (sumEl) sumEl.textContent = '—';
            return;
        }
        var sum = 0;
        document.querySelectorAll('#members-table tbody tr').forEach(function (row) {
            if (row.style.display === 'none') {
                return;
            }
            var cb = row.querySelector('.js-member-selected');
            var input = row.querySelector('.js-ratio-input');
            if (cb && cb.checked && input && input.value !== '') {
                sum += parseInt(input.value, 10) || 0;
            }
        });
        var el = document.getElementById('ratio-sum');
        if (el) {
            el.textContent = String(sum);
            el.classList.toggle('text-danger', sum !== 100);
            el.classList.toggle('text-success', sum === 100);
        }
    }

    function syncFormUi() {
        syncMixedOfferExcludedFromMembers();
        syncRatioVisibility();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var source = document.getElementById('forecast-source');
        if (source) {
            source.addEventListener('change', syncFormUi);
        }
        var mixedSelect = document.getElementById('mixed-offer-id');
        if (mixedSelect) {
            mixedSelect.addEventListener('change', syncFormUi);
        }
        document.querySelectorAll('.js-member-selected, .js-ratio-input').forEach(function (el) {
            el.addEventListener('change', syncFormUi);
            el.addEventListener('input', updateRatioSum);
        });
        syncFormUi();
    });
})();
<?php
$this->Html->scriptEnd();
?>
