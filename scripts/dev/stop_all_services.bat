@echo off
setlocal EnableExtensions
echo ============================================================
echo Shifti - Arret de tous les services locaux
echo ============================================================
echo.
echo Recherche et arret des services...
echo.

REM Arreter le processus Python sur le port 8000 (OR-Tools)
echo [1/4] Arret du solveur OR-Tools (port 8000)...
set "STOPPED="
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
    taskkill /F /PID %%a >nul 2>&1
    if not errorlevel 1 (
        echo    [OK] Solveur OR-Tools arrete
        set "STOPPED=1"
    )
)
if not defined STOPPED echo    [INFO] Aucun process sur le port 8000

REM Arreter le processus Python sur le port 8001 (Prophet)
echo [2/4] Arret du service Prophet (port 8001)...
set "STOPPED="
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8001 ^| findstr LISTENING') do (
    taskkill /F /PID %%a >nul 2>&1
    if not errorlevel 1 (
        echo    [OK] Service Prophet arrete
        set "STOPPED=1"
    )
)
if not defined STOPPED echo    [INFO] Aucun process sur le port 8001

REM Arreter les fenetres workers CakePHP (titre fixe + arbre /T)
echo [3/4] Arret du worker Planning (CakePHP)...
taskkill /FI "WINDOWTITLE eq Planning Worker (CakePHP)*" /T /F >nul 2>&1
if not errorlevel 1 (
    echo    [OK] Fenetre Planning Worker fermee
) else (
    echo    [INFO] Fenetre Planning Worker introuvable
)

echo [4/4] Arret du worker Forecast (CakePHP)...
taskkill /FI "WINDOWTITLE eq Forecast Worker (CakePHP)*" /T /F >nul 2>&1
if not errorlevel 1 (
    echo    [OK] Fenetre Forecast Worker fermee
) else (
    echo    [INFO] Fenetre Forecast Worker introuvable
)

REM Filet de securite : tuer les process PHP CLI encore accroches aux commandes worker
echo.
echo Nettoyage des process PHP worker restants...
powershell -NoProfile -Command "$list = @(Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -like 'php*.exe' -and $_.CommandLine -and ($_.CommandLine -like '*planning_generation_worker*' -or $_.CommandLine -like '*forecast_scenario_worker*') }); if ($list.Count -eq 0) { Write-Host '   [INFO] Aucun process PHP worker restant' } else { foreach ($p in $list) { Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue; Write-Host ('   [OK] PHP PID ' + $p.ProcessId + ' arrete') } }"

echo.
echo ============================================================
echo SERVICES ARRETES !
echo ============================================================
echo.
echo OR-Tools, Prophet et les workers CakePHP ont ete arretes.
echo Vous pouvez les relancer avec scripts\dev\start_all_services.bat
echo.

pause
endlocal
