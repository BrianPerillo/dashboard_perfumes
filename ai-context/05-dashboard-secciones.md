# 05 · Dashboard: secciones (pestañas), filtros y lógica

Código principal:
* Componente: `app/Livewire/Dashboard.php` (~1200 líneas) y `app/Livewire/KpiPanel.php`
* Vista wrapper: `resources/views/dashboard.blade.php` (arma el panel de KPIs plegable + monta `@livewire('dashboard')`)
* Vista del componente: `resources/views/livewire/dashboard.blade.php` (Alpine con gráficos ApexCharts, panel de filtros teleportado al sidebar con `x-teleport="#sidebar-filters"`, loading overlay)
* Una vista por pestaña: `resources/views/livewire/dashboard/tab-*.blade.php` (se incluyen todas; cada una se muestra con `x-show="tab==='...'"`)

## Datos de origen

Todo sale de las tablas llenadas por el scraper (ver [03-base-de-datos.md](03-base-de-datos.md)): `corridas`, `tiendas`, `productos`, `observaciones`. El dashboard **siempre trabaja sobre la corrida más reciente** (`corridaActualId = corridas[0]`) salvo las pestañas históricas (evolución, seguir, cambios).

### Filtro base común a (casi) todas las queries

```
observaciones.tipo_descuento = 'rebaja'
AND (fuente IS NULL OR (fuente <> 'dos_precios' AND fuente NOT LIKE 'badge_%'))   -- Observacion::filtrarFuentesValidas()
AND descuento_pct BETWEEN descMin AND descMax                -- default 0..90
AND productos.tienda_id IN (tiendas seleccionadas)
AND productos.genero IN (géneros seleccionados)              -- solo si no están los 3
AND productos.tamano_ml IN (ml seleccionados)                -- solo si no están todos
AND LOWER(productos.marca) NOT IN ('rimmel')                 -- MARCAS_EXCLUIDAS (no son perfumes)
```

> **Nota:** desde 2026-10-29 el filtro de fuentes está centralizado en `Observacion::filtrarFuentesValidas($query, 'o.fuente')` (y `Observacion::scopeRebajas`). Antes se repetía ~15 veces con `whereNotIn(..., ['dos_precios','badge_%'])`, donde `'badge_%'` se comparaba como texto literal y no excluía nada. Las marcas excluidas viven en `Observacion::MARCAS_EXCLUIDAS` (usada por `Dashboard`, `KpiPanel` y `alertas:procesar`). Fuentes realmente presentes hoy: `api`, `tachado`, `tiendanube` (ver [03](03-base-de-datos.md)).
>
> Por qué se excluyen: según `Mejoras.md`, la fuente `dos_precios` genera **falsos descuentos** (ej. en Juleriaque); la única fuente confiable es el precio **tachado**.

## Filtros globales (panel en el sidebar)

Propiedades públicas del componente `Dashboard`:

| Propiedad | Qué filtra | Persistencia |
|---|---|---|
| `tiendasSeleccionadas` | Tiendas (default: todas) | sesión `dashboard_filtros.tiendas` |
| `generosSeleccionados` | `masculino`, `femenino`, `unisex` | sesión |
| `descMin` / `descMax` | Rango de % descuento (0–90) | sesión |
| `mlSeleccionados` / `mlDisponibles` | Tamaños en ml (de `productos.tamano_ml`) | **no** se persiste |
| `tablaFiltroBuscar`, `tablaFiltroMarcas`, `tablaFiltroTiendas` | Solo pestaña **Tabla** | no |
| `joyasUmbral` (default 30), `joyasTiendaFiltro`, `joyasOcultarFreq` | Solo **Joyas/Destacados** | no |
| `cambiosDesdeCorrida` / `cambiosHastaCorrida` | Solo **Cambios** | no |
| `seguirProdKey` | Solo **Seguir** (`"tienda||producto"` en minúsculas) | no |

Cualquier cambio global llama `recargarTodo()`: vacía los JSON cacheados en propiedades, recarga **solo la pestaña activa** (carga lazy en `cargarDatosParaTab`) y emite el evento `charts-refresh`.

## KPI Panel (`KpiPanel` + `kpi-panel.blade.php`)

Plegable ("Indicadores"). Compara las **2 últimas corridas**: perfumes en oferta (+delta), descuento promedio (+delta en pts), descuento máximo, tiendas activas (incluye observaciones de transferencia). Muestra rango "datos desde … hasta …" (primera y última corrida).

## Pestañas

| Ruta | En menú* | Vista | Método(s) en `Dashboard.php` | Qué muestra |
|---|---|---|---|---|
| `/joyas` | ✅ "Destacados" | `tab-joyas` | `cargarJoyas()`, `matchJoyas()` | Cards agrupadas por marca de perfumes "joya" de un catálogo curado con descuento ≥ umbral |
| `/tabla` | ✅ | `tab-tabla` | `cargarTabla()`, `cargarMasTabla()`, `buildTablaQuery()` | Tabla/cards de todos los productos en rebaja, orden hasta 3 niveles, búsqueda, favoritos, infinite scroll (100 por página) |
| `/cambios` | ✅ | `tab-cambios` | `cargarCambios()` | Diferencias entre dos corridas |
| `/evolucion` | ✅ | `tab-evolucion` | `cargarEvolucion()` | 3 gráficos temporales por corrida |
| `/comparar` | ❌ oculta | `tab-comparar` | `cargarComparar()` | Mismo perfume en 2+ tiendas, spread de precios |
| `/seguir` | ❌ oculta | `tab-seguir` | `cargarSeguirOpciones()`, `cargarSeguir()` | Historial de un producto en una tienda |
| `/tiendas` | ❌ oculta | `tab-tiendas` | `cargarResumenTiendas()` | Resumen por tienda |
| `/distribucion` | ✅ (solo barra superior/móvil) | `tab-distribucion` | `cargarDistribucion()` | Histograma de descuentos |
| `/transfer` | ✅ (solo barra superior/móvil) | `tab-transfer` | `cargarTransfer()` | Ofertas por transferencia/efectivo |
| `/marcas` | ✅ (solo barra superior/móvil) | `tab-marcas` | `cargarMarcas()` | Ranking de marcas (top 40) |
| `/conclusiones` | ❌ oculta | `tab-conclusiones` | `cargarConclusiones()`, `generarNarrativa()` | Texto automático + puntos clave |

\* Ver las tres listas de menú en [04-rutas-y-navegacion.md](04-rutas-y-navegacion.md): el sidebar muestra solo 4 pestañas; la barra superior y la móvil muestran 7. `comparar`, `seguir`, `tiendas` y `conclusiones` están ocultas de todos los menús (rutas activas).

### Joyas / Destacados (`/joyas`)

* **Catálogo curado** hardcodeado en `Dashboard::CATALOGO`: ~17 marcas (Versace, Dior, Chanel, Armani, YSL, Rabanne, Carolina Herrera, Jean Paul Gaultier, Dolce & Gabbana, Calvin Klein, Hugo Boss, Montblanc, Azzaro, Givenchy, Lancôme, Issey Miyake, Bvlgari, Guerlain), cada una con `tokens` (para reconocer la marca en nombre/marca del producto) y `lineas` (nombre de línea → palabras clave que deben aparecer en el nombre del producto).
* `matchJoyas()` cruza las filas en rebaja contra el catálogo; deduplica por `producto|tienda`; aplica umbral (`joyasUmbral`, default 30%).
* Orden: por prioridad de marca (`brandPriority`: Versace 1, YSL 2, Armani 3, Dior 4, Chanel 5, D&G 6, Hugo Boss 7, Rabanne 8, JPG 9, Givenchy 10, Montblanc 11, Carolina Herrera 12; resto 99), luego % desc descendente. **Hay una copia de este orden también en `tab-joyas.blade.php`** (`$joyasBrandOrder`): al cambiarlo, tocar ambos.
* Enriquece cada joya con `freq_pct` (% de corridas totales donde estuvo en rebaja) y `dias_en_oferta` (días desde la primera vez en rebaja). Badges en la card: `⚠️ siempre en oferta` (≥365 días), `📌 habitualmente en oferta` (≥30), `🆕 oferta reciente` (<7), o `en oferta X% del tiempo`.
* "Ocultar siempre en oferta" (`joyasOcultarFreq`): descarta `freq_pct ≥ 85` cuando hay ≥3 corridas.
* Colores/íconos por % : ≥40 verde 🔥🔥; ≥30 🔥; ≥20 violeta; resto gris ✅.

### Tabla completa (`/tabla`)

* `buildTablaQuery()` une `observaciones/productos/tiendas/corridas`, filtra la corrida actual, aplica filtros globales + de tabla, y hasta 3 niveles de orden con whitelist de columnas (`descuento_pct, precio_actual, precio_lista, ahorro, marca, tienda, producto, fecha`).
* Paginación por *infinite scroll*: `TABLA_POR_PAGINA = 100`, `cargarMasTabla()` agrega páginas; `tablaTieneMas`, `tablaTotalCount`.
* **Favoritos** (solo logueados): guarda en tabla `favoritos` (modelo `Favorito`, `tipo='tabla'`) la configuración de filtros/orden (`config` JSON). Métodos: `guardarFavorito`, `aplicarFavorito`, `borrarFavorito`.

### Cambios (`/cambios`)

Compara dos corridas (`cambiosDesdeCorrida` default = la más antigua; `cambiosHastaCorrida` default = la última). Clave de producto = `lower(tienda)||lower(producto)`.
* **Nuevos en oferta**: están en "hasta" y no en "desde" (muestra 60, total real en `totalNuevos`).
* **Salieron de oferta**: al revés.
* **Mayores subas / bajas de descuento**: productos en ambas con delta de % (top 15 c/u).

### Evolución (`/evolucion`)

Serie por corrida: total en oferta, % desc promedio y máximo, ahorro total, nº tiendas. 3 gráficos ApexCharts (`chart-evo-prod`, `chart-evo-desc`, `chart-evo-ahorro`). Base para "Conclusiones".

### Comparar (`/comparar`)

Agrupa por nombre normalizado (`LOWER(p.nombre)`) en la corrida actual; solo productos en ≥2 tiendas con diferencia de precio >0; devuelve top 60 por ahorro (`precio_max - precio_min`) y `spread_pct`.

### Seguir (`/seguir`)

Selector de producto (opciones = todas las combinaciones tienda||producto que alguna vez estuvieron en rebaja) → serie histórica de precio lista, precio actual y % descuento (gráfico `chart-seguir`, evento `seguir-refresh`).

### Tiendas / Distribución / Marcas

* **Tiendas**: por tienda, cantidad, % desc promedio y máximo, ahorro total (corrida actual).
* **Distribución**: histograma en bins de 5 pts: `FLOOR(desc/5)*5` → "0–5%", "5–10%", …
* **Marcas**: top 40 por cantidad con desc promedio y máximo (excluye marca vacía).

### Transfer (`/transfer`)

Usa `tipo_descuento = 'transferencia'` y `precio_transferencia < precio_actual`; top 200 por ahorro relativo. Pensado para tiendas que ofrecen precio menor pagando por transferencia/efectivo. Envuelto en try/catch (si falla devuelve `[]`).

### Conclusiones (`/conclusiones`)

Texto generado por reglas (sin IA): puntos clave (cobertura, crecimiento de ofertas, pico, evolución del % promedio, mayor ahorro, tienda líder, mejor joya) y `generarNarrativa()` (2 párrafos). Correlaciona el pico con **fechas comerciales** (`eventosComerciales()`: San Valentín 14/2, Día del Padre (3er domingo de junio), Día del Amigo 20/7, Día de la Madre (3er domingo de octubre), Black Friday (4º viernes de noviembre), Navidad) con tolerancia de ±5 días.

## Frontend de las pestañas

* `livewire/dashboard.blade.php` define el `x-data` Alpine con `tab` (entangled con `tabActiva`), `charts` y los `build*()` de ApexCharts (`buildEvolucion`, `buildDistribucion`, `buildSeguir`) que respetan modo oscuro leyendo `html.dark`.
* El panel de filtros se **teleporta** (`x-teleport`) a `#sidebar-filters` (desktop, bajo el ítem activo del sidebar; ~l.189 de `livewire/dashboard.blade.php`) y hay **una segunda copia casi idéntica** teleportada a `#mobile-filters` (dropdown móvil, ~l.427). Si se agrega/cambia un filtro hay que modificar **ambos bloques**. No se muestra en `marcas` ni `distribucion`.
* Evento `filter-count-changed` informa el nº de filtros activos al layout (badge rojo en el ítem de menú).

## Rubros (desde 2026-10-29)
`Dashboard` y `KpiPanel` reciben el `rubro` (`perfumes` por defecto). Corridas, tiendas, géneros, marcas/ml disponibles y los filtros guardados en sesión (`dashboard_filtros_{rubro}`) son por rubro. Para **ropa**: sin joyas/transferencia/ml; filtro de **categoría** (ropa/calzado/accesorios, `setCategoria()`), géneros hombre/mujer/niños/unisex, y la tabla muestra tipo, color y **talles**. Detalle en [14](14-multirubro.md). Las etiquetas de género de perfumes siguen siendo Masculino/Femenino/Unisex (salen de la config).

## Filtros y favoritos de ropa (desde 2026-10-30)
* **Favoritos por rubro:** `favoritos.tipo` = `tabla` (perfumes, datos existentes) o `tabla_<rubro>` (ej. `tabla_ropa`); un favorito de perfumes no aparece en ropa (guardaría marcas/tiendas que allí darían 0 resultados). Helper `Dashboard::favTipo()`. La configuración guardada ahora incluye categoría, tipos y talles.
* **Filtros de ropa** (`resources/views/livewire/dashboard/filtros-ropa.blade.php`, incluido en el panel del sidebar y en el móvil; solo rubros con `filtro_categoria`): **Categoría** (ropa/calzado/accesorios), **Tipo de producto** (chips con conteo) y **Talle**. Las opciones se calculan de los productos en oferta de la corrida actual, respetando género y categoría (`cargarOpcionesRopa()`), y los talles se **agrupan por sistema** porque no son comparables: *Letras* (XS–3XL), *Niños (T / X / meses)* (2T, 6X, 6-12M), *Numérico (ropa niños)* (4, 6, 28…), *Calzado numérico* (8, 9.5…), *Calzado unisex (H / M)* (`H 10 / M 11.5` = hombre 10 / mujer 11.5), *Calzado infantil (C / Y)* (13.5C, 5.5Y), *Numérico (accesorios)* y *Otros* (32/32, XSA-C…). Un producto coincide si tiene **alguno** de los talles elegidos con stock (`productos.talles` = "XS, S, M"; búsqueda `CONCAT(', ', talles, ', ') LIKE '%, M, %'`).
* `filtrosRopa($q)` se aplica a **todas** las consultas que ya filtran por género (tabla, cambios, evolución, marcas, etc.), no solo a la tabla; selecciones que dejan de existir al cambiar género/categoría se descartan solas. Acciones: `setCategoria`, `toggleTipo`, `toggleTalle`, `limpiarTallesTipos`.
* La tabla de ropa muestra la **imagen** del producto (`productos.imagen`, URL del CDN de Nike con `loading="lazy"`; el scraper la guarda desde 2026-10-30, las corridas anteriores no la tienen).
* Textos "Perfumes en oferta" del gráfico de evolución ahora salen de `items_label` del rubro.

### Agrupar colores y rango de precio (ropa, 2026-10-30)
* **Agrupar colores del mismo modelo** (`agruparColores`, activo por defecto): Nike publica un producto por color (`productos.referencia` = `MODELO-COLOR`, ej. `IH0745-010`). La tabla agrupa por `tienda + modelo + precio lista + precio actual` (`Dashboard::agruparTablaPorModelo()`, subconsulta con `GROUP BY`): un renglón con los colores (`GROUP_CONCAT`), la unión ordenada de talles y un badge "N colores". Mantiene los 3 niveles de orden. Ejemplo real: 1.124 productos → 983 renglones. Casilla "Agrupar colores del mismo modelo" en el panel de filtros.
* **Rango de precio** (`precioMin` / `precioMax`, sobre `observaciones.precio_actual`): aplicado a todas las consultas vía `filtrosRopa()` (ej. $30.000–$60.000 → 170 productos).
* El total de la tabla se calcula con una subconsulta (`DB::query()->fromSub(...)->count()`), válido con o sin `GROUP BY`.

### Cambios y Seguir en ropa (2026-10-30)
Un mismo modelo existe en varios colores con el **mismo nombre**, así que la clave `tienda||nombre` (perfumes) los mezclaría. Para los rubros distintos de perfumes, **Cambios** y **Seguir** identifican el producto por `productos.id` (`Dashboard::claveProducto()`), y el nombre muestra el color ("Nike Stride · Negro"). Perfumes conserva la clave por nombre. La pestaña **Seguir producto** (historial de precio/descuento por corrida, 1 línea por color) pasó a ser visible en ropa (`config/rubros.php`). Verificado con una 2.ª corrida simulada en una transacción revertida: 110 nuevos en oferta, 15 subas de descuento, historial de 2 puntos; sin residuos en la base.

### Búsqueda global (desde 2026-10-30)
La búsqueda de **producto** y **marcas** salió del sidebar y es una barra debajo de "Indicadores" (`livewire/dashboard/barra-busqueda.blade.php`, incluida al tope de `livewire/dashboard.blade.php`), visible en todas las secciones del dashboard (también en móvil). Es **global**: `Dashboard::filtrosBusqueda()` (texto en `p.nombre` y `p.marca IN (...)`) se aplica a todas las consultas junto con los filtros de género/ropa, no solo a la tabla. Las marcas se agregan con autocompletado (varias, como chips con ×) y todo se guarda en la sesión (`dashboard_filtros_{rubro}` → `buscar`, `marcas`), así que **se conserva al cambiar de página**. Acciones: `toggleTablaFiltroMarca`, `limpiarBusqueda` (solo búsqueda), `limpiarFiltrosTabla` (búsqueda + orden). La búsqueda también se guarda dentro de los favoritos de la tabla.

### Prenda, deporte y barra sin contenedor (2026-10-30)
* **Prenda / tipo** (antes "Tipo de producto", ahora abierto por defecto): `productos.tipo` viene del spec "Tipo de producto" de Nike (Remera, Campera, Pantalón, Camiseta, Buzo, Shorts-bermudas, Calzas, Traje de baño…; en calzado solo `Calzado` → se muestra "Zapatillas" — y `Botines`). Nombres amigables en la vista (`$nombreTipo`), el valor filtrado es el original. Se puede combinar (Campera + Remera).
* **Deporte** (`productos.disciplina`, migración `2026_10_30_020000`): spec "Disciplina Extendida" de Nike (Fútbol, Running, Moda, Básquet, Tenis y Pádel, Golf, Atletismo, Jordan…). Sirve sobre todo para afinar el calzado. El scraper la guarda desde la corrida del 2026-10-31; **hasta entonces la sección no aparece** (sin datos). Filtro `disciplinasSeleccionadas`, acción `toggleDisciplina`, incluido en favoritos y en `limpiarTallesTipos`.
* Orden del panel de ropa: Categoría → Prenda/tipo → Deporte → Precio (+ agrupar colores) → Talle.
* La **barra de búsqueda** ya no tiene contenedor gris: va directamente sobre el fondo de la página.

### Panel de filtros: acordeón y línea (2026-10-30)
* **Comportamiento del panel lateral:** *Género* (abierto) y *Categoría* (siempre visible) quedan fijos; el resto de las secciones — Presentación (ml, perfumes), Tiendas, Prenda/tipo, Deporte, **Línea**, Precio y colores, Talle — arrancan **cerradas** y funcionan como **acordeón**: al abrir una se cierran las otras (evento de ventana `acc-abrir` + `x-effect` en cada sección; cada sección tiene un `id`). El título de cada sección muestra un contador cuando hay algo elegido.
* **Línea** (`productos.linea`, migración `2026_10_30_030000`): spec "Icono" de Nike (Air Max, Air Force 1, Mercurial, Phantom, Dunk…). Filtro `lineasSeleccionadas`/`toggleLinea`, incluido en favoritos y en el contador de filtros. El scraper la guarda desde la corrida del 2026-10-31; hasta entonces la sección no aparece.

### Prenda, Deporte y Línea: todo marcado por defecto (2026-10-30)
Estos tres atributos se comportan como **Género**: aparecen **todos seleccionados** (chips violetas) y el usuario **desmarca** lo que no quiere; contador "n/total" y botón "✓ Todos / × Ninguno" en cada sección. Internamente se guarda lo **desmarcado** (`tiposExcluidos`, `disciplinasExcluidas`, `lineasExcluidas`; vacío = sin filtro) y `filtrosRopa()` aplica `p.<col> IS NULL OR p.<col> NOT IN (excluidos)` (los productos sin ese dato siempre pasan); si se desmarca todo (`× Ninguno`) devuelve 0 resultados, igual que Género. Las exclusiones que dejan de existir al cambiar género/categoría se descartan solas, y los favoritos guardan las exclusiones. **Talle** conserva la lógica inversa (se eligen los talles que sirven) porque el producto debe tener *alguno* de ellos.

### Talles de calzado normalizados (2026-10-30)
`productos.talles_norm` (migración `2026_10_30_040000`, con backfill de los productos existentes) guarda los talles de **calzado** interpretados según el género, con la lógica de `App\Support\Talles::normalizar()` (copia en Python: `descuentos_ropa.py::normalizar_talles`; mantener iguales; test `TallesTest`): masculino `8` → `H8` (talle hombre); femenino `5` → `M5` (talle mujer); unisex `H 10 / M 11.5` → `H10` y `M11.5`; unisex con número simple → `H`; niños (`5.5Y`, `13C`) y ropa/accesorios sin cambios. Equivalencia de referencia: M = H + 1,5 (no se aplica automáticamente). El filtro **Talle** usa `COALESCE(talles_norm, talles)` y agrupa el calzado en *Calzado hombre (talle H)* y *Calzado mujer (talle M)* (+ *Calzado niños (número)* e *infantil C/Y*); las etiquetas se muestran sin el prefijo. La tabla sigue mostrando los talles originales de Nike.

## Talle de calzado argentino/europeo (2026-10-30)
Los talles numéricos **≥ 30** de calzado (Topper: 36–46) no se prefijan con H/M y `Dashboard::grupoTalle` los agrupa en **"Calzado (talle AR / EU)"**; los US de Nike siguen en "Calzado hombre (talle H)" / "Calzado mujer (talle M)". La alerta con un número simple (`44`) ya coincide con el talle sin prefijo.

## Auditoría de filtros de ropa con todas las tiendas (2026-10-30)
`tests/Feature/AuditFiltrosRopaTest.php` recorre contra la base real (solo lectura) cada tienda, categoría, una muestra de tipos/deportes/líneas, de talles, precio, búsqueda, marca y agrupar colores, y verifica que devuelvan filas coherentes. Se omite salvo con `DB_CONNECTION=mysql DB_DATABASE=perfumes_descuentos php artisan test --filter=AuditFiltrosRopa` (~3 min). Resultado 2026-10-30 con 7 tiendas: pasa (9485 aserciones). Ojo: los filtros se guardan en sesión por rubro, el test la limpia en cada caso. Los filtros de tipo/deporte dependen de la normalización de `descuentos_ropa.py` (ver [ropa-02](ropa-02-tiendas-y-plataformas.md)).

## Selector de visualización de Ofertas: tabla / tarjetas (2026-10-30)
Sobre los resultados de la pestaña Ofertas (`dashboard/tab-tabla.blade.php`) hay un switch con dos iconos: **tabla** (por defecto) y **tarjetas** (estilo e-commerce). Estado: `Dashboard::$tablaVista` (`'tabla'|'tarjetas'`), método `setTablaVista`, guardado en la sesión junto con los filtros (clave `vista` de `dashboard_filtros_{rubro}`). Solo cambia la presentación: usa las mismas filas (`tablaDatosJson`) y el mismo scroll infinito, orden y filtros. La tarjeta muestra la imagen cuadrada (`object-contain`, fondo blanco) con el badge `-NN%`, y al pie tienda · marca (clicables para filtrar), nombre (2 líneas), tipo · color · N colores, chips de talles (8 + "+N") o "Talles: consultá en la tienda", precio actual, precio de lista tachado y ahorro. Grilla 2 / 3 / 4 / 5 columnas según el ancho. Funciona igual en perfumes (sin imagen muestra el icono del rubro).

## Talle equivalente H ↔ M (2026-10-30)
En el filtro de Talle, cuando hay elegido algún talle de calzado `H…`/`M…` aparece el checkbox **"Incluir también el talle equivalente (H ↔ M, +1,5)"** (`Dashboard::$tallesEquivalentes`, `tallesConEquivalentes()`): el talle de mujer es el de hombre + 1,5 (H8 = M9.5), así que elegir `H8` suma `M9.5` y viceversa. Solo afecta al filtro (no a las alertas). Se limpia con "Restablecer…" y "Limpiar filtros". Cubierto por `AuditFiltrosRopaTest::test_talle_equivalente_hombre_mujer` (base real). Además, los talles de calzado publicados como rango o pegados (`40-40.5`, `39/40`, `38.39`) se expanden a valores sueltos (`expandir_rangos`).

## Comparar tiendas en ropa (2026-10-30)
Nueva pestaña **Comparar tiendas** (`/ropa/comparar`, `config/rubros.php` → ropa → `tabs`/`tabs_rutas`; vista `dashboard/tab-comparar.blade.php`, compartida con perfumes). `Dashboard::cargarComparar` detecta `rubro === 'ropa'` y usa `compararRopa`: agrupa las ofertas por **modelo** (`App\Support\Modelo::clave(tienda, referencia)`) y lista los que están en 2+ tiendas con diferencia de precio, mostrando por tienda precio, % y talles (la más barata en verde), la imagen y el ahorro máximo (tope 100). Respeta los filtros del sidebar. La clave es el código de la marca en minúsculas: Nike `AT5732-004`; Dexter lo guarda con prefijo de marca de 2 letras (`NIAT5732-004`, `PU…`, `TO…`) y se le quita; Puma `107154_01` → `107154-01`. Hoy cruzan ~50 modelos (Nike/Puma/Topper vendidos también por Dexter); Levi's, Kevingston, Wrangler y Prototype son marcas propias y no cruzan. **Ojo:** los talles se muestran tal cual los publica cada tienda (Nike usa talle US, Dexter/Topper talle argentino) y no son comparables entre sí. Test: `tests/Unit/ModeloTest.php`.
