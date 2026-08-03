@echo off
setlocal
set "SOLVER_DIR=%~dp0..\..\solver-python"
cd /d "%SOLVER_DIR%"

set "PYTHON_EXE=%SOLVER_DIR%\.venv\Scripts\python.exe"

echo ============================================================
echo OR-Tools Solver Service - Shifti
echo ============================================================
echo.
echo Environnement : solver-python\.venv (Python 3.13 du projet)
echo Demarrage du service sur le port 8000...
echo Documentation disponible sur http://localhost:8000/docs
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

"%PYTHON_EXE%" -m uvicorn main:app --host 0.0.0.0 --port 8000 --reload

pause
endlocal
