@echo off
setlocal

REM Mode "auto-reload" (dev):
REM - Relance le worker forecast en --once en boucle pour recharger le code PHP
REM - Permet d'éviter de redémarrer manuellement après une modification

call "%~dp0_env_php.bat"
if errorlevel 1 (
    pause
    exit /b 1
)

cd /d "%~dp0..\.."

echo [ForecastWorker] Mode auto-reload (boucle --once). Ctrl+C pour arreter.
echo.

:loop
call "%~dp0..\..\bin\cake.bat" forecast_scenario_worker --once
REM Petite pause pour eviter de saturer CPU quand il n'y a pas de scénario
timeout /t 2 /nobreak >nul
goto loop
