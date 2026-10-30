# 09 · Panel de administración y Google Analytics

Ruta `/admin` (nombre `admin.dashboard`) — componente `app/Livewire/Admin/Dashboard.php` + vista `resources/views/livewire/admin/dashboard.blade.php`. Middleware: `auth` + `admin` (ver [06](06-autenticacion.md)). Layout app con `activeTab='admin'`.

## Secciones de la pantalla

1. **KPIs de la base de datos** (`$dbStats`): total de usuarios, admins, alertas, alertas por email.
2. **Google Analytics** (si está configurado): sesiones, páginas vistas, usuarios, duración promedio de sesión; gráfico de visitas por día; top 8 páginas; dispositivos (con %); "activos ahora" (tiempo real). Selector de período: 7 / 14 / 30 / 90 días (`periodo`, default 30; `updatedPeriodo()` recarga).
3. **Test de integraciones**:
   * 📧 `testEmail()` → `Mail::raw` al email del admin logueado.
   * ✈️ `testTelegram()` → pide `telegramChatTest`, envía mensaje de prueba por la API de Telegram.
   * 💳 `testMercadoPago()` → crea `Suscripcion` pendiente del plan `pro` para el admin y redirige al checkout (sandbox si el token es TEST).
4. **Envío manual de alertas**: `procesarAlertasEmail()` → `Artisan::call('alertas:procesar')` y muestra la salida del comando (equivale al cron automático).
5. **Usuarios**: tabla de todos (`orderByDesc is_admin, orderBy name`) con botón `toggleAdmin($id)` (no permite quitarse el rol a uno mismo).

## Google Analytics — dos capas

### 1) Tracking (gtag.js)
En `resources/views/components/layouts/app.blade.php`: si `config('services.google.analytics_id')` existe, carga `googletagmanager.com/gtag/js?id=…` y hace `gtag('config', id)`. En localhost no aparece en reportes reales.

### 2) Reporting (Data API v1beta)
`cargarAnalytics()` (se ejecuta en `mount()` y al cambiar el período):
* Requiere `GOOGLE_ANALYTICS_PROPERTY_ID` y que exista el archivo de credenciales: `base_path(config('services.google.analytics_credentials'))` (JSON de service account, p. ej. `storage/app/analytics/…json`, **no versionar**).
* Cliente: `Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient`.
* Reportes: métricas generales (`sessions, screenPageViews, totalUsers, averageSessionDuration`); por `date` (sesiones); por `pagePath` (top 8 por `screenPageViews`); por `deviceCategory`; realtime `activeUsers`.
* Cualquier excepción → `gaOk=false` (la UI muestra "SIN DATOS" sin romper el resto) y se loguea `GA error: …`.
* Variables (`.env` → `config/services.php` bajo `services.google`): `GOOGLE_ANALYTICS_ID` (G-XXXX, tracking), `GOOGLE_ANALYTICS_PROPERTY_ID` (numérico), `GOOGLE_ANALYTICS_CREDENTIALS` (ruta relativa al proyecto).
* Hay además `config/analytics.php` (de `spatie/laravel-analytics`, paquete instalado pero **no usado**).
* Prerrequisitos en Google: proyecto en Cloud Console con "Google Analytics Data API" habilitada; service account; agregar su email como "Lector" en la propiedad GA4.

## Comando CLI relacionado
`php artisan admin:grant {email}` — alterna el rol admin (ver [06](06-autenticacion.md)).

## Estado de los scrapers (desde 2026-10-30)
Tarjeta "🕷️ Estado de los scrapers" en `/admin` (`Admin\Dashboard::saludScrapers()`): por cada rubro muestra la última corrida (id, fecha, antigüedad en horas, total de productos) y una tabla por sitio/sección desde `corrida_sitios` (plataforma, encontrados, con descuento, segundos, estado `ok`/`vacio`/`bloqueado`/`error`, mensaje). Etiquetas: ✓ todo ok, ⚠ N sitio(s) con problemas, ⚠ desactualizada (> 36 h), sin corridas.
