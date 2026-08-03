<?php
/**
 * @var \App\View\AppView $this
 * @var array $userContracts
 */
?>
<div class="card border-info mb-4">
    <div class="card-header bg-info text-white">
        <i class="bi bi-file-earmark-text"></i> Contrats
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm" id="contracts-table">
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
                        <tr>
                            <td>
                                <?= $this->Form->hidden("contracts.{$index}.id", ['value' => $contract->id ?? '']) ?>
                                <?= $this->Form->control("contracts.{$index}.start_date", [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => ($contract->start_date instanceof \DateTimeInterface ? $contract->start_date->format('Y-m-d') : $contract->start_date),
                                ]) ?>
                            </td>
                            <td>
                                <?= $this->Form->control("contracts.{$index}.end_date", [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => ($contract->end_date instanceof \DateTimeInterface ? $contract->end_date->format('Y-m-d') : $contract->end_date),
                                    'empty' => true,
                                ]) ?>
                            </td>
                            <td>
                                <?php 
                                $todayStr = date('Y-m-d');
                                
                                // Extraire les dates du contrat en format string Y-m-d
                                $contractStartStr = null;
                                $contractEndStr = null;
                                
                                if ($contract->start_date && is_object($contract->start_date) && method_exists($contract->start_date, 'format')) {
                                    $contractStartStr = $contract->start_date->format('Y-m-d');
                                }
                                
                                if ($contract->end_date && is_object($contract->end_date) && method_exists($contract->end_date, 'format')) {
                                    $contractEndStr = $contract->end_date->format('Y-m-d');
                                }
                                
                                $isActive = false;
                                if ($contractStartStr) {
                                    $startMatch = $contractStartStr <= $todayStr;
                                    $endMatch = $contractEndStr === null || $contractEndStr >= $todayStr;
                                    $isActive = $startMatch && $endMatch;
                                }
                                ?>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Terminé</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isActive && $contract->end_date === null): ?>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-x-circle"></i> Clôturer',
                                        ['action' => 'closeContract', $contract->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment clôturer ce contrat à la date du jour ?',
                                            'class' => 'btn btn-sm btn-outline-warning',
                                            'escape' => false,
                                            'data' => ['end_date' => date('Y-m-d')] // Idéalement, on ouvrirait une modale pour choisir la date
                                        ]
                                    ) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-contract-btn">
            <i class="bi bi-plus-circle"></i> Ajouter un contrat
        </button>
    </div>
</div>

<script>
document.getElementById('add-contract-btn')?.addEventListener('click', function() {
    const tbody = document.querySelector('#contracts-table tbody');
    const index = tbody.querySelectorAll('tr').length;
    const today = new Date().toISOString().split('T')[0];
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <input type="hidden" name="contracts[${index}][id]" value="">
            <input type="date" name="contracts[${index}][start_date]" 
                   class="form-control form-control-sm" value="${today}">
        </td>
        <td>
            <input type="date" name="contracts[${index}][end_date]" 
                   class="form-control form-control-sm" value="">
        </td>
        <td><span class="badge bg-success">Nouveau</span></td>
        <td></td>
    `;
    tbody.appendChild(row);
});
</script>
