<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ExcelPlanningParserService;
use App\Resource\ExcelUploadsResource;
use Cake\Http\Exception\BadRequestException;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;

/**
 * ExcelUploads Controller
 */
class ExcelUploadsController extends AppController
{
    /**
     * Initialisation
     */
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Méthode pour afficher le formulaire d'upload et traiter l'upload
     */
    public function upload()
    {
        $this->Authorization->authorize(new ExcelUploadsResource(), 'upload');
        
        // Récupérer les agents non reconnus de la session (si redirection depuis preview)
        $unrecognizedAgents = $this->request->getSession()->read('excel_unrecognized_agents') ?? [];
        $this->request->getSession()->delete('excel_unrecognized_agents'); // Nettoyer après lecture
        $this->set(compact('unrecognizedAgents'));
        
        // Si c'est une requête POST (envoi du formulaire)
        if ($this->request->is('post')) {
            try {
                $file = $this->request->getData('file'); 

                // Vérification basique du fichier
                if (!$file) {
                    $this->Flash->error('Aucun fichier n\'a été sélectionné.');
                    return;
                }

                if ($file->getError() !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur (upload_max_filesize).',
                        UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire.',
                        UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé.',
                        UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
                        UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
                        UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier.',
                    ];
                    $errorMsg = $errorMessages[$file->getError()] ?? 'Erreur d\'upload inconnue (Code: ' . $file->getError() . ')';
                    $this->Flash->error($errorMsg);
                    return;
                }

                // Vérification extension (assouplie pour le debugging)
                $filename = $file->getClientFilename();
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $extLower = strtolower($ext);
                
                // Autoriser XML et XLS (XML Excel 2003 peut avoir une extension .xls)
                $allowedExtensions = ['xml', 'xls'];
                if (!in_array($extLower, $allowedExtensions)) {
                    $this->Flash->error('Extension de fichier non autorisée. Extensions acceptées : ' . implode(', ', $allowedExtensions) . '. Fichier reçu : ' . $ext);
                    return;
                }

                // Vérification MimeType (assouplie - plusieurs types possibles pour XML Excel 2003)
                $mimeType = $file->getClientMediaType();
                $allowedMimeTypes = [
                    'text/xml',
                    'application/xml',
                    'text/plain',
                    'application/octet-stream', // Certains serveurs envoient ce type pour XML
                    'application/vnd.ms-excel', // Type MIME standard pour .xls
                    'application/x-msexcel',    // Variante pour .xls
                ];
                
                // Log pour debugging (ne bloque pas l'upload)
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::write('debug', "ExcelUploads: MimeType non standard détecté : $mimeType pour le fichier $filename (autorisé pour debugging)");
                }

                // Récupération du contexte mois/année AVANT le déplacement du fichier
                $contextMonth = (int)$this->request->getData('context_month', (int)date('n'));
                $contextYear = (int)$this->request->getData('context_year', (int)date('Y'));
                
                // Validation des valeurs
                if ($contextMonth < 1 || $contextMonth > 12) {
                    $this->Flash->error('Le mois doit être entre 1 et 12.');
                    return;
                }
                
                if ($contextYear < 2000 || $contextYear > 2100) {
                    $this->Flash->error('L\'année doit être entre 2000 et 2100.');
                    return;
                }

                // Déplacement temporaire
                $targetPath = TMP . 'excel_' . time() . '_' . $filename;
                try {
                    $file->moveTo($targetPath);
                } catch (\Exception $e) {
                    $this->Flash->error('Erreur lors du déplacement du fichier : ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
                    return;
                }
                
                // Stocker le chemin et le contexte en session pour preview/process
                $this->request->getSession()->write('excel_uploaded_file', $targetPath);
                $this->request->getSession()->write('excel_context_month', $contextMonth);
                $this->request->getSession()->write('excel_context_year', $contextYear);

                // Vérifier que le contenu est du XML valide (même si l'extension est .xls)
                $fileContent = file_get_contents($targetPath, false, null, 0, 1024); // Lire les premiers 1024 octets
                $isXmlContent = (
                    strpos($fileContent, '<?xml') !== false ||
                    strpos($fileContent, '<Workbook') !== false ||
                    strpos($fileContent, '<ss:Workbook') !== false
                );
                
                if (!$isXmlContent) {
                    // Vérifier si c'est un fichier binaire Excel (BIFF)
                    $isBinaryExcel = (substr($fileContent, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
                    
                    if ($isBinaryExcel) {
                        @unlink($targetPath);
                        $this->Flash->error(
                            '<strong>Format non supporté :</strong> Ce fichier est un Excel binaire (.xls BIFF).<br>' .
                            'Seuls les fichiers <strong>XML Excel 2003</strong> sont acceptés.<br><br>' .
                            '<strong>Solution :</strong> Dans Excel, utilisez "Enregistrer sous" → "Feuille de calcul XML 2003 (*.xml)"',
                            ['escape' => false]
                        );
                        return;
                    } else {
                        @unlink($targetPath);
                        $this->Flash->error(
                            '<strong>Format non reconnu :</strong> Le fichier ne semble pas être un XML Excel 2003 valide.<br>' .
                            'Vérifiez que le fichier source est bien au format XML.',
                            ['escape' => false]
                        );
                        return;
                    }
                }

                try {
                    
                    // Appel du Service de Parsing pour validation avec contexte
                    $parserService = new ExcelPlanningParserService();
                    $parsedData = $parserService->parseFile($targetPath, [
                        'context_month' => $contextMonth,
                        'context_year' => $contextYear
                    ]);

                    // Log des données brutes reçues du parser
                    Log::write('debug', 'ExcelUploadsController: Données brutes reçues du parser : ' . count($parsedData));
                    if (!empty($parsedData)) {
                        Log::write('debug', 'ExcelUploadsController: Exemple premier agent : ' . print_r($parsedData[0], true));
                    }

                    $this->Flash->success('Fichier analysé avec succès ! ' . count($parsedData) . ' agent(s) détecté(s) dans le fichier.');
                    
                    // Rediriger vers preview
                    return $this->redirect(['action' => 'preview']);

                } catch (\Exception $e) {
                    $errorMsg = 'Erreur lors de l\'analyse du fichier : ' . $e->getMessage();
                    if ($e->getCode()) {
                        $errorMsg .= ' (Code: ' . $e->getCode() . ')';
                    }
                    if ($e->getFile()) {
                        $errorMsg .= ' [Fichier: ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
                    }
                    $this->Flash->error($errorMsg);
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                    $this->request->getSession()->delete('excel_uploaded_file');
                }
            } catch (\Exception $e) {
                $errorMsg = 'Erreur lors de l\'upload : ' . $e->getMessage();
                if ($e->getCode()) {
                    $errorMsg .= ' (Code: ' . $e->getCode() . ')';
                }
                if ($e->getFile()) {
                    $errorMsg .= ' [Fichier: ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
                }
                $this->Flash->error($errorMsg);
            }
        }
    }

    /**
     * Méthode pour prévisualiser les données parsées
     */
    public function preview()
    {
        $this->Authorization->authorize(new ExcelUploadsResource(), 'preview');
        
        $uploadedFile = $this->request->getSession()->read('excel_uploaded_file');
        if (!$uploadedFile || !file_exists($uploadedFile)) {
            $this->Flash->error('Aucun fichier trouvé. Veuillez d\'abord uploader un fichier.');
            return $this->redirect(['action' => 'upload']);
        }

        try {
            // Récupération du contexte mois/année depuis la session ou valeurs par défaut
            $contextMonth = $this->request->getSession()->read('excel_context_month') ?? (int)date('n');
            $contextYear = $this->request->getSession()->read('excel_context_year') ?? (int)date('Y');
            
            $parserService = new ExcelPlanningParserService();
            $parsedData = $parserService->parseFile($uploadedFile, [
                'context_month' => $contextMonth,
                'context_year' => $contextYear
            ]);
            
            // Log des données brutes reçues du parser
            Log::write('debug', 'ExcelUploadsController: Données brutes reçues du parser : ' . count($parsedData));
            if (!empty($parsedData)) {
                Log::write('debug', 'ExcelUploadsController: Exemple premier agent : ' . print_r($parsedData[0], true));
            }
            
            if (empty($parsedData)) {
                $this->Flash->error('Aucune donnée trouvée dans le fichier. Vérifiez que :<br>' .
                    '- Les matricules du fichier correspondent à des utilisateurs en base de données<br>' .
                    '- Les utilisateurs ont des absences ou du télétravail dans le fichier<br>' .
                    '- Le format du fichier est correct (XML Excel 2003)',
                    ['escape' => false]);
                return $this->redirect(['action' => 'upload']);
            }
            
            // Préparer les données pour l'affichage
            $ranges = [];
            $unrecognizedAgents = []; // Liste des agents non reconnus en BDD
            $recognizedAgentsCount = 0; // Compteur des agents reconnus
            
            $Offers = $this->fetchTable('Offers');
            $offers = $Offers->find('list', [
                'keyField' => 'id',
                'valueField' => 'name'
            ])->toArray();
            
            // Charger les offres complètes pour avoir accès à la couleur
            $offersById = [];
            $allOffers = $Offers->find()->toArray();
            foreach ($allOffers as $offer) {
                $offersById[$offer->id] = $offer;
            }
            
            // Recherche des utilisateurs pour chaque agent trouvé dans le fichier
            $Users = $this->fetchTable('Users');
            foreach ($parsedData as $agentData) {
                // Extraire les informations de l'agent depuis les données parsées
                $agentCode = $agentData['code'] ?? $agentData['agent'] ?? '';
                $lastName = $agentData['last_name'] ?? '';
                $firstName = $agentData['first_name'] ?? '';
                $fullName = $agentData['name'] ?? trim($lastName . ' ' . $firstName);
                
                // Si pas de matricule, passer
                if (empty($agentCode)) {
                    continue;
                }
                
                // Recherche de l'utilisateur en base de données par matricule uniquement
                $user = $Users->find()
                    ->where(['user_code LIKE' => '%' . $agentCode])
                    ->first();
                
                if ($user) {
                    // Utilisateur trouvé - continuer le traitement
                    $agent = $agentData;
                    $agent['user_id'] = $user->id;
                    $recognizedAgentsCount++;
                } else {
                    // Utilisateur non trouvé - stocker pour affichage
                    Log::write('debug', 'ExcelUploadsController: Utilisateur non trouvé en BDD : [' . $fullName . '] (code: ' . $agentCode . ')');
                    $unrecognizedAgents[] = [
                        'name' => $fullName ?: $agentCode,
                        'code' => $agentCode,
                        'absences_count' => count($agentData['absences'] ?? []),
                        'remote_work_count' => count($agentData['remote_work'] ?? []),
                    ];
                    continue; // Passer à l'agent suivant
                }
                
                // Traiter les absences
                if (!empty($agent['absences'])) {
                    foreach ($agent['absences'] as $absence) {
                        // Le service retourne ['type' => '...', 'data' => [...]]
                        // On extrait 'data' et on ajoute user_id
                        if (isset($absence['data'])) {
                            $range = $absence['data'];
                            $range['user_id'] = $agent['user_id'];
                            $ranges[] = $range;
                        }
                    }
                }
                // Traiter le télétravail
                if (!empty($agent['remote_work'])) {
                    foreach ($agent['remote_work'] as $rw) {
                        // Le service retourne ['type' => '...', 'data' => [...]]
                        // On extrait 'data' et on ajoute user_id
                        if (isset($rw['data'])) {
                            $range = $rw['data'];
                            $range['user_id'] = $agent['user_id'];
                            $ranges[] = $range;
                        }
                    }
                }
            }
            
            // Construire le message de résumé
            $totalAgentsInFile = count($parsedData);
            $unrecognizedCount = count($unrecognizedAgents);
            
            if (empty($ranges)) {
                // Aucune donnée exploitable
                if ($unrecognizedCount === $totalAgentsInFile) {
                    // Tous les agents sont non reconnus
                    $this->Flash->error(
                        '<strong>Aucun agent reconnu !</strong><br>' .
                        $totalAgentsInFile . ' agent(s) détecté(s) dans le fichier, mais aucun matricule ne correspond en base de données.<br>' .
                        'Veuillez vérifier que les matricules existent dans l\'application ou créer les utilisateurs manquants.',
                        ['escape' => false]
                    );
                    // Stocker les agents non reconnus en session pour affichage sur la page upload
                    $this->request->getSession()->write('excel_unrecognized_agents', $unrecognizedAgents);
                } else {
                    $this->Flash->warning(
                        $recognizedAgentsCount . ' agent(s) reconnu(s) sur ' . $totalAgentsInFile . ' détecté(s), ' .
                        'mais aucune absence ou télétravail n\'a été détecté dans le fichier.'
                    );
                }
                return $this->redirect(['action' => 'upload']);
            }
            
            // Charger les utilisateurs AVANT le groupement (pour pouvoir trier par nom)
            $usersById = [];
            $userIds = array_unique(array_filter(array_column($ranges, 'user_id')));
            if (!empty($userIds)) {
                $Users = $this->fetchTable('Users');
                $users = $Users->find()
                    ->where(['id IN' => $userIds])
                    ->toArray();
                foreach ($users as $user) {
                    $usersById[$user->id] = $user;
                }
            }
            
            // Grouper les ranges (avec tri par user_name, offer_id, date_start)
            $groupedRanges = $this->groupRanges($ranges, $offersById, $usersById);
            
            // Charger les disponibilités des utilisateurs pour la vue grille
            $availabilitiesByUser = [];
            if (!empty($userIds)) {
                $UserAvailabilities = $this->fetchTable('UserAvailabilities');
                $availabilities = $UserAvailabilities->find()
                    ->where(['user_id IN' => $userIds])
                    ->toArray();
                foreach ($availabilities as $avail) {
                    $availabilitiesByUser[$avail->user_id][$avail->day_of_week] = [
                        'start' => $avail->availability_start_time,
                        'end' => $avail->availability_end_time,
                    ];
                }
            }
            
            $this->set(compact('groupedRanges', 'offers', 'usersById', 'offersById', 'contextMonth', 'contextYear', 'availabilitiesByUser', 'unrecognizedAgents', 'recognizedAgentsCount'));
            
        } catch (\Exception $e) {
            $errorMsg = 'Erreur lors de l\'analyse : ' . $e->getMessage();
            if ($e->getCode()) {
                $errorMsg .= ' (Code: ' . $e->getCode() . ')';
            }
            if ($e->getFile()) {
                $errorMsg .= ' [Fichier: ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            }
            $this->Flash->error($errorMsg);
            return $this->redirect(['action' => 'upload']);
        }
    }

    /**
     * Méthode pour traiter et enregistrer les données
     */
    public function process()
    {
        $this->Authorization->authorize(new ExcelUploadsResource(), 'process');
        
        if (!$this->request->is('post')) {
            throw new BadRequestException('Méthode POST requise');
        }
        
        $uploadedFile = $this->request->getSession()->read('excel_uploaded_file');
        if (!$uploadedFile || !file_exists($uploadedFile)) {
            $this->Flash->error('Aucun fichier trouvé.');
            return $this->redirect(['action' => 'upload']);
        }

        try {
            // Récupération du contexte mois/année depuis la session ou valeurs par défaut
            $contextMonth = $this->request->getSession()->read('excel_context_month') ?? (int)date('n');
            $contextYear = $this->request->getSession()->read('excel_context_year') ?? (int)date('Y');
            
            $parserService = new ExcelPlanningParserService();
            $parsedData = $parserService->parseFile($uploadedFile, [
                'context_month' => $contextMonth,
                'context_year' => $contextYear
            ]);
            
            if (empty($parsedData)) {
                $this->Flash->warning('Aucune donnée trouvée dans le fichier.');
                return $this->redirect(['action' => 'preview']);
            }
            
            // Charger les offres avant le regroupement
            $Offers = $this->fetchTable('Offers');
            $offers = $Offers->find('list', [
                'keyField' => 'id',
                'valueField' => 'name'
            ])->toArray();
            
            // Charger la table Users pour résoudre les matricules
            $Users = $this->fetchTable('Users');
            
            // Préparer les données
            $ranges = [];
            foreach ($parsedData as $agentData) {
                // Extraire le matricule de l'agent
                $agentCode = $agentData['code'] ?? $agentData['agent'] ?? '';
                if (empty($agentCode)) {
                    continue;
                }
                
                // Rechercher l'utilisateur par matricule
                $user = $Users->find()
                    ->where(['user_code LIKE' => '%' . $agentCode])
                    ->first();
                
                if (!$user) {
                    // Utilisateur non trouvé - ignorer
                    continue;
                }
                
                $userId = $user->id;
                
                // Traiter les absences
                if (!empty($agentData['absences'])) {
                    foreach ($agentData['absences'] as $absence) {
                        // Le service retourne ['type' => '...', 'data' => [...]]
                        // On extrait 'data' et on ajoute user_id
                        if (isset($absence['data'])) {
                            $range = $absence['data'];
                            $range['user_id'] = $userId;
                            $ranges[] = $range;
                        }
                    }
                }
                // Traiter le télétravail
                if (!empty($agentData['remote_work'])) {
                    foreach ($agentData['remote_work'] as $rw) {
                        // Le service retourne ['type' => '...', 'data' => [...]]
                        // On extrait 'data' et on ajoute user_id
                        if (isset($rw['data'])) {
                            $range = $rw['data'];
                            $range['user_id'] = $userId;
                            $ranges[] = $range;
                        }
                    }
                }
            }
            
            if (empty($ranges)) {
                $this->Flash->warning('Aucune plage (absence ou télétravail) trouvée dans le fichier.');
                return $this->redirect(['action' => 'preview']);
            }
            
            // Charger les offres complètes pour groupRanges
            $offersById = [];
            $allOffers = $Offers->find()->toArray();
            foreach ($allOffers as $offer) {
                $offersById[$offer->id] = $offer;
            }
            
            // Grouper les ranges avant de sauvegarder
            $groupedRanges = $this->groupRanges($ranges, $offersById);
            
            // Filtrer les lignes supprimées si présentes
            $excludedIndices = $this->request->getData('excluded_indices', []);
            if (!empty($excludedIndices)) {
                $excludedIndices = is_array($excludedIndices) ? $excludedIndices : explode(',', $excludedIndices);
                $excludedIndices = array_map('intval', $excludedIndices);
                $groupedRanges = array_filter($groupedRanges, function($index) use ($excludedIndices) {
                    return !in_array($index, $excludedIndices);
                }, ARRAY_FILTER_USE_KEY);
                // Réindexer le tableau
                $groupedRanges = array_values($groupedRanges);
            }
            
            if (empty($groupedRanges)) {
                $this->Flash->warning('Aucune plage valide après regroupement.');
                return $this->redirect(['action' => 'preview']);
            }
            
            $Ranges = $this->fetchTable('Ranges');
            $saved = 0;
            $skipped = 0;
            $errors = [];
            
            foreach ($groupedRanges as $rangeData) {
                // Vérifier que les données essentielles sont présentes
                if (empty($rangeData['user_id']) || empty($rangeData['offer_id'])) {
                    $errors[] = 'Plage invalide : user_id ou offer_id manquant';
                    continue;
                }
                
                // Vérifier que les dates sont valides
                if (empty($rangeData['date_start']) || empty($rangeData['date_end'])) {
                    $errors[] = 'Plage invalide : dates manquantes';
                    continue;
                }
                
                // Préparer les données pour l'entité
                $entityData = [
                    'user_id' => (int)$rangeData['user_id'],
                    'offer_id' => (int)$rangeData['offer_id'],
                    'comment' => $rangeData['comment'] ?? '',
                ];
                
                // Convertir les dates en format datetime si nécessaire
                $dateStart = $rangeData['date_start'];
                if ($dateStart instanceof FrozenTime) {
                    $entityData['date_start'] = $dateStart;
                } else {
                    $entityData['date_start'] = FrozenTime::parse($dateStart);
                }
                
                $dateEnd = $rangeData['date_end'];
                if ($dateEnd instanceof FrozenTime) {
                    $entityData['date_end'] = $dateEnd;
                } else {
                    $entityData['date_end'] = FrozenTime::parse($dateEnd);
                }
                
                // Vérifier si une plage identique existe déjà (doublon)
                $existingRange = $Ranges->find()
                    ->where([
                        'user_id' => $entityData['user_id'],
                        'offer_id' => $entityData['offer_id'],
                        'date_start' => $entityData['date_start']->format('Y-m-d H:i:s'),
                        'date_end' => $entityData['date_end']->format('Y-m-d H:i:s'),
                    ])
                    ->first();
                
                if ($existingRange) {
                    $skipped++;
                    continue; // Ignorer les doublons
                }
                
                $range = $Ranges->newEntity($entityData);
                if ($Ranges->save($range)) {
                    $saved++;
                } else {
                    $validationErrors = $range->getErrors();
                    $errorMsg = 'Erreur pour la plage du ' . 
                        ($entityData['date_start'] instanceof FrozenTime ? $entityData['date_start']->i18nFormat('dd/MM/yyyy') : 'date inconnue');
                    if (!empty($validationErrors)) {
                        $errorMsg .= ' : ' . json_encode($validationErrors);
                    }
                    $errors[] = $errorMsg;
                }
            }
            
            // Nettoyer le fichier temporaire
            if (file_exists($uploadedFile)) {
                unlink($uploadedFile);
            }
            $this->request->getSession()->delete('excel_uploaded_file');
            
            if ($saved > 0) {
                $this->Flash->success("$saved plage(s) enregistrée(s) avec succès.");
            }
            if ($skipped > 0) {
                $this->Flash->info("$skipped plage(s) ignorée(s) (déjà présentes en base de données).");
            }
            if (!empty($errors)) {
                $errorCount = count($errors);
                $this->Flash->error("$errorCount plage(s) n'ont pas pu être enregistrée(s). " . 
                    (count($errors) <= 5 ? implode(' | ', $errors) : 'Voir les logs pour plus de détails.'));
            }
            
            if ($saved === 0 && $skipped === 0 && empty($errors)) {
                $this->Flash->warning('Aucune plage n\'a pu être enregistrée.');
            }
            
            return $this->redirect(['action' => 'upload']);
            
        } catch (\Exception $e) {
            $errorMsg = 'Erreur lors du traitement : ' . $e->getMessage();
            if ($e->getCode()) {
                $errorMsg .= ' (Code: ' . $e->getCode() . ')';
            }
            if ($e->getFile()) {
                $errorMsg .= ' [Fichier: ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            }
            $this->Flash->error($errorMsg);
            return $this->redirect(['action' => 'preview']);
        }
    }

    /**
     * Groupe les ranges consécutives pour le même agent et la même offre
     */
    private function groupRanges(array $ranges, array $offersById = [], array $usersById = []): array
    {
        if (empty($ranges)) {
            return [];
        }

        // NOTE: on ne log plus legend_type (remplacé par is_validated dans l'UI)

        // Étape 1 : Déduplication - fusionner les ranges qui se chevauchent ou sont adjacentes le même jour
        $deduplicated = [];
        $rangesByDay = [];
        
        foreach ($ranges as $range) {
            $dateStart = $range['date_start'];
            if (!$dateStart instanceof FrozenTime) {
                $dateStart = FrozenTime::parse($dateStart);
            }
            $dateEnd = $range['date_end'];
            if (!$dateEnd instanceof FrozenTime) {
                $dateEnd = FrozenTime::parse($dateEnd);
            }
            
            $dayKey = $dateStart->format('Y-m-d');
            $isValidated = !empty($range['is_validated']);
            $demandStatus = $range['demand_status'] ?? 'real';
            $validationKey = $isValidated ? '_validated' : '_not_validated';
            $userOfferKey = $range['user_id'] . '_' . $range['offer_id'] . $validationKey . '_' . $demandStatus;
            $key = $dayKey . '_' . $userOfferKey;
            
            if (!isset($rangesByDay[$key])) {
                $rangesByDay[$key] = [
                    'user_id' => $range['user_id'],
                    'offer_id' => $range['offer_id'],
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'comment' => $range['comment'] ?? '',
                    'is_validated' => $isValidated,
                    'demand_status' => $demandStatus,
                ];
            } else {
                // Fusionner : prendre le début le plus tôt et la fin la plus tardive
                // (seulement si le statut validé/non validé est identique, ce qui est garanti par la clé)
                if ($dateStart->getTimestamp() < $rangesByDay[$key]['date_start']->getTimestamp()) {
                    $rangesByDay[$key]['date_start'] = $dateStart;
                }
                if ($dateEnd->getTimestamp() > $rangesByDay[$key]['date_end']->getTimestamp()) {
                    $rangesByDay[$key]['date_end'] = $dateEnd;
                }
            }
        }
        
        // Normaliser les journées complètes (>= 23h) à 00:00:00-23:59:59
        foreach ($rangesByDay as $key => $range) {
            $duration = $range['date_end']->getTimestamp() - $range['date_start']->getTimestamp();
            if ($duration >= 82800) { // 23 heures en secondes
                $range['date_start'] = FrozenTime::createFromFormat('Y-m-d H:i:s', $range['date_start']->format('Y-m-d') . ' 00:00:00');
                $range['date_end'] = FrozenTime::createFromFormat('Y-m-d H:i:s', $range['date_end']->format('Y-m-d') . ' 23:59:59');
            }
            $deduplicated[] = $range;
        }
        
        // Étape 2 : Grouper les ranges consécutives avec les mêmes heures
        // Tri par : user_name (alphabétique), puis offer_id, puis date_start
        usort($deduplicated, function($a, $b) use ($usersById) {
            // Tri par nom d'utilisateur (alphabétique)
            $userA = $usersById[$a['user_id']] ?? null;
            $userB = $usersById[$b['user_id']] ?? null;
            $nameA = $userA ? ($userA->last_name . ' ' . $userA->first_name) : '';
            $nameB = $userB ? ($userB->last_name . ' ' . $userB->first_name) : '';
            $cmp = strcasecmp($nameA, $nameB);
            if ($cmp !== 0) return $cmp;
            
            // Puis par offer_id
            $cmp = $a['offer_id'] <=> $b['offer_id'];
            if ($cmp !== 0) return $cmp;
            
            // Puis par date_start
            return $a['date_start']->getTimestamp() <=> $b['date_start']->getTimestamp();
        });
        
        $grouped = [];
        $currentGroup = null;
        
        foreach ($deduplicated as $range) {
            $startTime = $range['date_start']->format('H:i:s');
            $endTime = $range['date_end']->format('H:i:s');
            
            if ($currentGroup === null) {
                $currentGroup = $range;
                if (!isset($currentGroup['is_validated'])) {
                    $currentGroup['is_validated'] = false;
                }
                if (!isset($currentGroup['demand_status'])) {
                    $currentGroup['demand_status'] = 'real';
                }
            } else {
                // Vérifier si on peut regrouper
                $canGroup = (
                    $currentGroup['user_id'] === $range['user_id'] &&
                    $currentGroup['offer_id'] === $range['offer_id'] &&
                    $currentGroup['date_start']->format('H:i:s') === $startTime &&
                    $currentGroup['date_end']->format('H:i:s') === $endTime &&
                    ($currentGroup['is_validated'] ?? false) === ($range['is_validated'] ?? false) &&
                    ($currentGroup['demand_status'] ?? 'real') === ($range['demand_status'] ?? 'real')
                );
                
                if ($canGroup) {
                    $currentEnd = clone $currentGroup['date_end'];
                    $currentEnd->setTime(0, 0, 0);
                    $nextStart = clone $range['date_start'];
                    $nextStart->setTime(0, 0, 0);
                    
                    // Vérifier si les jours sont consécutifs
                    $daysDiff = ($nextStart->getTimestamp() - $currentEnd->getTimestamp()) / 86400;
                    
                    if ($daysDiff <= 1) {
                        $currentGroup['date_end'] = $range['date_end'];
                    } else {
                        // Sauvegarder le groupe actuel et commencer un nouveau
                        $grouped[] = $currentGroup;
                        $currentGroup = $range;
                        if (!isset($currentGroup['is_validated'])) {
                            $currentGroup['is_validated'] = false;
                        }
                        if (!isset($currentGroup['demand_status'])) {
                            $currentGroup['demand_status'] = 'real';
                        }
                    }
                } else {
                    // Sauvegarder le groupe actuel et commencer un nouveau
                    $grouped[] = $currentGroup;
                    $currentGroup = $range;
                    if (!isset($currentGroup['is_validated'])) {
                        $currentGroup['is_validated'] = false;
                    }
                    if (!isset($currentGroup['demand_status'])) {
                        $currentGroup['demand_status'] = 'real';
                    }
                }
            }
        }
        
        if ($currentGroup !== null) {
            $grouped[] = $currentGroup;
        }
        
        // Générer les commentaires génériques basés sur le nom de l'offre
        foreach ($grouped as &$range) {
            // Toujours utiliser le nom de l'offre pour le commentaire
            $offer = $offersById[$range['offer_id']] ?? null;
            $offerName = $offer ? $offer->name : 'Événement';
            $range['comment'] = $offerName . ' - GroomRH';
        }
        
        return $grouped;
    }
}