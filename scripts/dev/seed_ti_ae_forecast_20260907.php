<?php
declare(strict_types=1);

/**
 * Injecte forecast (+ need) TI-AE pour 2026-09-07 = somme TI + AE (scénario 26).
 * Rend TI-AE forecastable et assure le lien scénario.
 *
 *   php scripts/dev/seed_ti_ae_forecast_20260907.php
 */

use Cake\ORM\TableRegistry;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/config/bootstrap.php';

const SCENARIO_ID = 26;
const DATE_STR = '2026-09-07';

$Offers = TableRegistry::getTableLocator()->get('Offers');
$Series = TableRegistry::getTableLocator()->get('ScenarioSeries');
$Links = TableRegistry::getTableLocator()->get('ForecastScenariosOffers');

$byName = [];
foreach ($Offers->find()->where(['name IN' => ['TI', 'AE', 'TI-AE']]) as $o) {
    $byName[$o->name] = $o;
}

$tiAe = $byName['TI-AE'];
$tiAe->is_forecastable = true;
$Offers->saveOrFail($tiAe);
echo "[OK] TI-AE is_forecastable=1 (id={$tiAe->id})\n";

$link = $Links->find()->where([
    'scenario_id' => SCENARIO_ID,
    'offer_id' => (int)$tiAe->id,
])->first();
if (!$link) {
    $link = $Links->newEntity([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$tiAe->id,
        'forecast_method' => 'prophet',
    ]);
    $Links->saveOrFail($link);
    echo "[OK] Lien scénario créé id={$link->id}\n";
} else {
    echo "[..] Lien scénario déjà présent id={$link->id}\n";
}

/**
 * @return array{0: array<string,mixed>, 1: \App\Model\Entity\ScenarioSeries}
 */
function loadSeries($Series, int $offerId, string $type): array
{
    $row = $Series->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => $offerId,
        'date' => DATE_STR,
        'type' => $type,
    ])->first();
    if (!$row) {
        throw new RuntimeException("Série manquante offer={$offerId} type={$type} date=" . DATE_STR);
    }
    $data = json_decode((string)$row->data_json, true);
    if (!is_array($data)) {
        throw new RuntimeException("data_json invalide offer={$offerId} type={$type}");
    }

    return [$data, $row];
}

[$tiForecast, $tiForecastRow] = loadSeries($Series, (int)$byName['TI']->id, 'forecast');
[$aeForecast, $aeForecastRow] = loadSeries($Series, (int)$byName['AE']->id, 'forecast');
[$tiNeed, $tiNeedRow] = loadSeries($Series, (int)$byName['TI']->id, 'need');
[$aeNeed, $aeNeedRow] = loadSeries($Series, (int)$byName['AE']->id, 'need');

// Forecast : somme des volumes, DMT pondéré par volume
$mixedForecast = [];
$keys = array_unique(array_merge(array_keys($tiForecast), array_keys($aeForecast)));
sort($keys);
foreach ($keys as $tk) {
    $tiSlot = $tiForecast[$tk] ?? null;
    $aeSlot = $aeForecast[$tk] ?? null;
    $tiVol = is_array($tiSlot) ? (int)($tiSlot['volume'] ?? 0) : (int)$tiSlot;
    $aeVol = is_array($aeSlot) ? (int)($aeSlot['volume'] ?? 0) : (int)$aeSlot;
    $tiDmt = is_array($tiSlot) ? (float)($tiSlot['dmt'] ?? 0) : 0.0;
    $aeDmt = is_array($aeSlot) ? (float)($aeSlot['dmt'] ?? 0) : 0.0;
    $vol = $tiVol + $aeVol;
    if ($vol > 0) {
        $dmt = (int)round(($tiVol * $tiDmt + $aeVol * $aeDmt) / $vol);
    } else {
        $dmt = (int)round((($tiDmt ?: $aeDmt) + ($aeDmt ?: $tiDmt)) / 2);
    }
    $mixedForecast[$tk] = ['volume' => $vol, 'dmt' => $dmt];
}

// Need : somme des besoins agents
$mixedNeed = [];
$needKeys = array_unique(array_merge(array_keys($tiNeed), array_keys($aeNeed)));
sort($needKeys);
foreach ($needKeys as $tk) {
    $mixedNeed[$tk] = (int)($tiNeed[$tk] ?? 0) + (int)($aeNeed[$tk] ?? 0);
}

function upsertSeries($Series, int $offerId, string $type, array $data, $template): void
{
    $row = $Series->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => $offerId,
        'date' => DATE_STR,
        'type' => $type,
    ])->first();
    if ($row) {
        $row->data_json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $Series->saveOrFail($row);
        echo "[OK] Série {$type} TI-AE mise à jour id={$row->id}\n";
    } else {
        $entity = $Series->newEntity([
            'scenario_id' => SCENARIO_ID,
            'offer_id' => $offerId,
            'date' => DATE_STR,
            'type' => $type,
            'step_seconds' => (int)$template->step_seconds,
            'start_time' => $template->start_time,
            'end_time' => $template->end_time,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        $Series->saveOrFail($entity);
        echo "[OK] Série {$type} TI-AE créée id={$entity->id}\n";
    }
}

upsertSeries($Series, (int)$tiAe->id, 'forecast', $mixedForecast, $tiForecastRow);
upsertSeries($Series, (int)$tiAe->id, 'need', $mixedNeed, $tiNeedRow);

$volSum = array_sum(array_column($mixedForecast, 'volume'));
$needSum = array_sum($mixedNeed);
echo "[..] forecast volume total={$volSum} | need total={$needSum}\n";
echo "[..] sample 09:00 forecast=" . json_encode($mixedForecast['09:00:00'] ?? null)
    . ' need=' . ($mixedNeed['09:00:00'] ?? 'n/a') . "\n";
echo "Done.\n";
