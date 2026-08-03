<?php
declare(strict_types=1);

namespace App\Controller;

// Importe le lecteur CSV
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * HistoricalData Controller
 *
 * @property \App\Model\Table\HistoricalDataTable $HistoricalData
 * @method \App\Model\Entity\HistoricalData[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class HistoricalDataController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // Désactive DebugKit pour ce contrôleur pour éviter les erreurs de mémoire
        try {
            $this->loadComponent('DebugKit.DebugKit');
            $this->DebugKit->disable();
        } catch (\Exception $e) {
            // Si DebugKit n'est pas chargé (ex: en prod), on ne fait rien.
        }
    }

    /**
     * Page d'importation de CSV
     */
    public function import()
    {
        // Vérification des autorisations
        $this->Authorization->authorize(new \App\Resource\HistoricalDataResource(), 'import');
        
        // Charger la liste des offres forecastables pour le dropdown
        // (le CSV peut toujours cibler n'importe quelle offre via offer_id / offer_name)
        $OffersTable = $this->fetchTable('Offers');
        $offers = $OffersTable->find('forecastable')
            ->find('list', [
                'keyField' => 'id',
                'valueField' => 'name',
            ])
            ->order(['name' => 'ASC'])
            ->toArray();
        
        // Récupérer les jours travaillés depuis WfmSettings pour pré-cocher la checkbox
        $WfmSettingsTable = $this->fetchTable('WfmSettings');
        $wfmSettings = $WfmSettingsTable->find()->first();
        $workedDays = [];
        if ($wfmSettings && !empty($wfmSettings->worked_days_json)) {
            $workedDays = is_string($wfmSettings->worked_days_json) 
                ? json_decode($wfmSettings->worked_days_json, true) 
                : $wfmSettings->worked_days_json;
            $workedDays = is_array($workedDays) ? array_map('intval', $workedDays) : [];
        }
        
        // Si worked_days_json est vide, on considère par défaut lundi-vendredi (1-5)
        if (empty($workedDays)) {
            $workedDays = [1, 2, 3, 4, 5];
        }
        
        // Pré-cocher "Exclure jours non travaillés" si samedi (6) et dimanche (7) ne sont pas travaillés
        $excludeNonWorkedDaysDefault = !in_array(6, $workedDays) && !in_array(7, $workedDays);
        
        // SOLUTION AU TIMEOUT ET MÉMOIRE pour gros fichiers
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // CORRECTION : Initialisation des variables pour la vue
        $importLog = [];
        $totalSaved = 0;
        $selectedOfferId = null;
        $totalExcluded = 0; // Compteur des lignes exclues (jours non travaillés)

        if ($this->request->is('post')) {
            // Récupérer l'offre sélectionnée (peut être vide si le CSV contient offer_id)
            $selectedOfferId = $this->request->getData('offer_id');
            
            // Récupérer l'option d'exclusion des jours non travaillés
            $excludeNonWorkedDays = (bool)$this->request->getData('exclude_non_worked_days');
            
            $file = $this->request->getData('uploaded_file');

            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                try {
                    // Récupérer le chemin du fichier AVANT d'accéder au stream
                    $tmpName = $_FILES['uploaded_file']['tmp_name'] ?? null;
                    if (!$tmpName || !file_exists($tmpName)) {
                        throw new \Exception('Impossible de lire le fichier uploadé');
                    }
                    $filePath = $tmpName;
                    
                    // Vérifier la taille du fichier (max 50 Mo)
                    $fileSize = filesize($filePath);
                    $maxSize = 50 * 1024 * 1024; // 50 Mo
                    if ($fileSize > $maxSize) {
                        throw new \Exception(sprintf(
                            'Fichier trop volumineux : %.1f Mo (maximum : %.0f Mo)',
                            $fileSize / 1024 / 1024,
                            $maxSize / 1024 / 1024
                        ));
                    }
                    
                    // Détecter et convertir l'encodage si nécessaire (UTF-16 → UTF-8)
                    $content = file_get_contents($filePath);
                    $encoding = mb_detect_encoding($content, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1'], true);
                    
                    // Vérifier si c'est UTF-16 (présence de BOM ou caractères nuls)
                    $bom16LE = chr(0xFF) . chr(0xFE);
                    $bom16BE = chr(0xFE) . chr(0xFF);
                    $isUtf16 = (substr($content, 0, 2) === $bom16LE || substr($content, 0, 2) === $bom16BE || strpos($content, "\x00") !== false);
                    
                    if ($isUtf16) {
                        // Convertir UTF-16 en UTF-8
                        if (substr($content, 0, 2) === $bom16LE) {
                            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
                        } elseif (substr($content, 0, 2) === $bom16BE) {
                            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
                        } else {
                            // Essayer de détecter automatiquement
                            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
                        }
                        
                        // Sauvegarder le fichier converti
                        file_put_contents($filePath, $content);
                    }
                    
                    // Détecter automatiquement le délimiteur
                    $handle = fopen($filePath, 'r');
                    $firstLine = fgets($handle);
                    fclose($handle);
                    
                    $delimiter = ';'; // Défaut
                    if (strpos($firstLine, "\t") !== false) {
                        $delimiter = "\t";
                    } elseif (strpos($firstLine, ',') !== false && strpos($firstLine, ';') === false) {
                        $delimiter = ',';
                    }
                    
                    // Ouvre le fichier CSV
                    $csv = Reader::createFromPath($filePath);
                    $csv->setHeaderOffset(0); // 1ère ligne = en-tête
                    $csv->setDelimiter($delimiter);

                    $stmt = (new Statement());
                    $records = $stmt->process($csv);
                    
                    // Pré-charger la correspondance offer_name → offer_id si nécessaire
                    $offerNameToId = [];

                    // Étape 1 : Parser toutes les lignes du CSV
                    $dataToImport = [];
                    $minDate = null;
                    $maxDate = null;
                    $offerIds = [];

                    foreach ($records as $record) {
                        // Nettoie les clés (en-têtes) pour enlever les espaces
                        $record = array_combine(
                            array_map('trim', array_keys($record)),
                            array_values($record)
                        );

                        try {
                            // Vérifier que la colonne datetime_interval existe et n'est pas vide
                            if (!isset($record['datetime_interval']) || $record['datetime_interval'] === null || $record['datetime_interval'] === '') {
                                throw new \Exception('Colonne datetime_interval manquante ou vide');
                            }
                            
                            // Gère la date en format FR
                            $dateString = trim($record['datetime_interval']);
                            
                            // Tolérance : ajouter :00 si les secondes manquent
                            if (preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $dateString)) {
                                $dateString .= ':00'; // Ajouter les secondes
                            }
                            
                            $dt = \DateTime::createFromFormat('d/m/Y H:i:s', $dateString);

                            if ($dt === false) {
                                throw new \Exception('Format de date invalide (attendu: DD/MM/YYYY HH:MM:SS ou DD/MM/YYYY HH:MM)');
                            }

                            // Exclure les jours non travaillés si l'option est activée
                            if ($excludeNonWorkedDays) {
                                // DateTime::format('N') retourne 1 (lundi) à 7 (dimanche)
                                $dayOfWeek = (int)$dt->format('N');
                                if (!in_array($dayOfWeek, $workedDays)) {
                                    $totalExcluded++;
                                    continue; // Passer à la ligne suivante
                                }
                            }

                            // Utiliser l'offer_id ou offer_name du CSV, sinon utiliser celui sélectionné
                            if (!empty($record['offer_id'])) {
                                $offerId = (int)$record['offer_id'];
                            } elseif (!empty($record['offer_name'])) {
                                // Résoudre offer_name → offer_id
                                $offerName = trim($record['offer_name']);
                                if (!isset($offerNameToId[$offerName])) {
                                    // Charger la correspondance depuis la BDD
                                    $offer = $OffersTable->find()
                                        ->where(['name' => $offerName])
                                        ->first();
                                    if ($offer) {
                                        $offerNameToId[$offerName] = $offer->id;
                                    } else {
                                        throw new \Exception("Offre non trouvée : '{$offerName}'");
                                    }
                                }
                                $offerId = $offerNameToId[$offerName];
                            } elseif (!empty($selectedOfferId)) {
                                $offerId = (int)$selectedOfferId;
                            } else {
                                throw new \Exception('Aucune offre définie : sélectionnez une offre ou ajoutez la colonne offer_id/offer_name dans votre CSV');
                            }
                            
                            $datetime = $dt->format('Y-m-d H:i:s');
                            
                            // Clé unique : offer_id + datetime_interval
                            $key = $offerId . '|' . $datetime;
                            
                            // Vérifier les colonnes obligatoires
                            if (!isset($record['call_volume']) || $record['call_volume'] === '') {
                                throw new \Exception('Colonne call_volume manquante ou vide');
                            }
                            if (!isset($record['avg_handle_time_seconds']) || $record['avg_handle_time_seconds'] === '') {
                                throw new \Exception('Colonne avg_handle_time_seconds manquante ou vide');
                            }
                            
                            $dataToImport[$key] = [
                                'offer_id' => $offerId,
                                'call_volume' => (int)$record['call_volume'],
                                'avg_handle_time_seconds' => (int)$record['avg_handle_time_seconds'],
                                'datetime_interval' => $datetime
                            ];
                            
                            // Suivre les plages de dates et offres
                            if ($minDate === null || $dt < $minDate) {
                                $minDate = $dt;
                            }
                            if ($maxDate === null || $dt > $maxDate) {
                                $maxDate = $dt;
                            }
                            $offerIds[$offerId] = true;

                        } catch (\Exception $e) {
                            $importLog[] = "Erreur Conversion Ligne: " . json_encode($record) . " - Erreur: " . $e->getMessage();
                            continue;
                        }
                    }
                    
                    if (empty($dataToImport)) {
                        $message = 'Aucune ligne valide trouvée dans le fichier.';
                        if ($totalExcluded > 0) {
                            $message .= sprintf(' (%d ligne(s) exclue(s) car jours non travaillés — décochez l\'option si nécessaire)', $totalExcluded);
                        }
                        if (!empty($importLog)) {
                            $message .= sprintf(' + %d erreur(s) de parsing.', count($importLog));
                        }
                        $this->Flash->warning($message);
                        $this->set(compact('importLog', 'offers', 'selectedOfferId', 'excludeNonWorkedDaysDefault', 'workedDays'));
                        return;
                    }

                    // Étape 2 : Récupérer les données existantes dans la plage
                    $existingData = $this->HistoricalData->find()
                        ->where([
                            'offer_id IN' => array_keys($offerIds),
                            'datetime_interval >=' => $minDate->format('Y-m-d H:i:s'),
                            'datetime_interval <=' => $maxDate->format('Y-m-d H:i:s')
                        ])
                        ->all();

                    // Mapper les données existantes par clé
                    $existingMap = [];
                    foreach ($existingData as $existing) {
                        $key = $existing->offer_id . '|' . $existing->datetime_interval->format('Y-m-d H:i:s');
                        $existingMap[$key] = $existing;
                    }

                    // Étape 3 : Séparer en UPDATE et INSERT
                    $toUpdate = [];
                    $toInsert = [];
                    
                    foreach ($dataToImport as $key => $data) {
                        if (isset($existingMap[$key])) {
                            // Existe déjà → UPDATE
                            $entity = $existingMap[$key];
                            $entity = $this->HistoricalData->patchEntity($entity, $data);
                            $toUpdate[] = $entity;
                        } else {
                            // N'existe pas → INSERT
                            $entity = $this->HistoricalData->newEntity($data);
                            if (!$entity->hasErrors()) {
                                $toInsert[] = $entity;
                            } else {
                                $importLog[] = "Erreur Validation: " . json_encode($data) . " - " . json_encode($entity->getErrors());
                            }
                        }
                        }

                    // Étape 4 : Sauvegarder par paquets
                    $chunkSize = 1000;
                    $totalUpdated = 0;
                    $totalInserted = 0;

                    // Sauvegarder les mises à jour
                    if (!empty($toUpdate)) {
                        $chunks = array_chunk($toUpdate, $chunkSize);
                        foreach ($chunks as $chunk) {
                            $saved = $this->HistoricalData->saveMany($chunk);
                            if ($saved) {
                                $totalUpdated += count($chunk);
                            } else {
                                $importLog[] = "Erreur lors de la mise à jour d'un paquet.";
                            }
                        }
                    }

                    // Sauvegarder les insertions
                    if (!empty($toInsert)) {
                        $chunks = array_chunk($toInsert, $chunkSize);
                        foreach ($chunks as $chunk) {
                            $saved = $this->HistoricalData->saveMany($chunk);
                            if ($saved) {
                                $totalInserted += count($chunk);
                        } else {
                                $importLog[] = "Erreur lors de l'insertion d'un paquet.";
                            }
                        }
                    }
                    
                    $totalSaved = $totalUpdated + $totalInserted;

                    // --- Flash message final ---
                    if ($totalSaved > 0 || $totalExcluded > 0) {
                        $message = sprintf(
                            '%d ligne(s) traitée(s) : %d mise(s) à jour, %d insertion(s)',
                            $totalSaved,
                            $totalUpdated,
                            $totalInserted
                        );
                        if ($totalExcluded > 0) {
                            $message .= sprintf(' — %d ligne(s) exclue(s) (jours non travaillés)', $totalExcluded);
                        }
                        $this->Flash->success($message);
                    }
                    if (!empty($importLog)) {
                        $this->Flash->warning('Certaines lignes n\'ont pas pu être importées. Voir le log ci-dessous.');
                    }
                    if ($totalSaved == 0 && empty($importLog)) {
                        $this->Flash->warning('Aucune ligne valide à importer n\'a été trouvée dans le fichier.');
                    }

                } catch (\Exception $e) {
                    // CORRECTION : Gérer l'erreur principale (lecture CSV, etc.)
                    $this->Flash->error('Erreur critique lors de la lecture du fichier : ' . $e->getMessage());
                    // S'assurer que les variables sont définies même en cas d'erreur
                    $this->set(compact('importLog', 'offers', 'selectedOfferId', 'excludeNonWorkedDaysDefault', 'workedDays'));
                    return;
                }
            } else {
                $this->Flash->error('Erreur lors de l\'upload du fichier. Code: ' . ($file ? $file->getError() : 'Inconnu'));
            }
        } // Fin if ('post')

        // CORRECTION : Toujours envoyer $importLog à la vue
        // (pour que la variable existe aussi en GET)
        $this->set(compact('importLog', 'offers', 'selectedOfferId', 'excludeNonWorkedDaysDefault', 'workedDays'));
    }

    /**
     * Page de visualisation graphique des données historiques
     */
    public function visualize()
    {
        // Vérification des autorisations (Admin + Manager)
        $this->Authorization->authorize(new \App\Resource\HistoricalDataResource(), 'visualize');
        
        // Récupérer la liste des offres pour le filtre
        $OffersTable = $this->fetchTable('Offers');
        $offers = $OffersTable->find('all')
            ->select(['id', 'name'])
            ->order(['name' => 'ASC'])
            ->all();
        
        // Récupérer les paramètres WFM pour les horaires de journée
        $WfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $dayStartTime = (string)$WfmSettings->day_start_time;
        $dayEndTime = (string)$WfmSettings->day_end_time;
        
        // Déterminer la plage de dates par défaut (30 derniers jours)
        $defaultEndDate = new \DateTime('now');
        $defaultStartDate = (clone $defaultEndDate)->modify('-30 days');
        
        // Récupérer les filtres si présents
        $selectedOffers = $this->request->getQuery('offers', []);
        $startDate = $this->request->getQuery('start_date', $defaultStartDate->format('Y-m-d'));
        $endDate = $this->request->getQuery('end_date', $defaultEndDate->format('Y-m-d'));
        $granularity = $this->request->getQuery('granularity', '15min');
        
        // Valider la granularité
        if (!in_array($granularity, ['15min', 'hour', 'day'])) {
            $granularity = '15min';
        }
        
        // Initialiser les données
        $chartData = null;
        $statistics = null;
        $hasData = false;
        
        // Si des offres sont sélectionnées, charger les données
        if (!empty($selectedOffers) && is_array($selectedOffers)) {
            try {
                $result = $this->loadHistoricalDataForChart($selectedOffers, $startDate, $endDate, $dayStartTime, $dayEndTime, $granularity);
                $chartData = $result['chartData'];
                $statistics = $result['statistics'];
                $hasData = !empty($chartData);
            } catch (\Exception $e) {
                $this->Flash->error('Erreur lors du chargement des données : ' . $e->getMessage());
            }
        }
        
        $this->set(compact('offers', 'selectedOffers', 'startDate', 'endDate', 'granularity', 'chartData', 'statistics', 'hasData'));
    }

    /**
     * Endpoint AJAX pour récupérer les données graphiques
     */
    public function getData()
    {
        // Vérification des autorisations
        $this->Authorization->authorize(new \App\Resource\HistoricalDataResource(), 'getData');
        
        $this->request->allowMethod(['get', 'post']);
        
        $offerIds = $this->request->getQuery('offers', []);
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');
        $granularity = $this->request->getQuery('granularity', '15min');
        
        // Valider la granularité
        if (!in_array($granularity, ['15min', 'hour', 'day'])) {
            $granularity = '15min';
        }
        
        // Validation
        if (empty($offerIds) || !is_array($offerIds)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'Au moins une offre doit être sélectionnée'
                ]));
        }
        
        if (empty($startDate) || empty($endDate)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'Les dates de début et fin sont requises'
                ]));
        }
        
        // Limiter à 3 offres maximum
        if (count($offerIds) > 3) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'Maximum 3 offres sélectionnables'
                ]));
        }
        
        // Limiter la plage à 90 jours maximum
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $diff = $start->diff($end);
        if ($diff->days > 90) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'La plage ne peut pas dépasser 90 jours'
                ]));
        }
        
        try {
            // Récupérer les paramètres WFM
            $WfmSettings = $this->fetchTable('WfmSettings')->find()->first();
            $dayStartTime = (string)$WfmSettings->day_start_time;
            $dayEndTime = (string)$WfmSettings->day_end_time;
            
            // Charger les données
            $result = $this->loadHistoricalDataForChart($offerIds, $startDate, $endDate, $dayStartTime, $dayEndTime, $granularity);
            
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'chartData' => $result['chartData'],
                    'statistics' => $result['statistics']
                ]));
            
        } catch (\Exception $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Charge les données historiques et les formate pour les graphiques
     *
     * @param array $offerIds IDs des offres
     * @param string $startDate Date de début (Y-m-d)
     * @param string $endDate Date de fin (Y-m-d)
     * @param string $dayStartTime Heure de début de journée
     * @param string $dayEndTime Heure de fin de journée
     * @param string $granularity Granularité ('15min', 'hour', 'day')
     * @return array
     */
    private function loadHistoricalDataForChart(array $offerIds, string $startDate, string $endDate, string $dayStartTime, string $dayEndTime, string $granularity = '15min'): array
    {
        $start = new \DateTime($startDate . ' ' . $dayStartTime);
        $end = new \DateTime($endDate . ' ' . $dayEndTime);
        
        // Récupérer les noms des offres
        $OffersTable = $this->fetchTable('Offers');
        $offerNames = $OffersTable->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])->where(['id IN' => $offerIds])->toArray();
        
        // Structure pour stocker les séries
        $volumeSeries = [];
        $dmtSeries = [];
        $allCategories = [];
        $allStatistics = [];
        
        foreach ($offerIds as $offerId) {
            $offerName = $offerNames[$offerId] ?? "Offre #$offerId";
            
            // Récupérer les données
            $data = $this->HistoricalData->find()
                ->where([
                    'offer_id' => $offerId,
                    'datetime_interval >=' => $start,
                    'datetime_interval <=' => $end
                ])
                ->order(['datetime_interval' => 'ASC'])
                ->all();
            
            if ($data->count() === 0) {
                continue;
            }
            
            $volumeData = [];
            $dmtData = [];
            $categories = [];
            
            $totalVolume = 0;
            $totalDmt = 0;
            $maxVolume = 0;
            $minDmt = PHP_INT_MAX;
            $maxDmt = 0;
            $count = 0;
            
            // Agréger les données selon la granularité
            $aggregated = $this->aggregateData($data, $granularity);
            
            foreach ($aggregated as $point) {
                $categories[] = $point['category'];
                $volumeData[] = $point['volume'];
                $dmtData[] = $point['dmt'];
                
                // Calcul des statistiques
                $totalVolume += $point['volume'];
                $totalDmt += $point['dmt'];
                $maxVolume = max($maxVolume, $point['volume']);
                $minDmt = min($minDmt, $point['dmt']);
                $maxDmt = max($maxDmt, $point['dmt']);
                $count++;
            }
            
            $volumeSeries[] = [
                'name' => $offerName . ' (Volume)',
                'data' => $volumeData
            ];
            
            $dmtSeries[] = [
                'name' => $offerName . ' (DMT)',
                'data' => $dmtData
            ];
            
            if (empty($allCategories)) {
                $allCategories = $categories;
            }
            
            $allStatistics[$offerName] = [
                'volume_total' => $totalVolume,
                'volume_avg' => $count > 0 ? round($totalVolume / $count, 2) : 0,
                'volume_max' => $maxVolume,
                'dmt_avg' => $count > 0 ? round($totalDmt / $count, 0) : 0,
                'dmt_min' => $minDmt === PHP_INT_MAX ? 0 : $minDmt,
                'dmt_max' => $maxDmt,
                'data_points' => $count
            ];
        }
        
        return [
            'chartData' => [
                'categories' => $allCategories,
                'volumeSeries' => $volumeSeries,
                'dmtSeries' => $dmtSeries
            ],
            'statistics' => $allStatistics
        ];
    }

    /**
     * Agrège les données selon la granularité demandée
     *
     * @param \Cake\Datasource\ResultSetInterface $data Données brutes
     * @param string $granularity Granularité ('15min', 'hour', 'day')
     * @return array Données agrégées
     */
    private function aggregateData($data, string $granularity): array
    {
        $aggregated = [];
        $tempBuckets = [];
        
        foreach ($data as $row) {
            $dt = $row->datetime_interval;
            
            // Déterminer la clé d'agrégation selon la granularité
            switch ($granularity) {
                case 'hour':
                    // Regrouper par heure
                    $key = $dt->format('Y-m-d H:00:00');
                    $category = $dt->format('d/m/Y H:00');
                    break;
                    
                case 'day':
                    // Regrouper par jour
                    $key = $dt->format('Y-m-d');
                    $category = $dt->format('d/m/Y');
                    break;
                    
                case '15min':
                default:
                    // Pas d'agrégation, garder les tranches de 15 minutes
                    $key = $dt->format('Y-m-d H:i:00');
                    $category = $dt->format('d/m/Y H:i');
                    break;
            }
            
            // Agréger les données
            if (!isset($tempBuckets[$key])) {
                $tempBuckets[$key] = [
                    'category' => $category,
                    'volume_sum' => 0,
                    'dmt_sum' => 0,
                    'count' => 0
                ];
            }
            
            $tempBuckets[$key]['volume_sum'] += (int)$row->call_volume;
            $tempBuckets[$key]['dmt_sum'] += (int)$row->avg_handle_time_seconds;
            $tempBuckets[$key]['count']++;
        }
        
        // Formater le résultat
        // Volume = SOMME (total des appels sur la période)
        // DMT = MOYENNE (durée moyenne de traitement)
        foreach ($tempBuckets as $bucket) {
            $aggregated[] = [
                'category' => $bucket['category'],
                'volume' => $bucket['volume_sum'], // Toujours la somme (total)
                'dmt' => round($bucket['dmt_sum'] / $bucket['count'], 0) // Moyenne
            ];
        }
        
        return $aggregated;
    }
}
