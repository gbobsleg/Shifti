# Shifti — Contrats solveur (PHP ↔ Python)

Synthèse de [`solver-python/README_SERVICES.md`](../solver-python/README_SERVICES.md). Garder les payloads JSON rétrocompatibles sauf décision d’architecture qui versionne explicitement un changement.

**Langue :** cette doc est en français. Les chemins d’API et clés JSON restent en anglais comme dans le code.

## Processus

| Service | Entrée | Port / mode |
|---------|--------|-------------|
| Solveurs OR-Tools | `solver-python/main.py` | **8000** |
| Prévision Prophet | `solver-python/forecast_prophet.py` | **8001** |
| Tuning Optuna Prophet | `solver-python/prophet_tuning_worker.py` | Worker (pas d’HTTP) |

Docker : programmes Supervisord `python-solver`, `python-prophet`, `python-prophet-tuning` dans le conteneur `app`.

Clé de config CakePHP : `PythonSolver.url` (défaut `http://127.0.0.1:8000`).

## Routes OR-Tools (`:8000`)

| Méthode | Chemin | Rôle | Appelants PHP typiques |
|---------|--------|------|-------------------------|
| `GET` | `/health` | Vivacité | Health checks |
| `POST` | `/api/v1/solve-fixed-activities` | Passe 1 — activités fixes | `ScheduleDayGenerationService`, `SchedulesController` |
| `POST` | `/api/v1/solve-schedule` | Passe 2 — couverture / forecastables | Idem ; clé optionnelle `offer_groups` (legacy bit-identique si absente) |
| `POST` | `/api/v1/solve-rotation` | Rotations | `ScheduleDayGenerationService` |

### Groupes d’offres (passe 2)

Le payload peut inclure `offer_groups` (voir [feature_groupes_offres.md](feature_groupes_offres.md)). Côté Python : `OfferGroupSpec` / branche couverture dans `solver_coverage.py`. Sans la clé → chemin legacy.

## Prophet (`:8001`)

Service HTTP de prévision utilisé par les flux Cake (`ForecastService` et scénarios WFM associés). Env : `DB_*` alignés sur MariaDB du Compose.

## Worker Optuna

- Consomme `prophet_tuning_jobs` (claim atomique, backtest walk-forward, Optuna, brouillon / auto-apply).
- Flags de saisonnalité V1 : weekly+daily ON ; yearly OFF si historique utile &lt; 365 j ; monthly OFF si &lt; 90 j ; multiplicatif + jours fériés FR forcés — écrits dans le brouillon, ce ne sont pas des hyperparamètres Optuna.
- Tickers Cake : `bin/cake prophet_tuning_scheduler_ticker` (boucle minute) ; one-shot debug `bin/cake prophet_tuning_scheduler`.
- Docker : `cake-prophet-tuning-ticker` + `python-prophet-tuning`.
- Windows : `scripts/dev/start_prophet_tuning_*.bat`, inclus dans `start_all_services.bat`.

## Workers Cake en arrière-plan

| Worker | Rôle |
|--------|------|
| `cake-worker` | Jobs de génération de planning |
| `cake-forecast-worker` | Jobs liés à la prévision |
| Tickers Prophet | Enqueue des jobs Optuna selon la config cron WFM |

## Règles de contrat pour les agents

1. Ne pas renommer / supprimer des champs request/response sans spec d’architecture approuvée et changements PHP + Python coordonnés.
2. Préférer des clés optionnelles additives (ex. `offer_groups`) aux ruptures.
3. Documenter tout nouvel endpoint ou champ ici et dans `solver-python/README_SERVICES.md` (en français pour la prose).
4. `python-solver` possède l’implémentation Python ; `php-backend` possède les appels HTTP et constructeurs de payload.
