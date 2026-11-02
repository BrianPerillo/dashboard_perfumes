# Scraper Python: sitios y tecnicas de extraccion

> Documento 2 de 3. Ver `scraper-01-descripcion-y-ejecucion.md` (ejecucion) y `scraper-03-modelo-de-datos-y-normalizacion.md` (datos / MySQL).
> Referencias `:linea` = `C:\xampp\htdocs\perfumes-dashboard\descuentos_master.py` (version del 2026-08-31). Verificado 2026-10-29.

---

## 1. Tabla resumen

El scraper cubre **12 tiendas** con **33 entradas** en `SITES` (l.76-265): cada entrada = tienda + genero (`masculino` / `femenino` / `unisex`). El campo `name` es el **nombre canonico** que se guarda en `tiendas.nombre`; las entradas de una misma tienda comparten `name` y se diferencian por `genero`.

| Nombre canonico (DB) | Dominio | Plataforma (`platform`) | Tecnica | Entradas | Funcion principal | `fuente` guardada | Obs. en ultima corrida (#156) |
|---|---|---|---|---|---|---|---|
| `pigmento` | www.perfumeriaspigmento.com.ar | `vtex` | API catalogo VTEX (JSON) | M, F, U | `scrap_vtex` l.666 | `api` | 250 |
| `beauty24` | www.beauty24.com.ar | `vtex` | API VTEX | M, F, U | `scrap_vtex` | `api` | 461 |
| `rouge` | www.perfumeriasrouge.com | `vtex` (`sc=1`) | API VTEX | M, F, U | `scrap_vtex` | `api` | 405 |
| `fiorani` | www.fiorani.com | `vtex` | API VTEX | M, F, U | `scrap_vtex` | `api` | 308 |
| `getthelook` | www.getthelook.com.ar | `vtex` (`sc=1`) | API VTEX | M, F | `scrap_vtex` | `api` | 168 |
| `farmalife` | www.farmalife.com.ar | `vtex` (`sc=1`) | API VTEX, varios paths | M (2 paths), F | `scrap_vtex` | `api` | 252 |
| `unishop` | unishop.com.ar | `unishop` | HTML SSR + JSON `window.__SERVER_DATA__` (sin navegador) | M, F, U | `scrap_unishop` l.806 | `api` | 290 |
| `ambrosia` | www.ambrosiaperfumes.com.ar | `tiendanube` | HTML SSR + atributo `data-variants` (sin navegador) | M, F, U | `scrap_tiendanube` l.928 | `tiendanube` | 0 entre 2026-10-22 y 2026-10-29 (regex rota); **corregida 2026-10-29**, debería volver en la corrida del 30/10 |
| `juleriaque` | www.juleriaque.com.ar | `html` | Playwright + DOM heuristico + detalle de variantes | M, F, U | `scrap_html` l.1432 | `tachado` | 152 |
| `parfumerie` | www.parfumerie.com.ar | `html` | Playwright + DOM heuristico (scroll) | M, F, U | `scrap_html` | `tachado` | 27 |
| `ruizyroca` | www.ruizyrocaperfumeria.com | `html` | Playwright + DOM heuristico (`?mpage=`) | M, F, U | `scrap_html` | `tachado` | 61 |
| `normy` | www.perfumerianormy.com.ar | `html` (WooCommerce) | Playwright + `<del>/<ins>` (paginacion `/page/N/`) | M, F | `scrap_html` | `tachado` | 269 |

M = masculino, F = femenino, U = unisex. "Obs. en ultima corrida" = filas de `observaciones` para `corrida_id=156` (solo ofertas, ver doc 03).

Orden de ejecucion en cada corrida: primero las entradas sin navegador en el orden de `SITES` (pigmento, beauty24, rouge, fiorani, getthelook, farmalife, unishop, ambrosia) y luego las HTML (juleriaque, parfumerie, ruizyroca, normy).

## 2. Conceptos comunes

### 2.1 Registro `Item` producido por todos los adaptadores
`tienda, marca, nombre, precio_lista, precio_actual, descuento_pct, fuente, url, tipo_descuento ("rebaja"|"transferencia"), precio_transferencia, genero, presentacion, imagen` (l.283-300; detalle en doc 03). El `genero` lo asigna `main()` **despues** del scraping a partir de la entrada de `SITES` (l.1821-1823 y l.1869-1871); los adaptadores no lo conocen.

### 2.2 Calculo del descuento
* `_dto(lista, actual) = round((lista - actual) / lista * 100, 1)` si `lista > actual > 0`; si no, `0.0` (l.303).
* **Precio lista** = precio "original/tachado"; **precio oferta (actual)** = precio de venta vigente. En los adaptadores API (VTEX, Unishop, Tiendanube) salen directo de campos JSON; en HTML se infieren del DOM (precio tachado = lista).
* Los items con `descuento_pct == 0` (sin oferta) los devuelven VTEX y Unishop igual; luego `main()` los descarta (doc 01 paso 7).
* Solo cuentan como descuento real en HTML las cards con **precio tachado explicito** (`fuente="tachado"`). Las fuentes `dos_precios` y `badge_%` se calculan pero se descartan por defecto (`_FUENTES_HTML_DEFAULT = ["tachado"]`, l.1389) porque daban falsos positivos (changelog 2026-06-26: el badge aparece en el listado pero el producto no tiene rebaja al abrirlo).

### 2.3 Tamano (ml) y nombre
* En VTEX se itera **cada SKU** (cada presentacion = un Item) y se arma el nombre con `_nombre_con_tamano(productName, nameComplete)` (l.344) para que incluya los ml. En Unishop/Tiendanube el nombre se toma tal cual.
* En HTML el tamano solo aparece si esta en el nombre de la card o si `variantes_detalle` logra leer botones de variante (ver 5.3).
* `tamano_ml` y `presentacion` se calculan al guardar en DB (doc 03).
* **Foto (`Item.imagen`, desde 2026-11-01)**, siempre normalizada con `_url_imagen` (https, absoluta, sin `data:`):
  * VTEX: `items[].images[0].imageUrl` del primer SKU con foto (igual para todas las presentaciones del producto); las URL `/arquivos/ids/<id>/` se piden a 400 px de ancho (`/arquivos/ids/<id>-400-auto/`, verificado en los 6 hosts VTEX: 40 KB-1 MB originales → 5-75 KB).
  * Unishop: `images[imageIdx].square.fullUrl` (270 px, CloudFront), si no la `thumb`.
  * Tiendanube: la entrada mayor ≤ 640 px del `data-srcset` de la primera `<img>` de la tarjeta; si no, `image_url` de la variante con `-1024-1024` → `-480-0` (`_imagen_tiendanube`).
  * HTML (Playwright): `JS_EXTRAER` devuelve `img` (primera `<img>` con fuente real: `currentSrc`, `data-src`, `srcset`…; descarta `data:`, placeholders, logos y `.svg`); si la tarjeta detectada no contiene la `<img>`, sube hasta 3 padres **mientras no aparezcan otros precios** (caso Ruiz y Roca). Las variantes expandidas por detalle (`_scrap_detalle_variantes`) heredan la foto del Item base.
  * Probado 2026-11-01 (sin correr el scraper completo): VTEX 6/6 hosts con foto, Unishop 24/24, Tiendanube 12/12, HTML Ruiz y Roca 8/8 y Normy 8/8 (imagen `200 OK`). Juleriaque y Parfumerie no se pudieron probar: en esa prueba (Chromium sin cabeza) no se renderizaron los precios en 10 s, igual que el último log del scraper (`0 productos`); hasta que se arreglen no tendrán foto.

### 2.4 Ritmo y cabeceras
`requests.Session` con UA aleatorio, `Accept: application/json`, `Accept-Language: es-AR`; en VTEX ademas `Referer`. Pausas aleatorias 0.4-0.9 s entre paginas. Timeout 25 s, 1 solo intento (doc 01, 6.2).

---

## 3. Motor VTEX (pigmento, beauty24, rouge, fiorani, getthelook, farmalife)

### 3.1 Funciones
| Funcion | Linea | Rol |
|---|---|---|
| `scrap_vtex(session, site, max_items)` | 666 | Orquesta estrategias de busqueda y paginacion |
| `descubrir_categorias(session, host, keywords)` | 586 | Autodescubre IDs de categoria desde `/api/catalog_system/pub/category/tree/50` |
| `_parse_vtex_productos(data, host, tienda)` | 612 | Convierte el JSON de productos en `Item` (uno por SKU) |

### 3.2 Endpoint y paginacion
```
GET https://{host}/api/catalog_system/pub/products/search/{category_path}?_from=0&_to=49[&sc=1]
```
* Paso fijo de 50 (`paso = 50`); `_from/_to` crecen hasta `max_items` (800 productos por entrada, `--max-items`). Corta si la pagina trae menos de 50 o vacia. El tope de 800 cuenta **productos**, no SKUs (una entrada puede devolver mas de 800 Items; ej. Rouge masculino 1045 en la corrida 156).
* `sc=1` (sales channel) esta fijado en rouge, getthelook y farmalife; en Rouge es obligatorio (la version vieja comentaba "sin el -> 0 productos").
* Ante HTTP != 200/206 o JSON invalido se corta la paginacion y se devuelve lo acumulado.

### 3.3 Estrategias en cascada (`scrap_vtex`)
| # | Condicion | Accion |
|---|---|---|
| 0 | `site["cat_ids"]` definido | `?fq=C:/{id}/` por cada id; si se usan, NO se cae a las demas (ningun sitio lo usa hoy) |
| 1 | `category_path` | `/products/search/{path}`; si trae items, termina |
| 1b | `category_paths` (lista) | un request paginado por path; NO cae a autodescubrimiento (usado por Farmalife) |
| 2 | `cat_keywords` | `descubrir_categorias` busca en el arbol los nodos cuyo nombre normalizado contiene alguna keyword; `?fq=C:/{id}/` por id |
| 3 | `ft` (lista de textos) | busqueda `?ft=...` (ningun sitio lo usa hoy) |
| 4 | `buscar_todo` True | catalogo completo (ningun sitio lo usa hoy; Fiorani lo usaba antes del 2026-06-26) |

En `logs_scraper.txt` el log `categorias detectadas: [...]` (estrategia 2) aparece 31 veces: beauty24 (20), fiorani (3), rouge (3), getthelook (3), pigmento (2). Es decir, el `category_path` fallo ocasionalmente y el autodescubrimiento actuo de respaldo; riesgo: puede traer categorias mas amplias que lo deseado (comentado en la config).

### 3.4 Campos JSON usados y reglas de precio (`_parse_vtex_productos`)
| Dato | Campo VTEX |
|---|---|
| nombre producto | `productName` |
| marca | `brand` |
| URL | `https://{host}/{linkText}/p?skuId={itemId}` |
| SKUs | `items[]` (se recorren **todos**) |
| precio actual | `items[i].sellers[0].commertialOffer.Price` |
| precio lista | `...commertialOffer.ListPrice` |
| stock | `IsAvailable`, o `AvailableQuantity > 0` si no existe |
| nombre del SKU | `items[i].nameComplete` (o `name` como respaldo) |
| presentacion | `_presentacion(items[i].name, nombre)` |

Reglas: se usa **solo el primer seller**; se descartan SKUs sin precio (`Price` = 0) y **sin stock**; `precio_lista = ListPrice or Price`; `descuento_pct = _dto(ListPrice, Price)`; `fuente="api"`; `tipo_descuento="rebaja"`. **No** se consideran cuotas ni precios por medio de pago (el campo `Price` ya incluye promociones aplicadas por VTEX). Puede haber `ListPrice < Price` (queda `descuento=0` y se filtra).

---

## 4. Tiendas VTEX (config por entrada)

### 4.1 `pigmento` (www.perfumeriaspigmento.com.ar)
* M: `category_path="perfume/hombre"`, F: `"Perfume/Mujer"`, U: `"Perfume/Unisex"` (diferencias de mayusculas tal cual en el codigo); keywords de respaldo `hombre/masculin/men`, `mujer/femeni/women`, `unisex`.
* Los nombres de SKU ya traen ml (ej. "1989 Blue EDT 100 Ml"; marca por ejemplo "Kevingston"). Es el caso que motivo iterar todos los SKUs (ver `Mejoras.md`: antes se guardaba un solo precio por producto aunque hubiera 75/125/200 ml).
* Se observo autodescubrimiento 2 veces en el log.

### 4.2 `beauty24` (www.beauty24.com.ar)
* Paths: `perfumes-y-fragancias/perfumes-de-hombre`, `.../perfumes-de-mujer`, `.../perfumes-unisex`.
* La tienda con mas ofertas (461 obs. en la corrida 156; descuento medio ~36%). Muchos productos al 65% (ej. Dsquared2).
* Es la que mas recurre al autodescubrimiento (20 veces) y la que mas veces dio `vacio` entre las VTEX (beauty24_femenino 7, beauty24_masculino 6, beauty24_unisex 3 sobre 131 corridas).

### 4.3 `rouge` (www.perfumeriasrouge.com)
* Mismos paths que Beauty24. **`sc=1` obligatorio.**
* Observacion historica no verificada: en las primeras corridas masculino y femenino devolvieron la misma cantidad (767/767), lo que sugiere que el path no filtraba por genero en ese momento; hoy los totales difieren.

### 4.4 `fiorani` (www.fiorani.com)
* Paths `fragancias/fragancias-masculinas`, `-femeninas`, `-unisex`. Antes (hasta 2026-06-26) usaba `buscar_todo` (catalogo entero, mezclaba generos).

### 4.5 `getthelook` (www.getthelook.com.ar)
* `fragancias/masculinas` y `fragancias/femeninas`, `sc=1`. Sin unisex (no existe categoria).

### 4.6 `farmalife` (www.farmalife.com.ar)
* Usa `category_paths` (estrategia 1b): masculino = `fragancias/premium/masculinas` + `fragancias/semiselectivas/masculinas`; femenino = solo `fragancias/premium/femeninas` (los semiselectivos femeninos no se incluyen a proposito). `sc=1`. Sin unisex ("pocos y mal catalogados").
* Al ser farmacia, un autodescubrimiento amplio traeria categorias de farmacia; por eso se fijan los paths y no hay respaldo.

---

## 5. Unishop (`unishop`, unishop.com.ar): SSR con JSON embebido

* **Funciones:** `scrap_unishop` (l.806), `_extraer_server_data` (l.763, regex `_RE_SERVER_DATA` l.758), `_item_de_unishop` (l.773).
* **URLs:** `https://unishop.com.ar/perfumes-y-fragancias/importadas/{masculinas|femeninas|unisex}` + `?page=N`.
* **Tecnica:** el sitio es una SPA Vue renderizada en servidor; el HTML trae `window.__SERVER_DATA__ = {...}`. Se extrae por regex y se parsea `_resolvedData.shop.products` y `_resolvedData.shop.pagination.lastPage`.
* **Paginacion:** `?page=1..lastPage`, con tope `pag_max=30` (24 productos por pagina segun el comentario). Corta si no hay `products`.
* **Campos:** de cada producto se toma una variante: la que tiene `forDisplay` o, si no, la primera (**solo una variante por producto**). Precio: `variant.pvPrice.price` (actual) y `pvPrice.prevPrice` (lista; solo si `prev > price`). Marca: `brand.name`. Nombre: `name`. URL: `https://unishop.com.ar/p/{urlName}/{productId}`. Stock: `stock.availability == "InStock"` o `infiniteQty` o `qty > 0`; si no, se omite.
* **Fuente:** `api`. No usa `discountPct` del JSON; recalcula con `_dto`.
* **Particularidad:** muchos productos con oferta (en la corrida 156: 174 + 168 + 11 items, casi todos con descuento; descuento medio ~17%). Warning si no aparece `__SERVER_DATA__` en la pagina 1.

## 6. Ambrosia (`ambrosia`, www.ambrosiaperfumes.com.ar): Tiendanube

* **Funciones:** `scrap_tiendanube` (l.928), `_items_de_tiendanube` (l.866), `_num_ar` (l.854); regex `_RE_TN_ITEM/_VARIANTS/_NOMBRE/_URL` (l.848-851).
* **URLs:** `https://www.ambrosiaperfumes.com.ar/fragancias/?G%C3%A9nero={Masculino|Femenino|Unisex}` + `&page=N`, tope `pag_max=12`. Corta si una pagina no trae items nuevos (pagina repetida).
* **Tecnica:** cada card es `<div class="js-product-item-private product-item ...">` con atributo `data-variants="[{...}]"` (JSON HTML-escapado). Se usa **solo la primera variante** (`arr[0]`). Nombre desde `js-item-name`, URL desde el primer `href` que contenga `/productos/`.
* **Campos de `data-variants`:** `price_number`, `compare_at_price_number`, `promotional_price_number`, `price_with_payment_discount_short` (texto tipo `$60.000,00`, convertido con `_num_ar`), `available`, `stock`.
* **Reglas de precio (l.902-910):**

| Caso | actual | lista | `tipo_descuento` |
|---|---|---|---|
| `promotional_price < price` | promo | price | `rebaja` |
| `compare_at_price > price` | price | compare_at | `rebaja` |
| `precio_con_descuento_de_pago < price` | price | price (dto 0) | `transferencia` (se guarda `precio_transferencia`) |
| ninguno | (se omite) | | |

  Descuento por transferencia observado: **exactamente 20%** (`precio_transferencia = 0.8 * precio_actual` en todas las filas de la base). Casi todo Ambrosia es `transferencia`: en la base hay 109.602 observaciones `transferencia` y solo 41 `rebaja` para `fuente='tiendanube'`. Los items `transferencia` **no van al Excel**, solo a MySQL.
* **Marca:** no se extrae (`marca=""` -> `NULL` en DB).
* **Problema (RESUELTO 2026-10-29):** desde la corrida id 149 (2026-10-22) Ambrosia devolvía **0 productos** ('no encontre productos Tiendanube (data-variants)'). Causa: el tag de cada card pasó a venir partido en varias líneas (`<div` + salto de línea + `class="js-product-item-private product-item ..."`) y la regex `_RE_TN_ITEM` exigía un único espacio literal. Corrección: `_RE_TN_ITEM = r'<div\s+class="js-product-item-private product-item'` (l.848). Verificado con un GET real a la página 1 de Masculino: 12 ítems (todos `transferencia`) con la regex nueva, 0 con la vieja. **Pendiente de confirmar** en la próxima corrida programada. Mientras estuvo roto, la pestaña "Transferencia" del dashboard no recibió datos nuevos (`tab-transfer.blade.php`).
* Antes del 2026-10-22 solo fallo esporadicamente (2 corridas `vacio` aisladas en `ambrosia_masculino`; causa no verificada). Total historico de `vacio`: 10 de 131 por entrada, 8 de ellas consecutivas desde la corrida 149.

---

## 7. Motor HTML con Playwright (juleriaque, parfumerie, ruizyroca, normy)

### 7.1 Funciones
| Funcion | Linea | Rol |
|---|---|---|
| `scrap_html(page, site)` | 1432 | Paso 1: recorre paginas del listado; Paso 2: expande variantes en detalle |
| `_urls_de_sitio(site)` | 1350 | Arma las URLs a visitar (`urls`, `pag_param`, `pag_path`) |
| `JS_EXTRAER` | 962 | JavaScript inyectado: barrido heuristico del DOM, devuelve "cards" |
| `_cards_a_items(cards, tienda, url_default, fuentes_validas)` | 1392 | Convierte cards en `Item` aplicando reglas de fuente |
| `_scrap_detalle_variantes(page, item_base, fuentes_ok)` | 1255 | Entra a la ficha y genera un Item por variante de tamano |
| `JS_TITULO_DETALLE`, `JS_VARIANTES_BOTONES`, `JS_PRECIO_DETALLE` | 1190 / 1203 / 1219 | JS auxiliar de la ficha |

### 7.2 Parametros por entrada de `SITES` (HTML)
`url` (o `urls`), `pag_param` + `pag_base` + `pag_max` (paginacion por query), `pag_path` (paginacion por path), `scrolls` (cantidad de scrolls), `variantes_detalle` (default **True**), `fuentes_validas` (default `["tachado"]`), `requiere_link` (default True).

### 7.3 Flujo de `scrap_html`
1. Por cada URL: `page.goto(wait_until="domcontentloaded", timeout=45s)`; espera hasta 10 s a que haya >5 simbolos `$` en el body; hace `scrolls` veces `mouse.wheel(0, 4000)` con 800 ms de pausa (para cargar scroll infinito / lazy load); ejecuta `JS_EXTRAER`.
2. Dedupe por `clave` (`tienda, nombre.lower(), precio_actual`). Si hay paginacion (`pag_param`/`pag_path`), corta cuando una pagina (indice > 0) no aporta items nuevos.
3. Si `variantes_detalle` es True, visita **cada** producto con descuento (una navegacion por producto) y lo expande (7.5). Si es False, devuelve el listado.

### 7.4 `JS_EXTRAER` (heuristica del DOM, resumen)
* Candidatos: todo elemento con `$` en su texto, con un `<a href>` dentro (o `<img>` si `requiere_link=False`) y texto <= 1400 caracteres; se ordenan del mas chico al mas grande para quedarse con la card mas especifica (y marcarla con `data-cardused` para no repetir).
* Precios: recorre **hojas** del DOM con `$`, descarta basura por contexto (regex `BASURA`: cuotas, intereses, envio, "c/u", "ahora N", "sin imp", "N x $") y numeros "prohibidos" (cuotas, precio sin impuestos, **precio por transferencia/efectivo**: numero que antecede a "transferencia"/"efectivo"). Descarta tambien nodos cuya clase indique precio sin impuestos (`excl-tax`, `sin-imp`, `base-price`...; caso Parfumerie `final-price-excl-tax`).
* Tachado: `<del>/<s>/<strike>`, clase con `old|list|regular|strike|tachad|antes|prev|compare|was`, o `text-decoration: line-through` computado (hasta 3 niveles hacia arriba). Va a `lista[]`; el resto a `normales[]`.
* Patron semantico `<del>viejo</del> + <ins>nuevo</ins>` (WooCommerce, agregado 2026-08-31, l.1088-1105): se lee directo con `textContent`.
* Badge `NN%` en el texto (si el texto sugiere descuento): `pct`.
* Marca y nombre: prioridad JSON-LD `Schema.org Product` -> selectores `data-fs-brand-name`, `[itemprop=brand]`, `[class*=brand|marca]` -> titulos `h1-h4`/`[class*=title|name]` -> `title` de enlaces -> `alt` de imagen. Si el nombre empieza con la marca se la quita. Filtra nombres de UI ("comprar", "ver mas"...).
* URL: primer `<a>` que no sea `/marca/`, `/categoria/`, `/coleccion/`, `/brand/`.

### 7.5 Reglas de `_cards_a_items` y variantes
| Condicion | actual | lista | fuente |
|---|---|---|---|
| hay `lista[]` (tachados) y `normales[]` | `max(normales)` | `max(lista)` | `tachado` |
| `>= 2` normales sin tachado | `normales[0]` | `normales[-1]` | `dos_precios` (descartado por defecto) |
| 1 normal + badge % | normal | `actual / (1 - pct/100)` | `badge_%` (descartado por defecto) |

* Se exige `lista > actual`. Si hay badge y difiere en mas de 3 puntos del descuento calculado, **se usa el % del badge** (`descuento_pct`) aunque los precios no lo reflejen (posible inconsistencia entre `descuento_pct` y los precios guardados).
* `_scrap_detalle_variantes`: abre la URL de la ficha; lee el `h1` (nombre completo con variante); `JS_VARIANTES_BOTONES` busca botones/labels/radios cuyo texto contenga "NN ml" (<30 caracteres). Sin botones -> intenta mejorar el nombre con el h1 y devuelve el item. Un boton -> usa h1/boton para el nombre y **mantiene el precio del listado**. Varios botones -> hace click en cada uno, espera 900 ms, lee `JS_PRECIO_DETALLE` (tachados via `del/s/[class*=old|anterior|compare|list-price]` -> lista; selectores `sellingPrice/spotPrice/current-price/...` -> actual, ambos > 1000) y **solo conserva variantes con descuento** (`lista > actual`, dto >= 0.5); si ninguna califica devuelve el item base.

### 7.6 Juleriaque (`juleriaque`, www.juleriaque.com.ar)
* URLs: `https://www.juleriaque.com.ar/fragancias?category-1=fragancias&category-3={masculinas|femeninas|unisex}&sort=score_desc&page=0`. La URL masculina **no** lleva los filtros `category-2=premium&category-2=low-cost`, `fuzzy=0&operator=and&facets=...` que si llevan femenino y unisex (diferencia en la config, motivo no documentado).
* Paginacion `page` base **0**, `pag_max=10`, `scrolls=8`, `variantes_detalle=True`. Corta al encontrar una pagina sin items nuevos (en el log los listados se agotan tipicamente en 2-10 paginas).
* Particularidades: URLs de producto tipo `/slug-<ids>/p`; el JS incluye selectores `data-fs-*` (FastStore), lo que sugiere una tienda VTEX FastStore (no verificado). Fuente `tachado`. Los nombres de producto suelen NO traer ml: en la corrida 156 el **90% (137 de 152)** de sus observaciones quedaron con `tamano_ml = NULL` (la expansion de variantes no siempre detecta botones).
* Historia: era la tienda con mas falsos positivos `badge_%` (origen del filtro de fuentes del 2026-06-26).

### 7.7 Parfumerie (`parfumerie`, www.parfumerie.com.ar)
* URLs: `/fragancias/masculinas`, `/fragancias/femeninas`, `/fragancias/unisex`. **Sin paginacion**: `scrolls=16` (scroll infinito). `variantes_detalle` = True (default).
* Particularidad: la card trae un "precio sin impuestos" (clase `final-price-excl-tax`) que el JS excluye por clase para no confundirlo con el precio de venta (por eso se elige `max(normales)` como actual). Fuente `tachado` (se quito `badge_%` el 2026-06-26). Se quito la URL "parfusale".
* Pocos resultados (27 obs. en la corrida 156; 8+10+9 productos del listado).

### 7.8 Ruiz y Roca (`ruizyroca`, www.ruizyrocaperfumeria.com)
* URLs: `/fragancias1/masculinas/`, `/fragancias1/femeninas/`, `/fragancias1/unisex3/` con `?mpage=N` (base 1, `pag_max=12`), `scrolls=6`, `variantes_detalle` True (default).
* Pocos resultados (61 obs.; 60 de 61 sin `tamano_ml`).

### 7.9 Normy (`normy`, www.perfumerianormy.com.ar): WooCommerce
* URLs: `https://www.perfumerianormy.com.ar/categoria-producto/fragancias/fragancias-masculinas/` y `.../fragancias-femeninas/`. **Sin unisex.**
* Paginacion por **path** (`pag_path: "page/{n}/"`, base 1, `pag_max=20`), `scrolls=4`, `variantes_detalle=False` (los nombres ya traen el tamano: "100ML VAP.").
* Tecnica: patron `<del>precio viejo</del><ins>precio nuevo</ins>` leido directo (agregado el 2026-08-31). Antes se usaba scroll (`scrolls=12`) sobre una URL equivocada (`/promociones/` hasta el 2026-06-26) y Normy salio **vacio en 79 corridas** hasta el 2026-08-31.
* **Marca no extraida**: `marca` queda `NULL` en el 100% de sus observaciones (la marca esta embebida en el nombre: "1 MILLION BLACK PACO RABANNE E.D.Parfum").
* Riesgo: en la corrida del 29-10 la pagina 20 todavia aportaba items nuevos (hasta 142 productos por genero); el tope `pag_max=20` podria truncar el catalogo (no verificado).

---

## 8. Resumen de particularidades transversales

| Tema | Tratamiento |
|---|---|
| Precio por transferencia / efectivo | Solo se captura en Ambrosia (`precio_transferencia`, `tipo_descuento='transferencia'`). En HTML esos precios se **excluyen** a proposito del calculo. VTEX y Unishop no los consideran. |
| Cuotas / intereses | Ignorados (regex en HTML; en APIs se usan `Price`/`pvPrice`, no cuotas). |
| Stock | VTEX/Unishop/Tiendanube omiten no disponibles. HTML no lo evalua. |
| Marca | Tal cual la entrega cada sitio; vacia en Normy y Ambrosia. No hay normalizacion en el scraper (doc 03). |
| Tamano ml | Del nombre/variante; ver cobertura por tienda en doc 03. |
| Genero | De la entrada de `SITES`, no del producto. Un mismo producto en dos generos termina con un solo `genero` (doc 03). |
| Anti-bot | Solo UA aleatorio y pausas; sin proxies ni reintentos. |
| Cambios de HTML | Ambrosia (regex), Juleriaque/Parfumerie/Ruiz y Roca dependen de heuristicas de DOM: si bajan los totales conviene correr `--solo <tienda> --headful` para ver las cards (el docstring del script lo recomienda). |
