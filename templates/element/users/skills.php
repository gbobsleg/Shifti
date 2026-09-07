<?php
/**
 * Element: Compétences (Offres)
 *
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface|array<int,string> $offers
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

$offersList = $offers instanceof \Cake\Collection\CollectionInterface
    ? $offers->toArray()
    : (is_array($offers) ? $offers : iterator_to_array($offers));

$offerGroups = \Cake\ORM\TableRegistry::getTableLocator()->get('OfferGroups')->find()
    ->contain([
        'MixedOffers',
        'OfferGroupMembers' => ['Offers'],
    ])
    ->orderBy(['OfferGroups.name' => 'ASC'])
    ->all();

$groupedOfferIds = [];
$groupBlocks = [];

foreach ($offerGroups as $group) {
    $memberNames = [];
    $memberRows = [];
    foreach ($group->offer_group_members ?? [] as $member) {
        $memberId = (int)$member->offer_id;
        $memberName = $member->offer->name ?? ($offersList[$memberId] ?? null);
        if ($memberName) {
            $memberNames[] = $memberName;
        }
        if (array_key_exists($memberId, $offersList)) {
            $memberRows[] = $memberId;
            $groupedOfferIds[$memberId] = true;
        }
    }

    $mixedId = (int)$group->mixed_offer_id;
    $mixedName = $group->mixed_offer->name ?? ($offersList[$mixedId] ?? 'mixte');
    $hasMixed = array_key_exists($mixedId, $offersList);
    if ($hasMixed) {
        $groupedOfferIds[$mixedId] = true;
    }

    if (!$hasMixed && $memberRows === []) {
        continue;
    }

    $groupBlocks[] = [
        'name' => $group->name,
        'mixed_name' => $mixedName,
        'has_mixed' => $hasMixed,
        'mixed_id' => $mixedId,
        'member_names' => $memberNames,
        'member_rows' => $memberRows,
    ];
}

$ungrouped = [];
foreach ($offersList as $offerId => $offerName) {
    if (!isset($groupedOfferIds[(int)$offerId])) {
        $ungrouped[(int)$offerId] = $offerName;
    }
}

$view = $this;
$renderSkillRow = function (int $offerId, string $offerName, ?string $role = null) use ($view, $userSkills, $dateFieldOptions): void {
    $isSelected = isset($userSkills[$offerId]);
    $startRaw = $isSelected ? ($userSkills[$offerId]['validity_start'] ?? null) : null;
    $endRaw = $isSelected ? ($userSkills[$offerId]['validity_end'] ?? null) : null;
    $validityStart = ($startRaw instanceof \DateTimeInterface) ? $startRaw->format('Y-m-d') : (is_string($startRaw) ? $startRaw : '');
    $validityEnd = ($endRaw instanceof \DateTimeInterface) ? $endRaw->format('Y-m-d') : (is_string($endRaw) ? $endRaw : '');
    $roleLabel = $role === 'mixed' ? 'Mixte' : ($role === 'member' ? 'Membre' : null);
    ?>
        <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <?= $view->Form->checkbox("skills.{$offerId}.selected", [
                    'checked' => $isSelected,
                    'class' => 'form-check-input js-skill-checkbox',
                    'id' => "skill-{$offerId}",
                    'data-skill-id' => $offerId,
                ]) ?>
                <label for="skill-<?= $offerId ?>" class="mb-0">
                    <?= h($offerName) ?>
                    <?php if ($roleLabel): ?>
                        <span class="badge bg-light text-dark border fw-normal"><?= h($roleLabel) ?></span>
                    <?php endif; ?>
                </label>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small">Du</span>
                <?= $view->Form->control("skills.{$offerId}.validity_start", $dateFieldOptions + ['value' => $validityStart]) ?>
                <span class="text-muted small">au</span>
                <?= $view->Form->control("skills.{$offerId}.validity_end", $dateFieldOptions + ['value' => $validityEnd]) ?>
            </div>
        </div>
    <?php
};
?>

<section class="crud-section">
    <h2 class="crud-section-title">Compétences</h2>
    <p class="text-muted mb-2">
        Cochez les profils que l’agent est <strong>autorisé à tenir</strong>.
        Les dates de validité sont optionnelles (vide = sans limite).
    </p>
    <?php if ($groupBlocks !== []): ?>
        <div class="crud-warn">
            Dans un groupe d’offres, chaque case est indépendante&nbsp;: ce n’est pas «&nbsp;tout ou rien&nbsp;».
            Un agent peut n’avoir que le mixte, qu’un membre, ou une combinaison.
        </div>
    <?php endif; ?>

    <?php foreach ($groupBlocks as $block):
        $memberList = $block['member_names'] !== []
            ? implode(', ', $block['member_names'])
            : 'les flux membres';
        ?>
        <h3 class="crud-subsection-title">Groupe <?= h($block['name']) ?></h3>
        <p class="text-muted small mb-2">
            Cochez uniquement ce que l’agent peut réellement faire — il n’est pas obligatoire de tout cocher.
            <br>
            <strong><?= h($memberList) ?></strong> (membres)&nbsp;: planifiable uniquement sur ce flux.
            <?php if ($block['mixed_name']): ?>
                <br>
                <strong><?= h($block['mixed_name']) ?></strong> (mixte)&nbsp;: 1 capacité partagée qui peut couvrir
                <?= h($memberList) ?>. Un agent «&nbsp;<?= h($block['mixed_name']) ?> seul&nbsp;» suffit.
            <?php endif; ?>
        </p>
        <?php
        foreach ($block['member_rows'] as $memberId) {
            $renderSkillRow((int)$memberId, (string)$offersList[$memberId], 'member');
        }
        if ($block['has_mixed']) {
            $renderSkillRow((int)$block['mixed_id'], (string)$offersList[$block['mixed_id']], 'mixed');
        }
        ?>
    <?php endforeach; ?>

    <?php if ($ungrouped !== []): ?>
        <?php if ($groupBlocks !== []): ?>
            <h3 class="crud-subsection-title">Autres compétences</h3>
        <?php endif; ?>
        <?php foreach ($ungrouped as $offerId => $offerName):
            $renderSkillRow((int)$offerId, (string)$offerName);
        endforeach; ?>
    <?php endif; ?>
</section>
