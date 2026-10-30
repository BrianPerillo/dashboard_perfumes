@echo off
rem ======================================================================
rem Lanzador del scraper de ropa (descuentos_ropa.py): Nike Argentina y los sitios que se sumen.
rem Tarea de Windows sugerida: "descuentos_ropa" (diaria, ver scripts\instalar_tareas_windows.ps1).
rem Acepta argumentos extra, por ejemplo: scripts\descuentos_ropa.bat --solo nike --no-db
rem Log: logs_ropa.txt en la raiz del proyecto. Ver ai-context\ropa-01-scraper-nike.md
rem ======================================================================
setlocal

cd /d "%~dp0.."

rem Arrancar MySQL (con la config de XAMPP) solo si no esta corriendo
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >NUL
if errorlevel 1 (
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    ping -n 6 127.0.0.1 >NUL
)

python descuentos_ropa.py %* >> logs_ropa.txt 2>&1

exit /b %errorlevel%
