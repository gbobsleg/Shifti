<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>
<?php
$isEdit = $this->request->getParam('action') === 'edit';
$this->assign('title', $isEdit ? 'Éditer Disponibilité' : 'Nouvelle Disponibilité');
?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            <?= $isEdit ? 'Éditer Disponibilité' : 'Créer Disponibilité' ?>
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
        <?= $this->Form->create($userAvailability) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note :</strong> L'utilisateur et le jour ne peuvent pas être modifiés pour éviter les doublons. Seuls les horaires sont modifiables.
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-person"></i> Utilisateur</label>
                        <div class="form-control bg-light" style="cursor: not-allowed;">
                            <strong><?= h($userAvailability->user->full_name ?? 'N/A') ?></strong>
                        </div>
                        <?= $this->Form->hidden('user_id') ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-week"></i> Jour de la Semaine</label>
                        <div class="form-control bg-light" style="cursor: not-allowed;">
                            <strong>
                                <?php
                                $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
                                echo $days[$userAvailability->day_of_week] ?? 'N/A';
                                ?>
                            </strong>
                        </div>
                        <?= $this->Form->hidden('day_of_week') ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock"></i> Début Disponibilité</label>
                        <?= $this->Form->control('availability_start_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-fill"></i> Fin Disponibilité</label>
                        <?= $this->Form->control('availability_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-history"></i> Fin la plus tôt</label>
                        <?= $this->Form->control('earliest_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'empty' => true,
                            'class' => 'form-control'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Optionnel
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> ' . ($isEdit ? 'Enregistrer' : 'Créer'), [
                'class' => 'btn btn-' . ($isEdit ? 'primary' : 'success') . ' mr-3',
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
