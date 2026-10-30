# AGENTS.md — Instrucciones para agentes de IA

Proyecto: **Descuentos Perfumes ARG** (multi-rubro: perfumes y ropa) — dashboard Laravel 12 + Livewire 4 que muestra descuentos scrapeados (scripts Python) de tiendas online argentinas, con usuarios, alertas (email/Telegram), planes y pagos (MercadoPago) y panel admin.

## 1. Antes de hacer CUALQUIER cosa

1. **Leé [`ai-context/README.md`](ai-context/README.md)** (índice de la documentación del sistema) y luego los documentos que correspondan a la tarea. Es la fuente de verdad sobre cómo funciona el sitio, la base de datos, el scraper y las integraciones.
2. Si la documentación y el código discrepan, **manda el código**; corregí la documentación en el mismo cambio.
3. Revisá [`ai-context/12-problemas-conocidos-y-pendientes.md`](ai-context/12-problemas-conocidos-y-pendientes.md) para no re-descubrir bugs ya conocidos.

## 2. Regla de oro: mantener la documentación actualizada

**Todo cambio en el proyecto debe reflejarse en `ai-context/` en el mismo trabajo** (no "después"). Un cambio no está terminado hasta que la doc está al día.

| Si cambiás… | Actualizá |
|---|---|
| Migraciones, tablas, columnas, relaciones, modelos Eloquent | `03-base-de-datos.md` (+ `scraper-03-*` si afecta al scraper) |
| `routes/web.php`, menús, middleware | `04-rutas-y-navegacion.md` |
| `Livewire/Dashboard.php`, `KpiPanel`, `dashboard/tab-*.blade.php`, filtros, catálogo de joyas | `05-dashboard-secciones.md` |
| Login, registro, verificación, reset, roles admin | `06-autenticacion.md` |
| Alertas, `ProcesarAlertas`, mails, Telegram | `07-alertas-y-notificaciones.md` |
| Planes, suscripciones, MercadoPago, webhook | `08-planes-y-pagos.md` |
| Panel `/admin`, Google Analytics | `09-panel-admin-y-analytics.md` |
| Layouts, CSS, JS, componentes UI | `10-frontend-layouts-ui.md` |
| `.env`/config, comandos artisan, schedule, despliegue | `11-entorno-config-despliegue.md` |
| Scraper Python (`descuentos_master.py`), sitios scrapeados, normalización, cadencia | `scraper-01-*`, `scraper-02-*`, `scraper-03-*` |
| Rubros (`config/rubros.php`), scraper de ropa (`descuentos_ropa.py`), nuevos sitios/rubros | `14-multirubro.md`, `ropa-01-scraper-nike.md` |
| Bug nuevo detectado / bug corregido / deuda técnica | `12-problemas-conocidos-y-pendientes.md` |
| **Cualquier cambio** | agregar una entrada (fecha ISO, qué y por qué) a `13-changelog-documentacion.md` |
| Se crea/renombra/elimina un documento | actualizar el índice `ai-context/README.md` |

Reglas para escribir documentación:
* En **español**, concisa y verificable: citá archivos/métodos reales (`app/Livewire/Dashboard.php::cargarJoyas`).
* Distinguí hechos **verificados** de **supuestos** ("no verificado").
* **Nunca** escribas secretos (valores de `.env`, tokens, contraseñas, contenido de `storage/app/analytics/*.json`). Solo nombres de variables.
* Si agregás un tema nuevo, creá un `.md` nuevo en `ai-context/` en lugar de inflar uno existente, y enlazalo desde el índice.

## 3. Convenciones del código

* **Stack**: PHP ^8.2, Laravel 12, Livewire 4 (componentes de página completa, sin controladores de vista), Alpine.js, Tailwind CSS v4, ApexCharts, MySQL (XAMPP). Windows + XAMPP en local.
* **Idioma**: UI y mensajes en español rioplatense (voseo: "Ingresá", "Revisá"). Dominio y nombres en español (`Alerta`, `Suscripcion`, `Observacion`).
* **Estilo UI**: usar tokens `var(--c-*)` (soportan modo oscuro por clase `.dark`); acentos violeta. Probar claro y oscuro.
* **Encoding**: guardar archivos en UTF-8 sin doble codificación (hay un archivo con mojibake: `app/Livewire/Notificaciones.php`; no propagar el problema).
* Leer datos de scraping con `DB::table(...)` / scopes de `Observacion`; el filtro estándar de ofertas está descrito en `05-dashboard-secciones.md`.
* Seguir el estilo del código vecino (densidad de comentarios, nombres, idioma). Formateo PHP: `vendor/bin/pint`.

## 4. Cuidado especial (zonas delicadas)

* **Las tablas `tiendas, productos, observaciones, corridas, corrida_sitios, favoritos, usuarios` y la vista `v_descuentos` pertenecen a los scrapers**; Laravel solo las modifica con migraciones defensivas (ver `database/migrations/2026_10_30_000000_add_rubro_multirubro.php`). No las alteres sin coordinar scrapers + Laravel + doc `03`. Toda consulta de "última corrida" debe filtrar por **rubro** (`Corrida::deRubro`/`ultima`).
* **MySQL (XAMPP):** nunca arrancarlo dos veces ni con `start mysqld.exe` sin `--defaults-file`; hacé respaldo antes de tocar el datadir (ver incidente en `11-…`). No ejecutes `DROP`/`DELETE` masivos sin que el usuario lo autorice.
* **`.env`, `storage/app/analytics/*.json`, tokens de MP/Telegram/Gmail**: no leer valores, no imprimirlos, no commitearlos.
* **Pagos (`MercadoPagoService`, `PagoController`)**: cambios requieren probar en sandbox (`TEST-` token). Ver bug conocido #1 en `12-…`.
* **Base de datos de desarrollo con datos reales** (≈435k observaciones): evitá `migrate:fresh`, `db:wipe` o deletes masivos. Consultas de solo lectura con `php artisan tinker --execute='…'`.
* El scraper `descuentos_master.py` corre de forma programada contra sitios de terceros: no lo ejecutes completo sin que el usuario lo pida (genera tráfico, un .xlsx y filas nuevas en la DB).
* `.gitignore` ignora `*.py` y `*.md` salvo excepciones (`README.md`, `AGENTS.md`, `CLAUDE.md`, `ai-context/**/*.md`, `descuentos_master.py`, `descuentos_master_cambios.md`). Un `.py`/`.md` nuevo fuera de esas rutas no se versiona: agregar la excepción en `.gitignore`.
* Las tareas programadas de Windows (scraper diario 13:00 y alertas cada 3 h) usan los lanzadores de `scripts/` (ver `ai-context/11-entorno-config-despliegue.md`). Si cambiás sus rutas o argumentos, reinstalá con `scripts/instalar_tareas_windows.ps1`.

* **Tailwind v4 se compila**: el sitio sirve `public/build` (ignorado por git). Si agregás clases nuevas en una vista y no se ven (ícono pisado, anchos ignorados…), el CSS compilado está viejo: ejecutá `npm run build` (o `npm run dev`). No hay `public/hot` ni worker de Vite corriendo por defecto.

## 5. Comandos útiles

```bash
php artisan serve                      # servidor local (puerto 8000) — o vía Apache: http://localhost/perfumes-dashboard/public
npm run dev | npm run build            # Vite
php artisan migrate                    # migraciones (solo tablas de la app)
php artisan alertas:procesar --dry-run # simular alertas sin enviar
php artisan admin:grant {email}        # alternar rol admin
php artisan route:list                 # rutas
vendor/bin/pint                        # formatear PHP
```

## 6. Cierre de cada tarea (checklist)

- [ ] El cambio funciona (probado o explícitamente marcado como no probado).
- [ ] `ai-context/` actualizado según la tabla de la sección 2.
- [ ] Entrada agregada en `ai-context/13-changelog-documentacion.md`.
- [ ] Sin secretos en código ni docs.
