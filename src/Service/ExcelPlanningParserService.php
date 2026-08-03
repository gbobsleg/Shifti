<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use SimpleXMLElement;
use RuntimeException;
use Cake\Log\Log;
use Cake\I18n\Time;

/**
 * Service de parsing Excel - Version Pivot (Continuité Temporelle)
 * Logique : Start -> Pivot -> End (Pas de trou déjeuner forcé)
 */
class ExcelPlanningParserService
{
    use LocatorAwareTrait;

    private const START_COL_INDEX = 4; // Colonne E

    // --- PROPRIÉTÉS PAR DÉFAUT (Écrasées par WfmSettings) ---
    private array $mappingRules = [];
    private array $workedDays = [1, 2, 3, 4, 5];
    
    // Horaires par défaut
    private string $dayStartTime = '08:00:00'; // Début Journée
    private string $halfDayPivot = '13:00:00'; // Pivot Matin/Aprèm
    private string $dayEndTime   = '18:00:00'; // Fin Journée

    public function parseFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) throw new RuntimeException("Fichier introuvable");

        $this->loadConfiguration();

        $content = file_get_contents($filePath);
        $content = preg_replace('/&(?!(?:apos|quot|[gl]t|amp);|#\d+;|#[xX][0-9a-fA-F]+;)/', '&amp;', $content);
        $content = str_replace(['ss:', 'x:', 'o:', 'html:', 'xmlns="'], ['', '', '', '', 'ns="'], $content);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_PARSEHUGE);
        if ($xml === false) throw new RuntimeException("XML invalide");

        $worksheet = $xml->Worksheet[0] ?? null;
        if (!$worksheet) throw new RuntimeException("Pas de Worksheet");

        $stylesMap = [];
        if (isset($xml->Styles->Style)) {
            foreach ($xml->Styles->Style as $style) {
                $id = (string)$style['ID'];
                $color = (string)($style->Interior['Color'] ?? '');
                if ($id && $color) {
                    $stylesMap[$id] = strtolower(ltrim($color, '#'));
                }
            }
        }

        $month = (int)($options['context_month'] ?? date('m'));
        $year = (int)($options['context_year'] ?? date('Y'));

        $agentsData = [];
        $rows = $worksheet->Table->Row;
        $limit = count($rows);

        for ($i = 3; $i < $limit; $i++) {
            if (!isset($rows[$i])) continue;
            $row = $rows[$i];

            $agentCode = $this->getCellValue($row, 3); // Matricule (colonne D)
            if (empty($agentCode) || mb_strlen($agentCode) < 2 || $agentCode === 'N° agent') continue;
            
            // Récupérer aussi le nom et prénom (colonnes B et C)
            $lastName = $this->getCellValue($row, 1);  // Nom (colonne B)
            $firstName = $this->getCellValue($row, 2); // Prénom (colonne C)
            $fullName = trim($lastName . ' ' . $firstName);

            $cells = [];
            foreach ($row->Cell as $cell) {
                $idx = isset($cell['Index']) ? (int)$cell['Index'] - 1 : count($cells);
                while (count($cells) < $idx) $cells[] = null;
                $cells[$idx] = $cell;
            }

            $currentAgent = [
                'agent' => $agentCode,           // Matricule (pour compatibilité)
                'code' => $agentCode,            // Matricule
                'name' => $fullName ?: $agentCode, // Nom complet (ou matricule si vide)
                'last_name' => $lastName,
                'first_name' => $firstName,
                'absences' => [],
                'remote_work' => []
            ];

            $this->analyzeCalendar($cells, $currentAgent, $stylesMap, $month, $year);

            $agentsData[] = $currentAgent;
        }

        return $agentsData;
    }

    private function analyzeCalendar(array $cells, array &$agentData, array $stylesMap, int $month, int $year): void
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayOfWeek = (int)date('N', strtotime($dateStr));
            if (!in_array($dayOfWeek, $this->workedDays)) continue;

            $colIndexAM = self::START_COL_INDEX + (($day - 1) * 2);
            $colIndexPM = $colIndexAM + 1;

            $cellAM = $cells[$colIndexAM] ?? null;
            $cellPM = $cells[$colIndexPM] ?? null;

            $infoAM = $this->extractCellInfo($cellAM, $stylesMap);
            $infoPM = $this->extractCellInfo($cellPM, $stylesMap);

            // Harmonisation par Continuité
            if (($infoAM['color'] === $infoPM['color'])) {
                if ($this->shouldPropagate($infoPM['match_absence'], $infoAM['match_absence'])) {
                    $infoAM['match_absence'] = $infoPM['match_absence'];
                    if ($infoPM['times']) $infoAM['times'] = $infoPM['times'];
                    if (!empty($infoPM['comment_raw'])) $infoAM['comment_raw'] = $infoPM['comment_raw'];
                } elseif ($this->shouldPropagate($infoAM['match_absence'], $infoPM['match_absence'])) {
                    $infoPM['match_absence'] = $infoAM['match_absence'];
                    if ($infoAM['times']) $infoPM['times'] = $infoAM['times'];
                    if (!empty($infoAM['comment_raw'])) $infoPM['comment_raw'] = $infoAM['comment_raw'];
                }

                if ($this->shouldPropagate($infoPM['match_remote'], $infoAM['match_remote'])) {
                    $infoAM['match_remote'] = $infoPM['match_remote'];
                } elseif ($this->shouldPropagate($infoAM['match_remote'], $infoPM['match_remote'])) {
                    $infoPM['match_remote'] = $infoAM['match_remote'];
                }
            }

            // Statuts & Validation
            $statusAM = ($infoAM['text'] === 'P' || str_contains(strtolower($infoAM['comment_raw']), 'prévisionnel')) ? 'forecast' : 'real';
            $statusPM = ($infoPM['text'] === 'P' || str_contains(strtolower($infoPM['comment_raw']), 'prévisionnel')) ? 'forecast' : 'real';

            $validatedAM = $this->isEventValidated($infoAM['comment_raw']);
            $validatedPM = $this->isEventValidated($infoPM['comment_raw']);

            $isMerged = $cellAM && (isset($cellAM['MergeAcross']) && (int)$cellAM['MergeAcross'] >= 1);

            // Fusion Intelligente
            $sameAbsence = ($infoAM['match_absence']['offer_id'] ?? null) === ($infoPM['match_absence']['offer_id'] ?? null);
            $sameRemote  = ($infoAM['match_remote']['offer_id'] ?? null) === ($infoPM['match_remote']['offer_id'] ?? null);
            $sameStatus  = ($statusAM === $statusPM);
            $sameValidation = ($validatedAM === $validatedPM);

            if ($isMerged || ($sameAbsence && $sameRemote && $sameStatus && $sameValidation)) {
                // Journée complète (Start -> End)
                $infoToUse = !empty($infoPM['comment_raw']) ? $infoPM : $infoAM;
                $this->createEventsForSlot($agentData, $dateStr, $this->dayStartTime, $this->dayEndTime, $infoToUse, $statusAM, $validatedAM);
            } else {
                // Demi-journées (Utilisation du PIVOT)
                // Matin : Start -> Pivot
                $this->createEventsForSlot($agentData, $dateStr, $this->dayStartTime, $this->halfDayPivot, $infoAM, $statusAM, $validatedAM);
                // Après-midi : Pivot -> End
                $this->createEventsForSlot($agentData, $dateStr, $this->halfDayPivot, $this->dayEndTime, $infoPM, $statusPM, $validatedPM);
            }
        }
    }

    private function isEventValidated(string $comment): bool
    {
        $normalized = mb_strtolower($comment);
        if (str_contains($normalized, '(en attente)') || str_contains($normalized, 'en attente')) {
            return false;
        }
        return true;
    }

    private function shouldPropagate(?array $source, ?array $target): bool 
    {
        if ($source === null) return false;
        if ($target === null) return true;
        return ($source['matched_by_keyword'] ?? false) && !($target['matched_by_keyword'] ?? false);
    }

    private function createEventsForSlot(array &$agentData, string $dateStr, string $start, string $end, array $info, string $status, bool $isValidated): void
    {
        if ($info['match_absence']) {
            $times = $info['times'] ?? ['start' => $start, 'end' => $end];
            $this->addEventToAgent($agentData, 'absences', $dateStr, $times['start'], $times['end'], $info['match_absence'], $status, $isValidated);
        }

        if ($info['match_remote']) {
            $times = $info['times'] ?? ['start' => $start, 'end' => $end];
            $this->addEventToAgent($agentData, 'remote_work', $dateStr, $times['start'], $times['end'], $info['match_remote'], $status, $isValidated);
        }
    }

    private function addEventToAgent(array &$agentData, string $type, string $dateStr, string $start, string $end, array $match, string $status, bool $isValidated): void
    {
        $event = [
            'data' => [
                'date_start' => $dateStr . ' ' . $start,
                'date_end'   => $dateStr . ' ' . $end,
                'offer_id'   => $match['offer_id'],
                'comment'    => $match['offer_name'],
                'is_validated' => $isValidated,
                'demand_status' => $status
            ]
        ];
        $agentData[$type][] = $event;
    }

    private function extractCellInfo(?SimpleXMLElement $cell, array $stylesMap): array
    {
        $default = [
            'text' => '', 'color' => null, 'comment_raw' => '', 'times' => null,
            'match_absence' => null,
            'match_remote'  => null,
        ];

        if (!$cell) return $default;

        $textVal = trim((string)($cell->Data ?? ''));
        $styleID = (string)($cell['StyleID'] ?? '');
        $hexColor = $stylesMap[$styleID] ?? null;

        $rawComment = '';
        $times = null;
        if (isset($cell->Comment->Data)) {
            $rawXml = $cell->Comment->Data->asXML();
            if ($rawXml) {
                $commentText = strip_tags($rawXml);
                $commentText = html_entity_decode($commentText);
                $rawComment = str_replace(["\xC2\xA0", "&nbsp;"], ' ', trim($commentText));
                
                if (preg_match('/de\s+(\d{1,2}:\d{2})\s+à\s+(\d{1,2}:\d{2})/iu', $rawComment, $matches)) {
                    $times = ['start' => $matches[1] . ':00', 'end' => $matches[2] . ':00'];
                }
            }
        }

        $matchAbsence = $this->resolveRule($textVal, $hexColor, $rawComment, false);
        $matchRemote = $this->resolveRule($textVal, $hexColor, $rawComment, true);

        return [
            'text'        => $textVal,
            'color'       => $hexColor,
            'comment_raw' => $rawComment,
            'times'       => $times,
            'match_absence' => $matchAbsence,
            'match_remote'  => $matchRemote
        ];
    }

    private function resolveRule(string $text, ?string $color, string $comment, ?bool $expectRemote = null): ?array
    {
        $textLower = mb_strtolower($text);
        $commentLower = mb_strtolower($comment);

        foreach ($this->mappingRules as $rule) {
            if ($expectRemote !== null && $rule['is_remote'] !== $expectRemote) continue;

            if (!empty($rule['keywords'])) {
                if (($text !== '' && str_contains($textLower, $rule['keywords'])) || 
                    ($comment !== '' && str_contains($commentLower, $rule['keywords']))) {
                    $rule['matched_by_keyword'] = true;
                    return $rule;
                }
            }
        }

        if ($color !== null) {
            foreach ($this->mappingRules as $rule) {
                if ($expectRemote !== null && $rule['is_remote'] !== $expectRemote) continue;

                if (!empty($rule['color_code']) && $color === $rule['color_code']) {
                    if (empty($rule['keywords'])) {
                        $rule['matched_by_keyword'] = false;
                        return $rule;
                    }
                    if ($text === '' || $text === 'P') {
                        $rule['matched_by_keyword'] = false;
                        return $rule;
                    }
                }
            }
        }
        return null;
    }

    private function loadConfiguration(): void
    {
        $mappingsTable = $this->fetchTable('PlanningEventMappings');
        $query = $mappingsTable->find()->contain(['Offers'])
            ->orderBy(['PlanningEventMappings.priority' => 'DESC', 'PlanningEventMappings.id' => 'ASC']);

        foreach ($query as $rule) {
            if (!$rule->offer) continue;
            
            $nameLower = strtolower($rule->offer->name);
            $isRemoteStrict = str_contains($nameLower, 'télétravail') || str_contains($nameLower, 'telework');

            $this->mappingRules[] = [
                'keywords'   => !empty($rule->keywords) ? mb_strtolower(trim($rule->keywords)) : null,
                'color_code' => !empty($rule->color_code) ? strtolower(trim($rule->color_code)) : null,
                'offer_id'   => $rule->offer->id,
                'offer_name' => $rule->offer->name,
                'is_remote'  => $isRemoteStrict,
            ];
        }

        try {
            $wfmTable = $this->fetchTable('WfmSettings');
            $settings = $wfmTable->find()->first();
            if ($settings) {
                if (!empty($settings->worked_days_json)) {
                    $days = is_string($settings->worked_days_json) ? json_decode($settings->worked_days_json, true) : $settings->worked_days_json;
                    if (is_array($days)) $this->workedDays = array_map('intval', $days);
                }
                
                // Heures et Pivot
                if (!empty($settings->day_start_time)) {
                    $this->dayStartTime = ($settings->day_start_time instanceof Time) 
                        ? $settings->day_start_time->format('H:i:s') 
                        : $settings->day_start_time;
                }
                if (!empty($settings->day_end_time)) {
                    $this->dayEndTime = ($settings->day_end_time instanceof Time) 
                        ? $settings->day_end_time->format('H:i:s') 
                        : $settings->day_end_time;
                }
                // Chargement du PIVOT
                if (!empty($settings->half_day_pivot)) {
                    $this->halfDayPivot = ($settings->half_day_pivot instanceof Time) 
                        ? $settings->half_day_pivot->format('H:i:s') 
                        : $settings->half_day_pivot;
                }
            }
        } catch (\Exception $e) {
            Log::write('warning', "Parser: Erreur WfmSettings " . $e->getMessage());
        }
    }

    private function getCellValue(SimpleXMLElement $row, int $targetIndex): string
    {
        $currentIndex = 0;
        foreach ($row->Cell as $cell) {
            if (isset($cell['Index'])) $currentIndex = (int)$cell['Index'] - 1;
            if ($currentIndex === $targetIndex) return trim((string)($cell->Data ?? ''));
            if ($currentIndex > $targetIndex) return '';
            $currentIndex++;
        }
        return '';
    }
}