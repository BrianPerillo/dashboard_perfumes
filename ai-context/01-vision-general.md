# 01 · Visión general del sistema

## ¿Qué es?

**Descuentos Perfumes ARG** es un sitio web que recopila (scrapea) periódicamente los precios de perfumes en tiendas online argentinas, detecta cuáles están realmente en oferta y los presenta en un dashboard analítico. Además ofrece cuentas de usuario con **alertas** (email / Telegram) cuando aparece un descuento que le interesa al usuario, **planes de suscripción** pagos (MercadoPago) y un **panel de administración** con métricas de Google Analytics.

El nombre visible en la UI es "Descuentos · Perfumes ARG" (🧴). Idioma de la interfaz: **español rioplatense** (voseo: "Revisá", "Ingresá"). Moneda: ARS.

## Los dos grandes subsistemas

```
┌──────────────────────────────┐        ┌──────────────────────────────────────────┐
│  SCRAPER (Python)            │        │  WEB (Laravel 12 + Livewire 4)           │
│  descuentos_master.py        │        │                                          │
│  - Corre fuera de Laravel    │ MySQL  │  - Lee (solo lectura) productos,         │
│  - Visita las tiendas        │───────▶│    observaciones, corridas, tiendas      │
│  - Genera .xlsx + escribe DB │        │  - Escribe: users, alertas, planes,      │
│  - Crea una "corrida"        │        │    suscripciones, favoritos              │
└──────────────────────────────┘        └──────────────────────────────────────────┘
        │ .xlsx                                    │
        ▼                                          ▼
 excel_perfumes/                        Navegador del usuario (dashboard, alertas…)
```

* **El scraper es dueño de las tablas de datos de scraping** (`tiendas`, `productos`, `observaciones`, `corridas`, `corrida_sitios`, `favoritos`): Laravel NO tiene migraciones para ellas, solo modelos Eloquent / queries que las leen. Ver [03-base-de-datos.md](03-base-de-datos.md) y [scraper-03-modelo-de-datos-y-normalizacion.md](scraper-03-modelo-de-datos-y-normalizacion.md).
* **Laravel es dueño de las tablas de aplicación** (`users`, `alertas`, `planes`, `suscripciones`, `sessions`, `cache`, `jobs`, …), creadas por migraciones.
* Los datos **no son en tiempo real**: cada ejecución del scraper genera una *corrida* (snapshot). El dashboard muestra por defecto la corrida más reciente y permite comparar corridas en el tiempo.

## Funcionalidades principales (resumen)

| Área | Qué hace | Doc |
|---|---|---|
| Dashboard público | 11 secciones (pestañas) con análisis de ofertas; hoy solo 4 visibles en el menú | [05-dashboard-secciones.md](05-dashboard-secciones.md) |
| Autenticación | Registro con **código de 6 dígitos por email**, login con rate limit, recuperar contraseña | [06-autenticacion.md](06-autenticacion.md) |
| Alertas | El usuario define alertas por marca / tienda / producto; el comando `alertas:procesar` las envía por email o Telegram | [07-alertas-y-notificaciones.md](07-alertas-y-notificaciones.md) |
| Planes y pagos | Planes Gratis / Pro / Premium, checkout MercadoPago + webhook | [08-planes-y-pagos.md](08-planes-y-pagos.md) |
| Admin | KPIs de DB, Google Analytics, tests de integraciones, envío manual de alertas, gestión de admins | [09-panel-admin-y-analytics.md](09-panel-admin-y-analytics.md) |
| Scraper | Python + Playwright/requests; scrapea las tiendas, normaliza y guarda | `scraper-0X-*.md` |

## Público objetivo / modelo de negocio

* Visitantes anónimos: pueden ver el dashboard completo (no hace falta login para las pestañas).
* Usuarios registrados (email verificado): pueden crear alertas y guardar "favoritos" (filtros guardados de la tabla).
* Planes pagos: `Plan` define `max_alertas` y `canal_telegram` (ver [08-planes-y-pagos.md](08-planes-y-pagos.md); **ojo:** actualmente esos límites no se aplican en el código de alertas).

## Glosario

| Término | Significado |
|---|---|
| **Corrida** | Una ejecución completa del scraper = un snapshot de precios en un momento. Tabla `corridas`. |
| **Observación** | Un precio observado de un producto en una corrida. Tabla `observaciones` (precio lista, precio actual, % descuento, tipo de descuento, fuente). |
| **Producto** | Un ítem único de una tienda (nombre, marca, URL, tamaño ml, género). Tabla `productos`. |
| **Tienda** | Un sitio scrapeado (ej. Juleriaque). Tabla `tiendas`. |
| **Rebaja** | `observaciones.tipo_descuento = 'rebaja'`: precio tachado/precio lista real vs. actual. Es lo que muestra el dashboard. |
| **Transferencia** | `tipo_descuento = 'transferencia'`: precio especial por pago con transferencia/efectivo. Solo pestaña "Transfer". |
| **Joya / Destacado** | Perfume de una marca/línea del catálogo curado (`Dashboard::CATALOGO`) con descuento ≥ umbral (30% por defecto). La pestaña `joyas` se rotula "Destacados" en el menú. |
| **Snapshot** | Sinónimo de corrida en la UI. |
| **Fuente** | `observaciones.fuente`: cómo se detectó el descuento (ej. `tachado`, `dos_precios`, `badge_*`). Ver scraper docs. |
