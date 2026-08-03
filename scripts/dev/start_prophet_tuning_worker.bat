@echo off
setlocal
set "SOLVER_DIR=%~dp0..\..\solver-python"
cd /d "%SOLVER_DIR%"

set "PYTHON_EXE=%SOLVER_DIR%\.venv\Scripts\python.exe"

echo ============================================================
echo Prophet Optuna Tuning Worker - Shifti
echo ============================================================
echo.
echo Environnement : solver-python\.venv
echo Boucle continue (poll jobs queued, sleep 5s)
echo.
echo Pour arreter : Ctrl+C
echo ============================================================
echo.

if not exist "%PYTHON_EXE%" (
    echo [ERREUR] Interpreteur introuvable : %PYTHON_EXE%
    echo Recreez le venv puis : .venv\Scripts\python.exe -m pip install -r requirements.txt
    pause
    exit /b 1
)

"%PYTHON_EXE%" prophet_tuning_worker.py

pause
endlocal
