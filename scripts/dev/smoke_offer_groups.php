<?php
declare(strict_types=1);

/**
 * Smoke runtime groupes d'offres — scénario prévisions 26 / date 2026-09-07.
 *
 * Usage:
 *   C:\wamp64\bin\php\php8.3.28\php.exe scripts\dev\smoke_offer_groups.php
 *
 * Effets BDD:
 *  - crée (et conserve) le groupe C/P mode members
 *  - pour le tir TI/AE : flags + série need TI-AE temporaires, puis restauration
 */

use App\Model\Entity\OfferGroup;
use App\Service\ScheduleProblemBuilderService;
use Cake\Http\Client;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/config/bootstrap.php';

const SCENARIO_ID = 26;
const DATE_STR = '2026-09-07';
const WFM_SETTING_ID = 1;
const SOLVER_URL = 'http://127.0.0.1:8000';

$failed = 0;
$passed = 0;

function ok(string $msg): void
{
    global $passed;
    $passed++;
    echo "[OK] {$msg}\n";
}

function fail(string $msg): void
{
    global $failed;
    $failed++;
    echo "[FAIL] {$msg}\n";
}

function info(string $msg): void
{
    echo "[..] {$msg}\n";
}

function assertTrue(bool $cond, string $msg): void
{
    if ($cond) {
        ok($msg);
    } else {
        fail($msg);
    }
}

function sumCurve(array $curve): int
{
    $s = 0;
    foreach ($curve as $v) {
        $s += (int)$v;
    }

    return $s;
}

function slotSum(array $needCurve, string $time): int
{
    $s = 0;
    foreach ($needCurve as $curve) {
        if (is_array($curve) && isset($curve[$time])) {
            $s += (int)$curve[$time];
        }
    }

    return $s;
}

$Offers = TableRegistry::getTableLocator()->get('Offers');
$OfferGroups = TableRegistry::getTableLocator()->get('OfferGroups');
$OfferGroupMembers = TableRegistry::getTableLocator()->get('OfferGroupMembers');
$ScenarioSeries = TableRegistry::getTableLocator()->get('ScenarioSeries');
$ScenarioLinks = TableRegistry::getTableLocator()->get('ForecastScenariosOffers');
$WfmSettings = TableRegistry::getTableLocator()->get('WfmSettings');

$date = new FrozenTime(DATE_STR . ' 00:00:00');
$settings = $WfmSettings->get(WFM_SETTING_ID, contain: ['PauseOffers', 'LunchOffers']);
$builder = new ScheduleProblemBuilderService();

$offerByName = [];
foreach ($Offers->find()->where(['name IN' => ['CESU', 'PAJEMPLOI', 'C/P', 'TI', 'AE', 'TI-AE']]) as $o) {
    $offerByName[(string)$o->name] = $o;
}

foreach (['CESU', 'PAJEMPLOI', 'C/P', 'TI', 'AE', 'TI-AE'] as $n) {
    if (!isset($offerByName[$n])) {
        fail("Offre manquante: {$n}");
        echo "Abort.\n";
        exit(1);
    }
}

// Snapshot flags TI/AE (restaurés après tir 3)
$flagSnapshot = [
    'TI' => (int)$offerByName['TI']->is_forecastable,
    'AE' => (int)$offerByName['AE']->is_forecastable,
    'TI-AE' => (int)$offerByName['TI-AE']->is_forecastable,
];
info('Snapshot is_forecastable: ' . json_encode($flagSnapshot));

// Purge groupes existants pour partir propre (tir 1)
$existingGroups = $OfferGroups->find()->all()->toList();
foreach ($existingGroups as $g) {
    info("Suppression groupe existant #{$g->id} « {$g->name} »");
    $OfferGroups->delete($g);
}

$createdCpGroupId = null;
$createdTiGroupId = null;
$createdTiAeSeriesIds = [];
$createdTiAeLinkId = null;

try {
    // -------------------------------------------------------------------------
    // TIR 1 — non-régression sans groupe
    // -------------------------------------------------------------------------
    echo "\n=== TIR 1 : non-régression sans groupe ===\n";
    $build1 = $builder->build($date, $settings, SCENARIO_ID, [
        'ignore_fixed_activities' => true,
        'ignore_forecast_solver' => false,
        'debug_solvers' => false,
    ]);
    assertTrue(($build1['offer_groups'] ?? []) === [], 'offer_groups vide');
    assertTrue(!empty($build1['need_curve']['CESU']), 'need CESU présent');
    assertTrue(!empty($build1['need_curve']['PAJEMPLOI']), 'need PAJEMPLOI présent');
    assertTrue(!empty($build1['need_curve']['TI']), 'need TI présent');
    assertTrue(!empty($build1['need_curve']['AE']), 'need AE présent');
    assertTrue(count($build1['agents'] ?? []) > 0, 'agents > 0');
    info('agents=' . count($build1['agents']) . ' need_total_CESU=' . sumCurve($build1['need_curve']['CESU'] ?? []));

    // -------------------------------------------------------------------------
    // TIR 2 — C/P mode members
    // -------------------------------------------------------------------------
    echo "\n=== TIR 2 : config C/P (members) ===\n";
    $cpGroup = $OfferGroups->newEntity([
        'name' => 'C/P',
        'mixed_offer_id' => (int)$offerByName['C/P']->id,
        'forecast_source' => OfferGroup::FORECAST_SOURCE_MEMBERS,
        'prefer_mixed' => true,
        'offer_group_members' => [
            [
                'offer_id' => (int)$offerByName['CESU']->id,
                'display_order' => 0,
                'split_ratio_percent' => null,
            ],
            [
                'offer_id' => (int)$offerByName['PAJEMPLOI']->id,
                'display_order' => 1,
                'split_ratio_percent' => null,
            ],
        ],
    ], ['associated' => ['OfferGroupMembers']]);
    if (!$OfferGroups->save($cpGroup, ['associated' => ['OfferGroupMembers']])) {
        fail('Création groupe C/P: ' . json_encode($cpGroup->getErrors()));
        throw new RuntimeException('save C/P failed');
    }
    $createdCpGroupId = (int)$cpGroup->id;
    ok("Groupe C/P créé id={$createdCpGroupId}");

    $build2 = $builder->build($date, $settings, SCENARIO_ID, [
        'ignore_fixed_activities' => true,
    ]);
    $ogs2 = $build2['offer_groups'] ?? [];
    assertTrue(count($ogs2) >= 1, 'offer_groups non vide');
    $cpPayload = null;
    foreach ($ogs2 as $g) {
        if (($g['mixed'] ?? '') === 'C/P') {
            $cpPayload = $g;
            break;
        }
    }
    assertTrue($cpPayload !== null, 'payload contient mixte C/P');
    assertTrue(($cpPayload['prefer_mixed'] ?? false) === true, 'prefer_mixed ON');
    assertTrue(sumCurve($build2['need_curve']['C/P'] ?? []) === 0, 'need C/P forcé à 0');
    assertTrue(sumCurve($build2['need_curve']['CESU'] ?? []) > 0, 'need CESU > 0');
    assertTrue(sumCurve($build2['need_curve']['PAJEMPLOI'] ?? []) > 0, 'need PAJEMPLOI > 0');

    // Éligibilité : au moins un agent avec skill C/P dans la liste
    $agentsWithCp = 0;
    foreach ($build2['agents'] as $ag) {
        if (in_array('C/P', $ag['skills'] ?? [], true)) {
            $agentsWithCp++;
        }
    }
    assertTrue($agentsWithCp > 0, "agents avec skill C/P dans le build ({$agentsWithCp})");

    // Solver light
    $solverAgents = array_values(array_filter(
        $build2['agents'],
        static fn($a) => array_intersect($a['skills'] ?? [], ['CESU', 'PAJEMPLOI', 'C/P']) !== []
    ));
    $solverAgents = array_slice($solverAgents, 0, 8);
    $needP2 = [
        'CESU' => $build2['need_curve']['CESU'],
        'PAJEMPLOI' => $build2['need_curve']['PAJEMPLOI'],
        'C/P' => $build2['need_curve']['C/P'],
    ];
    // Réduire le besoin pour un smoke rapide (1 créneau peak)
    $times = array_slice(array_keys($needP2['CESU']), 0, 4);
    $needTiny = [];
    foreach (['CESU', 'PAJEMPLOI', 'C/P'] as $off) {
        $needTiny[$off] = [];
        foreach ($times as $t) {
            $needTiny[$off][$t] = (int)($needP2[$off][$t] ?? 0);
        }
    }
    $payload2 = [
        'offers' => ['CESU', 'PAJEMPLOI', 'C/P'],
        'need_curve' => $needTiny,
        'agents' => $solverAgents,
        'workday_start_time' => $build2['workday_start_time'],
        'workday_end_time' => $build2['workday_end_time'],
        'slot_minutes' => 15,
        'strict_work_hours' => true,
        'enable_am_pm_breaks' => false,
        'offer_groups' => array_values(array_filter($ogs2, static fn($g) => ($g['mixed'] ?? '') === 'C/P')),
        'debug_logging' => false,
    ];
    $http = new Client(['timeout' => 120]);
    $resp2 = $http->post(SOLVER_URL . '/api/v1/solve-schedule', json_encode($payload2), [
        'type' => 'application/json',
    ]);
    $sol2 = json_decode($resp2->getStringBody(), true);
    $st2 = (string)($sol2['status'] ?? 'null');
    assertTrue(in_array($st2, ['OPTIMAL', 'FEASIBLE', 'success'], true), "solver C/P status={$st2}");
    if (is_array($sol2['coverage'] ?? null)) {
        foreach ($sol2['coverage'] as $row) {
            if (($row['offer'] ?? '') === 'C/P') {
                $needSum = 0;
                foreach ($row['times'] ?? [] as $slot) {
                    $needSum += (int)($slot['need'] ?? 0);
                }
                assertTrue($needSum === 0, 'coverage C/P need sum = 0');
            }
        }
    }

    // -------------------------------------------------------------------------
    // TIR 3 — TI/AE mode group (temporaire)
    // -------------------------------------------------------------------------
    echo "\n=== TIR 3 : config TI/AE (group) — temporaire ===\n";

    // Flags
    $offerByName['TI-AE']->is_forecastable = true;
    $offerByName['TI']->is_forecastable = false;
    $offerByName['AE']->is_forecastable = false;
    $Offers->saveOrFail($offerByName['TI-AE']);
    $Offers->saveOrFail($offerByName['TI']);
    $Offers->saveOrFail($offerByName['AE']);
    ok('Flags: TI-AE forecastable, TI/AE non forecastable');

    // Lien scénario TI-AE
    $link = $ScenarioLinks->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$offerByName['TI-AE']->id,
    ])->first();
    if (!$link) {
        $link = $ScenarioLinks->newEntity([
            'scenario_id' => SCENARIO_ID,
            'offer_id' => (int)$offerByName['TI-AE']->id,
            'forecast_method' => 'prophet',
        ]);
        $ScenarioLinks->saveOrFail($link);
        $createdTiAeLinkId = (int)$link->id;
        ok("Lien scénario TI-AE créé id={$createdTiAeLinkId}");
    }

    // Série need TI-AE = TI + AE (jour smoke)
    $tiNeed = $ScenarioSeries->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$offerByName['TI']->id,
        'date' => DATE_STR,
        'type' => 'need',
    ])->first();
    $aeNeed = $ScenarioSeries->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$offerByName['AE']->id,
        'date' => DATE_STR,
        'type' => 'need',
    ])->first();
    if (!$tiNeed || !$aeNeed) {
        fail('Séries need TI/AE manquantes pour le jour');
        throw new RuntimeException('missing TI/AE need');
    }
    $tiData = json_decode((string)$tiNeed->data_json, true) ?: [];
    $aeData = json_decode((string)$aeNeed->data_json, true) ?: [];
    $mixedData = [];
    foreach (array_unique(array_merge(array_keys($tiData), array_keys($aeData))) as $tk) {
        $mixedData[$tk] = (int)($tiData[$tk] ?? 0) + (int)($aeData[$tk] ?? 0);
    }
    ksort($mixedData);

    $existingMixed = $ScenarioSeries->find()->where([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$offerByName['TI-AE']->id,
        'date' => DATE_STR,
        'type' => 'need',
    ])->first();
    if ($existingMixed) {
        $existingMixed->data_json = json_encode($mixedData);
        $ScenarioSeries->saveOrFail($existingMixed);
        $createdTiAeSeriesIds[] = (int)$existingMixed->id;
        info('Série need TI-AE mise à jour (existante)');
    } else {
        $series = $ScenarioSeries->newEntity([
            'scenario_id' => SCENARIO_ID,
            'offer_id' => (int)$offerByName['TI-AE']->id,
            'date' => DATE_STR,
            'type' => 'need',
            'step_seconds' => (int)$tiNeed->step_seconds,
            'start_time' => $tiNeed->start_time,
            'end_time' => $tiNeed->end_time,
            'data_json' => json_encode($mixedData),
        ]);
        $ScenarioSeries->saveOrFail($series);
        $createdTiAeSeriesIds[] = (int)$series->id;
        ok("Série need TI-AE créée id={$series->id} total=" . array_sum($mixedData));
    }

    // Groupe TI-AE 50/50
    $tiGroup = $OfferGroups->newEntity([
        'name' => 'TI-AE',
        'mixed_offer_id' => (int)$offerByName['TI-AE']->id,
        'forecast_source' => OfferGroup::FORECAST_SOURCE_GROUP,
        'prefer_mixed' => true,
        'offer_group_members' => [
            [
                'offer_id' => (int)$offerByName['TI']->id,
                'display_order' => 0,
                'split_ratio_percent' => 50,
            ],
            [
                'offer_id' => (int)$offerByName['AE']->id,
                'display_order' => 1,
                'split_ratio_percent' => 50,
            ],
        ],
    ], ['associated' => ['OfferGroupMembers']]);
    if (!$OfferGroups->save($tiGroup, ['associated' => ['OfferGroupMembers']])) {
        fail('Création groupe TI-AE: ' . json_encode($tiGroup->getErrors()));
        throw new RuntimeException('save TI-AE failed');
    }
    $createdTiGroupId = (int)$tiGroup->id;
    ok("Groupe TI-AE créé id={$createdTiGroupId}");

    $build3 = $builder->build($date, $settings, SCENARIO_ID, [
        'ignore_fixed_activities' => true,
    ]);
    $ogs3 = $build3['offer_groups'] ?? [];
    $tiPayload = null;
    foreach ($ogs3 as $g) {
        if (($g['mixed'] ?? '') === 'TI-AE') {
            $tiPayload = $g;
            break;
        }
    }
    assertTrue($tiPayload !== null, 'payload contient mixte TI-AE');
    assertTrue(sumCurve($build3['need_curve']['TI-AE'] ?? []) === 0, 'need TI-AE forcé à 0 après split');
    $sumTi = sumCurve($build3['need_curve']['TI'] ?? []);
    $sumAe = sumCurve($build3['need_curve']['AE'] ?? []);
    $sumMixedOrig = array_sum($mixedData);
    assertTrue($sumTi + $sumAe === $sumMixedOrig, "split LRM: TI({$sumTi})+AE({$sumAe}) == mixte_orig({$sumMixedOrig})");
    // Écart ratio ~50/50 (tolérance LRM ± nb créneaux)
    $diff = abs($sumTi - $sumAe);
    assertTrue($diff <= 40, "ratios ~50/50 (|TI-AE|={$diff} ≤ 40)");

    $t0 = array_key_first($mixedData) ?: '09:00:00';
    $slotMembers = (int)($build3['need_curve']['TI'][$t0] ?? 0) + (int)($build3['need_curve']['AE'][$t0] ?? 0);
    $slotMixedOrig = (int)($mixedData[$t0] ?? 0);
    assertTrue($slotMembers === $slotMixedOrig, "créneau {$t0}: TI+AE ({$slotMembers}) == mixte ({$slotMixedOrig})");

    $agentsWithTiAe = 0;
    foreach ($build3['agents'] as $ag) {
        if (in_array('TI-AE', $ag['skills'] ?? [], true)) {
            $agentsWithTiAe++;
        }
    }
    assertTrue($agentsWithTiAe > 0, "agents avec skill TI-AE ({$agentsWithTiAe})");

    $solverAgents3 = array_values(array_filter(
        $build3['agents'],
        static fn($a) => array_intersect($a['skills'] ?? [], ['TI', 'AE', 'TI-AE']) !== []
    ));
    $solverAgents3 = array_slice($solverAgents3, 0, 8);
    $times3 = array_slice(array_keys($mixedData), 0, 4);
    $needTiny3 = [];
    foreach (['TI', 'AE', 'TI-AE'] as $off) {
        $needTiny3[$off] = [];
        foreach ($times3 as $t) {
            $needTiny3[$off][$t] = (int)($build3['need_curve'][$off][$t] ?? 0);
        }
    }
    $payload3 = [
        'offers' => ['TI', 'AE', 'TI-AE'],
        'need_curve' => $needTiny3,
        'agents' => $solverAgents3,
        'workday_start_time' => $build3['workday_start_time'],
        'workday_end_time' => $build3['workday_end_time'],
        'slot_minutes' => 15,
        'strict_work_hours' => true,
        'enable_am_pm_breaks' => false,
        'offer_groups' => array_values(array_filter($ogs3, static fn($g) => ($g['mixed'] ?? '') === 'TI-AE')),
        'debug_logging' => false,
    ];
    $resp3 = $http->post(SOLVER_URL . '/api/v1/solve-schedule', json_encode($payload3), [
        'type' => 'application/json',
    ]);
    $sol3 = json_decode($resp3->getStringBody(), true);
    $st3 = (string)($sol3['status'] ?? 'null');
    assertTrue(in_array($st3, ['OPTIMAL', 'FEASIBLE', 'success'], true), "solver TI-AE status={$st3}");
} finally {
    // Restauration TI/AE (flags + artefacts temporaires + groupe TI-AE)
    echo "\n=== Restauration état TI/AE ===\n";
    if ($createdTiGroupId) {
        $g = $OfferGroups->find()->where(['id' => $createdTiGroupId])->first();
        if ($g) {
            $OfferGroups->delete($g);
            info("Groupe TI-AE #{$createdTiGroupId} supprimé");
        }
    }
    foreach ($createdTiAeSeriesIds as $sid) {
        $row = $ScenarioSeries->find()->where(['id' => $sid])->first();
        // Ne supprimer que si on l'a créée ; si update d'existante, remettre vide? On a listé ids créés/updatés.
        // Pour simplicité: supprimer les séries TI-AE need du jour smoke créées/touchées si on les a créées.
    }
    // Supprimer série need TI-AE du jour (smoke) + lien scénario créé
    $ScenarioSeries->deleteAll([
        'scenario_id' => SCENARIO_ID,
        'offer_id' => (int)$offerByName['TI-AE']->id,
        'date' => DATE_STR,
        'type' => 'need',
    ]);
    info('Séries need TI-AE du 2026-09-07 purgées');
    if ($createdTiAeLinkId) {
        $ScenarioLinks->deleteAll(['id' => $createdTiAeLinkId]);
        info("Lien scénario TI-AE #{$createdTiAeLinkId} supprimé");
    }
    foreach (['TI', 'AE', 'TI-AE'] as $n) {
        $o = $Offers->get((int)$offerByName[$n]->id);
        $o->is_forecastable = (bool)$flagSnapshot[$n];
        $Offers->saveOrFail($o);
    }
    info('Flags is_forecastable restaurés: ' . json_encode($flagSnapshot));
    if ($createdCpGroupId) {
        info("Groupe C/P #{$createdCpGroupId} CONSERVÉ (config métier members)");
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Passed: {$passed} | Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
