@echo off
setlocal

REM Lance le worker de génération de planning (jobs en tâche de fond)
REM À exécuter depuis Windows (Wamp/CLI).

call "%~dp0_env_php.bat"
if errorlevel 1 (
    pause
    exit /b 1
)

cd /d "%~dp0..\.."

echo [PlanningWorker] Demarrage...
echo (Fermez cette fenetre pour arreter le worker)
echo.

call "%~dp0..\..\bin\cake.bat" planning_generation_worker

echo.
echo [PlanningWorker] Termine.
pause
