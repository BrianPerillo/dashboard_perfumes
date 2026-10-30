# 14 · Multi-rubro (perfumes, ropa y los que vengan)

Desde 2026-10-29 el sitio deja de ser "solo perfumes": cada **rubro** tiene sus tiendas, scraper, corridas y productos, pero comparte usuarios, alertas, planes, admin, layout y el dashboard. Rubros hoy: `perfumes` (por defecto) y `ropa` (Nike Argentina; Lacoste y otros después).

> **Estado (2026-10-29):** migración **aplicada** a MySQL (todo lo existente = `perfumes`), dashboard `/ropa/*` y perfumes verificados contra la base real (todas las pestañas cargan), scraper de ropa guardando en MySQL (probado; corrida real de Nike: 1.124 productos con descuento en `/ropa/tabla`), alertas por rubro implementadas. Pendiente: ver [12](12-problemas-conocidos-y-pendientes.md).

## Decisión de arquitectura

Mismo proyecto, **datos compartidos + columna `rubro`**, scraper independiente por rubro. Motivo: la parte cara de mantener (login, verificación, alertas email/Telegram, planes y MercadoPago, admin, Analytics, layout) es idéntica; un proyecto aparte la duplicaría y obligaría a dos cuentas/suscripciones. Alternativa descartada: tablas separadas `ropa_*` (duplicaría todas las consultas del dashboard).

## Configuración central: `config/rubros.php`

Fuente de verdad de rubros. Por rubro: `nombre`, `icono`, `items_label` (KPI), `prefijo` de URL (perfumes = vacío por compatibilidad), `tab_inicial`, `tabs` (visibles en menús: `[etiqueta, icono, en_sidebar]`), `tabs_rutas` (válidas por URL, incluidas las ocultas), `generos` (valor de `productos.genero` → etiqueta), `filtro_ml` y `filtro_categoria` (qué filtros se muestran).

**Para sumar un rubro nuevo:** (1) entrada en `config/rubros.php`; (2) scraper propio que escriba con `rubro='<slug>'`; (3) listo: rutas, selector de rubro y menús salen de la config. Test: `tests/Unit/RubrosConfigTest`.

## Modelo de datos (migración `2026_10_30_000000_add_rubro_multirubro`)

| Tabla | Cambio |
|---|---|
| `tiendas` | `rubro` VARCHAR(20) NOT NULL DEFAULT `'perfumes'` (+ índice) |
| `corridas` | `rubro` (default `'perfumes'`); la unicidad pasa de `(fecha_hora)` a `(rubro, fecha_hora)` |
| `productos` | nuevas columnas opcionales: `categoria`, `tipo`, `color`, `talles`, `referencia`, `imagen`; `genero` enum + `'ninos'` |
| `alertas` | `rubro` (default `'perfumes'`): el formulario permite elegir rubro, las sugerencias (marcas/tiendas/productos) son del rubro y `ProcesarAlertas` solo mira corridas de ese rubro |

* Todo lo existente queda como `perfumes` por `DEFAULT`; `descuentos_master.py` **no necesita cambios** (no escribe `rubro`).
* Ropa mapea género: hombre→`masculino`, mujer→`femenino`, `ninos`, `unisex` (las etiquetas por rubro salen de `config/rubros.php`).
* `observaciones` es idéntica: `precio_lista`, `precio_actual`, `descuento_pct`, `tipo_descuento` (`rebaja` si hay descuento, `ninguno` si no; el dashboard solo muestra `rebaja`), `fuente='vtex_state'`.
* Identidad de producto de ropa: `nombre_norm` = `referencia` en minúsculas (modelo+color, ej. `ih0745-010`), estable aunque cambie el nombre.
* El migration ignora todo si no existen las tablas o si el driver no es MySQL (tests con sqlite).

## Rutas y navegación

* Perfumes: sin cambios (`/joyas`, `/tabla`, …). Ropa: `/ropa` → redirige a `/ropa/tabla`; `/ropa/{tab}` (`tabs_rutas` de ropa). Generadas en `routes/web.php` recorriendo `config('rubros.rubros')`; la ruta comodín de perfumes sigue al final.
* Layout `components.layouts.app`: nuevo prop `rubro`; **las 3 listas de pestañas ahora salen de la config** (antes estaban triplicadas) y hay un **selector de rubro** (🧴 Perfumes | 👕 Ropa y calzado) en el sidebar y en la barra móvil. Helpers de blade: `$tabUrl($tab)`, `$rubroUrl($slug)`.
* Pestañas de ropa hoy: Ofertas (`tabla`), Cambios recientes, Evolución, Seguir producto, Marcas. (`joyas`, `transfer`, ml y catálogo de "joyas" son solo de perfumes.)

## Componentes parametrizados por rubro

* `Livewire\Dashboard::mount($activeTab, $rubro)`: tiendas, corridas, marcas y ml disponibles, géneros (`generosDisponibles`) y filtros de sesión (`dashboard_filtros_{rubro}`) son del rubro. Nueva propiedad `categoriaFiltro` (ropa/calzado/accesorios) y acción `setCategoria()`; la tabla agrega `talles`, `categoria`, `tipo`, `color`.
* `Livewire\KpiPanel::mount($rubro)`; `dashboard.blade.php` pasa `rubro` a layout/KPI/dashboard.
* Modelos: `Corrida::deRubro($rubro)` y `Corrida::ultima($rubro = 'perfumes')`; `Tienda::deRubro()`; `Alerta` acepta `rubro`.
* **Regla:** toda consulta "de la última corrida" debe filtrar por rubro (`Corrida::deRubro`/`ultima`). Las consultas por `tienda_id` quedan acotadas solas porque `tiendasSeleccionadas` ⊆ tiendas del rubro.

## Pendiente (ver [12](12-problemas-conocidos-y-pendientes.md))
Pestañas propias de ropa (por tienda, comparar entre sitios); crear la tarea de Windows del scraper de ropa (`scripts/instalar_tareas_windows.ps1`, diaria 15:00); segundo sitio: **Lacoste está bloqueado a navegadores automatizados** (ver [ropa-01](ropa-01-scraper-nike.md)).

> **Layout (2026-10-30):** el menú lateral muestra solo los filtros de la página; las pestañas de cada rubro (`config/rubros.php` → `tabs`) se navegan desde el header. Ver [10](10-frontend-layouts-ui.md).
