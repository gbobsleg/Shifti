<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\Log\Log;

/**
 * Aligne la mise à jour des agents entre Passe 1 et Passe 2:
 * - construit unavailable_intervals avec allow_lunch selon lunch_overlap_allowed
 * - calcule preferred_lunch_starts selon lunch_attach_mode (before/after)
 * - supprime earliest_end_time si unavailable_intervals non vide (cohérence solveur)
 */
class AgentsAfterFixedActivitiesService
{
    /**
     * @param array<int,array<string,mixed>> $agents
     * @param array<int,array<string,mixed>> $assignments (Passe 1)
     * @param array<int,array<string,mixed>> $fixedActivities (utilisées en Passe 1)
     * @param array{start:string,end:string}|null $lunchWindow (optionnel)
     * @return array<int,array<string,mixed>>
     */
    public function update(array $agents, array $assignments, array $fixedActivities, ?array $lunchWindow = null): array
    {
        // Mapping activité => politiques repas + mode de collage
        $lunchPolicyByActivity = [];
        $lunchAttachModeByActivity = [];
        foreach ($fixedActivities as $fa) {
            if (empty($fa['offer_name'])) {
                continue;
            }
            $name = (string)$fa['offer_name'];
            $allow = array_key_exists('lunch_overlap_allowed', $fa) ? (bool)$fa['lunch_overlap_allowed'] : true;
            $lunchPolicyByActivity[$name] = $allow;
            $mode = isset($fa['lunch_attach_mode']) ? (string)$fa['lunch_attach_mode'] : 'none';
            if (!in_array($mode, ['none', 'before', 'after'], true)) {
                $mode = 'none';
            }
            $lunchAttachModeByActivity[$name] = $mode;
        }

        $unavailableByAgent = [];
        $preferredLunchStartsByAgent = [];
        
        // Dictionnaire pour collecter les pauses une seule fois par agent (éviter les doublons)
        // Structure: $breaksCollectedByAgent[$agentId]['am_break'] = ['start' => ..., 'end' => ..., 'activity' => ...]
        $breaksCollectedByAgent = [];

        // Fenêtre de filtrage des préférences (si non fournie, fallback existant)
        $lunchStartDefault = new FrozenTime(($lunchWindow['start'] ?? '11:30:00'));
        $lunchEndDefault = new FrozenTime(($lunchWindow['end'] ?? '14:00:00'));

        foreach ($assignments as $assignment) {
            $agentId = (int)($assignment['agent_id'] ?? 0);
            if ($agentId <= 0) {
                continue;
            }
            $start = $assignment['start'] ?? null;
            $end = $assignment['end'] ?? null;
            $activityName = $assignment['activity'] ?? null;
            if (!$start || !$end) {
                continue;
            }

            // Vérifier si ce créneau correspond à une pause : si oui, ne pas l'ajouter comme activité
            // (la pause sera ajoutée à la fin avec une priorité visuelle)
            $isBreakSlot = false;
            if (isset($assignment['breaks']) && is_array($assignment['breaks'])) {
                $breaks = $assignment['breaks'];
                $slotStart = (string)$start;
                $slotEnd = (string)$end;
                
                // Vérifier pause AM (Correction : utiliser >= et <= pour l'inclusion)
                if (isset($breaks['am_break']) && is_array($breaks['am_break']) && count($breaks['am_break']) >= 2) {
                    $breakStart = (string)$breaks['am_break'][0];
                    $breakEnd = (string)$breaks['am_break'][1];
                    // DEBUG: Afficher les valeurs brutes comparées
                    Log::write('debug', sprintf(
                        "[AgentsAfterFixedActivities] Agent %d - AM Break comparison: slotStart='%s' (len=%d) vs breakStart='%s' (len=%d), slotEnd='%s' (len=%d) vs breakEnd='%s' (len=%d)",
                        $agentId,
                        $slotStart, strlen($slotStart),
                        $breakStart, strlen($breakStart),
                        $slotEnd, strlen($slotEnd),
                        $breakEnd, strlen($breakEnd)
                    ));
                    // Le créneau est une pause s'il est INCLUS dans l'intervalle de pause
                    if ($slotStart >= $breakStart && $slotEnd <= $breakEnd) {
                        $isBreakSlot = true;
                        Log::write('debug', sprintf("[AgentsAfterFixedActivities] Agent %d - BREAK DETECTED (AM): slot [%s-%s] is within break [%s-%s]", $agentId, $slotStart, $slotEnd, $breakStart, $breakEnd));
                    }
                }

                // Vérifier pause PM (Correction : utiliser >= et <= pour l'inclusion)
                if (!$isBreakSlot && isset($breaks['pm_break']) && is_array($breaks['pm_break']) && count($breaks['pm_break']) >= 2) {
                    $breakStart = (string)$breaks['pm_break'][0];
                    $breakEnd = (string)$breaks['pm_break'][1];
                    // DEBUG: Afficher les valeurs brutes comparées
                    Log::write('debug', sprintf(
                        "[AgentsAfterFixedActivities] Agent %d - PM Break comparison: slotStart='%s' (len=%d) vs breakStart='%s' (len=%d), slotEnd='%s' (len=%d) vs breakEnd='%s' (len=%d)",
                        $agentId,
                        $slotStart, strlen($slotStart),
                        $breakStart, strlen($breakStart),
                        $slotEnd, strlen($slotEnd),
                        $breakEnd, strlen($breakEnd)
                    ));
                    // Le créneau est une pause s'il est INCLUS dans l'intervalle de pause
                    if ($slotStart >= $breakStart && $slotEnd <= $breakEnd) {
                        $isBreakSlot = true;
                        Log::write('debug', sprintf("[AgentsAfterFixedActivities] Agent %d - BREAK DETECTED (PM): slot [%s-%s] is within break [%s-%s]", $agentId, $slotStart, $slotEnd, $breakStart, $breakEnd));
                    }
                }

                // Vérifier lunch
                if (!$isBreakSlot && isset($breaks['lunch']) && is_array($breaks['lunch']) && count($breaks['lunch']) >= 2) {
                    $breakStart = (string)$breaks['lunch'][0];
                    $breakEnd = (string)$breaks['lunch'][1];
                    if ($slotStart >= $breakStart && $slotEnd <= $breakEnd) {
                        $isBreakSlot = true;
                        Log::write('debug', sprintf("[AgentsAfterFixedActivities] Agent %d - BREAK DETECTED (Lunch): slot [%s-%s] is within break [%s-%s]", $agentId, $slotStart, $slotEnd, $breakStart, $breakEnd));
                    }
                }
            }
            
            // Si ce créneau correspond à une pause, ne pas l'ajouter comme activité (skip)
            // Les pauses seront ajoutées à la fin avec un libellé distinct
            if ($isBreakSlot) {
                // Collecter les pauses pour l'ajout à la fin (déduplication automatique via !isset)
                if (isset($assignment['breaks']) && is_array($assignment['breaks'])) {
                    $breaks = $assignment['breaks'];
                    
                    // Pause AM : stocker une seule fois par agent
                    if (isset($breaks['am_break']) && is_array($breaks['am_break']) && count($breaks['am_break']) >= 2) {
                        if (!isset($breaksCollectedByAgent[$agentId]['am_break'])) {
                            $breaksCollectedByAgent[$agentId]['am_break'] = [
                                'start' => (string)$breaks['am_break'][0],
                                'end' => (string)$breaks['am_break'][1],
                                'activity' => $activityName,
                            ];
                        }
                    }
                    
                    // Pause PM : stocker une seule fois par agent
                    if (isset($breaks['pm_break']) && is_array($breaks['pm_break']) && count($breaks['pm_break']) >= 2) {
                        if (!isset($breaksCollectedByAgent[$agentId]['pm_break'])) {
                            $breaksCollectedByAgent[$agentId]['pm_break'] = [
                                'start' => (string)$breaks['pm_break'][0],
                                'end' => (string)$breaks['pm_break'][1],
                                'activity' => $activityName,
                            ];
                        }
                    }
                    
                    // Lunch : stocker une seule fois par agent
                    if (isset($breaks['lunch']) && is_array($breaks['lunch']) && count($breaks['lunch']) >= 2) {
                        if (!isset($breaksCollectedByAgent[$agentId]['lunch'])) {
                            $breaksCollectedByAgent[$agentId]['lunch'] = [
                                'start' => (string)$breaks['lunch'][0],
                                'end' => (string)$breaks['lunch'][1],
                                'activity' => $activityName,
                            ];
                        }
                    }
                }
                continue; // Skip cet assignment, c'est un créneau de pause
            }
            
            // Ce créneau n'est PAS une pause : ajouter l'intervalle d'activité normalement
            $allowLunch = true;
            if ($activityName && isset($lunchPolicyByActivity[$activityName])) {
                $allowLunch = $lunchPolicyByActivity[$activityName];
            }
            
            // allow_breaks: autoriser les pauses AM/PM sur les activités fixes longues
            // Par défaut: true (les pauses peuvent recouvrir l'activité fixe)
            // On pourrait ajouter une option dans FixedActivityRules si besoin, mais pour l'instant on autorise toujours
            $allowBreaks = true;
            
            $unavailableByAgent[$agentId][] = [
                'start' => (string)$start,
                'end' => (string)$end,
                'allow_lunch' => $allowLunch,
                'allow_breaks' => $allowBreaks,
            ];

            // Collecter les pauses planifiées (si présentes dans cet assignment mais pas dans ce créneau)
            // Les pauses sont collectées pour l'ajout à la fin avec déduplication automatique
            if (isset($assignment['breaks']) && is_array($assignment['breaks'])) {
                $breaks = $assignment['breaks'];
                
                // Pause AM : stocker une seule fois par agent
                if (isset($breaks['am_break']) && is_array($breaks['am_break']) && count($breaks['am_break']) >= 2) {
                    if (!isset($breaksCollectedByAgent[$agentId]['am_break'])) {
                        $breaksCollectedByAgent[$agentId]['am_break'] = [
                            'start' => (string)$breaks['am_break'][0],
                            'end' => (string)$breaks['am_break'][1],
                            'activity' => $activityName,
                        ];
                    }
                }
                
                // Pause PM : stocker une seule fois par agent
                if (isset($breaks['pm_break']) && is_array($breaks['pm_break']) && count($breaks['pm_break']) >= 2) {
                    if (!isset($breaksCollectedByAgent[$agentId]['pm_break'])) {
                        $breaksCollectedByAgent[$agentId]['pm_break'] = [
                            'start' => (string)$breaks['pm_break'][0],
                            'end' => (string)$breaks['pm_break'][1],
                            'activity' => $activityName,
                        ];
                    }
                }
                
                // Lunch : stocker une seule fois par agent
                if (isset($breaks['lunch']) && is_array($breaks['lunch']) && count($breaks['lunch']) >= 2) {
                    if (!isset($breaksCollectedByAgent[$agentId]['lunch'])) {
                        $breaksCollectedByAgent[$agentId]['lunch'] = [
                            'start' => (string)$breaks['lunch'][0],
                            'end' => (string)$breaks['lunch'][1],
                            'activity' => $activityName,
                        ];
                    }
                }
            }

            // Préférence repas
            if ($activityName && isset($lunchAttachModeByActivity[$activityName])) {
                $mode = $lunchAttachModeByActivity[$activityName];
                if ($mode !== 'none') {
                    try {
                        $startTime = new FrozenTime((string)$start);
                        $endTime = new FrozenTime((string)$end);
                    } catch (\Throwable) {
                        $startTime = null;
                        $endTime = null;
                    }

                    if ($startTime && $endTime) {
                        $idealStart = null;
                        if ($mode === 'before') {
                            $idealStart = (clone $startTime)->subMinutes(60);
                        } elseif ($mode === 'after') {
                            $idealStart = clone $endTime;
                        }

                        if ($idealStart !== null && $idealStart >= $lunchStartDefault && $idealStart < $lunchEndDefault) {
                            $idealStr = $idealStart->format('H:i:s');
                            if (!isset($preferredLunchStartsByAgent[$agentId])) {
                                $preferredLunchStartsByAgent[$agentId] = [];
                            }
                            if (!in_array($idealStr, $preferredLunchStartsByAgent[$agentId], true)) {
                                $preferredLunchStartsByAgent[$agentId][] = $idealStr;
                            }
                        }
                    }
                }
            }
        }

        // DEBUG supprimé : logs verbeux dans boucle critique

        // Ajouter les pauses collectées une seule fois par agent dans unavailableByAgent
        // Renommer l'activity pour que l'interface affiche un libellé distinct ("Pause - [NomActivité]")
        foreach ($breaksCollectedByAgent as $agentId => $breaksByType) {
            // Pause AM
            if (isset($breaksByType['am_break'])) {
                $originalActivity = $breaksByType['am_break']['activity'] ?? null;
                $pauseActivity = $originalActivity ? "Pause - {$originalActivity}" : "Pause";
                $unavailableByAgent[$agentId][] = [
                    'start' => $breaksByType['am_break']['start'],
                    'end' => $breaksByType['am_break']['end'],
                    'allow_lunch' => false,
                    'allow_breaks' => false,
                    'activity' => $pauseActivity,
                ];
            }
            
            // Pause PM
            if (isset($breaksByType['pm_break'])) {
                $originalActivity = $breaksByType['pm_break']['activity'] ?? null;
                $pauseActivity = $originalActivity ? "Pause - {$originalActivity}" : "Pause";
                $unavailableByAgent[$agentId][] = [
                    'start' => $breaksByType['pm_break']['start'],
                    'end' => $breaksByType['pm_break']['end'],
                    'allow_lunch' => false,
                    'allow_breaks' => false,
                    'activity' => $pauseActivity,
                ];
            }
            
            // Lunch
            if (isset($breaksByType['lunch'])) {
                $originalActivity = $breaksByType['lunch']['activity'] ?? null;
                $pauseActivity = $originalActivity ? "Pause - {$originalActivity}" : "Pause";
                $unavailableByAgent[$agentId][] = [
                    'start' => $breaksByType['lunch']['start'],
                    'end' => $breaksByType['lunch']['end'],
                    'allow_lunch' => false,
                    'allow_breaks' => false,
                    'activity' => $pauseActivity,
                ];
            }
        }

        $updated = [];
        foreach ($agents as $agent) {
            $agentId = (int)($agent['id'] ?? 0);
            $a = $agent;
            $a['unavailable_intervals'] = !empty($unavailableByAgent[$agentId]) ? $unavailableByAgent[$agentId] : null;
            $a['preferred_lunch_starts'] = !empty($preferredLunchStartsByAgent[$agentId]) ? $preferredLunchStartsByAgent[$agentId] : null;

            // Alignement: si activités fixes (unavailable_intervals), supprimer earliest_end_time (incohérence sinon)
            if (!empty($a['unavailable_intervals']) && isset($a['earliest_end_time'])) {
                unset($a['earliest_end_time']);
            }

            $updated[] = $a;
        }

        // DEBUG supprimé : logs verbeux avec dumps JSON

        return $updated;
    }
}


