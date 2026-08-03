@echo off
setlocal

REM Ticker cron Optuna : verifie chaque minute jour+heure WFM (Europe/Paris)
call "%~dp0_env_php.bat"
if errorlevel 1 (
    pause
    exit /b 1
)

cd /d "%~dp0..\.."

echo [OptunaTicker] Demarrage...
echo (Fermez cette fenetre pour arreter le ticker)
echo.

call "%~dp0..\..\bin\cake.bat" prophet_tuning_scheduler_ticker

echo.
echo [OptunaTicker] Termine.
pause
