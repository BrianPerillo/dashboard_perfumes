@echo off
rem ======================================================================
rem Deja MySQL (XAMPP) corriendo, arrancandolo solo si hace falta. Lo usan todos los lanzadores con: call "%~dp0mysql_asegurar.bat"
rem
rem Por que un candado: al encender la PC varias tareas atrasadas arrancan juntas (StartWhenAvailable) y cada una veia
rem "mysqld no esta corriendo" y lo lanzaba -> DOS mysqld sobre el mismo datadir (danaron tablas el 2026-10-29 y volvio a
rem pasar el 2026-11-08). "mkdir" es atomico: solo la primera tarea lanza mysqld; las demas esperan a que levante.
rem Ver ai-context\11-entorno-config-despliegue.md
rem ======================================================================
set "MYSQL_LOCK=%TEMP%\mysql_arranque.lock"

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >NUL
if not errorlevel 1 goto :fin

mkdir "%MYSQL_LOCK%" 2>NUL
if errorlevel 1 goto :esperar

:arrancar
start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
rem esperar a que levante (ping en lugar de timeout: funciona sin consola)
ping -n 9 127.0.0.1 >NUL
rmdir "%MYSQL_LOCK%" 2>NUL
goto :fin

:esperar
rem otra tarea esta arrancando MySQL: darle tiempo y volver a mirar
ping -n 16 127.0.0.1 >NUL
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >NUL
if not errorlevel 1 goto :fin
rem el candado quedo viejo (la tarea que lo creo se corto): rehacerlo y arrancar
rmdir "%MYSQL_LOCK%" 2>NUL
mkdir "%MYSQL_LOCK%" 2>NUL
goto :arrancar

:fin
exit /b 0
