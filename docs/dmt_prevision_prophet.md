# DMT dans les prévisions Prophet

Décision retenue : **proposition B** (niveau hebdomadaire figé + forme intra-journalière). Pas de second modèle Prophet sur la DMT.

## Constantes

| Paramètre | Valeur |
|-----------|--------|
| Fenêtre de forme | 12 mois avant `history_end` |
| Niveau | DMT pondérée par le volume des 4 dernières semaines ISO **complètes** `<= history_end` |
| Seuil de volume (forme 15 min) | 5 appels |
| Cascade | 15 min → 30 min → 60 min → coefficient 1 |
| Clip amont (historique) | P5/P95 de l’offre (`volume > 0`), puis 90–1200 s |
| Clip à l’émission (`ForecastPoint.dmt`) | **90–1200 s uniquement** |
| Lissage | 25 % / 50 % / 25 % (bords : 2 points) |
| Renormalisation | `k = Σ(vol × DMT_brute) / Σ(vol × DMT_lissée)` sur **toute** la fenêtre 12 mois |

## Formule par créneau prévu

```text
DMT = clip_90_1200(niveau × coefficient[jour_ISO][HH:MM])
```

Jour ISO : `"1"` = lundi. Créneau absent → coefficient `1`.

## JSON figé sur le scénario

Stocké dans `forecast_scenarios_offers.prophet_settings_json` → `dmt_profile` :

```json
{
  "level_seconds": 360,
  "coefficients": {
    "1": { "08:15": 1.25, "08:30": 1.10 }
  }
}
```

Calculé **une fois** en Python sur le DataFrame brut 15 min (`ds`, `y`, `dmt`), **avant** l’entraînement Prophet. Le solveur de planning lit le need déjà écrit dans `scenario_series` ; il ne recalcule pas 12 mois d’historique.

Les clés jour sont des **chaînes** (`"1"`…`"7"`) pour un JSON objet (accès O(1)), pas un tableau.

## Fichiers

- [`solver-python/dmt_profile.py`](../solver-python/dmt_profile.py)
- [`solver-python/forecast_prophet.py`](../solver-python/forecast_prophet.py)
- [`src/Service/ProphetForecastHelper.php`](../src/Service/ProphetForecastHelper.php)
- [`src/Service/WfmScenarioService.php`](../src/Service/WfmScenarioService.php)

La méthode **moyenne historique** (hors Prophet) est inchangée.
