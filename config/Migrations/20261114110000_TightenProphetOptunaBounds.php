<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class TightenProphetOptunaBounds extends BaseMigration
{
    /**
     * Réécrit le JSON Optuna déjà stocké : l'ancien merge gardait
     * sps_min = 0,01, 50 essais, seuil 1 % et un cron quotidien.
     */
    public function up(): void
    {
        if (!$this->hasTable('wfm_settings')) {
            return;
        }

        $rows = $this->fetchAll('SELECT id, optuna_settings_json FROM wfm_settings');
        $pdo = $this->getAdapter()->getConnection();
        $stmt = $pdo->prepare('UPDATE wfm_settings SET optuna_settings_json = :json WHERE id = :id');

        foreach ($rows as $row) {
            $raw = $row['optuna_settings_json'] ?? null;
            $data = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $data = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $data = $raw;
            }

            $data['changepoint_prior_scale_min'] = 0.005;
            $data['changepoint_prior_scale_max'] = 0.10;
            $data['seasonality_prior_scale_min'] = 1.0;
            $data['seasonality_prior_scale_max'] = 50.0;
            $data['n_changepoints_min'] = 25;
            $data['n_changepoints_max'] = 25;
            $data['n_trials'] = 30;
            $data['auto_apply_min_mae_improvement_pct'] = 3;
            $data['cron_period_days'] = 7;
            $data['cutoff_lags_days'] = [90, 180, 270, 360];
            $data['n_cutoffs'] = 4;
            $data['test_horizon_days'] = 90;

            $stmt->execute([
                'json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'id' => $row['id'],
            ]);
        }
    }

    public function down(): void
    {
    }
}
