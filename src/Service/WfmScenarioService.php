<?php
namespace App\Service;

use App\Model\Entity\WfmSetting;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTime;
use DateTimeInterface;

class WfmScenarioService
{
    use LocatorAwareTrait;

    private $ForecastService;
    private $WfmCalculatorService;
    private $ProphetForecastHelper;
    private $ForecastScenarios;
    private $ForecastScenariosOffers;
    private $ScenarioSeries;
    private $Offers;

    public function __construct(ForecastService $forecastService, WfmCalculatorService $calculatorService)
    {
        $this->ForecastService = $forecastService;
        $this->WfmCalculatorService = $calculatorService;
        $this->ProphetForecastHelper = new ProphetForecastHelper();
        $this->ForecastScenarios = $this->fetchTable('ForecastScenarios');
        $this->ForecastScenariosOffers = $this->fetchTable('ForecastScenariosOffers');
        $this->ScenarioSeries = $this->fetchTable('ScenarioSeries');
        $this->Offers = $this->fetchTable('Offers');
    }

    public function createScenario(
        string $name,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $offerIds,
        array $settingsSnapshot,
        ?int $createdBy = null,
        array $overridesByOffer = [],
        array $methodsByOffer = []
    ): int
    {
        $scenario = $this->ForecastScenarios->newEntity([
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'settings_snapshot_json' => json_encode($settingsSnapshot),
            'status' => 'draft',
            'created_by' => $createdBy,
        ]);

        $this->ForecastScenarios->saveOrFail($scenario);

        // Pré-calculer les paramètres Prophet effectifs par offre (défauts système + profil d'offre + éventuels overrides)
        $systemProphetDefaults = $this->getSystemProphetDefaults();

        foreach ($offerIds as $offerId) {
            $offerId = (int)$offerId;
            $override = $overridesByOffer[$offerId] ?? null;

            // Point de départ : défauts système
            $effectiveProphetSettings = $systemProphetDefaults;

            // Profil Prophet par défaut de l'offre
            $offerProfile = [];
            $offer = $this->Offers->get((int)$offerId);
            $rawOfferProfile = $offer->prophet_default_settings_json ?? null;
            if (is_string($rawOfferProfile) && $rawOfferProfile !== '') {
                $decoded = json_decode($rawOfferProfile, true);
                if (is_array($decoded)) {
                    $offerProfile = $decoded;
                }
            } elseif (is_array($rawOfferProfile)) {
                $offerProfile = $rawOfferProfile;
            }
            if (!empty($offerProfile)) {
                $effectiveProphetSettings = array_merge($effectiveProphetSettings, $offerProfile);
            }

            // Éventuels overrides passés lors de la création (aujourd'hui non utilisés côté UI, mais supportés)
            if (is_array($override) && !empty($override)) {
                $effectiveProphetSettings = array_merge($effectiveProphetSettings, $override);
            }

            $method = $methodsByOffer[$offerId] ?? $offer->default_forecast_method ?? 'historical';

            $link = $this->ForecastScenariosOffers->newEntity([
                'scenario_id' => $scenario->id,
                'offer_id' => $offerId,
                'forecast_method' => $method,
                // Snapshot Prophet complet par offre et par scénario
                // (initialisé ici, puis mis à jour lors de l'exécution du scénario si nécessaire).
                'prophet_settings_json' => json_encode($effectiveProphetSettings),
            ]);
            $this->ForecastScenariosOffers->saveOrFail($link);
        }

        return (int)$scenario->id;
    }

    public function runScenario(int $scenarioId, WfmSetting $settings): void
    {
        $scenario = $this->ForecastScenarios->get($scenarioId);

        error_log("[WFM Scenario {$scenarioId}] Lancement du scénario (méthode par offre).");

        $start = $this->toDateTime($scenario->start_date);
        $end = $this->toDateTime($scenario->end_date);
        $daysInPeriod = (int)$start->diff($end)->days + 1;

        $historicalLinks = $this->ForecastScenariosOffers->find()
            ->where([
                'scenario_id' => $scenarioId,
                'forecast_method' => 'historical',
            ])
            ->all()
            ->toList();

        $prophetLinks = $this->ForecastScenariosOffers
            ->find()
            ->where([
                'scenario_id' => $scenarioId,
                'forecast_method' => 'prophet',
            ])
            ->contain(['Offers'])
            ->all()
            ->toList();

        $offersTotal = count($historicalLinks) + count($prophetLinks);
        $daysTotal = $offersTotal * $daysInPeriod;

        $this->reconnectDb();
        $this->ForecastScenarios->updateAll(
            [
                'progress_offers_done' => 0,
                'progress_offers_total' => $offersTotal,
                'progress_days_done' => 0,
                'progress_days_total' => $daysTotal,
                'progress_offer_id' => null,
                'progress_offer_name' => null,
                'progress_date' => null,
                'error_message' => null,
            ],
            ['id' => $scenarioId],
        );

        $offersDone = 0;
        $daysDone = 0;

        $this->runScenarioWithHistorical(
            $scenarioId,
            $scenario,
            $settings,
            $historicalLinks,
            $offersDone,
            $daysDone,
        );
        $this->runScenarioWithProphet(
            $scenarioId,
            $scenario,
            $settings,
            $prophetLinks,
            $offersDone,
            $daysDone,
        );

        $this->reconnectDb();
        $finishedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->ForecastScenarios->updateAll(
            [
                'status' => 'completed',
                'finished_at' => $finishedAt,
                'error_message' => null,
                'progress_offer_id' => null,
                'progress_offer_name' => null,
                'progress_date' => null,
                'progress_offers_done' => $offersTotal,
                'progress_days_done' => $daysTotal,
            ],
            ['id' => $scenarioId],
        );
        error_log("[WFM Scenario {$scenarioId}] Terminé avec succès.");
    }

    /**
     * Blindage 2 : reconnecte MySQL avant les écritures post-attente API (Gone Away).
     * CakePHP 5 : disconnect/connect sont sur le Driver, pas sur Connection.
     */
    private function reconnectDb(): void
    {
        $driver = $this->ForecastScenarios->getConnection()->getDriver();
        $driver->disconnect();
        $driver->connect();
    }

    /**
     * Met à jour les champs de progression du scénario (après reconnect).
     *
     * @param array<string, mixed> $fields
     */
    private function updateScenarioProgress(int $scenarioId, array $fields): void
    {
        $this->reconnectDb();
        $this->ForecastScenarios->updateAll($fields, ['id' => $scenarioId]);
    }

    /**
     * Exécute le scénario avec la méthode Prophet (commits par offre, sans transaction globale).
     *
     * @param list<\App\Model\Entity\ForecastScenariosOffer> $links
     */
    private function runScenarioWithProphet(
        int $scenarioId,
        $scenario,
        WfmSetting $settings,
        array $links,
        int &$offersDone,
        int &$daysDone,
    ): void {
        if ($links === []) {
            return;
        }

        if (!$this->ProphetForecastHelper->isServiceAvailable()) {
            throw new \Exception('Service Prophet non disponible. Impossible de calculer le scénario.');
        }

        $start = $this->toDateTime($scenario->start_date);
        $end = $this->toDateTime($scenario->end_date);
        $systemProphetDefaults = $this->getSystemProphetDefaults();
        $totalOffers = count($links);
        $offerIndex = 0;
        $metricsPerOffer = [];
        $connection = $this->ScenarioSeries->getConnection();

        foreach ($links as $link) {
            $offerIndex++;
            $offerId = (int)$link->offer_id;
            $offerName = $link->offer->name ?? "Offre #{$offerId}";

            error_log("[WFM Scenario {$scenarioId}] Prophet - Offre {$offerIndex}/{$totalOffers}: {$offerName}");

            $this->updateScenarioProgress($scenarioId, [
                'progress_offer_id' => $offerId,
                'progress_offer_name' => $offerName,
                'progress_date' => $start->format('Y-m-d'),
                'progress_offers_done' => $offersDone,
                'progress_days_done' => $daysDone,
            ]);

            $effectiveWfmSettings = clone $settings;
            $effectiveProphetSettings = $systemProphetDefaults;

            $offerProfile = [];
            $rawOfferProfile = $link->offer->prophet_default_settings_json ?? null;
            if (is_string($rawOfferProfile) && $rawOfferProfile !== '') {
                $decoded = json_decode($rawOfferProfile, true);
                if (is_array($decoded)) {
                    $offerProfile = $decoded;
                }
            } elseif (is_array($rawOfferProfile)) {
                $offerProfile = $rawOfferProfile;
            }
            if (!empty($offerProfile)) {
                $effectiveProphetSettings = array_merge($effectiveProphetSettings, $offerProfile);
            }

            $existingSnapshot = [];
            if (!empty($link->prophet_settings_json)) {
                if (is_string($link->prophet_settings_json)) {
                    $decoded = json_decode($link->prophet_settings_json, true);
                    if (is_array($decoded)) {
                        $existingSnapshot = $decoded;
                    }
                } elseif (is_array($link->prophet_settings_json)) {
                    $existingSnapshot = $link->prophet_settings_json;
                }
            }
            if (!empty($existingSnapshot)) {
                $prophetKeys = [
                    'history_start_date', 'history_end_date', 'seasonality_mode',
                    'yearly_seasonality', 'weekly_seasonality', 'monthly_seasonality', 'monthly_fourier_order', 'daily_seasonality',
                    'changepoint_prior_scale', 'seasonality_prior_scale',
                    'growth', 'n_changepoints', 'changepoint_range', 'use_french_holidays',
                ];
                foreach ($existingSnapshot as $k => $v) {
                    if ($v === '' || $v === null) {
                        continue;
                    }
                    if (in_array($k, $prophetKeys, true)) {
                        $convertedValue = $v;
                        if (in_array($k, ['yearly_seasonality', 'weekly_seasonality', 'monthly_seasonality', 'daily_seasonality'], true)) {
                            $convertedValue = ($v === '1' || $v === 1 || $v === true);
                        } elseif (in_array($k, ['n_changepoints', 'monthly_fourier_order'], true)) {
                            $convertedValue = (int)$v;
                        } elseif (in_array($k, ['changepoint_prior_scale', 'seasonality_prior_scale', 'changepoint_range'], true)) {
                            $convertedValue = (float)$v;
                        }
                        $effectiveProphetSettings[$k] = $convertedValue;
                    }
                }
            }

            $this->reconnectDb();
            $link->prophet_settings_json = json_encode($effectiveProphetSettings);
            $this->ForecastScenariosOffers->save($link);

            error_log("[Scenario {$scenarioId}] Settings Prophet finaux pour offre {$offerId}: " . json_encode($effectiveProphetSettings));

            // Appel API externe (hors transaction) — peut durer longtemps
            $forecasts = $this->ProphetForecastHelper->generateBatchForecast(
                $offerId,
                $start,
                $end,
                $effectiveProphetSettings,
                $effectiveWfmSettings,
            );

            if (!empty($forecasts)) {
                $firstDay = array_key_first($forecasts);
                if (isset($forecasts[$firstDay]['metrics'])) {
                    $metricsPerOffer[] = [
                        'offer_id' => $offerId,
                        'metrics' => $forecasts[$firstDay]['metrics'],
                    ];
                }
            }

            // Commit par offre : reconnect puis transaction courte pour les séries
            $this->reconnectDb();
            $connection->begin();
            try {
                foreach ($forecasts as $dateStr => $dayData) {
                    $d = new DateTime($dateStr);
                    $forecast = $dayData['forecast'];
                    $need = $this->calculateNeedFromForecast($forecast, $effectiveWfmSettings);

                    $this->upsertSeries($scenarioId, $offerId, $d, 'forecast', $effectiveWfmSettings, $forecast);
                    $this->upsertSeries($scenarioId, $offerId, $d, 'need', $effectiveWfmSettings, $need);

                    $daysDone++;
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollback();
                error_log("[WFM Scenario {$scenarioId}] Échec Prophet (offre {$offerId}): " . $e->getMessage());
                throw $e;
            }

            // Après commit : reconnect + progress (jours de l'offre + offre terminée)
            $offersDone++;
            $lastDate = !empty($forecasts) ? (string)array_key_last($forecasts) : $end->format('Y-m-d');
            $this->updateScenarioProgress($scenarioId, [
                'progress_offer_id' => $offerId,
                'progress_offer_name' => $offerName,
                'progress_date' => $lastDate,
                'progress_offers_done' => $offersDone,
                'progress_days_done' => $daysDone,
            ]);
        }

        if (!empty($metricsPerOffer)) {
            $avgMape = array_sum(array_column(array_column($metricsPerOffer, 'metrics'), 'mape')) / count($metricsPerOffer);
            $avgMae = array_sum(array_column(array_column($metricsPerOffer, 'metrics'), 'mae')) / count($metricsPerOffer);
            $avgRmse = array_sum(array_column(array_column($metricsPerOffer, 'metrics'), 'rmse')) / count($metricsPerOffer);

            $allMetrics = [
                'mape' => round($avgMape, 2),
                'mae' => round($avgMae, 2),
                'rmse' => round($avgRmse, 2),
                'per_offer' => $metricsPerOffer,
            ];

            $this->reconnectDb();
            $this->ForecastScenarios->updateAll(
                ['prophet_metrics_json' => json_encode($allMetrics)],
                ['id' => $scenarioId],
            );
        }

        error_log("[WFM Scenario {$scenarioId}] Prophet terminé.");
    }

    /**
     * Exécute le scénario historique (boucle offre → jours, commit par offre).
     *
     * @param list<\App\Model\Entity\ForecastScenariosOffer> $links
     */
    private function runScenarioWithHistorical(
        int $scenarioId,
        $scenario,
        WfmSetting $settings,
        array $links,
        int &$offersDone,
        int &$daysDone,
    ): void {
        if ($links === []) {
            return;
        }

        $start = $this->toDateTime($scenario->start_date);
        $end = $this->toDateTime($scenario->end_date);
        $totalDays = (int)$start->diff($end)->days + 1;
        $totalOffers = count($links);
        $offerIndex = 0;
        $connection = $this->ScenarioSeries->getConnection();

        // Contenir Offers pour le nom de progression si absente
        $offerNames = [];
        foreach ($links as $link) {
            $oid = (int)$link->offer_id;
            if (!isset($offerNames[$oid])) {
                try {
                    $offer = $this->Offers->get($oid);
                    $offerNames[$oid] = (string)($offer->name ?? "Offre #{$oid}");
                } catch (\Throwable) {
                    $offerNames[$oid] = "Offre #{$oid}";
                }
            }
        }

        foreach ($links as $link) {
            $offerIndex++;
            $offerId = (int)$link->offer_id;
            $offerName = $offerNames[$offerId] ?? "Offre #{$offerId}";

            $this->updateScenarioProgress($scenarioId, [
                'progress_offer_id' => $offerId,
                'progress_offer_name' => $offerName,
                'progress_date' => $start->format('Y-m-d'),
                'progress_offers_done' => $offersDone,
                'progress_days_done' => $daysDone,
            ]);

            $effectiveSettingsBase = clone $settings;
            $historyStart = null;
            $historyEnd = null;
            if (!empty($link->prophet_settings_json)) {
                $ovr = json_decode((string)$link->prophet_settings_json, true) ?: [];
                foreach ($ovr as $k => $v) {
                    if (property_exists($effectiveSettingsBase, $k)) {
                        $effectiveSettingsBase->{$k} = $v;
                    }
                    if ($k === 'history_start_date') {
                        $historyStart = $v;
                    } elseif ($k === 'history_end_date') {
                        $historyEnd = $v;
                    }
                }
            }

            $this->reconnectDb();
            $connection->begin();
            try {
                $dayIndex = 0;
                for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
                    $dayIndex++;
                    $percent = round((($offerIndex - 1) * $totalDays + $dayIndex) / ($totalDays * $totalOffers) * 100, 1);
                    error_log("[WFM Scenario {$scenarioId}] Historical - Offre {$offerIndex}/{$totalOffers} - Jour {$dayIndex}/{$totalDays} ({$percent}%)");

                    $effectiveSettings = clone $effectiveSettingsBase;
                    $forecast = $this->ForecastService->getForecast($offerId, $d, $effectiveSettings, $historyStart, $historyEnd);
                    $need = $this->WfmCalculatorService->generateNeedForOffer($d, $effectiveSettings, $offerId);

                    $this->upsertSeries($scenarioId, $offerId, $d, 'forecast', $effectiveSettings, $forecast);
                    $this->upsertSeries($scenarioId, $offerId, $d, 'need', $effectiveSettings, $need);
                    $daysDone++;
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollback();
                error_log("[WFM Scenario {$scenarioId}] Échec Historical (offre {$offerId}): " . $e->getMessage());
                throw $e;
            }

            $offersDone++;
            $this->updateScenarioProgress($scenarioId, [
                'progress_offer_id' => $offerId,
                'progress_offer_name' => $offerName,
                'progress_date' => $end->format('Y-m-d'),
                'progress_offers_done' => $offersDone,
                'progress_days_done' => $daysDone,
            ]);
        }

        error_log("[WFM Scenario {$scenarioId}] Historical terminé.");
    }

    /**
     * Calcule le besoin en agents à partir d'une prévision de volume
     */
    private function calculateNeedFromForecast(array $forecast, WfmSetting $settings): array
    {
        $need = [];
        $intervalSeconds = 15 * 60;

        $shrinkagePct = max(0.0, min(99.0, (float)$settings->shrinkage_percent));
        $shrinkageFactor = 1.0 - ($shrinkagePct / 100.0);
        if ($shrinkageFactor <= 0.0) {
            $shrinkageFactor = 0.01;
        }

        foreach ($forecast as $timeSlot => $data) {
            $volume = (int)($data['volume'] ?? 0);
            $aht = (int)($data['dmt'] ?? 300);

            if ($volume <= 0 || $aht <= 0) {
                $need[$timeSlot] = 0;
                continue;
            }

            $workloadErlangs = ($volume * $aht) / $intervalSeconds;

            $agentsTheoriques = $this->WfmCalculatorService->calculateErlangC(
                (float)$workloadErlangs,
                ((float)$settings->service_level_percent) / 100.0,
                (int)$settings->service_level_seconds,
                (int)$aht
            );

            $need[$timeSlot] = max(0, (int)ceil($agentsTheoriques / $shrinkageFactor));
        }

        return $need;
    }

    /**
     * Défauts système Prophet globaux, à partir de wfm_settings.prophet_defaults_json
     * avec fallback sur des valeurs codées en dur si la colonne est vide.
     */
    private function getSystemProphetDefaults(): array
    {
        /** @var \App\Model\Table\WfmSettingsTable $WfmSettings */
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfm = $WfmSettings->find()->first();

        $defaults = [
            'history_start_date' => null,
            'history_end_date' => null,
            'seasonality_mode' => 'multiplicative',
            'yearly_seasonality' => true,
            'weekly_seasonality' => true,
            'monthly_seasonality' => true,
            'monthly_fourier_order' => 5,
            'daily_seasonality' => true,
            'changepoint_prior_scale' => 0.1,
            'seasonality_prior_scale' => 10.0,
            'growth' => 'linear',
            'n_changepoints' => 25,
            'changepoint_range' => 0.8,
            'use_french_holidays' => true,
        ];

        if ($wfm && !empty($wfm->prophet_defaults_json)) {
            $raw = $wfm->prophet_defaults_json;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $defaults = array_merge($defaults, $decoded);
                }
            } elseif (is_array($raw)) {
                $defaults = array_merge($defaults, $raw);
            }
        }

        return $defaults;
    }

    private function toDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTime($value->format('Y-m-d'));
        }
        $s = (string)$value;
        if (strpos($s, '/') !== false) {
            $dt = DateTime::createFromFormat('d/m/Y', $s);
            if ($dt instanceof DateTime) {
                return $dt;
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return new DateTime($s);
        }
        return new DateTime($s);
    }

    private function upsertSeries(int $scenarioId, int $offerId, DateTimeInterface $date, string $type, WfmSetting $settings, array $data): void
    {
        $startStr = (string)($settings->day_start_time ?? '');
        $endStr = (string)($settings->day_end_time ?? '');

        if ($startStr === '' || $endStr === '') {
            throw new \RuntimeException(
                "[WfmScenarioService] day_start_time/day_end_time manquants dans WfmSettings. " .
                "Configure d'abord le profil WFM avant de lancer un scénario."
            );
        }

        if (strlen($startStr) === 5) $startStr .= ':00';
        if (strlen($endStr) === 5) $endStr .= ':00';

        // OPTIMISATION: Utiliser deleteAll + insert au lieu de find + update pour éviter le SELECT
        // Plus efficace dans une transaction car on sait qu'on écrase de toute façon
        $this->ScenarioSeries->deleteAll([
            'scenario_id' => $scenarioId,
            'offer_id' => $offerId,
            'date' => $date->format('Y-m-d'),
            'type' => $type,
        ]);

        $entity = $this->ScenarioSeries->newEntity([
            'scenario_id' => $scenarioId,
            'offer_id' => $offerId,
            'date' => $date->format('Y-m-d'),
            'type' => $type,
            'step_seconds' => 900,
            'start_time' => $startStr,
            'end_time' => $endStr,
            'data_json' => json_encode($data),
        ]);

        $this->ScenarioSeries->saveOrFail($entity);
    }

    public function getSeries(int $scenarioId, int $offerId, DateTimeInterface $date, string $type): ?array
    {
        $row = $this->ScenarioSeries->find()
            ->select(['step_seconds', 'start_time', 'end_time', 'data_json'])
            ->where([
                'scenario_id' => $scenarioId,
                'offer_id' => $offerId,
                'date' => $date->format('Y-m-d'),
                'type' => $type,
            ])->first();

        if (!$row) return null;

        return [
            'stepSeconds' => (int)$row->step_seconds,
            'startTime' => (string)$row->start_time,
            'endTime' => (string)$row->end_time,
            'data' => json_decode((string)$row->data_json, true) ?: [],
        ];
    }

    public function updateScenario(
        int $scenarioId,
        string $name,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $offerIds,
        array $settingsSnapshot,
        array $methodsByOffer = []
    ): void {
        $scenario = $this->ForecastScenarios->get($scenarioId);
        $scenario = $this->ForecastScenarios->patchEntity($scenario, [
            'name' => $name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'settings_snapshot_json' => json_encode($settingsSnapshot),
            'status' => 'draft',
        ]);
        $this->ForecastScenarios->saveOrFail($scenario);

        // Sync offers
        $existingLinks = $this->ForecastScenariosOffers->find()
            ->where(['scenario_id' => $scenarioId])
            ->all();
        $existingOfferIds = [];
        foreach ($existingLinks as $l) { $existingOfferIds[] = (int)$l->offer_id; }

        $toAdd = array_diff($offerIds, $existingOfferIds);
        $toRemove = array_diff($existingOfferIds, $offerIds);

        // Mettre à jour la méthode de prévision pour les liens existants
        foreach ($existingLinks as $link) {
            $offerId = (int)$link->offer_id;
            if (in_array($offerId, $offerIds, true)) {
                $method = $methodsByOffer[$offerId] ?? 'historical';
                $link->forecast_method = $method;
                $this->ForecastScenariosOffers->save($link);
            }
        }

        // Préparer les défauts Prophet système pour les nouvelles offres ajoutées
        $systemProphetDefaults = $this->getSystemProphetDefaults();

        foreach ($toAdd as $offerId) {
            // Reconstruire un snapshot Prophet complet pour la nouvelle offre
            $effectiveProphetSettings = $systemProphetDefaults;

            $offerProfile = [];
            $offer = $this->Offers->get((int)$offerId);
            $rawOfferProfile = $offer->prophet_default_settings_json ?? null;
            if (is_string($rawOfferProfile) && $rawOfferProfile !== '') {
                $decoded = json_decode($rawOfferProfile, true);
                if (is_array($decoded)) {
                    $offerProfile = $decoded;
                }
            } elseif (is_array($rawOfferProfile)) {
                $offerProfile = $rawOfferProfile;
            }
            if (!empty($offerProfile)) {
                $effectiveProphetSettings = array_merge($effectiveProphetSettings, $offerProfile);
            }

            $method = $methodsByOffer[(int)$offerId] ?? $offer->default_forecast_method ?? 'historical';

            $link = $this->ForecastScenariosOffers->newEntity([
                'scenario_id' => $scenarioId,
                'offer_id' => (int)$offerId,
                'forecast_method' => $method,
                'prophet_settings_json' => json_encode($effectiveProphetSettings),
            ]);
            $this->ForecastScenariosOffers->save($link);
        }
        if (!empty($toRemove)) {
            $this->ForecastScenariosOffers->deleteAll([
                'scenario_id' => $scenarioId,
                'offer_id IN' => $toRemove,
            ]);
        }
    }
}


