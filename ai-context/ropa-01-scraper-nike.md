# ropa-01 · Scraper de ropa y calzado (Nike Argentina)

Script: `descuentos_ropa.py` (raíz, versionado). Lanzador: `scripts/descuentos_ropa.bat` (log en `logs_ropa.txt`). Rubro `ropa` (ver [14](14-multirubro.md)). Hermano de `descuentos_master.py` (perfumes), pero **independiente**: no comparten código todavía.

## Cómo es nike.com.ar (investigado el 2026-10-29)

| Aspecto | Hallazgo |
|---|---|
| Plataforma | **VTEX IO** (cuenta `nikearprod`), tienda sobre Cloudflare |
| HTTP directo (`requests`) | **403** con desafío "Just a moment…" → no sirve el truco de la API VTEX usado en los perfumes |
| Navegador real (Playwright headless) | Carga la página (200). **Aun así hay rate limit**: tras varias visitas seguidas devuelve **429** ("Un momento…"); las llamadas XHR secundarias del front (`/_v/public/graphql`, etc.) dan 429 casi siempre. Hay que ir despacio |
| Datos de producto | Embebidos en el HTML SSR: `<template data-varname="__STATE__"><script>{…}</script>` (estado Apollo de VTEX). **No hace falta ninguna API** |
| Listado | clave `$ROOT_QUERY.productSearch({...from:0,to:17...})` → `products` (refs), `recordsFiltered` (total). **18 productos por página**; paginación `?page=N` |
| Producto (`Product:sp-<id>`) | `productName`, `productReference` (modelo+color, ej. `IH0745-010`), `brand`, `link`, `categories`, `priceRange.sellingPrice.lowPrice` / `listPrice.lowPrice`, `specificationGroups` (Género, Tipo de producto, Color PDP, Categorías, Disciplina…), `items({"filter":"ALL_AVAILABLE"})` = SKUs |
| SKU | `variations` (talle, color), `sellers[0].commertialOffer` (`Price`, `ListPrice`, stock). El precio es el mismo para todos los talles → un descuento por producto + lista de talles |
| Secciones de ofertas | `/oportunidades/{hombre\|mujer\|nino-a}/{calzado\|ropa}` (+ Flash Days, Jordan, etc.) |
| Árbol de categorías | `/hombre\|mujer\|nino-a/{ropa\|calzado\|zapatillas}/<sub>` (remeras, buzos, camperas, pantalones-calzas, shorts-bermudas, medias, gorras…), `/accesorios/<sub>` |
| Listados por categoría | `/hombre/calzado` (805 productos), `/hombre/ropa`, `/mujer/…`, `/nino-a/…`, `/accesorios` son **listados reales** y aceptan `?order=OrderByBestDiscountDESC` (el mismo "Ordenar por descuento" del sitio) y `&page=N`. *Corrección:* durante las primeras pruebas parecían páginas vacías por un **bug del parser**: el estado trae **varias** consultas `productSearch` (una vacía de un carrusel + la real) y se tomaba la primera; ahora se elige la de más productos |

## Cómo funciona el script

1. Un solo `chromium` (headless), un contexto con UA de Chrome y `locale es-AR`; se **bloquean imágenes, fuentes y media** (menos carga).
2. Por cada sección: `cargar_pagina()` → `page.goto(url)`; pausa de **6–10 s × factor de ritmo** antes de cada página (el factor sube ×1,6 tras cada bloqueo hasta ×4 y baja ×0,92 con cada página exitosa: el script se autorregula); si el título es "Un momento…" o el status 403/429 → **espera creciente (45 s, 90 s, 135 s)** y hasta 3 intentos; si no cede lanza `Bloqueado` y el script sigue con la siguiente sección. Entre secciones, 8–14 s.
3. `extraer_state()` parsea el JSON; `productos_de_state()` arma `ItemRopa` (tienda, marca, nombre, referencia, género, categoría, tipo, color, talles con stock ordenados XS→XL / numéricos, precio lista/actual, % descuento, URL).
4. Deduplica por `(tienda, referencia)` entre secciones; pagina hasta `recordsFiltered` o página sin nuevos.
5. Salida: Excel en `excel_ropa/descuentos_ropa_<d>-<m>-<Y>_<H-M-S>.xlsx` (solo productos con descuento), `--json` opcional con todos, y **MySQL** (a menos que `--no-db`).
6. Guardado en MySQL (`guardar_en_mysql`): corrida `rubro='ropa'`, upsert de tienda/producto, observaciones de **todos** los productos vistos (`rebaja` o `ninguno`), `corrida_sitios` por sección (`nike_<género>_<categoría>`, plataforma `vtex_io`). **Control de calidad:** una sección *vacía* (la página cargó pero no hay ofertas) es legítima; invalidan la corrida las secciones *bloqueadas* o con *error*: si más del 30 % fallan (`--umbral-secciones`, 0,7) o no hay ningún producto, no guarda (código de salida 3) salvo `--force-db`. Requiere la migración multi-rubro (si falta `corridas.rubro` aborta con mensaje).

## Uso

```
python descuentos_ropa.py --solo nike --no-db                                  # prueba completa (6 secciones, ~15-25 min), solo Excel
python descuentos_ropa.py --solo nike --seccion oportunidades/hombre/ropa --max-paginas 2 --no-db   # prueba corta
python descuentos_ropa.py --solo nike --json salida.json --no-db
scripts\descuentos_ropa.bat                                                    # corrida normal → MySQL
```
Opciones: `--modo descuento|oportunidades` (defecto `descuento`), `--seccion TEXTO…` (filtra por substring del path), `--max-paginas`, `--min-descuento`, `--headful`, `--force-db`, `-o`. **Ojo en Git Bash:** `--seccion /hombre/ropa` se convierte en una ruta de Windows; usar `hombre/ropa` sin barra inicial.

## Resultados de las pruebas (2026-10-29)

Corrida completa de `--solo nike` (6 secciones de `/oportunidades`), 20:00→20:22 (**22 min**, ~12 bloqueos 429/403 absorbidos por el ritmo adaptativo, ninguna sección perdida por bloqueo):

| Sección | Estado | Productos | Con descuento |
|---|---|---|---|
| hombre / ropa | ok | 298 | 292 |
| mujer / ropa | ok | 102 | 96 |
| niños / ropa | ok | 57 | 57 |
| niños / calzado | ok | 43 | 43 |
| hombre / calzado | **vacía** | 0 | 0 |
| mujer / calzado | **vacía** | 0 | 0 |
| **Total únicos** | | **497** | **485** (hasta 60 % OFF, promedio 39,9 %) |

* **CORRECCIÓN (el usuario lo detectó con una captura):** el calzado de hombre/mujer **sí tiene ofertas**; solo estaban vacías las URLs `/oportunidades/hombre|mujer/calzado`. Por eso el modo por defecto pasó a `descuento` (categorías ordenadas por mayor descuento, ver abajo). Lo siguiente describe lo observado en la primera corrida (modo `oportunidades`): `/oportunidades/hombre/calzado` y `/oportunidades/calzado` muestran "no encontramos resultados" (verificado en el HTML): hoy no hay calzado de adulto en oferta por esa vía (`/oportunidades/hombre/zapatillas` da 404). La sección queda en el scraper por si aparece.
* Con los 12 productos sin descuento incluidos el script guarda 497; se importaron a MySQL 485 (los del Excel) como corrida #158 (`rubro='ropa'`, 20:00:33): 291 hombre-ropa, 94 mujer-ropa, 57 niños-ropa, 43 niños-calzado. Dashboard `/ropa/tabla` verificado: 485 filas, filtros de categoría/género, KPI propios (485 productos, 39,9 % promedio, 60 % máximo).
* Guardado desde el propio script: probado con una corrida de 1 sección (18 productos; luego borrada). Primera corrida completa **importada desde su Excel** (para no repetir 22 min de scraping contra Nike); la próxima corrida programada ejercita el camino completo.
* Pendiente: ejecución programada, 2.ª corrida (para que Cambios/Evolución tengan contenido) y medir la cobertura de `/oportunidades`.

## Corrida completa en modo `descuento` (2026-10-29 20:31 → 21:03, 32 min) — corrida #159

| Sección | Total en el sitio | Páginas leídas hasta cortar | Productos vistos | Con descuento |
|---|---|---|---|---|
| hombre / calzado | 805 | ~24 | 429 | 409 |
| hombre / ropa | 856 | ~18 | 324 | 294 |
| mujer / calzado | 432 | ~12 | 216 | 188 |
| mujer / ropa | 409 | ~7 | 126 | 101 |
| niños / calzado | 129 | ~4 | 72 | 48 |
| niños / ropa | 140 | ~5 | 90 | 57 |
| accesorios | 370 | ~5 | 90 | 58 |
| **Total únicos** | | | **1.316** | **1.124** (hasta 60 %, promedio 37,7 %) |

Guardado solo por el propio script en MySQL (camino completo `descuentos_ropa.bat` → MySQL validado). Se leyó ~30 % del catálogo para obtener el 100 % de las ofertas. La corrida de prueba #158 (modo `oportunidades`, 485 productos, sin calzado de adulto) se eliminó para que Cambios/Evolución no comparen coberturas distintas. Esa corrida arrancó antes de agregar la captura de `imagen`: `productos.imagen` queda vacío hasta la próxima corrida.

## Modo `descuento` (por defecto desde 2026-10-29)
Secciones: `/hombre|mujer|nino-a/{calzado,ropa}` y `/accesorios`, todas con `?order=OrderByBestDiscountDESC`. Como el listado viene ordenado de mayor a menor descuento, se pagina **hasta la primera página sin ningún producto con descuento** (`cortar_sin_descuento`): cobertura completa de las ofertas con pocas páginas, y sin depender de `/oportunidades` (que no incluía todo el calzado). Verificado: `/hombre/calzado` 3 páginas → 54 productos con descuento; Nike Alphafly 3 $529.999 → $212.000 (60 %), igual que en el sitio.

## Límites y riesgos

* Rate limit / Cloudflare: se mitiga con ritmo lento y reintentos; **no se evade** la protección. Si Nike bloquea (429 sostenido o desafío), el script marca la sección `bloqueado` y sigue. Si se vuelve inviable, habrá que replantear (ritmo más lento, otra hora, o pedir datos por otra vía).
* (Reemplazado) modo `catalogo`: **experimental**. Recorre subcategorías de ropa; solo se verificó `/hombre/ropa/remeras`. Falta medir cuántos descuentos existen fuera de `/oportunidades` (cobertura) antes de decidir si vale la pena.
* Las secciones de oportunidades incluyen algunos productos sin descuento (17 de 299 en hombre/ropa): se guardan como `ninguno`.
* `imagen`: se guarda la URL de la primera foto del primer SKU (`items[].images[0].imageUrl` del estado; no se descargan archivos). La UI la muestra con hotlink al CDN de Nike (`vtexassets.com`): revisar condiciones de uso antes de exponerlo públicamente.
* `descuento = (lista − actual) / lista` tomando `priceRange.lowPrice`; si un producto tiene variantes con distinto precio podría subestimarse.
* Términos de uso de Nike: se consultan solo páginas públicas a ritmo humano-lento; conviene revisar sus condiciones antes de escalar.

## Cómo agregar otro sitio de ropa (Lacoste, etc.)
Agregar una entrada en `SITES` con su `host`, `secciones` y, si no es VTEX IO con `__STATE__`, una función `productos_de_*` equivalente; mantener el mismo `ItemRopa`. Registrar el sitio en `config/rubros.php` no hace falta (el rubro ya existe). Lacoste: investigar primero su plataforma (hay un HTML guardado en `Clases\descuentos\lacoste html para claude.txt`).

## Lacoste (investigado, bloqueado)
`www.lacoste.com/ar/` es **Salesforce Commerce Cloud (Demandware)**; sección de ofertas: `/ar/lacoste/oportunidades/`. El HTML que habías guardado a mano muestra que cada tarjeta (`js-product-tile`, `data-pid`) trae un `<script class="js-product-tile-tracking">` con un JSON `{"items":[{"item_id","item_name","item_category2","item_category3":"MAN|WMN…","item_custom_brand","size_availability","price","full_price","discount"}]}`: precio, precio de lista y descuento ya calculados, 32 tarjetas por página. **Pero** una visita con Playwright headless devolvió **403 "Access Denied"** (protección anti-bots). Se decidió no intentar evadirla (stealth/proxies). Opciones: contactar a Lacoste por un feed/afiliados, ejecutar desde un navegador normal con el usuario presente, o descartar el sitio. Hasta decidir, no hay scraper de Lacoste.

## Atributos extra del producto (2026-10-30)
Specs útiles de Nike que se guardan: `Tipo de producto` (→ `tipo`), `Disciplina Extendida` (→ `disciplina`, Fútbol/Running/Moda/Básquet/Golf…), `Color PDP` (→ `color`). `Icono` (→ `linea`: Air Max, Air Force 1, Mercurial, Phantom, Dunk…) también se guarda desde la corrida del 31/10. **No** se guardan aún: `Colecciones`, `Tecnología` (Flyknit, Zoom Air…), `Superficie`, `Altura de Bota`.

## Primera corrida programada (2026-10-30 15:00, corrida #164) y corrección del corte de paginación

* La tarea `descuentos_ropa` corrió sola a las 15:00 (resultado 0) y terminó 15:25: 7 secciones sin bloqueos, **1.153 productos / 982 con descuento** (luego 1.299 / ~1.110 tras la corrección). `productos.imagen` 100 %, `disciplina` 1.133 productos, `linea` 379 (Air Max 67, Air Force 1 38, Mercurial 36, Air Jordan 1 31, Dunk 26…).
* **Bug (mío) en el corte de paginación:** el fin del listado se decidía con `len(items) < 18` (productos *legibles*). En hombre/ropa la página 9 trajo 18 productos de los cuales 2 no tenían precio, quedaron 16 → el script cortó y se perdieron ~150 productos (160 contra 324 del día anterior). **Corregido:** `productos_de_state()` devuelve también `n_crudos` (productos devueltos por el sitio) y el corte usa `crudos < PAGINA_TAMANO`.
* **Opción nueva `--fecha AAAA-MM-DDTHH:MM:SS`**: guarda dentro de la corrida existente con esa `fecha_hora` (sirve para completar una sección fallida sin crear otra corrida; no genera Excel). `guardar_en_mysql` ahora **recalcula** `total_productos`, `min_descuento` y `max_descuento` de la corrida desde `observaciones`, así que es correcto aunque se complete por partes. Ejemplo usado: `python descuentos_ropa.py --solo nike --seccion hombre/ropa --fecha 2026-10-30T15:00:02` (306 productos, 288 con descuento). Usar el formato con `T` (sin espacios): los espacios se parten al pasar por `.bat`/`Start-Process`.
* **Cómo detectar algo así:** comparar contra el día anterior por sección (`corrida_sitios.con_descuento`); una caída fuerte de una sola sección es señal de corte prematuro o bloqueo.

## Talles normalizados (2026-10-30)
`guardar_en_mysql` guarda también `productos.talles_norm` (`normalizar_talles(genero_db, categoria, talles)`): en calzado interpreta el número por género (hombre `H`, mujer `M`), parte `H 10 / M 11.5` y deja niños, ropa y accesorios igual. Verificado con la distribución real: calzado masculino usa números de hombre (8–13), femenino números de mujer (5–9.5), solo ~28 talles unisex vienen en formato doble.
