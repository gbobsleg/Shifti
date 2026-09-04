<?php
/**
 * Element: Compétences (Offres)
 *
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface|string[] $offers
 * @var array<int,array{validity_start:mixed,validity_end:mixed}> $userSkills
 */
$dateFieldOptions = [
    'label' => false,
    'type' => 'date',
    'class' => 'form-control form-control-sm',
    'empty' => true,
    'style' => 'width: 11rem;',
    'templates' => ['inputContainer' => '{{content}}'],
];
?>

<section class="crud-section">
    <h2 class="crud-section-title">Compétences</h2>
    <p class="text-muted">
        Cochez les offres maîtrisées. Les dates de validité sont optionnelles (vide = sans limite).
    </p>
    <?php foreach ($offers as $offerId => $offerName):
        $isSelected = isset($userSkills[$offerId]);
        $startRaw = $isSelected ? ($userSkills[$offerId]['validity_start'] ?? null) : null;
        $endRaw = $isSelected ? ($userSkills[$offerId]['validity_end'] ?? null) : null;
        $validityStart = ($startRaw instanceof \DateTimeInterface) ? $startRaw->format('Y-m-d') : (is_string($startRaw) ? $startRaw : '');
        $validityEnd = ($endRaw instanceof \DateTimeInterface) ? $endRaw->format('Y-m-d') : (is_string($endRaw) ? $endRaw : '');
        ?>
        <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <?= $this->Form->checkbox("skills.{$offerId}.selected", [
                    'checked' => $isSelected,
                    'class' => 'form-check-input js-skill-checkbox',
                    'id' => "skill-{$offerId}",
                    'data-skill-id' => $offerId,
                ]) ?>
                <label for="skill-<?= (int)$offerId ?>" class="mb-0">
                    <?= h($offerName) ?>
                </label>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small">Du</span>
                <?= $this->Form->control("skills.{$offerId}.validity_start", $dateFieldOptions + ['value' => $validityStart]) ?>
                <span class="text-muted small">au</span>
                <?= $this->Form->control("skills.{$offerId}.validity_end", $dateFieldOptions + ['value' => $validityEnd]) ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>
