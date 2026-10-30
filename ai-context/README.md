# ai-context — Documentación del sistema para humanos e IAs

Esta carpeta es la **fuente de verdad** sobre cómo funciona *Descuentos Perfumes ARG*. Está pensada para que cualquier IA (o desarrollador nuevo) obtenga contexto completo sin tener que leer todo el código.

> **Regla:** todo cambio en el sitio, la base de datos o el scraper se refleja acá **en el mismo trabajo**. Las reglas completas están en [`../AGENTS.md`](../AGENTS.md) (que las IAs leen automáticamente desde la raíz; `CLAUDE.md` lo importa para Claude Code).

Última revisión integral: **2026-10-29** (ver [13-changelog-documentacion.md](13-changelog-documentacion.md)).

## Por dónde empezar

| Quiero… | Leer |
|---|---|
| Entender qué es el sitio y cómo encajan las piezas | [01-vision-general.md](01-vision-general.md) → [02-arquitectura-y-stack.md](02-arquitectura-y-stack.md) |
| Saber qué tablas hay y cómo se relacionan | [03-base-de-datos.md](03-base-de-datos.md) |
| Tocar el dashboard (pestañas, filtros, joyas) | [05-dashboard-secciones.md](05-dashboard-secciones.md) |
| Entender el scraper | [scraper-01](scraper-01-descripcion-y-ejecucion.md) → [scraper-02](scraper-02-sitios-y-extraccion.md) → [scraper-03](scraper-03-modelo-de-datos-y-normalizacion.md) |
| Configurar entorno / desplegar | [11-entorno-config-despliegue.md](11-entorno-config-despliegue.md) |
| Ver bugs conocidos antes de arreglar algo | [12-problemas-conocidos-y-pendientes.md](12-problemas-conocidos-y-pendientes.md) |

## Índice de documentos

| Archivo | Contenido |
|---|---|
| [01-vision-general.md](01-vision-general.md) | Qué es el sistema, subsistemas (scraper + web), funcionalidades, glosario |
| [02-arquitectura-y-stack.md](02-arquitectura-y-stack.md) | Stack, estructura de carpetas, patrones de código, middleware, cola/schedule, tests |
| [03-base-de-datos.md](03-base-de-datos.md) | Todas las tablas (esquema real), relaciones, dueños (Laravel vs scraper), inconsistencias |
| [04-rutas-y-navegacion.md](04-rutas-y-navegacion.md) | Tabla de rutas, middleware, menús (3 listas), redirecciones |
| [05-dashboard-secciones.md](05-dashboard-secciones.md) | Las 11 pestañas, filtros globales, KPIs, lógica de joyas/cambios/conclusiones |
| [06-autenticacion.md](06-autenticacion.md) | Registro con código por email, login, reset, roles admin |
| [07-alertas-y-notificaciones.md](07-alertas-y-notificaciones.md) | Alertas de usuario, comando `alertas:procesar`, email y Telegram |
| [08-planes-y-pagos.md](08-planes-y-pagos.md) | Planes, suscripciones, flujo MercadoPago y webhook, qué falta |
| [09-panel-admin-y-analytics.md](09-panel-admin-y-analytics.md) | Panel `/admin`, Google Analytics, tests de integraciones |
| [10-frontend-layouts-ui.md](10-frontend-layouts-ui.md) | Layouts, sistema de diseño, Alpine/Livewire/ApexCharts, reglas de UI |
| [11-entorno-config-despliegue.md](11-entorno-config-despliegue.md) | Setup, variables de entorno, comandos, programación de tareas, producción |
| [12-problemas-conocidos-y-pendientes.md](12-problemas-conocidos-y-pendientes.md) | Bugs, deuda técnica, ideas pendientes |
| [13-changelog-documentacion.md](13-changelog-documentacion.md) | Registro cronológico de cambios al sistema/documentación |
| [14-multirubro.md](14-multirubro.md) | Arquitectura multi-rubro (perfumes, ropa…): config, datos, rutas, selector, cómo sumar un rubro |
| [ropa-01-scraper-nike.md](ropa-01-scraper-nike.md) | Scraper de ropa: cómo es Nike Argentina, cómo se lee, uso, límites |
| [scraper-01-descripcion-y-ejecucion.md](scraper-01-descripcion-y-ejecucion.md) | El script `descuentos_master.py`: cómo se ejecuta, flujo, logs, archivos |
| [scraper-02-sitios-y-extraccion.md](scraper-02-sitios-y-extraccion.md) | Cada sitio scrapeado y cómo se extraen los datos |
| [scraper-03-modelo-de-datos-y-normalizacion.md](scraper-03-modelo-de-datos-y-normalizacion.md) | Normalización, detección de ofertas, escritura en la DB |

## Mapa rápido código → documento

| Carpeta / archivo | Doc |
|---|---|
| `routes/web.php`, `bootstrap/app.php` | 04 |
| `app/Livewire/Dashboard.php`, `KpiPanel.php`, `resources/views/livewire/dashboard*` | 05 |
| `app/Livewire/Auth/*`, `app/Models/User.php`, `AdminMiddleware`, `AdminGrant` | 06 |
| `app/Livewire/Notificaciones.php`, `app/Models/Alerta.php`, `ProcesarAlertas`, `app/Mail/*` | 07 |
| `app/Livewire/Planes.php`, `Plan`, `Suscripcion`, `MercadoPagoService`, `PagoController`, `pago/*` | 08 |
| `app/Livewire/Admin/Dashboard.php`, `config/analytics.php`, `config/services.php` | 09, 11 |
| `resources/views/components/layouts/*`, `resources/css`, `resources/js` | 10 |
| `database/migrations/*`, `app/Models/*` | 03 |
| `descuentos_master.py`, `excel_perfumes/`, `logs_scraper.txt` | scraper-01/02/03 |
| `scripts/` (lanzadores de las tareas de Windows) | 11, scraper-01, 07 |
| `config/rubros.php`, `descuentos_ropa.py` | 14, ropa-01 |

## Documentos previos en la raíz (históricos)

`ARQUITECTURA.md`, `notificaciones.md`, `mercadopago.md`, `Mejoras.md`, `Cambios_Realizar_Antes_Subir_Produccion.md`, `descuentos_master_cambios.md`. Siguen siendo útiles como guías paso a paso (Gmail/Telegram/cron, credenciales de MP, checklist de producción, changelog del scraper), pero **si contradicen a `ai-context/` o al código, manda `ai-context/`** (ej. `ARQUITECTURA.md` describe la verificación de email por link firmado; hoy es por código de 6 dígitos).

## Convenciones de esta documentación

* Español; tablas y bloques de código; rutas de archivo reales.
* "Verificado" = comprobado en código/datos reales; "no verificado" = inferido.
* Sin secretos (solo nombres de variables de entorno).
* Un tema = un archivo. Los temas nuevos reciben su propio `.md` y una fila en este índice.
