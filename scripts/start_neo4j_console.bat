@echo off
setlocal

set "DBMS_ROOT=%USERPROFILE%\.Neo4jDesktop2\Data\dbmss"
set "NEO4J_BIN="
set "NEO4J_DBMS_HOME="

if not exist "%DBMS_ROOT%" (
    echo [ERROR] Neo4j Desktop DBMS folder not found:
    echo %DBMS_ROOT%
    echo.
    pause
    exit /b 1
)

for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "Get-ChildItem -Path '%DBMS_ROOT%' -Recurse -Filter neo4j.bat -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName"`) do (
    if not defined NEO4J_BIN (
        set "NEO4J_BIN=%%I"
        for %%H in ("%%~dpI..") do set "NEO4J_DBMS_HOME=%%~fH"
    )
)

if not defined NEO4J_BIN (
    echo [ERROR] neo4j.bat not found under:
    echo %DBMS_ROOT%
    echo.
    pause
    exit /b 1
)

if not exist "%NEO4J_BIN%" (
    echo [ERROR] neo4j.bat path resolved but does not exist:
    echo %NEO4J_BIN%
    echo.
    pause
    exit /b 1
)

cd /d "%NEO4J_DBMS_HOME%"
title Neo4j Console

if /i "%~1"=="--check" (
    echo [OK] Neo4j executable resolved:
    echo %NEO4J_BIN%
    echo.
    echo [OK] Neo4j DBMS home:
    echo %NEO4J_DBMS_HOME%
    exit /b 0
)

echo Starting Neo4j from:
echo %NEO4J_DBMS_HOME%
echo.
echo Keep this window open while Neo4j is running.
echo Close this window to stop the console process.
echo.

call "%NEO4J_BIN%" console

set "EXIT_CODE=%ERRORLEVEL%"
echo.
echo Neo4j console exited with code %EXIT_CODE%.
echo If startup failed, copy the last error lines from this window.
pause

endlocal
exit /b %EXIT_CODE%
