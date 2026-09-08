<?php
namespace App\Service;

use App\Model\Entity\WfmSetting;
use Cake\Core\Configure;
use Cake\Http\Client;
use DateTimeInterface;
use DateTime;
use Exception;

/**
 * Helper pour communiquer avec le service Python Prophet
 * 
 * Ce service PHP fait le pont entre CakePHP et le service Python Prophet (FastAPI)
 */
class ProphetForecastHelper
{
    private $httpClient;
    private $serviceUrl;
    private $timeout;
    private $enabled;

    public function __construct()
    {
        $this->httpClient = new Client();
        $this->serviceUrl = Configure::read('PythonForecast.url', 'http://127.0.0.1:8001');
        $this->timeout = Configure::read('PythonForecast.timeout', 600);
        $this->enabled = Configure::read('PythonForecast.enabled', true);
    }

    /**
     * Vérifie si le service Prophet est disponible
     *
     * @return bool
     */
    public function isServiceAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = $this->httpClient->get($this->serviceUrl . '/health', [], [
                'timeout' => 5
            ]);

            if ($response->isOk()) {
                $data = $response->getJson();
                return isset($data['status']) && $data['status'] === 'ok';
            }
        } catch (Exception $e) {
            // Service indisp onible
        }

        return false;
    }

    /**
     * Génère une prévision Prophet pour une offre et une date
     *
     * @param int $offerId ID de l'offre
     * @param DateTimeInterface $date Date à prévoir
     * @param array $prophetSettings Paramètres Prophet
     * @param WfmSetting $wfmSettings Paramètres WFM
     * @return array|null Prévisions au format ['HH:MM:SS' => ['volume' => X, 'dmt' => Y]] ou null si erreur
     */
    public function generateForecast(
        int $offerId,
        DateTimeInterface $date,
        array $prophetSettings,
        WfmSetting $wfmSettings
    ): ?array {
        if (!$this->isServiceAvailable()) {
            throw new Exception("Service Prophet non disponible sur {$this->serviceUrl}");
        }

        try {
            $payload = [
                'offer_id' => $offerId,
                'date' => $date->format('Y-m-d'),
                'prophet_settings' => $this->formatProphetSettings($prophetSettings),
                'wfm_settings' => [
                    'day_start_time' => (string)$wfmSettings->day_start_time,
                    'day_end_time' => (string)$wfmSettings->day_end_time,
                ]
            ];

            $response = $this->httpClient->post(
                $this->serviceUrl . '/forecast/generate',
                json_encode($payload),
                [
                    'type' => 'json',
                    'timeout' => $this->timeout
                ]
            );

            if ($response->isOk()) {
                $data = $response->getJson();

                if (!empty($data['success']) && !empty($data['forecast'])) {
                    // Convertir le format Prophet vers le format attendu
                    return $this->convertProphetForecast($data['forecast'], $data['metrics'] ?? null);
                }

                throw new Exception('Réponse Prophet invalide: ' . json_encode($data));
            }

            $error = $response->getStringBody();
            throw new Exception("Erreur Prophet (HTTP {$response->getStatusCode()}): {$error}");

        } catch (Exception $e) {
            error_log("[ProphetForecastHelper] Erreur generateForecast: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Génère des prévisions Prophet pour une période (batch)
     *
     * @param int $offerId ID de l'offre
     * @param DateTimeInterface $startDate Date de début
     * @param DateTimeInterface $endDate Date de fin
     * @param array $prophetSettings Paramètres Prophet
     * @param WfmSetting $wfmSettings Paramètres WFM
     * @return array {days: [date => forecast], dmt_profile: ?array}
     */
    public function generateBatchForecast(
        int $offerId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $prophetSettings,
        WfmSetting $wfmSettings
    ): array {
        if (!$this->isServiceAvailable()) {
            throw new Exception("Service Prophet non disponible sur {$this->serviceUrl}");
        }

        try {
            $formattedSettings = $this->formatProphetSettings($prophetSettings);
            
            // Log détaillé des settings Prophet transmis à Python
            error_log("[ProphetHelper] Offre {$offerId} - Settings Prophet transmis: " . json_encode($formattedSettings));
            
            $payload = [
                'offer_id' => $offerId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'prophet_settings' => $formattedSettings,
                'wfm_settings' => [
                    'day_start_time' => (string)$wfmSettings->day_start_time,
                    'day_end_time' => (string)$wfmSettings->day_end_time,
                ]
            ];

            error_log("[ProphetForecastHelper] Batch forecast pour offre {$offerId}");
            error_log("[ProphetForecastHelper] Période prévisions: {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
            error_log("[ProphetForecastHelper] Prophet settings: " . json_encode($formattedSettings));
            
            // DEBUG pour logistic growth
            if (isset($formattedSettings['growth']) && $formattedSettings['growth'] === 'logistic') {
                error_log("[ProphetForecastHelper] ⚠️ Mode LOGISTIC détecté");
                error_log("[ProphetForecastHelper] growth_cap = " . ($formattedSettings['growth_cap'] ?? 'NULL/MANQUANT'));
            }

            $response = $this->httpClient->post(
                $this->serviceUrl . '/forecast/batch',
                json_encode($payload),
                [
                    'type' => 'json',
                    'timeout' => $this->timeout
                ]
            );

            if ($response->isOk()) {
                $data = $response->getJson();

                if (!empty($data['success']) && !empty($data['results'])) {
                    $forecasts = [];
                    $metrics = $data['metrics'] ?? null;

                    foreach ($data['results'] as $dayResult) {
                        $date = $dayResult['date'];
                        $forecasts[$date] = [
                            'forecast' => $this->convertProphetForecast($dayResult['forecast'], null),
                            'metrics' => $metrics
                        ];
                    }

                    return [
                        'days' => $forecasts,
                        'dmt_profile' => self::normalizeDmtProfile($data['dmt_profile'] ?? null),
                    ];
                }

                throw new Exception('Réponse Prophet batch invalide: ' . json_encode($data));
            }

            $error = $response->getStringBody();
            throw new Exception("Erreur Prophet batch (HTTP {$response->getStatusCode()}): {$error}");

        } catch (Exception $e) {
            error_log("[ProphetForecastHelper] Erreur generateBatchForecast: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Formate les paramètres Prophet pour l'API Python
     *
     * @param array $settings Paramètres depuis le formulaire
     * @return array Paramètres formatés pour Prophet
     */
    private function formatProphetSettings(array $settings): array
    {
        return [
            // Plage de données historiques (IMPORTANT !)
            'history_start_date' => $this->nullableDate($settings['history_start_date'] ?? null),
            'history_end_date' => $this->nullableDate($settings['history_end_date'] ?? null),
            
            // Mode de saisonnalité
            'seasonality_mode' => $settings['seasonality_mode'] ?? 'additive',
            
            // Saisonnalités
            'yearly_seasonality' => (bool)($settings['yearly_seasonality'] ?? true),
            'weekly_seasonality' => (bool)($settings['weekly_seasonality'] ?? true),
            'monthly_seasonality' => (bool)($settings['monthly_seasonality'] ?? false),
            'monthly_fourier_order' => (int)($settings['monthly_fourier_order'] ?? 5),
            'daily_seasonality' => (bool)($settings['daily_seasonality'] ?? true),
            
            // Sensibilités
            'changepoint_prior_scale' => (float)($settings['changepoint_prior_scale'] ?? 0.05),
            'seasonality_prior_scale' => (float)($settings['seasonality_prior_scale'] ?? 10.0),
            
            // Croissance (toujours linear)
            'growth' => 'linear',
            'n_changepoints' => (int)($settings['n_changepoints'] ?? 25),
            'changepoint_range' => (float)($settings['changepoint_range'] ?? 0.8),
            
            // Jours fériés
            'use_french_holidays' => (bool)($settings['use_french_holidays'] ?? true),
            'custom_holidays' => $settings['custom_holidays'] ?? null
        ];
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    /**
     * Convertit le format Prophet vers le format WFM attendu
     *
     * @param array $prophetForecast Prévisions au format Prophet
     * @param array|null $metrics Métriques optionnelles
     * @return array Format: ['HH:MM:SS' => ['volume' => X, 'dmt' => Y]]
     */
    private function convertProphetForecast(array $prophetForecast, ?array $metrics): array
    {
        $result = [];

        foreach ($prophetForecast as $timeSlot => $data) {
            // Prophet peut renvoyer des objets ForecastPoint ou des arrays simples
            if (is_array($data)) {
                $result[$timeSlot] = [
                    'volume' => $data['volume'] ?? 0,
                    'dmt' => $data['dmt'] ?? 300
                ];
            } elseif (is_object($data)) {
                $result[$timeSlot] = [
                    'volume' => $data->volume ?? 0,
                    'dmt' => $data->dmt ?? 300
                ];
            }
        }

        return $result;
    }

    /**
     * Clés jour ISO en string ("1" = lundi) pour un JSON objet O(1), pas un tableau [].
     *
     * @param array|null $profile
     * @return array|null
     */
    public static function normalizeDmtProfile(?array $profile): ?array
    {
        if ($profile === null || $profile === []) {
            return null;
        }

        $coefficients = [];
        foreach (($profile['coefficients'] ?? []) as $dow => $slots) {
            $dayKey = (string)((int)$dow);
            if ($dayKey === '0') {
                continue;
            }
            $coefficients[$dayKey] = [];
            if (!is_array($slots)) {
                continue;
            }
            foreach ($slots as $hhmm => $coeff) {
                $coefficients[$dayKey][(string)$hhmm] = (float)$coeff;
            }
        }
        $profile['coefficients'] = $coefficients;
        if (array_key_exists('level_seconds', $profile)) {
            $profile['level_seconds'] = (float)$profile['level_seconds'];
        }

        return $profile;
    }

    /**
     * Teste la connexion au service Prophet
     *
     * @return array Résultats du test
     */
    public function testConnection(): array
    {
        try {
            $response = $this->httpClient->get($this->serviceUrl . '/forecast/test', [], [
                'timeout' => 10
            ]);

            if ($response->isOk()) {
                return [
                    'success' => true,
                    'data' => $response->getJson()
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->getStatusCode()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

