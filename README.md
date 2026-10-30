# Descuentos · Perfumes ARG

Dashboard web que muestra, analiza y alerta sobre **descuentos de perfumes en tiendas online argentinas**. Un scraper en Python recopila periódicamente los precios (cada ejecución es una *corrida*), los guarda en MySQL y esta aplicación Laravel los presenta en 11 secciones analíticas, con cuentas de usuario, alertas por email/Telegram, planes de suscripción (MercadoPago) y panel de administración (Google Analytics).

**Stack:** PHP 8.2 · Laravel 12 · Livewire 4 · Alpine.js · Tailwind CSS v4 · ApexCharts · MySQL · Python (scraper).

## Documentación

Toda la documentación técnica vive en [`ai-context/`](ai-context/README.md) (índice completo allí):

* [Visión general](ai-context/01-vision-general.md) · [Arquitectura](ai-context/02-arquitectura-y-stack.md) · [Base de datos](ai-context/03-base-de-datos.md)
* [Rutas](ai-context/04-rutas-y-navegacion.md) · [Dashboard](ai-context/05-dashboard-secciones.md) · [Autenticación](ai-context/06-autenticacion.md)
* [Alertas](ai-context/07-alertas-y-notificaciones.md) · [Planes y pagos](ai-context/08-planes-y-pagos.md) · [Admin y Analytics](ai-context/09-panel-admin-y-analytics.md)
* [Scraper](ai-context/scraper-01-descripcion-y-ejecucion.md) · [Entorno y despliegue](ai-context/11-entorno-config-despliegue.md) · [Problemas conocidos](ai-context/12-problemas-conocidos-y-pendientes.md)

Las reglas para IAs y colaboradores (incluida la obligación de mantener la documentación actualizada) están en [`AGENTS.md`](AGENTS.md).

## Puesta en marcha rápida (local, XAMPP)

```bash
composer install
cp .env.example .env        # completar DB (mysql: perfumes_descuentos), mail, etc.
php artisan key:generate
php artisan migrate
php artisan db:seed --class=PlanesSeeder
npm install && npm run build
php artisan serve           # o abrir http://localhost/perfumes-dashboard/public
```

Las tablas de datos de scraping (`tiendas`, `productos`, `observaciones`, `corridas`, …) las crea y llena el scraper (`descuentos_master.py`); ver [ai-context/03-base-de-datos.md](ai-context/03-base-de-datos.md) y la documentación del scraper.

## Comandos propios

```bash
php artisan alertas:procesar [--dry-run]   # envía alertas de descuento (email/Telegram)
php artisan admin:grant {email}            # alterna rol admin de un usuario
```
