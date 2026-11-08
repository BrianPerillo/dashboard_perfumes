@echo off
rem ======================================================================
rem Envia las alertas de descuento (php artisan alertas:procesar).
rem Tarea de Windows: "alertas_perfumes" (cada 3 horas).
rem Log: storage\logs\alertas_cron.log
rem Acepta argumentos extra, por ejemplo: scripts\alertas_procesar.bat --dry-run
rem Ver ai-context\07-alertas-y-notificaciones.md
rem ======================================================================
setlocal

cd /d "%~dp0.."

rem MySQL debe estar levantado (XAMPP); si no, arrancarlo (sin pisarse con otras tareas)
call "%~dp0mysql_asegurar.bat"

echo ---- %date% %time% alertas:procesar %* >> storage\logs\alertas_cron.log
"C:\xampp\php\php.exe" artisan alertas:procesar %* >> storage\logs\alertas_cron.log 2>&1

exit /b %errorlevel%
