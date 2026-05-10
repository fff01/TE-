@echo off
setlocal

set "NEO4J_DBMS_HOME=C:\Users\Dee\.Neo4jDesktop2\Data\dbmss\dbms-c5bb13d1-8ea4-47bc-95ba-227bf634a785"
set "NEO4J_BIN=%NEO4J_DBMS_HOME%\bin\neo4j.bat"

if not exist "%NEO4J_BIN%" (
    echo [ERROR] neo4j.bat not found:
    echo %NEO4J_BIN%
    exit /b 1
)

echo Starting Neo4j from:
echo %NEO4J_DBMS_HOME%
echo.
echo A new console window will stay open while Neo4j is running.

start "Neo4j Console" cmd /k "\"%NEO4J_BIN%\" console"

endlocal
