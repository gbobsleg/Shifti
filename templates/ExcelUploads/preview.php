<?php
/**
 * @var \App\View\AppView $this
 * @var array $groupedRanges
 * @var array $offers
 * @var array $usersById
 * @var array $offersById
 * @var int $contextMonth
 * @var int $contextYear
 * @var array $availabilitiesByUser
 * @var array $unrecognizedAgents Agents du fichier non reconnus en BDD
 * @var int $recognizedAgentsCount Nombre d'agents reconnus
 */
?>
<?php $this->assign('title', 'Prévisualisation des données Excel'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php $this->Html->css('excel_uploads_preview', ['block' => true]); ?>

<?php
// --- FONCTIONS HELPER ---

/**
 * Calcule la couleur de contraste (noir ou blanc) selon la couleur de fond
 */
function getContrastColor($hexColor) {
    $hexColor = ltrim($hexColor, '#');
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.5 ? '#000000' : '#FFFFFF';
}

/**
 * Vérifie si un agent est disponible pour une demi-journée donnée
 * @param int $userId ID de l'utilisateur
 * @param int $dayOfWeek Jour de la semaine (1=Lundi à 7=Dimanche)
 * @param string $period 'AM' ou 'PM'
 * @param array $availabilitiesByUser Tableau des disponibilités indexé par user_id et day_of_week
 * @param int $pivotHour Heure pivot (défaut 13)
 * @return bool True si disponible, False sinon
 */
function isAgentAvailable($userId, $dayOfWeek, $period, $availabilitiesByUser, $pivotHour = 13) {
    // Si aucune disponibilité définie pour cet agent → disponible tout le temps
    if (!isset($availabilitiesByUser[$userId]) || empty($availabilitiesByUser[$userId])) {
        return true;
    }
    
    // Si pas de disponibilité pour ce jour de la semaine → indisponible
    if (!isset($availabilitiesByUser[$userId][$dayOfWeek])) {
        return false;
    }
    
    $avail = $availabilitiesByUser[$userId][$dayOfWeek];
    $startHour = (int)($avail['start'] instanceof \Cake\I18n\Time ? $avail['start']->format('H') : substr($avail['start'], 0, 2));
    $endHour = (int)($avail['end'] instanceof \Cake\I18n\Time ? $avail['end']->format('H') : substr($avail['end'], 0, 2));
    
    // Cas spécial : start == end (ex: 00:00 à 00:00) → indisponible toute la journée
    if ($startHour >= $endHour) {
        return false;
    }
    
    if ($period === 'AM') {
        // Disponible le matin si l'agent commence avant le pivot ET a des heures de travail le matin
        // (c'est-à-dire que sa plage chevauche le matin : start < pivot)
        return $startHour < $pivotHour;
    } else {
        // Disponible l'après-midi si l'agent termine après le pivot
        // (c'est-à-dire que sa plage chevauche l'après-midi : end > pivot)
        return $endHour > $pivotHour;
    }
}

/**
 * Transforme la liste linéaire d'événements en tableau indexé par agent/jour/période
 * Retourne: [user_id => [day => ['AM' => event, 'PM' => event]]]
 * Gère les événements multi-jours en colorant tous les jours couverts
 */
function buildGridData($groupedRanges, $usersById, $offersById, $contextMonth, $contextYear) {
    $gridData = [];
    $pivotHour = 13; // Heure pivot par défaut (13h00)
    
    // Récupérer l'heure pivot depuis WfmSettings si disponible
    try {
        $wfmTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WfmSettings');
        $wfmSettings = $wfmTable->find()->first();
        if ($wfmSettings && $wfmSettings->half_day_pivot) {
            $pivot = $wfmSettings->half_day_pivot;
            if ($pivot instanceof \Cake\I18n\Time) {
                $pivotHour = (int)$pivot->format('H');
            } elseif (is_string($pivot)) {
                $pivotHour = (int)substr($pivot, 0, 2);
            }
        }
    } catch (\Exception $e) {
        // Utiliser la valeur par défaut
    }
    
    $daysInMonth = (int)date('t', mktime(0, 0, 0, $contextMonth, 1, $contextYear));
    
    foreach ($groupedRanges as $rangeIndex => $range) {
        $userId = $range['user_id'];
        
        $dateStart = $range['date_start'];
        $dateEnd = $range['date_end'];
        if (!$dateStart instanceof \Cake\I18n\FrozenTime) {
            $dateStart = \Cake\I18n\FrozenTime::parse($dateStart);
        }
        if (!$dateEnd instanceof \Cake\I18n\FrozenTime) {
            $dateEnd = \Cake\I18n\FrozenTime::parse($dateEnd);
        }
        
        // Déterminer si c'est un événement télétravail ou absence
        $offer = $offersById[$range['offer_id']] ?? null;
        $isRemote = false;
        if ($offer) {
            $offerNameLower = strtolower($offer->name);
            $isRemote = str_contains($offerNameLower, 'télétravail') || str_contains($offerNameLower, 'telework');
        }
        
        $eventData = [
            'range_index' => $rangeIndex,
            'offer_id' => $range['offer_id'],
            'offer_name' => $offer ? $offer->name : 'Offre inconnue',
            'offer_color' => $offer && $offer->color ? $offer->color : '#6c757d',
            'is_remote' => $isRemote,
            'is_validated' => !empty($range['is_validated']),
            'demand_status' => $range['demand_status'] ?? 'real',
            'comment' => $range['comment'] ?? '',
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'time_start' => $dateStart->format('H:i'),
            'time_end' => $dateEnd->format('H:i'),
        ];
        
        if (!isset($gridData[$userId])) {
            $gridData[$userId] = [];
        }
        
        // Itérer sur tous les jours couverts par l'événement
        $currentDate = $dateStart->startOfDay();
        $endDate = $dateEnd->startOfDay();
        
        while ($currentDate <= $endDate) {
            $currentMonth = (int)$currentDate->format('n');
            $currentYear = (int)$currentDate->format('Y');
            
            // Ne traiter que les jours du mois de contexte
            if ($currentMonth === $contextMonth && $currentYear === $contextYear) {
                $day = (int)$currentDate->format('j');
                
                if (!isset($gridData[$userId][$day])) {
                    $gridData[$userId][$day] = ['AM' => null, 'PM' => null];
                }
                
                // Déterminer quelles demi-journées sont couvertes pour ce jour spécifique
                $isFirstDay = ($currentDate->format('Y-m-d') === $dateStart->format('Y-m-d'));
                $isLastDay = ($currentDate->format('Y-m-d') === $dateEnd->format('Y-m-d'));
                $isSingleDay = $isFirstDay && $isLastDay;
                
                $startHour = $isFirstDay ? (int)$dateStart->format('H') : 0;
                $endHour = $isLastDay ? (int)$dateEnd->format('H') : 23;
                $endMinute = $isLastDay ? (int)$dateEnd->format('i') : 59;
                
                // Créer une copie de eventData avec les horaires adaptés pour ce jour
                $dayEventData = $eventData;
                $dayEventData['time_start'] = $isFirstDay ? $dateStart->format('H:i') : '00:00';
                $dayEventData['time_end'] = $isLastDay ? $dateEnd->format('H:i') : '23:59';
                
                // Logique de couverture AM/PM
                if ($isSingleDay) {
                    // Événement sur un seul jour : utiliser la logique existante
                    $isFullDay = ($startHour <= 8 && ($endHour >= 17 || ($endHour == 23 && $endMinute == 59))) || 
                                 ($dateStart->format('H:i:s') === '00:00:00' && $dateEnd->format('H:i:s') === '23:59:59');
                    
                    if ($isFullDay) {
                        $gridData[$userId][$day]['AM'] = $dayEventData;
                        $gridData[$userId][$day]['PM'] = $dayEventData;
                    } elseif ($endHour < $pivotHour || ($endHour == $pivotHour && $endMinute == 0)) {
                        // Matin uniquement (se termine avant ou pile au pivot)
                        $gridData[$userId][$day]['AM'] = $dayEventData;
                    } elseif ($startHour >= $pivotHour) {
                        // Après-midi uniquement
                        $gridData[$userId][$day]['PM'] = $dayEventData;
                    } else {
                        // Chevauche les deux périodes
                        $gridData[$userId][$day]['AM'] = $dayEventData;
                        $gridData[$userId][$day]['PM'] = $dayEventData;
                    }
                } elseif ($isFirstDay) {
                    // Premier jour d'un événement multi-jours
                    if ($startHour < $pivotHour) {
                        $gridData[$userId][$day]['AM'] = $dayEventData;
                    }
                    $gridData[$userId][$day]['PM'] = $dayEventData; // Toujours l'après-midi
                } elseif ($isLastDay) {
                    // Dernier jour d'un événement multi-jours
                    $gridData[$userId][$day]['AM'] = $dayEventData; // Toujours le matin
                    if ($endHour >= $pivotHour || ($endHour == $pivotHour - 1 && $endMinute > 0)) {
                        $gridData[$userId][$day]['PM'] = $dayEventData;
                    }
                } else {
                    // Jour intermédiaire : toute la journée
                    $gridData[$userId][$day]['AM'] = $dayEventData;
                    $gridData[$userId][$day]['PM'] = $dayEventData;
                }
            }
            
            // Passer au jour suivant
            $currentDate = $currentDate->addDays(1);
        }
    }
    
    return $gridData;
}

/**
 * Retourne l'initiale française du jour de la semaine
 */
function getDayInitial($dayOfWeek) {
    $initials = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    return $initials[$dayOfWeek];
}

/**
 * Retourne le nom français complet du jour
 */
function getDayName($dayOfWeek) {
    $names = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    return $names[$dayOfWeek];
}

// Préparer les données de la grille
$gridData = buildGridData($groupedRanges, $usersById, $offersById, $contextMonth, $contextYear);
$daysInMonth = (int)date('t', mktime(0, 0, 0, $contextMonth, 1, $contextYear));
$monthNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// Collecter les offres présentes dans les données pour la légende
$presentOffers = [];
foreach ($gridData as $userId => $days) {
    foreach ($days as $day => $periods) {
        foreach (['AM', 'PM'] as $period) {
            if (!empty($periods[$period])) {
                $event = $periods[$period];
                $offerId = $event['offer_id'];
                if (!isset($presentOffers[$offerId])) {
                    $presentOffers[$offerId] = [
                        'name' => $event['offer_name'],
                        'color' => $event['offer_color'],
                    ];
                }
            }
        }
    }
}
// Trier par nom d'offre
uasort($presentOffers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
?>

<?php $this->start('tb_actions'); ?>
<li class="nav-item">
    <?= $this->Html->link(
        '<i class="bi bi-arrow-left me-2"></i> Retour à l\'upload',
        ['action' => 'upload'],
        ['class' => 'nav-link', 'escape' => false]
    ) ?>
</li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<style>
/* Styles pour la vue grille */
.grid-table {
    font-size: 11px;
    border-collapse: collapse;
}
.grid-table th, .grid-table td {
    border: 1px solid #dee2e6;
    text-align: center;
    vertical-align: middle;
}
.grid-table thead th {
    background: #f8f9fa;
    padding: 4px 2px;
    font-weight: 600;
}
.grid-table .month-header {
    background: #f8f9fa;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 4px;
}
.grid-table .day-header {
    width: 30px;
    min-width: 30px;
    max-width: 30px;
}
.grid-table .weekend {
    background-color: #e9ecef !important;
}
.grid-table .matricule-cell {
    text-align: center;
    padding: 4px 6px;
    white-space: nowrap;
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 6;
    min-width: 60px;
    font-size: 10px;
    color: #666;
}
.grid-table .agent-cell {
    text-align: left;
    padding: 4px 8px;
    white-space: nowrap;
    position: sticky;
    left: 60px;
    background: #fff;
    z-index: 5;
    min-width: 150px;
    font-weight: 500;
}
.grid-table tbody tr:nth-child(even) .matricule-cell,
.grid-table tbody tr:nth-child(even) .agent-cell {
    background: #f8f9fa;
}

/* Cellule calendrier divisée AM/PM - CARRÉE 
   Note: width 30px / height 26px car border-collapse:collapse 
   partage les bordures horizontales entre lignes mais pas verticales
   entre colonnes (1px bordure gauche + 1px droite = 2px de plus en largeur)
   + le séparateur AM|PM ajoute 1px, + possible arrondis navigateur */
.day-cell {
    padding: 0 !important;
    width: 31px;
    height: 26px;
    min-width: 31px;
    max-width: 31px;
    box-sizing: border-box;
}
.day-cell-inner {
    display: flex;
    flex-direction: row;
    height: 100%;
    width: 100%;
}
.half-cell {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    cursor: default;
}
.half-cell.am {
    border-right: 1px dashed #ccc;
}

/* Hachures pour forecast (appliqué par-dessus la couleur de l'offre) */
.half-cell.forecast {
    background-image: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 3px,
        rgba(255,255,255,0.5) 3px,
        rgba(255,255,255,0.5) 6px
    );
}
/* Éclaircir la couleur pour forecast */
.half-cell.forecast-overlay {
    opacity: 0.7;
}

/* Bordure pour non validé */
.half-cell.not-validated {
    box-shadow: inset 0 0 0 2px #e67e22;
}
.half-cell.not-validated::after {
    content: '?';
    position: absolute;
    top: 0;
}

/* Croix pour indisponible (agent non disponible selon ses paramètres) */
.half-cell.unavailable {
    background-color: #dee2e6;
    position: relative;
}
.half-cell.unavailable::before,
.half-cell.unavailable::after {
    content: '';
    position: absolute;
    width: 70%;
    height: 1px;
    background-color: #6c757d;
    top: 50%;
    left: 15%;
}
.half-cell.unavailable::before {
    transform: rotate(45deg);
}
.half-cell.unavailable::after {
    transform: rotate(-45deg);
    right: 1px;
    font-size: 8px;
    font-weight: bold;
    color: #e67e22;
}

/* Légende */
.grid-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}
.legend-box {
    width: 20px;
    height: 16px;
    border: 1px solid #ccc;
    border-radius: 2px;
}
.legend-box.forecast-demo {
    background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255,255,255,0.5) 2px, rgba(255,255,255,0.5) 4px);
    opacity: 0.7;
}
.legend-box.not-validated { 
    background-color: #fff;
    box-shadow: inset 0 0 0 2px #e67e22;
}
.legend-box.unavailable-legend {
    background-color: #dee2e6;
    position: relative;
}
.legend-box.unavailable-legend::before,
.legend-box.unavailable-legend::after {
    content: '';
    position: absolute;
    width: 60%;
    height: 1px;
    background-color: #6c757d;
    top: 50%;
    left: 20%;
}
.legend-box.unavailable-legend::before {
    transform: rotate(45deg);
}
.legend-box.unavailable-legend::after {
    transform: rotate(-45deg);
}
.legend-box.weekend-legend {
    background-color: #e9ecef;
}

/* Cellules supprimées dans la grille */
.half-cell.deleted {
    background-color: #f8f9fa !important;
    background-image: none !important;
    opacity: 0.3;
    position: relative;
}
.half-cell.deleted::before {
    content: '×';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 10px;
    color: #dc3545;
    font-weight: bold;
}

/* Section des éléments supprimés - Sticky Footer */
#deleted-section {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    max-height: 35vh;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
    border-radius: 8px 8px 0 0;
    overflow: hidden;
}
#deleted-section .card {
    border-radius: 8px 8px 0 0;
    margin-bottom: 0;
}
#deleted-section .card-body {
    max-height: calc(35vh - 50px);
    overflow-y: auto;
}
.bg-warning-light {
    background-color: #fff3cd;
}
#deleted-table {
    margin-bottom: 0;
}
#deleted-table tbody tr {
    background-color: #fff8e1;
}
#deleted-table .btn-restore {
    padding: 2px 8px;
    font-size: 11px;
}
/* Toggle collapse */
.deleted-header-toggle:hover {
    opacity: 0.8;
}
#toggle-deleted-icon,
#toggle-unrecognized-icon {
    transition: transform 0.2s ease;
}
#toggle-deleted-icon.collapsed,
#toggle-unrecognized-icon.collapsed {
    transform: rotate(-90deg);
}
#deleted-section.collapsed #deleted-body {
    display: none;
}
#deleted-section.collapsed {
    max-height: none;
}
#deleted-section.collapsed .card-body {
    max-height: 0;
    overflow: hidden;
}
/* Espace en bas pour éviter que le footer masque du contenu */
.preview-content-spacer {
    height: 0;
    transition: height 0.2s ease;
}
.preview-content-spacer.active {
    height: 38vh;
}

/* Onglets */
.nav-tabs .nav-link {
    font-weight: 500;
}
.nav-tabs .nav-link.active {
    background-color: #fff;
    border-bottom-color: #fff;
}
.tab-content {
    border: 1px solid #dee2e6;
    border-top: none;
    padding: 15px;
    background: #fff;
}

/* Table responsive pour la grille */
.grid-wrapper {
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
</style>

<div class="crud-app excel-uploads preview content">
    <div class="crud-header">
        <div>
            <h1>
                Prévisualisation des données
                <small class="crud-header-meta ms-2">(<?= h($monthNames[$contextMonth]) ?> <?= $contextYear ?>)</small>
            </h1>
            <?php if (empty($groupedRanges)): ?>
                <p class="crud-header-meta">Aucune donnée trouvée dans le fichier.</p>
            <?php else: ?>
                <p class="crud-header-meta">
                    <span id="preview-range-count"><?= count($groupedRanges) ?></span> plage(s)
                    pour <span id="preview-agent-count"><?= (int)$recognizedAgentsCount ?></span> agent(s) reconnu(s).
                    Vérifiez avant d'enregistrer.
                </p>
            <?php endif; ?>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'upload'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
            <?php if (!empty($groupedRanges)): ?>
                <?php if (!empty($unrecognizedAgents)): ?>
                <div class="crud-warn">
                    <div class="d-flex justify-content-between align-items-center gap-2" id="toggle-unrecognized" style="cursor: pointer;">
                        <span>
                            <i class="bi bi-chevron-down me-1" id="toggle-unrecognized-icon"></i>
                            <strong><?= count($unrecognizedAgents) ?></strong> agent(s) non reconnu(s) — données non importées
                            <small class="text-muted ms-2">(cliquer pour <?= count($unrecognizedAgents) > 5 ? 'voir la liste' : 'masquer' ?>)</small>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-unrecognized" title="Copier la liste">
                            Copier
                        </button>
                    </div>
                    <div id="unrecognized-body" <?= count($unrecognizedAgents) > 5 ? 'style="display: none;"' : '' ?>>
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm mb-0 small">
                                <thead>
                                    <tr>
                                        <th>Nom dans le fichier</th>
                                        <th>Code/Matricule</th>
                                        <th>Absences</th>
                                        <th>Télétravail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unrecognizedAgents as $agent): ?>
                                    <tr>
                                        <td><strong><?= h($agent['name']) ?></strong></td>
                                        <td><?= h($agent['code'] ?: '-') ?></td>
                                        <td class="text-center"><?= (int)$agent['absences_count'] ?></td>
                                        <td class="text-center"><?= (int)$agent['remote_work_count'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted mb-0 mt-2">
                            Vérifiez les matricules ou créez les utilisateurs manquants.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Onglets -->
                <ul class="nav nav-tabs crud-tabs" id="previewTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="list-tab" data-bs-toggle="tab" href="#list-view" role="tab">
                            <i class="bi bi-list-ul me-1"></i> Vue Liste
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="grid-tab" data-bs-toggle="tab" href="#grid-view" role="tab">
                            <i class="bi bi-grid-3x3 me-1"></i> Vue Grille
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="previewTabContent">
                    <!-- VUE LISTE -->
                    <div class="tab-pane fade show active" id="list-view" role="tabpanel">
                        <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-danger" id="delete-selected" disabled>
                                    <i class="bi bi-trash me-1"></i> Supprimer les lignes sélectionnées
                                </button>
                            </div>
                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                <label class="mb-0 small">
                                    <i class="bi bi-funnel me-1"></i> Filtres :
                                </label>
                                <select class="form-control form-control-sm filter-select" id="filter-offer">
                                    <option value="">Toutes les offres</option>
                                </select>
                                <select class="form-control form-control-sm filter-select" id="filter-agent">
                                    <option value="">Tous les agents</option>
                                </select>
                                <select class="form-control form-control-sm filter-select" id="filter-validation">
                                    <option value="">Toutes les validations</option>
                                </select>
                                <select class="form-control form-control-sm filter-select" id="filter-demand-status">
                                    <option value="">Tous les statuts</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="reset-filters">
                                    <i class="bi bi-x-circle me-1"></i> Réinitialiser
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm small" id="preview-table">
                                <thead>
                                    <tr>
                                        <th class="col-checkbox">
                                            <input type="checkbox" id="select-all-header">
                                        </th>
                                        <th class="sortable" data-sort="number">#</th>
                                        <th class="sortable" data-sort="text">Offre</th>
                                        <th class="sortable" data-sort="text">Validation</th>
                                        <th class="sortable" data-sort="text">Statut</th>
                                        <th class="sortable" data-sort="text">Matricule</th>
                                        <th class="sortable" data-sort="text">Agent</th>
                                        <th class="sortable col-date" data-sort="date">Date début</th>
                                        <th class="sortable col-date" data-sort="date">Date fin</th>
                                        <th class="sortable" data-sort="text">Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groupedRanges as $index => $range): ?>
                                        <?php
                                        $dateStart = $range['date_start'];
                                        $dateEnd = $range['date_end'];
                                        if (!$dateStart instanceof \Cake\I18n\FrozenTime) {
                                            $dateStart = \Cake\I18n\FrozenTime::parse($dateStart);
                                        }
                                        if (!$dateEnd instanceof \Cake\I18n\FrozenTime) {
                                            $dateEnd = \Cake\I18n\FrozenTime::parse($dateEnd);
                                        }
                                        
                                        $isFullDay = (
                                            $dateStart->format('H:i:s') === '00:00:00' &&
                                            $dateEnd->format('H:i:s') === '23:59:59'
                                        );
                                        
                                        $user = $usersById[$range['user_id']] ?? null;
                                        $userName = $user ? ($user->last_name . ' ' . $user->first_name) : 'Utilisateur inconnu';
                                        $userCode = $user ? ($user->user_code ?? '') : '';
                                        
                                        $offerName = $offers[$range['offer_id']] ?? 'Offre inconnue';
                                        $offer = $offersById[$range['offer_id']] ?? null;
                                        $offerColor = $offer && $offer->color ? $offer->color : '#6c757d';
                                        $isValidated = !empty($range['is_validated']);
                                        $validationKey = $isValidated ? 'validated' : 'not_validated';
                                        $demandStatus = $range['demand_status'] ?? 'real';
                                        $offerId = (int)($range['offer_id'] ?? 0);
                                        $userId = (int)($range['user_id'] ?? 0);
                                        ?>
                                        <tr data-range-index="<?= $index ?>" id="range-row-<?= $index ?>" 
                                            data-offer-id="<?= $offerId ?>"
                                            data-offer-label="<?= h($offerName) ?>"
                                            data-user-id="<?= $userId ?>"
                                            data-user-label="<?= h($userName) ?>"
                                            data-validation="<?= h($validationKey) ?>"
                                            data-demand-status="<?= h($demandStatus) ?>">
                                            <td class="py-2 text-center">
                                                <input type="checkbox" class="row-checkbox" data-index="<?= $index ?>" value="<?= $index ?>">
                                            </td>
                                            <td class="py-2"><?= $index + 1 ?></td>
                                            <td class="py-2">
                                                <span class="badge offer-badge" style="background-color: <?= h($offerColor) ?>; color: <?= getContrastColor($offerColor) ?>;">
                                                    <?= h($offerName) ?>
                                                </span>
                                            </td>
                                            <td class="py-2">
                                                <?php if ($isValidated): ?>
                                                    <span class="badge bg-success">Validé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Non validé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2">
                                                <?php
                                                $statusLabel = 'Réel';
                                                $statusClass = 'bg-info';
                                                if ($demandStatus === 'forecast') {
                                                    $statusLabel = 'Prévisionnel';
                                                    $statusClass = 'bg-warning';
                                                } elseif ($demandStatus === 'cancellation') {
                                                    $statusLabel = 'Annulation';
                                                    $statusClass = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $statusClass ?>"><?= h($statusLabel) ?></span>
                                            </td>
                                            <td class="py-2"><?= h($userCode) ?></td>
                                            <td class="py-2"><?= h($userName) ?></td>
                                            <td class="py-2" data-sort-value="<?= $dateStart->getTimestamp() ?>">
                                                <i class="bi bi-calendar-range me-1"></i>
                                                <?= $dateStart->i18nFormat('dd/MM/yyyy') ?>
                                                <?php if (!$isFullDay): ?>
                                                    <i class="bi bi-clock text-secondary ms-2 me-1"></i>
                                                    <span class="text-muted"><?= $dateStart->i18nFormat('HH:mm') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2" data-sort-value="<?= $dateEnd->getTimestamp() ?>">
                                                <i class="bi bi-calendar-range-fill me-1"></i>
                                                <?= $dateEnd->i18nFormat('dd/MM/yyyy') ?>
                                                <?php if (!$isFullDay): ?>
                                                    <i class="bi bi-clock-fill text-secondary ms-2 me-1"></i>
                                                    <span class="text-muted"><?= $dateEnd->i18nFormat('HH:mm') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-2"><?= h($range['comment'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- VUE GRILLE -->
                    <div class="tab-pane fade" id="grid-view" role="tabpanel">
                        <!-- Légende dynamique basée sur les offres présentes -->
                        <div class="grid-legend">
                            <strong class="me-2">Offres :</strong>
                            <?php foreach ($presentOffers as $offerId => $offerInfo): ?>
                                <div class="legend-item">
                                    <div class="legend-box" style="background-color: <?= h($offerInfo['color']) ?>;"></div>
                                    <span><?= h($offerInfo['name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <span class="text-muted mx-2">|</span>
                            <div class="legend-item">
                                <div class="legend-box forecast-demo" style="background-color: #6c757d;"></div>
                                <span>Prévisionnel (hachures)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-box not-validated"></div>
                                <span>Non validé (bordure orange)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-box unavailable-legend"></div>
                                <span>Indisponible (croix)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-box weekend-legend"></div>
                                <span>Week-end</span>
                            </div>
                            <span class="text-muted mx-2">|</span>
                            <div class="legend-item">
                                <span class="text-muted small">Chaque cellule : <strong>AM</strong> | <strong>PM</strong></span>
                            </div>
                        </div>

                        <div class="grid-wrapper">
                            <table class="grid-table">
                                <thead>
                                    <tr>
                                        <th class="matricule-cell" rowspan="2">Matricule</th>
                                        <th class="agent-cell" rowspan="2">Agent</th>
                                        <th colspan="<?= $daysInMonth ?>" class="month-header text-center">
                                            <?= h($monthNames[$contextMonth]) ?> <?= $contextYear ?>
                                        </th>
                                    </tr>
                                    <tr>
                                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                            <?php
                                            $dateObj = new DateTime("$contextYear-$contextMonth-$day");
                                            $dayOfWeek = (int)$dateObj->format('w'); // 0=Dim, 6=Sam
                                            $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
                                            $dayInitial = getDayInitial($dayOfWeek);
                                            $dayName = getDayName($dayOfWeek);
                                            ?>
                                            <th class="day-header <?= $isWeekend ? 'weekend' : '' ?>" 
                                                title="<?= h($dayName) ?> <?= $day ?> <?= h($monthNames[$contextMonth]) ?>">
                                                <?= $day ?><br><small><?= $dayInitial ?></small>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Trier les agents par nom et collecter les matricules
                                    $sortedUsers = [];
                                    foreach ($gridData as $userId => $days) {
                                        $user = $usersById[$userId] ?? null;
                                        $userName = $user ? ($user->last_name . ' ' . $user->first_name) : 'Utilisateur inconnu';
                                        $userCode = $user ? ($user->user_code ?? '') : '';
                                        $sortedUsers[$userId] = ['name' => $userName, 'code' => $userCode];
                                    }
                                    uasort($sortedUsers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                                    ?>
                                    <?php foreach ($sortedUsers as $userId => $userData): ?>
                                        <tr>
                                            <td class="matricule-cell"><?= h($userData['code']) ?></td>
                                            <td class="agent-cell"><?= h($userData['name']) ?></td>
                                            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                                <?php
                                                $dateObj = new DateTime("$contextYear-$contextMonth-$day");
                                                $dayOfWeekPHP = (int)$dateObj->format('w'); // 0=Dim, 6=Sam
                                                $isWeekend = ($dayOfWeekPHP === 0 || $dayOfWeekPHP === 6);
                                                
                                                // Convertir vers convention BDD : 1=Lun à 7=Dim
                                                $dayOfWeekDB = $dayOfWeekPHP === 0 ? 7 : $dayOfWeekPHP;
                                                
                                                $dayData = $gridData[$userId][$day] ?? ['AM' => null, 'PM' => null];
                                                $eventAM = $dayData['AM'];
                                                $eventPM = $dayData['PM'];
                                                
                                                // Vérifier la disponibilité de l'agent (pas sur les weekends)
                                                $availableAM = $isWeekend ? true : isAgentAvailable($userId, $dayOfWeekDB, 'AM', $availabilitiesByUser);
                                                $availablePM = $isWeekend ? true : isAgentAvailable($userId, $dayOfWeekDB, 'PM', $availabilitiesByUser);
                                                
                                                // Construire les classes, styles et tooltips pour AM
                                                $classAM = 'half-cell am';
                                                $styleAM = '';
                                                $tooltipAM = '';
                                                if (!$availableAM && !$eventAM && !$isWeekend) {
                                                    $classAM .= ' unavailable';
                                                    $tooltipAM = '[MATIN] Agent indisponible selon ses paramètres';
                                                } elseif ($eventAM) {
                                                    $styleAM = 'background-color: ' . h($eventAM['offer_color']) . ';';
                                                    if ($eventAM['demand_status'] === 'forecast') {
                                                        $classAM .= ' forecast forecast-overlay';
                                                    }
                                                    if (!$eventAM['is_validated']) {
                                                        $classAM .= ' not-validated';
                                                    }
                                                    $statusTxt = $eventAM['demand_status'] === 'forecast' ? 'Prévisionnel' : 'Réel';
                                                    $validTxt = $eventAM['is_validated'] ? 'Validé' : 'En attente';
                                                    $tooltipAM = "[MATIN] {$eventAM['offer_name']}\n";
                                                    $tooltipAM .= "Horaires: {$eventAM['time_start']} - {$eventAM['time_end']}\n";
                                                    $tooltipAM .= "Statut: $statusTxt | $validTxt";
                                                    if (!empty($eventAM['comment'])) {
                                                        $tooltipAM .= "\nCommentaire: {$eventAM['comment']}";
                                                    }
                                                }
                                                
                                                // Construire les classes, styles et tooltips pour PM
                                                $classPM = 'half-cell pm';
                                                $stylePM = '';
                                                $tooltipPM = '';
                                                if (!$availablePM && !$eventPM && !$isWeekend) {
                                                    $classPM .= ' unavailable';
                                                    $tooltipPM = '[APRÈS-MIDI] Agent indisponible selon ses paramètres';
                                                } elseif ($eventPM) {
                                                    $stylePM = 'background-color: ' . h($eventPM['offer_color']) . ';';
                                                    if ($eventPM['demand_status'] === 'forecast') {
                                                        $classPM .= ' forecast forecast-overlay';
                                                    }
                                                    if (!$eventPM['is_validated']) {
                                                        $classPM .= ' not-validated';
                                                    }
                                                    $statusTxt = $eventPM['demand_status'] === 'forecast' ? 'Prévisionnel' : 'Réel';
                                                    $validTxt = $eventPM['is_validated'] ? 'Validé' : 'En attente';
                                                    $tooltipPM = "[APRÈS-MIDI] {$eventPM['offer_name']}\n";
                                                    $tooltipPM .= "Horaires: {$eventPM['time_start']} - {$eventPM['time_end']}\n";
                                                    $tooltipPM .= "Statut: $statusTxt | $validTxt";
                                                    if (!empty($eventPM['comment'])) {
                                                        $tooltipPM .= "\nCommentaire: {$eventPM['comment']}";
                                                    }
                                                }
                                                ?>
                                                <?php
                                                $rangeIndexAM = $eventAM ? $eventAM['range_index'] : '';
                                                $rangeIndexPM = $eventPM ? $eventPM['range_index'] : '';
                                                ?>
                                                <td class="day-cell <?= $isWeekend ? 'weekend' : '' ?>">
                                                    <div class="day-cell-inner">
                                                        <div class="<?= $classAM ?>" style="<?= $styleAM ?>" <?= $tooltipAM ? 'title="' . h($tooltipAM) . '"' : '' ?> <?= $rangeIndexAM !== '' ? 'data-range-index="' . $rangeIndexAM . '"' : '' ?>></div>
                                                        <div class="<?= $classPM ?>" style="<?= $stylePM ?>" <?= $tooltipPM ? 'title="' . h($tooltipPM) . '"' : '' ?> <?= $rangeIndexPM !== '' ? 'data-range-index="' . $rangeIndexPM . '"' : '' ?>></div>
                                                    </div>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'process'],
                        'method' => 'post',
                        'id' => 'preview-form'
                    ]) ?>
                    <?= $this->Form->hidden('excluded_indices', ['id' => 'excluded-indices', 'value' => '']) ?>
                    <?= $this->Form->button('Enregistrer les données', [
                        'type' => 'submit',
                        'class' => 'btn btn-primary',
                    ]) ?>
                    <?= $this->Form->end() ?>
                    
                    <?= $this->Html->link(
                        'Annuler',
                        ['action' => 'upload'],
                        ['class' => 'btn btn-outline-secondary']
                    ) ?>
                </div>
                
                <!-- Spacer pour éviter que le footer sticky masque du contenu -->
                <div class="preview-content-spacer" id="content-spacer"></div>
            <?php endif; ?>
</div>

<!-- Section des éléments supprimés (sticky footer) -->
<div id="deleted-section" style="display: none;">
    <div class="card border-warning mb-0">
        <div class="card-header bg-warning-light d-flex justify-content-between align-items-center py-2">
            <span class="deleted-header-toggle" id="toggle-deleted-body" style="cursor: pointer;">
                <i class="bi bi-chevron-down me-1" id="toggle-deleted-icon"></i>
                <i class="bi bi-trash text-warning me-1"></i>
                <strong>Éléments supprimés</strong>
                <span class="badge bg-warning ms-2" id="deleted-count">0</span>
                <small class="text-muted ms-2">(cliquer pour réduire)</small>
            </span>
            <button type="button" class="btn btn-sm btn-outline-success" id="restore-all">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Tout restaurer
            </button>
        </div>
        <div class="card-body p-0" id="deleted-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small" id="deleted-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Offre</th>
                            <th>Matricule</th>
                            <th>Agent</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th class="text-center" style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rempli dynamiquement par JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
$(document).ready(function() {
    var excludedIndices = [];
    
    // Fonction pour mettre à jour le bouton de suppression
    function updateDeleteButton() {
        var checkedCount = $('.row-checkbox:checked:not(:disabled)').filter(function() {
            return !$(this).closest('tr').hasClass('d-none');
        }).length;
        $('#delete-selected').prop('disabled', checkedCount === 0);
        if (checkedCount > 0) {
            $('#delete-selected').html('<i class="bi bi-trash me-1"></i> Supprimer ' + checkedCount + ' ligne(s) sélectionnée(s)');
        } else {
            $('#delete-selected').html('<i class="bi bi-trash me-1"></i> Supprimer les lignes sélectionnées');
        }
    }
    
    // Fonction pour mettre à jour la checkbox "Tout sélectionner"
    function updateSelectAll() {
        var visibleCheckboxes = $('.row-checkbox:not(:disabled)').filter(function() {
            return !$(this).closest('tr').hasClass('d-none');
        });
        var checkedCheckboxes = visibleCheckboxes.filter(':checked');
        var allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedCheckboxes.length;
        $('#select-all-header').prop('checked', allChecked);
    }
    
    // Stockage des données des lignes supprimées pour restauration
    var deletedRowsData = {};
    
    // Fonction pour mettre à jour la section des supprimés
    function updateDeletedSection() {
        var count = Object.keys(deletedRowsData).length;
        $('#deleted-count').text(count);
        if (count > 0) {
            $('#deleted-section').slideDown(200);
            $('#content-spacer').addClass('active');
        } else {
            $('#deleted-section').slideUp(200);
            $('#content-spacer').removeClass('active');
        }
    }
    
    // Fonction pour ajouter une ligne au tableau des supprimés
    function addToDeletedTable(index, rowData) {
        var html = '<tr id="deleted-row-' + index + '" data-index="' + index + '">' +
            '<td><span class="badge" style="background-color: ' + rowData.offerColor + '; color: ' + rowData.offerTextColor + ';">' + rowData.offerName + '</span></td>' +
            '<td>' + rowData.userCode + '</td>' +
            '<td>' + rowData.userName + '</td>' +
            '<td>' + rowData.dateStart + '</td>' +
            '<td>' + rowData.dateEnd + '</td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-success btn-restore" data-index="' + index + '">' +
            '<i class="bi bi-arrow-counterclockwise"></i> Restaurer</button></td>' +
            '</tr>';
        $('#deleted-table tbody').append(html);
    }
    
    // Fonction pour restaurer une ligne
    function restoreRow(index) {
        // Retirer de la liste des exclus
        var idx = excludedIndices.indexOf(index);
        if (idx > -1) {
            excludedIndices.splice(idx, 1);
        }
        $('#excluded-indices').val(excludedIndices.join(','));
        
        // Réafficher dans la vue liste
        var row = $('#range-row-' + index);
        row.removeClass('d-none row-deleted');
        row.find('.row-checkbox').prop('disabled', false);
        row.fadeIn(300);
        
        // Restaurer les cellules dans la grille avec leur style original
        if (deletedRowsData[index] && deletedRowsData[index].gridCells) {
            deletedRowsData[index].gridCells.forEach(function(cellInfo) {
                var cell = $('.half-cell[data-range-index="' + index + '"]').filter(function() {
                    return $(this).hasClass(cellInfo.period.toLowerCase());
                });
                cell.each(function() {
                    $(this).removeClass('deleted');
                    if (cellInfo.style) {
                        $(this).attr('style', cellInfo.style);
                    }
                });
            });
        }
        
        // Retirer du tableau des supprimés
        $('#deleted-row-' + index).fadeOut(200, function() {
            $(this).remove();
        });
        delete deletedRowsData[index];
        
        // Mettre à jour les compteurs
        var remainingCount = $('#preview-table tbody tr:not(.d-none):not(.row-deleted)').length;
        $('#preview-range-count').text(remainingCount);
        updateDeletedSection();
        updateDeleteButton();
    }
    
    // Fonction pour supprimer les lignes sélectionnées
    function deleteSelectedRows() {
        var selectedIndices = [];
        $('.row-checkbox:checked:not(:disabled)').each(function() {
            var index = parseInt($(this).data('index'));
            selectedIndices.push(index);
            
            if (excludedIndices.indexOf(index) === -1) {
                excludedIndices.push(index);
            }
            
            // Récupérer les données de la ligne pour la restauration
            var row = $('#range-row-' + index);
            var rowData = {
                offerName: row.attr('data-offer-label'),
                offerColor: row.find('.offer-badge').css('background-color'),
                offerTextColor: row.find('.offer-badge').css('color'),
                userCode: row.find('td').eq(5).text().trim(),
                userName: row.attr('data-user-label'),
                dateStart: row.find('td').eq(7).text().trim().split('\n')[0].trim(),
                dateEnd: row.find('td').eq(8).text().trim().split('\n')[0].trim(),
                gridCells: []
            };
            
            // Sauvegarder les styles des cellules de grille avant suppression
            $('.half-cell[data-range-index="' + index + '"]').each(function() {
                rowData.gridCells.push({
                    period: $(this).hasClass('am') ? 'AM' : 'PM',
                    style: $(this).attr('style') || ''
                });
            });
            
            deletedRowsData[index] = rowData;
            
            // Ajouter au tableau des supprimés
            addToDeletedTable(index, rowData);
            
            // Masquer la ligne dans la vue liste
            row.fadeOut(300, function() {
                $(this).addClass('d-none row-deleted');
                $(this).find('.row-checkbox').prop('disabled', true);
            });
            
            // Synchroniser avec la vue grille : marquer les cellules correspondantes comme supprimées
            $('.half-cell[data-range-index="' + index + '"]').each(function() {
                $(this).addClass('deleted');
                $(this).removeAttr('style');
            });
        });
        
        $('.row-checkbox').prop('checked', false);
        $('#select-all-header').prop('checked', false);
        $('#excluded-indices').val(excludedIndices.join(','));
        
        var remainingCount = $('#preview-table tbody tr:not(.d-none):not(.row-deleted)').length;
        $('#preview-range-count').text(remainingCount);
        
        updateDeletedSection();
        updateDeleteButton();
    }
    
    // Événement : clic sur bouton restaurer individuel
    $(document).on('click', '.btn-restore', function() {
        var index = parseInt($(this).data('index'));
        restoreRow(index);
    });
    
    // Événement : clic sur "Tout restaurer"
    $('#restore-all').on('click', function() {
        var indicesToRestore = Object.keys(deletedRowsData).map(function(i) { return parseInt(i); });
        indicesToRestore.forEach(function(index) {
            restoreRow(index);
        });
    });
    
    // Événement : toggle collapse de la section supprimés
    $('#toggle-deleted-body').on('click', function() {
        var section = $('#deleted-section');
        var icon = $('#toggle-deleted-icon');
        var hint = $(this).find('small');
        
        section.toggleClass('collapsed');
        icon.toggleClass('collapsed');
        
        if (section.hasClass('collapsed')) {
            $('#deleted-body').slideUp(200);
            hint.text('(cliquer pour développer)');
            $('#content-spacer').removeClass('active');
        } else {
            $('#deleted-body').slideDown(200);
            hint.text('(cliquer pour réduire)');
            $('#content-spacer').addClass('active');
        }
    });
    
    $(document).on('change', '.row-checkbox', function() {
        updateDeleteButton();
        updateSelectAll();
    });
    
    $('#select-all-header').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.row-checkbox:not(:disabled)').prop('checked', isChecked);
        updateDeleteButton();
    });
    
    $('#delete-selected').on('click', function() {
        deleteSelectedRows();
    });
    
    $('a[href*="upload"]').on('click', function() {
        excludedIndices = [];
        deletedRowsData = {};
        $('#excluded-indices').val('');
        $('#deleted-table tbody').empty();
        updateDeletedSection();
    });
    
    updateDeleteButton();
    updateDeletedSection();
    
    // Gestion de la section agents non reconnus
    $('#toggle-unrecognized').on('click', function(e) {
        // Ne pas toggle si on clique sur le bouton Copier
        if ($(e.target).closest('#copy-unrecognized').length) return;
        
        var body = $('#unrecognized-body');
        var icon = $('#toggle-unrecognized-icon');
        var hint = $(this).find('small');
        
        body.slideToggle(200);
        icon.toggleClass('collapsed');
        
        if (body.is(':visible')) {
            hint.text('(cliquer pour masquer)');
        } else {
            hint.text('(cliquer pour voir la liste)');
        }
    });
    
    // Bouton copier la liste des agents non reconnus
    $('#copy-unrecognized').on('click', function(e) {
        e.stopPropagation();
        var text = '';
        $('#unrecognized-body table tbody tr').each(function() {
            var name = $(this).find('td:eq(0)').text().trim();
            var code = $(this).find('td:eq(1)').text().trim();
            text += name + (code !== '-' ? ' (' + code + ')' : '') + '\n';
        });
        
        navigator.clipboard.writeText(text.trim()).then(function() {
            var btn = $('#copy-unrecognized');
            var originalHtml = btn.html();
            btn.html('<i class="bi bi-check"></i> Copié !');
            btn.removeClass('btn-outline-secondary').addClass('btn-success');
            setTimeout(function() {
                btn.html(originalHtml);
                btn.removeClass('btn-success').addClass('btn-outline-secondary');
            }, 2000);
        });
    });
    
    // Initialiser l'icône du toggle agents non reconnus
    if ($('#unrecognized-body').is(':hidden')) {
        $('#toggle-unrecognized-icon').addClass('collapsed');
    }
    
    // Gestion des filtres
    function applyFilters() {
        var offerFilter = $('#filter-offer').val();
        var agentFilter = $('#filter-agent').val();
        var validationFilter = $('#filter-validation').val();
        var demandStatusFilter = $('#filter-demand-status').val();
        
        $('#preview-table tbody tr').each(function() {
            var $row = $(this);
            if ($row.hasClass('row-deleted')) {
                return;
            }
            
            var offerId = $row.attr('data-offer-id') || '';
            var userId = $row.attr('data-user-id') || '';
            var validation = $row.attr('data-validation') || '';
            var demandStatus = $row.attr('data-demand-status') || '';
            
            var showRow = true;
            
            if (offerFilter) showRow = showRow && (offerId === offerFilter);
            if (agentFilter) showRow = showRow && (userId === agentFilter);
            if (validationFilter) showRow = showRow && (validation === validationFilter);
            if (demandStatusFilter) showRow = showRow && (demandStatus === demandStatusFilter);
            
            if (showRow) {
                $row.removeClass('d-none');
            } else {
                $row.addClass('d-none');
            }
        });
        
        var visibleCount = $('#preview-table tbody tr:not(.d-none):not(.row-deleted)').length;
        $('#preview-range-count').text(visibleCount);
        
        updateDeleteButton();
    }

    function rebuildSelectOptions($select, allLabel, options, selectedValue) {
        $select.empty();
        $select.append($('<option>', { value: '', text: allLabel }));
        options.forEach(function(opt) {
            $select.append($('<option>', { value: opt.value, text: opt.label }));
        });
        if (selectedValue !== undefined) {
            $select.val(selectedValue);
        }
    }
    
    function refreshFilterOptions() {
        var $rows = $('#preview-table tbody tr').not('.row-deleted');
        
        var selectedOffer = $('#filter-offer').val();
        var selectedAgent = $('#filter-agent').val();
        var selectedValidation = $('#filter-validation').val();
        var selectedDemandStatus = $('#filter-demand-status').val();
        
        var offersMap = {};
        var usersMap = {};
        $rows.each(function() {
            var $r = $(this);
            var offerId = $r.attr('data-offer-id') || '';
            var offerLabel = $r.attr('data-offer-label') || offerId;
            var userId = $r.attr('data-user-id') || '';
            var userLabel = $r.attr('data-user-label') || userId;
            if (offerId) offersMap[offerId] = offerLabel;
            if (userId) usersMap[userId] = userLabel;
        });
        
        var offerOptions = Object.keys(offersMap).map(function(id) {
            return { value: id, label: offersMap[id] };
        }).sort(function(a, b) {
            return a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' });
        });
        
        var userOptions = Object.keys(usersMap).map(function(id) {
            return { value: id, label: usersMap[id] };
        }).sort(function(a, b) {
            return a.label.localeCompare(b.label, 'fr', { sensitivity: 'base' });
        });
        
        rebuildSelectOptions($('#filter-offer'), 'Toutes les offres', offerOptions, selectedOffer);
        rebuildSelectOptions($('#filter-agent'), 'Tous les agents', userOptions, selectedAgent);
        
        var validationSet = {};
        var demandStatusSet = {};
        $rows.each(function() {
            var $r = $(this);
            validationSet[$r.attr('data-validation') || ''] = true;
            demandStatusSet[$r.attr('data-demand-status') || ''] = true;
        });
        
        var validationLabel = {
            'validated': 'Validé',
            'not_validated': 'Non validé'
        };
        var demandStatusLabel = {
            'real': 'Réel',
            'forecast': 'Prévisionnel',
            'cancellation': 'Annulation'
        };
        
        var validationOrder = ['validated', 'not_validated'];
        var statusOrder = ['real', 'forecast', 'cancellation'];
        
        var validationOptions = validationOrder.filter(function(v) { return !!validationSet[v]; }).map(function(v) {
            return { value: v, label: validationLabel[v] || v };
        });
        
        var statusOptions = statusOrder.filter(function(v) { return !!demandStatusSet[v]; }).map(function(v) {
            return { value: v, label: demandStatusLabel[v] || v };
        });
        
        rebuildSelectOptions($('#filter-validation'), 'Toutes les validations', validationOptions, selectedValidation);
        rebuildSelectOptions($('#filter-demand-status'), 'Tous les statuts', statusOptions, selectedDemandStatus);
    }
    
    refreshFilterOptions();
    
    $('#filter-offer, #filter-agent, #filter-validation, #filter-demand-status').on('change', function() {
        applyFilters();
    });
    
    $('#reset-filters').on('click', function() {
        $('#filter-offer').val('');
        $('#filter-agent').val('');
        $('#filter-validation').val('');
        $('#filter-demand-status').val('');
        $('#preview-table tbody tr').removeClass('d-none');
        var totalCount = $('#preview-table tbody tr').length;
        $('#preview-range-count').text(totalCount);
        updateDeleteButton();
    });
    
    // Tri dynamique du tableau
    $('#preview-table .sortable').on('click', function(e) {
        e.preventDefault();
        var table = $('#preview-table');
        var tbody = table.find('tbody');
        var th = $(this);
        var column = th.index();
        var sortType = th.data('sort');
        var isAsc = th.hasClass('sort-asc');
        
        $('#preview-table .sortable').removeClass('sort-asc sort-desc');
        
        if (isAsc) {
            th.addClass('sort-desc');
        } else {
            th.addClass('sort-asc');
        }
        
        var rows = tbody.find('tr:not(.d-none)').toArray();
        
        if (rows.length === 0) return;
        
        rows.sort(function(a, b) {
            var aVal, bVal;
            var aCell = $(a).find('td').eq(column);
            var bCell = $(b).find('td').eq(column);
            
            if (aCell.attr('data-sort-value') !== undefined) {
                aVal = parseFloat(aCell.attr('data-sort-value')) || 0;
                bVal = parseFloat(bCell.attr('data-sort-value')) || 0;
            } else {
                aVal = aCell.text().trim();
                bVal = bCell.text().trim();
            }
            
            if (sortType === 'number') {
                aVal = parseInt(aVal) || 0;
                bVal = parseInt(bVal) || 0;
            } else if (sortType === 'date') {
                if (typeof aVal === 'string') {
                    aVal = parseFloat(aVal) || 0;
                }
                if (typeof bVal === 'string') {
                    bVal = parseFloat(bVal) || 0;
                }
            } else {
                aVal = String(aVal).toLowerCase();
                bVal = String(bVal).toLowerCase();
            }
            
            if (aVal < bVal) {
                return isAsc ? 1 : -1;
            }
            if (aVal > bVal) {
                return isAsc ? -1 : 1;
            }
            return 0;
        });
        
        tbody.empty();
        $.each(rows, function(index, row) {
            tbody.append(row);
        });
    });
});
<?php $this->Html->scriptEnd(); ?>
