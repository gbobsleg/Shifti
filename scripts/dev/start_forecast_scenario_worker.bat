@echo off
setlocal

REM Lance le worker de scénarios de prévision (file async)
REM À exécuter depuis Windows (Wamp/CLI).

cd /d "%~dp0..\.."

echo [ForecastWorker] Demarrage...
echo (Fermez cette fenetre pour arreter le worker)
echo.

call "%~dp0..\..\bin\cake.bat" forecast_scenario_worker

echo.
echo [ForecastWorker] Termine.
pause
