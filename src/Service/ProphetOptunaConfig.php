<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

/**
 * Config Optuna + flags Prophet figés V1 (alignés sur prophet_tuning_core.py).
 */
final class ProphetOptunaConfig
{
    /** Fuseau fixe pour le planning cron (affiché en UI). */
    public const CRON_TIMEZONE = 'Europe/Paris';

    /** Affichage des horodatages job (stockés en UTC en BDD). */
    public const DISPLAY_TIMEZONE = self::CRON_TIMEZONE;

    /** 1=Lundi … 7=Dimanche (aligné date('N')). */
    public const WEEKDAY_LABELS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public const DEFAULTS = [
        'cron_enabled' => false,
        'cron_period_days' => 7,
        'cron_weekdays' => [7], // Dimanche par défaut
        'cron_hour' => 2,
        'cron_minute' => 0,
        'cron_workday_start_hour' => 8, // alerte si fin estimée >= cette heure le lendemain ouvré
        'last_cron_enqueue_date' => null, // Y-m-d (Europe/Paris), anti double-fire
        'test_horizon_days' => 90,
        'n_cutoffs' => 4,
        'cutoff_lags_days' => [90, 180, 270, 360],
        'n_trials' => 30,
        'min_history_days' => 90,
        'auto_apply' => false,
        'auto_apply_min_mae_improvement_pct' => 3,
        'changepoint_prior_scale_min' => 0.005,
        'changepoint_prior_scale_max' => 0.10,
        'seasonality_prior_scale_min' => 1.0,
        'seasonality_prior_scale_max' => 50.0,
        'n_changepoints_min' => 25,
        'n_changepoints_max' => 25,
        'monthly_fourier_order_min' => 3,
        'monthly_fourier_order_max' => 10,
    ];

    /** Secondes / trial si pas encore d'historique (ordre de grandeur). */
    public const FALLBACK_SECONDS_PER_TRIAL = 180.0;

    /** Seuils d’adaptation automatique yearly/monthly (alignés Python). */
    public const YEARLY_MIN_HISTORY_DAYS = 365;
    public const MONTHLY_MIN_HISTORY_DAYS = 90;

    /**
     * Flags toujours imposés au apply (yearly/monthly viennent du brouillon Optuna).
     * Aligné sur FIXED_PROPHET_FLAGS Python (+ seasonality_flags_for_history).
     */
    public const FIXED_PROPHET_FLAGS = [
        'seasonality_mode' => 'multiplicative',
        'weekly_seasonality' => true,
        'daily_seasonality' => true,
        'growth' => 'linear',
        'changepoint_range' => 0.8,
        'n_changepoints' => 25,
        'monthly_seasonality' => false,
    ];

    public const CPS_MIN = 0.005;
    public const CPS_MAX = 0.10;
    public const SPS_MIN = 1.0;
    public const SPS_MAX = 50.0;

    /**
     * Texte UI : règles figées + adaptation historique.
     *
     * @param array<string, mixed>|null $optuna Config fusionnée ; si null, lit les paramètres WFM.
     */
    public static function fixedRulesHelpHtml(?array $optuna = null): string
    {
        $settings = $optuna !== null ? self::merge($optuna) : self::currentOptunaSettings();
        $y = self::YEARLY_MIN_HISTORY_DAYS;
        $lags = self::normalizeCutoffLags($settings['cutoff_lags_days'] ?? null);
        $horizon = (int)$settings['test_horizon_days'];
        $horizonLabel = $horizon > 1 ? 'jours' : 'jour';
        $lagLabel = implode(' / ', $lags);

        return '<div class="crud-help">'
            . '<h3 class="crud-subsection-title">Indicateurs</h3>'
            . '<ul>'
            . '<li><strong>WAPE</strong> (objectif) : écart relatif moyen entre prévision et réel, au grain journalier</li>'
            . '<li><strong>MAE</strong> : écart moyen en volume</li>'
            . '<li><strong>MAPE</strong> : écart moyen en pourcentage, point par point</li>'
            . '</ul>'
            . '<h3 class="crud-subsection-title">Déroulement</h3>'
            . '<ul>'
            . '<li>' . count($lags) . " fenêtres de test de {$horizon} {$horizonLabel}, "
            . "reculs {$lagLabel} jours depuis la fin d’historique</li>"
            . '<li>Premier essai = profil actuel ramené dans les bornes, réévalué sur ces fenêtres</li>'
            . '<li>Le score est celui du modèle brut. L’ancrage de tendance ne s’applique que si l’horizon demandé dépasse 90 jours, donc pas sur ces fenêtres.</li>'
            . '<li>Deux paramètres testés : sensibilité aux ruptures de tendance et force de la saisonnalité. '
            . 'Le nombre de ruptures est figé à 25. Le cycle mensuel Fourier n’est plus cherché.</li>'
            . '</ul>'
            . '<h3 class="crud-subsection-title">Saisonnalités pendant la recherche</h3>'
            . '<ul>'
            . '<li>Mode multiplicatif. Les fériés et les volumes nuls sont retirés de l’entraînement.</li>'
            . '<li>La forme de la semaine est apprise sur l’offre. La forme 15 min est une ventilation, pas une saisonnalité Prophet.</li>'
            . "<li>Annuelle : si au moins {$y} jours d’historique utile</li>"
            . '<li>Pas de Fourier mensuel. Le profil du mois est appris sur l’historique de chaque offre.</li>'
            . '</ul>'
            . '<p>Un profil déjà stocké hors bornes est ramené à l’émission, et le meilleur essai dans les bornes '
            . 'est appliqué même si le gain est inférieur au seuil.</p>'
            . '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private static function currentOptunaSettings(): array
    {
        $wfm = TableRegistry::getTableLocator()->get('WfmSettings')->find()->contain([])->first();

        return self::fromStorage($wfm?->optuna_settings_json);
    }

    /**
     * @param array<string, mixed>|null $raw
     * @return array<string, mixed>
     */
    public static function merge(?array $raw): array
    {
        $merged = self::DEFAULTS;
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if (!array_key_exists($key, $merged)) {
                    continue;
                }
                $merged[$key] = $value;
            }
        }

        $lags = self::normalizeCutoffLags($merged['cutoff_lags_days'] ?? null);
        $merged['cutoff_lags_days'] = $lags;
        $merged['n_cutoffs'] = count($lags);
        $merged['changepoint_prior_scale_min'] = max(self::CPS_MIN, (float)$merged['changepoint_prior_scale_min']);
        $merged['changepoint_prior_scale_max'] = min(self::CPS_MAX, (float)$merged['changepoint_prior_scale_max']);
        if ($merged['changepoint_prior_scale_min'] > $merged['changepoint_prior_scale_max']) {
            $merged['changepoint_prior_scale_min'] = self::CPS_MIN;
            $merged['changepoint_prior_scale_max'] = self::CPS_MAX;
        }
        $merged['seasonality_prior_scale_min'] = max(self::SPS_MIN, (float)$merged['seasonality_prior_scale_min']);
        $merged['seasonality_prior_scale_max'] = min(self::SPS_MAX, (float)$merged['seasonality_prior_scale_max']);
        if ($merged['seasonality_prior_scale_min'] > $merged['seasonality_prior_scale_max']) {
            $merged['seasonality_prior_scale_min'] = self::SPS_MIN;
            $merged['seasonality_prior_scale_max'] = self::SPS_MAX;
        }
        $merged['cron_weekdays'] = self::normalizeWeekdays($merged['cron_weekdays'] ?? [7]);
        $merged['cron_hour'] = max(0, min(23, (int)$merged['cron_hour']));
        $merged['cron_minute'] = max(0, min(59, (int)$merged['cron_minute']));
        $merged['cron_workday_start_hour'] = max(0, min(23, (int)$merged['cron_workday_start_hour']));

        return $merged;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    public static function normalizeWeekdays(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [7];
        }
        $days = [];
        foreach ($raw as $d) {
            $n = (int)$d;
            if ($n >= 1 && $n <= 7) {
                $days[] = $n;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);

        return $days !== [] ? $days : [7];
    }

    /**
     * @param mixed $raw JSON string|array|null
     * @return array<string, mixed>
     */
    public static function fromStorage(mixed $raw): array
    {
        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);
            $decoded = is_array($tmp) ? $tmp : null;
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }

        return self::merge($decoded);
    }

    /**
     * Impose les flags toujours figés ; préserve yearly/monthly du brouillon
     * (déjà adaptés à l’historique par le worker Python).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function applyFixedProphetFlags(array $params): array
    {
        $out = array_merge($params, self::FIXED_PROPHET_FLAGS);
        // Défauts de secours si brouillon incomplet (anciens drafts)
        if (!array_key_exists('yearly_seasonality', $out)) {
            $out['yearly_seasonality'] = true;
        }
        if (!array_key_exists('monthly_seasonality', $out)) {
            $out['monthly_seasonality'] = false;
        }

        return $out;
    }

    /**
     * Ramène un snapshot Prophet dans les bornes d'émission et pose les flags figés.
     * Le nom d'offre n'active plus de règle. Les clés hors profil
     * (dmt_profile, trend_damping) sont conservées.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function clampProphetSettings(array $settings, ?string $offerName = null): array
    {
        $out = self::applyFixedProphetFlags($settings);
        $out['changepoint_prior_scale'] = self::clampPrior(
            $out['changepoint_prior_scale'] ?? 0.05,
            self::CPS_MIN,
            self::CPS_MAX
        );
        $out['seasonality_prior_scale'] = self::clampPrior(
            $out['seasonality_prior_scale'] ?? 10.0,
            self::SPS_MIN,
            self::SPS_MAX
        );
        $out['n_changepoints'] = 25;
        unset($out['use_french_holidays'], $out['custom_holidays']);
        $out['monthly_seasonality'] = false;

        return $out;
    }

    public static function clampPrior(mixed $value, float $low, float $high): float
    {
        if (!is_numeric($value)) {
            $value = $low;
        }

        return (float)min($high, max($low, (float)$value));
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    public static function normalizeCutoffLags(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [90, 180, 270, 360];
        }
        $lags = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $lag = (int)$value;
            if ($lag >= 7 && $lag <= 3650) {
                $lags[] = $lag;
            }
        }
        $lags = array_values(array_unique($lags));
        sort($lags);

        return $lags !== [] ? $lags : [90, 180, 270, 360];
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    public static function decodeJson(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Formate un datetime BDD (UTC) pour l’UI en Europe/Paris.
     */
    public static function formatDateTimeForUi(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $dt = \DateTimeImmutable::createFromInterface($value);
            } else {
                $dt = new \DateTimeImmutable((string)$value, new \DateTimeZone('UTC'));
            }
        } catch (\Exception) {
            return (string)$value;
        }

        return $dt->setTimezone(new \DateTimeZone(self::DISPLAY_TIMEZONE))
            ->format('Y-m-d H:i:s');
    }
}
