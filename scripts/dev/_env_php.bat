@echo off
REM Ajoute php.exe au PATH pour les workers CLI (WAMP / SHIFTI_PHP).
REM A appeler via: call "%~dp0_env_php.bat"

where php >nul 2>&1
if not errorlevel 1 exit /b 0

if defined SHIFTI_PHP (
    if exist "%SHIFTI_PHP%\php.exe" (
        set "PATH=%SHIFTI_PHP%;%PATH%"
        exit /b 0
    )
    if exist "%SHIFTI_PHP%" (
        REM SHIFTI_PHP peut pointer directement vers php.exe
        for %%I in ("%SHIFTI_PHP%") do set "PATH=%%~dpI;%PATH%"
        exit /b 0
    )
)

REM Version PHP active dans WAMP (wampmanager.conf)
set "WAMP_PHP_VER="
if exist "C:\wamp64\wampmanager.conf" (
    for /f "tokens=2 delims==" %%A in ('findstr /R /C:"^phpVersion" "C:\wamp64\wampmanager.conf" 2^>nul') do (
        set "WAMP_PHP_VER=%%~A"
    )
)
if defined WAMP_PHP_VER (
    set "WAMP_PHP_VER=%WAMP_PHP_VER: =%"
    set "WAMP_PHP_VER=%WAMP_PHP_VER:"=%"
)
if defined WAMP_PHP_VER (
    if exist "C:\wamp64\bin\php\php%WAMP_PHP_VER%\php.exe" (
        set "PATH=C:\wamp64\bin\php\php%WAMP_PHP_VER%;%PATH%"
        exit /b 0
    )
)

REM Fallback : derniere version php8.x presente (ordre alphabetique)
set "PHP_FOUND="
for /d %%D in ("C:\wamp64\bin\php\php8.*") do (
    if exist "%%D\php.exe" set "PHP_FOUND=%%D"
)
if defined PHP_FOUND (
    set "PATH=%PHP_FOUND%;%PATH%"
    exit /b 0
)

echo [ERREUR] php.exe introuvable.
echo Ajoutez PHP au PATH systeme, ou definissez SHIFTI_PHP
echo (ex: set SHIFTI_PHP=C:\wamp64\bin\php\php8.3.28).
exit /b 1
