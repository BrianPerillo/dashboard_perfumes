# 11 · Entorno, configuración y despliegue

## Entorno local actual

* Windows 11 + **XAMPP** (Apache + MySQL/MariaDB). Proyecto en `C:\xampp\htdocs\perfumes-dashboard`.
* PHP 8.2.x (XAMPP), Composer, Node/npm (Vite 7), Python 3 (scraper; el `.pyc` existente es `cpython-313`).
* `APP_URL=http://localhost/perfumes-dashboard/public` (vía Apache) — alternativa: `php artisan serve` (+ `ngrok-start.bat` para exponerlo; usa puerto 8000).
* Base de datos MySQL/MariaDB `perfumes_descuentos` en `127.0.0.1:3306` (los valores de usuario/clave están en `.env`, **nunca** copiarlos a la documentación).

## Setup desde cero

```bash
composer install
cp .env.example .env            # y completar (ver variables abajo; .env.example trae sqlite por defecto → cambiar a mysql)
php artisan key:generate
php artisan migrate             # crea users/alertas/planes/suscripciones/sessions/cache/jobs
php artisan db:seed --class=PlanesSeeder
npm install && npm run build    # o npm run dev
```

> Las tablas del scraper (`tiendas`, `productos`, `observaciones`, `corridas`, `corrida_sitios`, `favoritos`, `usuarios`, vista `v_descuentos`) **no están en las migraciones de Laravel**: las crea/gestiona el scraper o un script SQL manual. En una base nueva hay que correr el scraper (o importar un dump) antes de que el dashboard muestre algo. Ver [03](03-base-de-datos.md) y [scraper-03](scraper-03-modelo-de-datos-y-normalizacion.md).

Atajos de `composer.json`: `composer setup` (instala, copia .env, key, migrate, npm install/build); `composer dev` (concurrently: `php artisan serve`, `queue:listen`, `pail`, `npm run dev`).

## Variables de entorno

Solo nombres (sin valores). Fuente: `.env.example`, `.env`, `config/services.php`.

| Grupo | Variables | Uso |
|---|---|---|
| App | `APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_LOCALE` | `APP_URL` influye en links de mails y `back_urls` de MP |
| DB | `DB_CONNECTION (mysql), DB_HOST, DB_PORT, DB_DATABASE (perfumes_descuentos), DB_USERNAME, DB_PASSWORD` | |
| Sesión/cache/cola | `SESSION_DRIVER (file), CACHE_STORE (file), QUEUE_CONNECTION (database)` | |
| Mail | `MAIL_MAILER (smtp\|log), MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION/MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD (App Password Gmail), MAIL_FROM_ADDRESS, MAIL_FROM_NAME` | |
| Telegram | `TELEGRAM_BOT_TOKEN` → `services.telegram.bot_token` | alertas + test admin |
| MercadoPago | `MP_ACCESS_TOKEN, MP_PUBLIC_KEY, MP_WEBHOOK_SECRET` → `services.mercadopago.*` | `TEST-` = sandbox, `APP_USR-` = producción |
| Google Analytics | `GOOGLE_ANALYTICS_ID, GOOGLE_ANALYTICS_PROPERTY_ID, GOOGLE_ANALYTICS_CREDENTIALS` → `services.google.*` | credenciales: JSON de service account en `storage/app/analytics/` |
| Proxies | `TRUSTED_PROXIES` (`*` por defecto; lista de IPs/CIDR separadas por coma en producción) → la lee `bootstrap/app.php` | |

> Al 2026-10-29 el `.env` local **no** define `TELEGRAM_BOT_TOKEN`, `MP_*` ni (probablemente) `TRUSTED_PROXIES`; sí define `GOOGLE_ANALYTICS_*` y `MAIL_MAILER=smtp`. Verificar con `php artisan tinker` antes de asumir que una integración está activa.

## Seguridad / archivos sensibles (NO commitear ni pegar en docs)

`.env`, `storage/app/analytics/*.json` (service account de Google), tokens de MP/Telegram, App Password de Gmail. `.gitignore` ya ignora `.env`. Revisar que el JSON de analytics no entre al repo (`storage/app/.gitignore` existe).

## Programación de tareas (estado actual)

| Tarea | Estado |
|---|---|
| Scraper (`descuentos_master.py`) | Tarea de Windows `descuentos_master_perfumes`, diaria 13:00 → hoy apunta al redirector `C:\xampp\htdocs\Clases\descuentos\descuentos_master_bat.bat`, que llama a `scripts\descuentos_master.bat` (del proyecto). Ver sección *Tareas de Windows* abajo y [scraper-01](scraper-01-descripcion-y-ejecucion.md) |
| `alertas:procesar` | Tarea de Windows `alertas_perfumes`, **cada 3 horas** desde las 14:00 → `scripts\alertas_procesar.bat`. Ya no usa `Schedule` de Laravel |
| `queue:work` | No hay jobs propios; no hace falta worker |

Para producción ver también `Cambios_Realizar_Antes_Subir_Produccion.md` (raíz), cuyo checklist resumido:
`SESSION_SECURE_COOKIE=true`; `TRUSTED_PROXIES` real; MP con credenciales `APP_USR-` y `MP_WEBHOOK_SECRET` (webhook público `https://dominio/webhook/mercadopago`); `MAIL_MAILER=smtp`+Gmail App Password; `TELEGRAM_BOT_TOKEN`; Google Analytics (ID, property, credenciales); `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` https; cron `* * * * * php artisan schedule:run`; `php artisan config:cache && route:cache`; probar registro→código, pago sandbox, mails.

### Tareas de Windows (carpeta `scripts/`, versionada)

| Archivo | Qué hace |
|---|---|
| `scripts/descuentos_master.bat` | Lanzador del scraper: va a la raíz del proyecto (`%~dp0..`), arranca MySQL **solo si no está corriendo**, ejecuta `python descuentos_master.py %*` y acumula el log en `logs_scraper.txt`. Acepta argumentos (ej. `--solo ambrosia --no-db -o <ruta>.xlsx` para pruebas sin tocar la DB) y propaga el código de salida |
| `scripts/alertas_procesar.bat` | Ejecuta `php artisan alertas:procesar %*` y registra en `storage\logs\alertas_cron.log` (también arranca MySQL si hace falta) |
| `scripts/ejecutar_oculto.vbs` | Lanza un `.bat` **sin ventana de consola** y espera su fin (propaga el código de salida). Las tareas `alertas_perfumes` y `descuentos_ropa` (modo Interactive) lo usan: `wscript.exe //B //Nologo scripts\ejecutar_oculto.vbs <ruta.bat>`. Antes abrían una consola negra en el escritorio en cada ejecución (la de ropa quedaba abierta ~2,5 h). `descuentos_master_perfumes` (LogonType Password) no abre ventana y sigue llamando directo al redirector |
| `scripts/instalar_tareas_windows.ps1` | Registra/actualiza las tareas `descuentos_master_perfumes` (solo cambia su acción), `alertas_perfumes` (cada 3 h) y `descuentos_ropa`; las dos últimas vía `Nueva-AccionOculta` (`ejecutar_oculto.vbs`) |

Estado verificado el 2026-10-29: `alertas_perfumes` creada (modo Interactive, próxima 20:00). La tarea `descuentos_master_perfumes` está guardada con la contraseña de Windows (`LogonType=Password`), por lo que **repuntarla requiere la contraseña**: no se pudo hacer desde una sesión sin interacción. Mientras tanto sigue apuntando a `C:\xampp\htdocs\Clases\descuentos\descuentos_master_bat.bat`, que ahora es un **redirector** de 1 línea (`call ...\perfumes-dashboard\scripts\descuentos_master.bat %*`; copia del original: `descuentos_master_bat.bat.bak-20261029`). **Para completar el cambio**: abrir PowerShell normal y ejecutar `powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\perfumes-dashboard\scripts\instalar_tareas_windows.ps1` (pide la contraseña); después se puede borrar el redirector.

## Comandos Artisan propios

| Comando | Descripción |
|---|---|
| `php artisan alertas:procesar [--dry-run]` | Detecta novedades y envía alertas (ver [07](07-alertas-y-notificaciones.md)) |
| `php artisan admin:grant {email}` | Alterna rol admin |

## Archivos de documentación previos en la raíz (históricos)

`ARQUITECTURA.md` (jun-2026, parcialmente desactualizado: describe verificación por link firmado, no por código), `notificaciones.md` (setup Gmail/Telegram/cron — sigue vigente), `mercadopago.md` (guía MP), `Mejoras.md` (ideas/bugs pendientes), `Cambios_Realizar_Antes_Subir_Produccion.md` (checklist), `descuentos_master_cambios.md` (changelog del scraper). **La fuente de verdad es ahora `ai-context/`**; si hay contradicción, manda `ai-context/` (y el código).

## Rendimiento de MySQL/PHP/Apache (2026-11-01)
`innodb_buffer_pool_size` estaba en 16 MB (default de XAMPP) y los datos+índices de scraping suman ~76 MB; se subió a 256 MB (`my.ini` → `innodb_buffer_pool_size=256M`, aplicado también en caliente con `SET GLOBAL`); además se activó `opcache` en `php.ini`. **Editar `php.ini` exige reiniciar Apache** (corre desde el panel de XAMPP: `httpd -k restart` no funciona). Detalle, medidas y pasos en [15-rendimiento.md](15-rendimiento.md). La caché de la app usa el driver `file` (`storage/framework/cache`).

## Scraper de ropa y su tarea
`scripts/descuentos_ropa.bat` ejecuta `python descuentos_ropa.py` (log `logs_ropa.txt`). Guardado en MySQL **validado** y, desde 2026-11-01, **tienda por tienda** en una corrida `en_curso` que se publica (`completa`) al final (ver [ropa-02](ropa-02-tiendas-y-plataformas.md)). La tarea `descuentos_ropa` (diaria 15:00, modo Interactive, **límite 5 h** desde el 2026-10-30: con 8 tiendas la corrida dura ~2,5 h y con el límite anterior de 2 h Windows la habría cortado antes de guardar) está **registrada desde el 2026-10-29** (`scripts/instalar_tareas_windows.ps1`, con autorización del usuario); lanza `scripts\descuentos_ropa.bat`, log en `logs_ropa.txt`. Detalle: [ropa-01-scraper-nike.md](ropa-01-scraper-nike.md).

## Incidente MySQL del 2026-10-29 (para no repetirlo) — RESUELTO
**Resolución:** `tiendas` recreada (12 filas con sus ids), `mysqlcheck` OK en toda la base, migración multi-rubro aplicada. MySQL quedó corriendo desde una instancia lanzada con `--defaults-file` de XAMPP; si el panel lo muestra en rojo, ver la regla de abajo antes de tocar nada.
* **Síntoma:** el panel de XAMPP mostraba MySQL en rojo y "no arrancaba". **Causa 1:** había un `mysqld` huérfano (iniciado el 26/10 por el `.bat` viejo con `start mysqld.exe` **sin** la config de XAMPP) que el panel no reconocía; cada intento de arrancar otro fallaba con `ibdata1 must be writable`. Se lo apagó con `mysqladmin -u root shutdown`.
* **Causa 2 (daño):** al iniciarlo desde el panel hubo **dos arranques casi simultáneos** (19:41:14 y 19:41:18) sobre el mismo datadir; las tablas `perfumes_descuentos.tiendas` y `mysql.gtid_slave_pos` quedaron con el tablespace id inválido (`4294967294`) y "no existen en el motor". El resto de las tablas pasa `mysqlcheck` OK. Respaldo físico previo a cualquier reparación: `C:\xampp\mysql\data_backup_20261029_prerepair`.
* **Reglas:** arrancar/parar MySQL **solo desde el panel** (un clic, esperar) o con los `.bat` del proyecto (que usan `--defaults-file=C:\xampp\mysql\bin\my.ini --standalone` y solo arrancan si `mysqld.exe` no está corriendo); **nunca** dos arranques a la vez ni `start mysqld.exe` sin la config. Log real del servidor: `C:\xampp\mysql\data\mysql_error.log`.

## Build del front
`public/build` (Vite + Tailwind v4) lo sirve el sitio y está fuera de git: tras cambiar vistas con clases nuevas hay que ejecutar `npm run build` (en producción también). Síntoma de build viejo: estilos que no se aplican a elementos nuevos.

## Tareas programadas, MySQL y XAMPP (comportamiento observado, 2026-10-29/30)
* Los lanzadores `scripts/*.bat` **arrancan MySQL por su cuenta si no está corriendo** (con `--defaults-file` de XAMPP). Por eso, si se apaga MySQL/XAMPP "para cerrar todo", la próxima tarea (alertas cada 3 h: 02:00, 05:00…, scraper de perfumes 13:00, scraper de ropa 15:00) **lo vuelve a levantar** y lo deja corriendo, sin que el panel de XAMPP lo reconozca (aparece en rojo aunque funcione).
* **Apache no lo usa ninguna tarea**: el sitio (`http://localhost/perfumes-dashboard/public`) solo está disponible si se inicia Apache desde el panel (o `php artisan serve`).
* Para apagar todo de forma ordenada: `mysqladmin -u root shutdown` (MySQL), detener Apache desde el panel (o su proceso principal `httpd.exe`) y cerrar el panel; si además se quiere que nada se reinicie solo, **pausar las 3 tareas** (`Disable-ScheduledTask`) y reactivarlas después. El cierre forzado del panel con procesos hijos vivos deja ventanas "Application Error" (inofensivas).
* Las tareas corren en modo *Interactive*: requieren la sesión de Windows iniciada.
