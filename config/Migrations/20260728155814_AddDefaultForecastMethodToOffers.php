<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddDefaultForecastMethodToOffers extends BaseMigration
{
    /**
     * Change Method.
     *
     * Ajoute la méthode de prévision par défaut au niveau offre
     * (pré-sélection UI des scénarios forecast).
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('offers')) {
            return;
        }

        $offers = $this->table('offers');
        if ($offers->hasColumn('default_forecast_method')) {
            return;
        }

        $offers->addColumn('default_forecast_method', 'string', [
            'default' => 'historical',
            'limit' => 20,
            'null' => false,
            'comment' => 'Méthode de prévision par défaut pour les scénarios: historical ou prophet',
            'after' => 'is_forecastable',
        ]);
        $offers->update();
    }
}
