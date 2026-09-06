<?php
/**
 * @var \App\View\AppView $this
 * @var array $userContracts
 */
$todayStr = date('Y-m-d');
?>
<section class="crud-section">
    <h2 class="crud-section-title">Contrats</h2>
    <p class="text-muted mb-3">
        Un seul contrat sans date de fin. Les périodes ne doivent pas se chevaucher.
        Pour clôturer un contrat, indiquez une date de fin puis Enregistrer.
    </p>
    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table" id="contracts-table">
            <thead>
                <tr>
                    <th>Date début</th>
                    <th>Date fin</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userContracts as $index => $contract): ?>
                    <?php
                    $contractId = (int)($contract->id ?? 0);
                    $startStr = null;
                    $endStr = null;
                    if (is_object($contract->start_date) && method_exists($contract->start_date, 'format')) {
                        $startStr = $contract->start_date->format('Y-m-d');
                    } elseif (is_string($contract->start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $contract->start_date)) {
                        $startStr = $contract->start_date;
                    }
                    if (is_object($contract->end_date) && method_exists($contract->end_date, 'format')) {
                        $endStr = $contract->end_date->format('Y-m-d');
                    } elseif (is_string($contract->end_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $contract->end_date)) {
                        $endStr = $contract->end_date;
                    }
                    $isOpen = $endStr === null;
                    $isActive = $startStr && $startStr <= $todayStr && ($isOpen || $endStr >= $todayStr);
                    ?>
                    <tr>
                        <td>
                            <input type="hidden" name="contracts[<?= (int)$index ?>][id]" value="<?= h((string)($contract->id ?? '')) ?>">
                            <input type="date"
                                   name="contracts[<?= (int)$index ?>][start_date]"
                                   class="form-control form-control-sm"
                                   autocomplete="off"
                                   value="<?= h((string)$startStr) ?>">
                        </td>
                        <td>
                            <input type="date"
                                   name="contracts[<?= (int)$index ?>][end_date]"
                                   class="form-control form-control-sm"
                                   autocomplete="off"
                                   value="<?= h((string)($endStr ?? '')) ?>">
                        </td>
                        <td>
                            <?= $isActive ? 'Actif' : 'Terminé' ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($contractId): ?>
                                <?php $deleteFormId = 'delete-contract-' . $contractId; ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger js-delete-contract"
                                        data-form="<?= h($deleteFormId) ?>"
                                        data-confirm="Supprimer ce contrat ?">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php
                                $this->append('contract_action_forms', sprintf(
                                    '<form method="post" action="%s" id="%s">'
                                    . '<input type="hidden" name="_method" value="POST">'
                                    . '<input type="hidden" name="_csrfToken" autocomplete="off" value="%s">'
                                    . '</form>',
                                    h($this->Url->build(['action' => 'deleteContract', $contractId])),
                                    h($deleteFormId),
                                    h((string)$this->request->getAttribute('csrfToken'))
                                ));
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-contract-btn">
        <i class="bi bi-plus-circle"></i> Ajouter un contrat
    </button>
</section>
