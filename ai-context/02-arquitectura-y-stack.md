# 02 · Arquitectura y stack técnico

## Stack

| Capa | Tecnología | Notas |
|---|---|---|
| Backend | PHP ^8.2 · **Laravel 12** | `composer.json` |
| Componentes reactivos | **Livewire ^4.3** | Todas las pantallas son componentes Livewire de página completa (no hay controladores de vistas, salvo `PagoController`) |
| JS interactivo | **Alpine.js** (viene con Livewire) | Estado de UI: tema oscuro, sidebar, tabs, gráficos |
| Estilos | **Tailwind CSS v4** (`@tailwindcss/vite`) | Tokens en `resources/css/app.css` (`--c-*`, `--color-primary-*`); dark mode por clase `.dark` |
| Build front | Vite 7 + `laravel-vite-plugin` | `npm run dev` / `npm run build`. Entradas: `resources/css/app.css`, `resources/js/app.js` |
| Gráficos | **ApexCharts 3** (CDN jsdelivr) | Cargado en el layout; se construyen desde Alpine en `livewire/dashboard.blade.php` |
| Alertas UI | SweetAlert2 11 (CDN) | Helper global `window.swalConfirmar(...)` |
| Fuentes | Google Fonts – Inter | |
| DB | **MySQL** (XAMPP) – base `perfumes_descuentos` | `.env.example` trae sqlite por defecto, el `.env` real usa mysql |
| Sesión / Cache / Cola | `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=database` (según `.env` local) | |
| Mail | SMTP Gmail (App Password) — `MAIL_MAILER=smtp` en `.env` local | ver [11](11-entorno-config-despliegue.md) |
| Pagos | `mercadopago/dx-php ^3.10` | |
| Analytics | `google/analytics-data ^0.22.3` (Data API v1beta) + gtag.js | `spatie/laravel-analytics` está instalado pero **no se usa** |
| Scraper | Python 3 (ver [scraper-01](scraper-01-descripcion-y-ejecucion.md)) | `descuentos_master.py` en la raíz del repo; lo lanza el Task Scheduler de Windows (no Laravel) |
| Dev tools | `laravel/pail`, `laravel/pint`, `barryvdh/laravel-debugbar`, PHPUnit 11 | |

> El proyecto vive en `C:\xampp\htdocs\perfumes-dashboard` (Windows + XAMPP). `APP_URL=http://localhost/perfumes-dashboard/public` en local. Para exponerlo al exterior existe `ngrok-start.bat` (levanta `php artisan serve --port=8000` + ngrok).

## Estructura del repositorio

```
perfumes-dashboard/
├── AGENTS.md                      ← reglas para IAs (leer primero)
├── ai-context/                    ← ESTA documentación
├── app/
│   ├── Console/Commands/          ProcesarAlertas (alertas:procesar), AdminGrant (admin:grant)
│   ├── Http/Controllers/          PagoController (redirects MP + webhook)
│   ├── Http/Middleware/           AdminMiddleware (alias 'admin')
│   ├── Livewire/                  Dashboard, KpiPanel, Notificaciones, Planes, Admin/Dashboard, Auth/*
│   ├── Mail/                      AlertaDescuento, VerificacionCodigo
│   ├── Models/                    Alerta, Corrida, CorridaSitio, Favorito, Observacion, Plan, Producto, Suscripcion, Tienda, User
│   ├── Providers/AppServiceProvider.php   (vacío)
│   ├── Support/Talles.php                 (normalización de talles de calzado: H/M; ver 05)
│   └── Services/MercadoPagoService.php
├── bootstrap/app.php              ← routing, trustProxies(env TRUSTED_PROXIES), alias middleware 'admin'
├── config/                        ← services.php define telegram / google / mercadopago
├── database/migrations, seeders/PlanesSeeder.php
├── resources/
│   ├── css/app.css, js/app.js (axios), 
│   └── views/
│       ├── components/layouts/app.blade.php   (layout principal: sidebar, navbar, scripts)
│       ├── components/layouts/auth.blade.php  (layout de login/registro)
│       ├── dashboard.blade.php                (wrapper: KPI panel + componente dashboard)
│       ├── livewire/…                         (vistas de componentes; dashboard/tab-*.blade.php)
│       ├── emails/                            (alerta_descuento, verificacion_codigo)
│       └── pago/                              (exito, pendiente, error)
├── routes/web.php, console.php    (console.php NO tiene schedule definido)
├── descuentos_master.py           ← SCRAPER activo (Python)
├── dashboard_descuentos.py        ← dashboard Python antiguo (ver scraper-01)
├── excel_perfumes/                ← .xlsx de salida del scraper
├── logs_scraper.txt               ← log del scraper
├── nbp_inventory.json             ← NO es del scraper: volcado de una API de impresoras (Nubeprint), datos de terceros (ver scraper-01 §12)
├── ARQUITECTURA.md, notificaciones.md, mercadopago.md, Mejoras.md,
│   Cambios_Realizar_Antes_Subir_Produccion.md, descuentos_master_cambios.md  ← docs previas sueltas
├── scripts/                       ← lanzadores de las tareas de Windows (descuentos_master.bat, alertas_procesar.bat, instalar_tareas_windows.ps1)
└── ngrok-start.bat
```

## Patrones de código del proyecto

* **Todo es Livewire de página completa**: `Route::get('/x', ComponenteLivewire::class)`; el layout se fija con `#[Layout('components.layouts.app', ['activeTab' => '…'])]` o `components.layouts.auth`.
* **Lectura de datos de scraping con Query Builder** (`DB::table('observaciones as o')->join(...)`) más que con Eloquent, en `Dashboard`, `KpiPanel`, `ProcesarAlertas`, `Notificaciones`. Los modelos Eloquent de scraping existen (`Observacion` con scopes `rebajas()` y `deCorrida()`), pero se usan poco.
* **Estado de filtros del dashboard** vive en propiedades públicas Livewire y se persiste parcialmente en sesión (`dashboard_filtros`).
* **Gráficos**: el componente PHP serializa datos a JSON en propiedades (`*Json`), la vista Alpine los lee con `$wire.xxxJson` y arma ApexCharts.
* **Servicios externos** (Telegram) se llaman directo con `Http::post` — no hay paquete de Telegram.
* Convención de nombres: dominio en español (`Alerta`, `Suscripcion`, `Plan` → tabla `planes`, `Observacion` → `observaciones`). Tablas en español plural con `$table` explícito cuando difiere del plural inglés.
* Hay textos con **problemas de encoding (mojibake)** en `app/Livewire/Notificaciones.php` (ej. "Â¡Alerta activada!"). Ver [12-problemas-conocidos-y-pendientes.md](12-problemas-conocidos-y-pendientes.md).

## Middleware y bootstrap (`bootstrap/app.php`)

* `trustProxies`: lee `TRUSTED_PROXIES` del `.env` (`*` por defecto = cualquier proxy, solo desarrollo; en producción poner IPs/CIDR separadas por coma).
* Alias `admin` → `App\Http\Middleware\AdminMiddleware`: si no hay sesión → redirige a `login`; si `!is_admin` → 403 "Acceso restringido.".
* Health check de Laravel en `/up`.
* No hay manejo de excepciones custom.

## Cola y schedule

* `QUEUE_CONNECTION=database` (tabla `jobs` existe por migración), pero **no hay jobs propios**. `AlertaDescuento` extiende `Mailable` con `Queueable` pero **no implementa `ShouldQueue`**: se envía sincrónicamente (a propósito: no hay `queue:work` corriendo; encolarlo sin worker dejaría los mails sin enviar).
* El envío de alertas lo programa la **tarea de Windows `alertas_perfumes`** (cada 3 h, llamando directo a `php artisan alertas:procesar`); `routes/console.php` **no** define `Schedule`. Ver [07](07-alertas-y-notificaciones.md) y [11](11-entorno-config-despliegue.md).

## Tests

`php artisan test` (PHPUnit sobre sqlite en memoria; `phpunit.xml` fija `APP_URL=http://localhost`). Tests propios: `tests/Feature/ExampleTest` (la raíz redirige a `/joyas`), `tests/Feature/PagoVistasTest` (las páginas de retorno de MP renderizan), `tests/Unit/FiltrosOfertasTest` (filtros de fuentes/marcas y escape Markdown de Telegram). No tocan la base MySQL real.

## Multi-rubro (desde 2026-10-29)
Ver [14-multirubro.md](14-multirubro.md). Archivos nuevos: `config/rubros.php` (rubros, menús, rutas, géneros), `descuentos_ropa.py` (scraper de ropa, raíz), `scripts/descuentos_ropa.bat`, migración `2026_10_30_000000_add_rubro_multirubro`, `tests/Unit/RubrosConfigTest.php`. El layout y el dashboard reciben el `rubro`; las pestañas y el selector de rubro salen de la config.
