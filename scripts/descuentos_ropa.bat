@echo off
rem ======================================================================
rem Lanzador del scraper de ropa (descuentos_ropa.py): Nike Argentina y los sitios que se sumen.
rem Tarea de Windows sugerida: "descuentos_ropa" (diaria, ver scripts\instalar_tareas_windows.ps1).
rem Acepta argumentos extra, por ejemplo: scripts\descuentos_ropa.bat --solo nike --no-db
rem Log: logs_ropa.txt en la raiz del proyecto. Ver ai-context\ropa-01-scraper-nike.md
rem ======================================================================
setlocal

cd /d "%~dp0.."

rem Arrancar MySQL (con la config de XAMPP) solo si no esta corriendo, sin pisarse con otras tareas
call "%~dp0mysql_asegurar.bat"

python descuentos_ropa.py %* >> logs_ropa.txt 2>&1

exit /b %errorlevel%
