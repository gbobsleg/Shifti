@echo off
setlocal
cd /d "%~dp0"

set "SOLVER_DIR=%~dp0..\..\solver-python"
set "PYTHON_EXE=%SOLVER_DIR%\.venv\Scripts\python.exe"

echo ============================================================
echo Shifti - Demarrage de tous les services locaux
echo ============================================================
echo.
echo Environnement : solver-python\.venv (Python 3.13 du projet)
echo Lancement des services :
echo   - OR-Tools Solver (Planning)       : http://localhost:8000
echo   - Prophet Forecast (Previsions)    : http://localhost:8001
echo   - Worker Planning (CakePHP)        : fenetre console dediee
echo   - Worker Forecast (CakePHP)        : fenetre console dediee
echo   - Worker Optuna Tuning (Python)    : fenetre console dediee
echo   - Ticker Optuna Cron (CakePHP)     : fenetre console dediee
echo.
echo Documentation API disponible :
echo   - OR-Tools : http://localhost:8000/docs
echo   - Prophet  : http://localhost:8001/docs
echo.
echo ============================================================
echo.

if not exist "%PYTHON_EXE%" (
    echo [ERREUR] Interpreteur introuvable : %PYTHON_EXE%
    echo.
    echo Recreez le venv du projet, par exemple :
    echo   cd solver-python
    echo   py -3.13 -m venv .venv
    echo   .venv\Scripts\python.exe -m pip install -r requirements.txt
    echo.
    pause
    exit /b 1
)

REM Demarrer le solveur OR-Tools en arriere-plan
echo [1/5] Demarrage du solveur OR-Tools (port 8000)...
start "OR-Tools Solver - Port 8000" cmd /k "cd /d %SOLVER_DIR% && %PYTHON_EXE% -m uvicorn main:app --host 0.0.0.0 --port 8000 --reload --timeout-keep-alive 120 --timeout-graceful-shutdown 120"

REM Attendre 2 secondes
timeout /t 2 /nobreak >nul

REM Demarrer le service Prophet en arriere-plan
echo [2/5] Demarrage du service Prophet (port 8001)...
start "Prophet Forecast - Port 8001" cmd /k "cd /d %SOLVER_DIR% && %PYTHON_EXE% forecast_prophet.py"

REM Attendre 1 seconde
timeout /t 1 /nobreak >nul

REM Demarrer le worker Planning CakePHP (auto-reload)
echo [3/5] Demarrage du worker Planning (CakePHP)...
REM Note: avec cmd.exe, l'echappement \" est interprete litteralement.
REM La forme correcte est: cmd /k ""C:\chemin\script.bat""
start "Planning Worker (CakePHP)" cmd /k ""%~dp0start_planning_generation_worker_autoreload.bat""

REM Attendre 1 seconde
timeout /t 1 /nobreak >nul

REM Demarrer le worker Forecast CakePHP (auto-reload)
echo [4/5] Demarrage du worker Forecast (CakePHP)...
start "Forecast Worker (CakePHP)" cmd /k ""%~dp0start_forecast_scenario_worker_autoreload.bat""

REM Attendre 1 seconde
timeout /t 1 /nobreak >nul

REM Demarrer le worker Optuna Tuning (Python, boucle continue)
echo [5/6] Demarrage du worker Optuna Tuning (Python)...
start "Optuna Tuning Worker (Python)" cmd /k ""%~dp0start_prophet_tuning_worker.bat""

REM Attendre 1 seconde
timeout /t 1 /nobreak >nul

REM Demarrer le ticker cron Optuna (CakePHP)
echo [6/6] Demarrage du ticker Optuna Cron (CakePHP)...
start "Optuna Cron Ticker (CakePHP)" cmd /k ""%~dp0start_prophet_tuning_scheduler_ticker.bat""

echo.
echo ============================================================
echo SERVICES DEMARRES !
echo ============================================================
echo.
echo Six fenetres de console ont ete ouvertes :
echo   1. OR-Tools Solver (port 8000)
echo   2. Prophet Forecast (port 8001)
echo   3. Planning Worker (CakePHP)
echo   4. Forecast Worker (CakePHP)
echo   5. Optuna Tuning Worker (Python)
echo   6. Optuna Cron Ticker (CakePHP)
echo.
echo Pour arreter un service : Ctrl+C dans sa fenetre
echo Pour tout arreter : scripts\dev\stop_all_services.bat
echo.
echo Le ticker respecte jours+heure configures dans WFM (Europe/Paris).
echo.
echo ============================================================
echo.
echo Vous pouvez fermer cette fenetre en toute securite.
echo Les services continueront de tourner en arriere-plan.
echo.

pause
endlocal
