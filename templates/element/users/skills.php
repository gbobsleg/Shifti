<?php
/**
 * Element: Compétences (Offres)
 *
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface|string[] $offers
 * @var array<int,array{validity_start:mixed,validity_end:mixed}> $userSkills
 */
?>

<div class="card border-success mb-4">
    <div class="card-header bg-success text-white">
        <i class="bi bi-award"></i> Compétences (Offres)
    </div>
    <div class="card-body">
        <p class="text-muted">
            <i class="bi bi-info-circle"></i>
            Sélectionnez les offres pour lesquelles cet utilisateur a des compétences et définissez optionnellement les dates de validité.
        </p>

        <div class="row">
            <?php foreach ($offers as $offerId => $offerName):
                $isSelected = isset($userSkills[$offerId]);
                $startRaw = $isSelected ? ($userSkills[$offerId]['validity_start'] ?? null) : null;
                $endRaw = $isSelected ? ($userSkills[$offerId]['validity_end'] ?? null) : null;
                $validityStart = ($startRaw instanceof \DateTimeInterface) ? $startRaw->format('Y-m-d') : (is_string($startRaw) ? $startRaw : '');
                $validityEnd = ($endRaw instanceof \DateTimeInterface) ? $endRaw->format('Y-m-d') : (is_string($endRaw) ? $endRaw : '');
                ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card border-light">
                        <div class="card-body p-3">
                            <div class="form-check mb-2">
                                <?= $this->Form->checkbox("skills.{$offerId}.selected", [
                                    'checked' => $isSelected,
                                    'class' => 'form-check-input js-skill-checkbox',
                                    'id' => "skill-{$offerId}",
                                    'data-skill-id' => $offerId,
                                ]) ?>
                                <label for="skill-<?= (int)$offerId ?>" class="form-check-label fw-bold" style="cursor: pointer;">
                                    <?= h($offerName) ?>
                                </label>
                            </div>
                            <div class="js-skill-dates-<?= (int)$offerId ?>" style="display: <?= $isSelected ? 'block' : 'none' ?>;">
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1">Validité Début</label>
                                    <?= $this->Form->control("skills.{$offerId}.validity_start", [
                                        'label' => false,
                                        'type' => 'date',
                                        'value' => $validityStart,
                                        'class' => 'form-control form-control-sm',
                                        'empty' => true,
                                    ]) ?>
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1">Validité Fin</label>
                                    <?= $this->Form->control("skills.{$offerId}.validity_end", [
                                        'label' => false,
                                        'type' => 'date',
                                        'value' => $validityEnd,
                                        'class' => 'form-control form-control-sm',
                                        'empty' => true,
                                    ]) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.js-skill-checkbox');
    
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const skillId = this.dataset.skillId;
            const datesContainer = document.querySelector('.js-skill-dates-' + skillId);
            
            if (datesContainer) {
                datesContainer.style.display = this.checked ? 'block' : 'none';
            }
        });
    });
});
</script>


