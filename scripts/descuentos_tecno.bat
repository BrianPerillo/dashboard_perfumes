@echo off
rem ======================================================================
rem Lanzador del scraper de tecnologia (descuentos_tecno.py): Compragamer, Maximus y Fullh4rd.
rem Tarea de Windows: "descuentos_tecno" (diaria 12:00, ver scripts\instalar_tareas_windows.ps1).
rem Acepta argumentos extra, por ejemplo: scripts\descuentos_tecno.bat --solo compragamer --no-db
rem Log: logs_tecno.txt en la raiz del proyecto. Ver ai-context\tecno-01-tiendas-y-precios.md
rem ======================================================================
setlocal

cd /d "%~dp0.."

rem Arrancar MySQL (con la config de XAMPP) solo si no esta corriendo, sin pisarse con otras tareas
call "%~dp0mysql_asegurar.bat"

python descuentos_tecno.py %* >> logs_tecno.txt 2>&1

exit /b %errorlevel%
