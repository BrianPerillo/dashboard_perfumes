@echo off
rem ======================================================================
rem Lanzador del scraper de descuentos (descuentos_master.py).
rem Tarea de Windows: "descuentos_master_perfumes" (diaria, 13:00).
rem Vive en <proyecto>\scripts, por eso trabaja con rutas relativas.
rem Acepta argumentos extra que pasa al script, por ejemplo:
rem     scripts\descuentos_master.bat --solo ambrosia --no-db
rem Ver ai-context\scraper-01-descripcion-y-ejecucion.md
rem ======================================================================
setlocal

rem Ir a la raiz del proyecto (carpeta padre de scripts\)
cd /d "%~dp0.."

rem 1. Arrancar MySQL solo si no esta corriendo
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >NUL
if errorlevel 1 (
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    rem esperar ~5 s a que MySQL levante (ping en lugar de timeout: funciona sin consola)
    ping -n 6 127.0.0.1 >NUL
)

rem 2. Ejecutar el scraper; el log se acumula en logs_scraper.txt
python descuentos_master.py %* >> logs_scraper.txt 2>&1

exit /b %errorlevel%
