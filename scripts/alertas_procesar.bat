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

rem MySQL debe estar levantado (XAMPP); si no, arrancarlo
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >NUL
if errorlevel 1 (
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    ping -n 6 127.0.0.1 >NUL
)

echo ---- %date% %time% alertas:procesar %* >> storage\logs\alertas_cron.log
"C:\xampp\php\php.exe" artisan alertas:procesar %* >> storage\logs\alertas_cron.log 2>&1

exit /b %errorlevel%
