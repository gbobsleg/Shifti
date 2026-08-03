# Services Python Shifti

Processus FastAPI et workers :

| Service | Entrée | Port / mode |
|---------|--------|-------------|
| Solveurs OR-Tools | `main.py` | 8000 |
| Prévisions Prophet | `forecast_prophet.py` | 8001 |
| Tuning Optuna Prophet | `prophet_tuning_worker.py` | worker (pas de HTTP) |

## Routes solveur (`main.py`)

- `GET /health`
- `POST /api/v1/solve-fixed-activities` — activités fixes (ciblage global)
- `POST /api/v1/solve-schedule` — couverture / forecastables
- `POST /api/v1/solve-rotation` — rotations

## Lancement local

```bash
cd solver-python
python -m venv .venv
# Windows: .venv\Scripts\activate
pip install -r requirements.txt

uvicorn main:app --reload --port 8000
python forecast_prophet.py
python prophet_tuning_worker.py
```

Sous Docker, ces processus sont gérés par Supervisord dans le conteneur `app`
(`python-solver`, `python-prophet`, `python-prophet-tuning`).

## Worker Optuna (`prophet_tuning_worker.py`)

Boucle continue qui consomme la table `prophet_tuning_jobs` (claim atomique,
backtest walk-forward, optimisation Optuna, écriture brouillon / auto-apply).

**Saisonnalités (V1) :** weekly + daily toujours ON ; yearly OFF si historique
utile &lt; 365 j ; monthly OFF si &lt; 90 j. Mode multiplicatif + jours fériés FR
toujours forcés. Ces flags adaptés sont écrits dans le brouillon (et préservés
au apply) — ce ne sont pas des hyperparamètres Optuna.

- Lancement Windows : `scripts/dev/start_prophet_tuning_worker.bat`
- Inclus dans `scripts/dev/start_all_services.bat`
- Docker : programme Supervisor `python-prophet-tuning`

## Ticker cron Optuna (CakePHP)

Boucle minute qui lit la config WFM (`cron_enabled`, jours, heure Europe/Paris)
et enqueue les offres éligibles — **plus besoin de crontab OS** pour le planning.

```bash
bin/cake prophet_tuning_scheduler_ticker
```

- Windows : `scripts/dev/start_prophet_tuning_scheduler_ticker.bat`
- Docker : `cake-prophet-tuning-ticker`
- Debug one-shot (ignore l’heure) : `bin/cake prophet_tuning_scheduler`

L’UI WFM affiche une **estimation de durée** de vague (offres × trials) et une
alerte si la fin estimée chevauche une journée ouvrée.

## Scripts Windows

Tous les lanceurs sont dans `../scripts/dev/` :

- `start_all_services.bat` / `stop_all_services.bat` — orchestrateur (Python + workers)
- `start_solver_service.bat` / `start_forecast_service.bat` — services FastAPI seuls
- `start_prophet_tuning_worker.bat` — worker Optuna
- `start_*_worker*.bat` — workers CakePHP

## Variables d’environnement (Prophet / Optuna)

`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` — en Docker, alignés sur MariaDB du compose.
