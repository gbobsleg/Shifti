@echo off
setlocal
cd /d "%~dp0"

set "PYTHON_EXE=%~dp0.venv\Scripts\python.exe"

echo ============================================================
echo Prophet Forecast Service - WFM Planning
echo ============================================================
echo.
echo Environnement : .venv (Python 3.13 du projet)
echo Demarrage du service sur le port 8001...
echo Documentation disponible sur http://localhost:8001/docs
echo.
echo Pour arreter le service : Ctrl+C
echo ============================================================
echo.

if not exist "%PYTHON_EXE%" (
    echo [ERREUR] Interpreteur introuvable : %PYTHON_EXE%
    echo Recreez le venv puis : .venv\Scripts\python.exe -m pip install -r requirements.txt
    pause
    exit /b 1
)

"%PYTHON_EXE%" forecast_prophet.py

pause
endlocal
