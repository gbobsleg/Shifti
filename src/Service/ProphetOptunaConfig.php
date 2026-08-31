<?php
declare(strict_types=1);

namespace App\Service;

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
        'test_horizon_days' => 14,
        'n_cutoffs' => 3,
        'n_trials' => 50,
        'min_history_days' => 90,
        'auto_apply' => false,
        'auto_apply_min_mae_improvement_pct' => 5,
        'changepoint_prior_scale_min' => 0.001,
        'changepoint_prior_scale_max' => 0.5,
        'seasonality_prior_scale_min' => 0.01,
        'seasonality_prior_scale_max' => 100.0,
        'n_changepoints_min' => 10,
        'n_changepoints_max' => 50,
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
        'use_french_holidays' => true,
    ];

    /**
     * Texte UI : règles figées + adaptation historique.
     */
    public static function fixedRulesHelpHtml(): string
    {
        $y = self::YEARLY_MIN_HISTORY_DAYS;
        $m = self::MONTHLY_MIN_HISTORY_DAYS;

        return '<strong>Règles pendant le tuning (V1) :</strong> '
            . 'Objectif Optuna : minimiser le <strong>WAPE</strong> (15 min, 3 cutoffs × 14 j). '
            . 'mode <em>multiplicatif</em>, jours fériés FR, '
            . 'saisonnalités <strong>hebdomadaire</strong> et <strong>journalière</strong> toujours ON. '
            . "Saisonnalité <strong>annuelle</strong> : ON seulement si historique utile ≥ {$y} j "
            . '(sinon OFF automatiquement). '
            . "Saisonnalité <strong>mensuelle</strong> : ON seulement si historique utile ≥ {$m} j. "
            . 'Les cases du profil Prophet ne sont pas respectées pendant la recherche ; '
            . 'le brouillon appliqué reprend ces flags adaptés. '
            . 'Params cherchés : '
            . '<code>changepoint_prior_scale</code>, '
            . '<code>seasonality_prior_scale</code>, '
            . '<code>n_changepoints</code>, '
            . '<code>monthly_fourier_order</code>. '
            . '1er essai Optuna = profil officiel, réévalué sur les fenêtres de test du job.';
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

        $merged['n_cutoffs'] = 3;
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
            $out['monthly_seasonality'] = true;
        }

        return $out;
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
