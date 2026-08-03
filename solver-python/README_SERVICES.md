# Services Python Shifti

Deux processus FastAPI :

| Service | Entrée | Port défaut |
|---------|--------|-------------|
| Solveurs OR-Tools | `main.py` | 8000 |
| Prévisions Prophet | `forecast_prophet.py` | 8001 |

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
```

Sous Docker, les deux services sont gérés par Supervisord dans le conteneur `app`.

## Scripts Windows

Tous les lanceurs sont dans `../scripts/dev/` :

- `start_all_services.bat` / `stop_all_services.bat` — orchestrateur (Python + workers CakePHP)
- `start_solver_service.bat` / `start_forecast_service.bat` — services Python seuls
- `start_*_worker*.bat` — workers CakePHP

## Variables d’environnement (Prophet)

`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` — en Docker, alignés sur MariaDB du compose.
