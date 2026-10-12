<?php
/**
 * @var \App\View\AppView $this
 * @var list<\App\Model\Entity\Role> $matrixRoles
 * @var array<string, array<string, string>> $capabilityGroups
 * @var array<int, list<string>> $grantedByRole
 */
$colCount = 1 + count($matrixRoles);
?>
<section class="crud-section">
    <h2 class="crud-section-title">Droits par rôle</h2>
    <p class="text-muted small">Lecture seule. Ces droits sont définis dans le code de l'application.</p>
    <div class="table-responsive">
        <table class="table table-sm crud-table align-middle">
            <thead>
            <tr>
                <th scope="col">Droit</th>
                <?php foreach ($matrixRoles as $matrixRole): ?>
                    <th scope="col" class="text-center">
                        <?= h($matrixRole->name) ?>
                        <?php if (($grantedByRole[(int)$matrixRole->id] ?? []) === []): ?>
                            <small class="d-block text-danger fw-normal">Aucun droit (code absent ou inconnu)</small>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <?php foreach ($capabilityGroups as $groupName => $capabilities): ?>
            <tbody>
                <tr class="table-light">
                    <th scope="rowgroup" colspan="<?= (int)$colCount ?>"><?= h($groupName) ?></th>
                </tr>
                <?php foreach ($capabilities as $capability => $label): ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?= h($label) ?></th>
                        <?php foreach ($matrixRoles as $matrixRole): ?>
                            <?php $has = in_array($capability, $grantedByRole[(int)$matrixRole->id] ?? [], true); ?>
                            <td class="text-center">
                                <?php if ($has): ?>
                                    <i class="bi bi-check-lg text-success" aria-hidden="true"></i>
                                    <span class="visually-hidden">Oui</span>
                                <?php else: ?>
                                    <span class="text-muted" aria-hidden="true">—</span>
                                    <span class="visually-hidden">Non</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>
</section>
