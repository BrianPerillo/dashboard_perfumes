# ropa-02 · Tiendas de ropa/calzado: plataformas y adaptadores

> Investigación del 2026-10-30 (solo lectura: una petición normal a la portada de cada sitio + sondeos puntuales). Complementa [ropa-01](ropa-01-scraper-nike.md) (Nike) y [14](14-multirubro.md).

## Adaptadores de `descuentos_ropa.py`

Cada sitio de `SITES` declara `platform`; `main()` despacha según ella (el navegador Playwright solo se abre si hay algún sitio `vtex_io`).

| `platform` | Cómo lee | Sitios | Función |
|---|---|---|---|
| `vtex_io` | Navegador real; estado `__STATE__` embebido (VTEX IO + Cloudflare, no admite HTTP directo) | Nike | `scrap_seccion` / `productos_de_state` |
| `kevingston` | Navegador abierto en el sitio; desde la página llama (mismo origen, como hace el propio sitio) a `POST /api/v1/products` (`order=4` = mayor descuento, 36 por página, `category` vacío = todo) y a `GET /api/v1/product/size/{sku}` (talles con stock, **una consulta por producto con descuento**, ~1 s c/u) | Kevingston | `scrap_kevingston` |
| `puma` | Navegador; cada página `/puma-sale?page=N&limit=32` se abre y los productos (`hits`: precio de lista/venta, %, colores, URL) se leen del estado de la app en `<script id="mobify-data">`; se recorre todo (no hay orden por descuento) | Puma | `scrap_puma` / `_hits_puma` / `_item_puma` |
| `sfcc_dexter` | Navegador en `/sale`; las páginas siguientes se piden (mismo origen) a `/on/demandware.store/Sites-Dexter-Site/default/Search-UpdateGrid?cgid=liquidacion&srule=product-discount&start=N&sz=36` | Dexter | `scrap_dexter` / `_tarjetas_sfcc` |
| `tiendanube` | HTTP simple; cada tarjeta trae `data-variants` (JSON de todas las variantes: color, talle, precio, precio anterior, stock) en `/productos/?page=N` (12 por página, sin orden por descuento: se recorre todo el catálogo) | Prototype | `scrap_tiendanube_ropa` (basado en el lector de `descuentos_master.py`) |
| `montagne` | HTTP simple + BeautifulSoup sobre `/categoria/164-ofertas?p=N&n=20` (HTML del servidor) | Montagne (**desactivada**) | `scrap_montagne` |
| `wrangler` | HTTP + BeautifulSoup sobre el fragmento `/last-chance/search?page=N&breadcrumb_category_id=171` (12 tarjetas por página) | Wrangler | `scrap_wrangler` |
| `vtex_api` | HTTP + JSON del catálogo público de VTEX: `/api/catalog_system/pub/products/search?O=OrderByBestDiscountDESC&_from=0&_to=49` (50 por pedido, máx. 2500; pausas de 2,5–4,5 s; espera creciente ante 403/429/503; corta al llegar a páginas sin descuentos) | Levi's, Topper | `scrap_seccion_api` / `producto_de_api` |

Para un sitio `vtex_api` nuevo basta una entrada en `SITES` con `host`, `marca` y `specs` (nombre de los campos del producto: `genero`, `tipo`, `color`, `linea`, `disciplina`, cada uno una lista de claves posibles) más una sección `catalogo`. Género/categoría se infieren de los specs y del árbol de categorías (`_genero_de_texto`, `_RE_CALZADO`, `_RE_ACCESORIOS`). Solo se guardan productos con **stock** (`AvailableQuantity > 0`); el precio es el del SKU con stock más barato y los talles son los SKUs con stock. En `corrida_sitios` cada sitio `vtex_api` es **una** fila (`levis_catalogo`, `topper_catalogo`) con plataforma `vtex_api`; `observaciones.fuente` = `vtex_api`.

## Estado por tienda

| Tienda | Plataforma detectada | Estado |
|---|---|---|
| Nike (`www.nike.com.ar`) | VTEX IO + Cloudflare | **Activa** (`vtex_io`) |
| Levi's (`www.levi.com.ar`) | VTEX IO, API de catálogo pública OK | **Activa** (`vtex_api`): ~1230 productos, ~700 leídos hasta que se acaban las ofertas, ~460 con descuento. `Linea` de Levi's son niveles de precio (Good/Better/Best): no se usa. Color vacío en ~65 % (el sitio no lo carga). |
| Topper (`www.topper.com.ar`) | VTEX, API de catálogo pública OK | **Activa** (`vtex_api`): ~860 productos, ~650 leídos, ~550 con descuento. Calzado con talle argentino (36–46): **sin prefijo H/M** (ver abajo). |
| Lacoste (`www.lacoste.com/ar/`) | Salesforce Commerce Cloud tras **Akamai Bot Manager** | **Bloqueada**: HTTP y navegador sin cabeza reciben `403 Access Denied` (Akamai). No se intentó sortear el bloqueo (ni navegador "real" automatizado ni evasión): es una medida anti-bots del sitio. Alternativas legítimas: pedir autorización/feed al sitio o programa de afiliados, o cargar datos a mano. **Decisión pendiente del usuario.** |
| Adidas (`www.adidas.com.ar`) | Akamai | **Bloqueada** igual que Lacoste. |
| Dexter (`www.dexter.com.ar`) | Salesforce Commerce Cloud (SFRA) tras Cloudflare | **Activa** (`sfcc_dexter`). **Revendedor multimarca** (Nike, Adidas, Puma, Topper, Lotto, Under Armour…): 2110 productos en `/sale` (2026-10-30, 4,5 min). La tarjeta no trae marca ni talles: la marca se deduce del nombre (`_MARCAS_MULTI`; ~15 % queda como "Dexter": NBA, Atomik, etc.). Su `data-pid` no coincide con el código de estilo de Nike, así que el cruce por `referencia` con Nike aún no es posible. |
| Puma (`ar.puma.com`) | Salesforce Composable Storefront (PWA Kit) tras Cloudflare | **Activa** (`puma`): 1122 productos en `/puma-sale` (773 con descuento) en ~6,5 min (36 páginas). **Sin talles** (no vienen en el listado; la ficha de cada producto tardaría ~2 h) y género deducido del nombre ("para hombre"). |
| Kevingston (`www.kevingston.com`) | VTEX + Cloudflare; `/api/catalog_system` devuelve 404 | **Activa** (`kevingston`; 1466 productos leídos el 2026-10-30, 40 min). Sitio propio (no VTEX). Categorías del sitio: `idcategory` 1 = hombre, 2 = mujer, 7 = teens & kids. Casi todo el catálogo (~1480) figura con descuento ("hasta 50 % OFF en todo el sitio"); se descartan sin stock y perfumería. El tipo sale de la primera palabra del nombre (más común por `idtype`). Sin color (el sitio lo da como estilo CSS). **Costo:** los talles requieren una consulta por producto (~1400 → ~40 min); si pesa demasiado, poner `"talles": False` en su sección. | |
| Prototype (`www.prototype.com.ar`) | Tiendanube + Cloudflare | **Activa** (`tiendanube`): catálogo completo `/productos/` (tope 100 páginas, ~3 s c/u; 2026-10-30: 642 productos, 352 con descuento, 4 min); talles desde `option1`. Género sin dato (queda *unisex*). |
| Stone (`stone.com.ar`) | WooCommerce | Pendiente. `stone.com.ar` es solo la vitrina corporativa (WordPress): su tienda minorista está en **`www.stoneshop.com.ar`, una Tienda Oficial de Mercado Libre** (URLs `/listado/...`, filtro `_Discount_5-100`). Habría que leerla vía Mercado Libre (sus listados o su API), que es otra plataforma (`mercadolibre`). |
| Montagne (`www.montagne.com.ar`) | Apache, sin marcador de plataforma conocido | Adaptador hecho, **desactivado** (`"activo": False` en `SITES`; se prueba con `--solo montagne`): `/categoria/164-ofertas` (~187 productos, incluye equipo de camping → categoría *accesorios*). **Duda de producto:** el precio de oferta se rotula "Comunidad Montagne" (precio para usuarios registrados en su comunidad, no para cualquier visitante); además no trae talles en el listado. Activar solo si se acepta mostrar ese precio. |
| Wrangler (`wranglerjeans.com.ar`, dominio confirmado por el usuario) | Sitio propio (nginx), sin marcador de plataforma; tarjetas de producto en el HTML renderizado con precio de lista, precio y "con transferencia" | **Activa** (`wrangler`), solo la sección LAST CHANCE (id 171, 16 productos hoy): el resto del catálogo casi no tiene descuentos (no verificado en todas las categorías). Las tarjetas traen precio, precio anterior y "con transferencia" (10 % menos; **no se guarda**, solo la rebaja). Sin talles en el listado. |

## Talles de calzado con numeración argentina/europea
Topper publica calzado con talle argentino (`44`, `36.5`). `Talles::normalizar` / `normalizar_talles` (mantener iguales) **no agregan prefijo H/M a números ≥ 30**; en el filtro de talle (`Dashboard::grupoTalle`) forman el grupo **"Calzado (talle AR / EU)"** (los talles US de Nike siguen siendo `H…`/`M…`).

## Pendiente (ver [12](12-problemas-conocidos-y-pendientes.md))
* Comparador entre tiendas por `referencia` y pestaña "Por tienda": solo tiene sentido cuando dos tiendas vendan la **misma marca** (p. ej. Nike en Dexter); con Nike/Levi's/Topper (marcas propias) no hay cruces todavía.
* Cadencia/ritmo por sitio (hoy un solo horario de las 15:00 para todos; Levi's+Topper agregan ~3 min).

## Lacoste: alternativas y criterio (2026-10-30)
Las técnicas que suelen proponerse para Akamai (impersonación TLS, navegadores *stealth*, proxies residenciales, APIs *unlocker*) son formas de **burlar una medida anti-bots** y no se usan en este proyecto. Vías aceptables: (1) pedir a Lacoste Argentina un feed/acceso o usar un programa de afiliados; (2) leer los precios de Lacoste desde un revendedor que sí permita lectura (p. ej. Dexter, si vende Lacoste) o desde el catálogo oficial de Mercado Libre vía su API; (3) flujo **asistido**: el usuario guarda a mano el HTML de cada listado desde su navegador (ya existe un ejemplo en `Clases\descuentos\lacoste html para claude.txt`) y un parser lo convierte a la corrida. Sin definir.

## Corrida #164 con todas las tiendas (2026-10-30)
Se cargaron en la misma corrida (`--fecha 2026-10-30T15:00:02`) Levi's, Topper, Kevingston, Dexter, Wrangler y Prototype junto a Nike: **6880 observaciones**, 7 tiendas. Con todas las tiendas activas la corrida diaria de las 15:00 pasa de ~32 min (solo Nike) a **~100 min** (Nike 32 + Kevingston 40 + Dexter 5 + Prototype 4 + Levi's/Topper 3). Palancas si pesa: `"talles": False` en Kevingston (quita ~35 min), `--solo`, o separar tareas por sitio. Dexter aporta ~30 % de los productos; Nike/Levi's/Topper/Kevingston/Wrangler/Prototype son marcas propias, Dexter es multimarca. **Nota**: la tarea programada ya incluye estas tiendas (usa todas las de `SITES` salvo las `"activo": False`); no se probó aún una corrida programada completa con todas.

## Tipos y deportes unificados entre tiendas (2026-10-30)
Cada tienda publica el tipo distinto ("Remera" / "Remeras y polos"; "Calzado" / "Zapatillas"; "Shorts-bermudas"…), lo que llenaba el filtro "Prenda / tipo" de ~130 valores casi repetidos. `descuentos_ropa.py::normalizar_tipo` asigna un **tipo canónico** (≈40: Zapatillas, Botines, Remera, Short / Bermuda, Jean, Campera, Mochilas y bolsos…) y su **categoría** (ropa/calzado/accesorios) mirando nombre + tipo crudo; el primer patrón de `_TIPOS` que coincide gana (el orden importa). `normalizar_disciplina` unifica los deportes con los nombres de Nike (Training→Entrenamiento, Casual→Moda…) y, si la tienda no trae deporte, lo deduce del nombre (Botines→Fútbol, "running"→Running…); sin dato queda NULL (el filtro no lo excluye). Ambas se aplican a todos los productos en `main()` (`unificar_tipos`) y a lo ya guardado con `python descuentos_ropa.py --renormalizar-tipos` (UPDATE de `productos.tipo/categoria/disciplina` de ropa; datos derivados, recalculables). Se descartan gift cards y nombres `null` (`_RE_EXCLUIR`). Dexter no trae color ni línea; Kevingston tampoco color.

## Talles de Dexter (2026-10-30)
`_talles_dexter`: por cada producto se baja el HTML de su ficha (mismo origen, sin renderizar, ~1 s) y se leen los `li.variation-attribute-size` **sin** la clase `disabled`. ~2160 productos ≈ 40 min (corrida diaria total ≈ 140 min). `"talles": False` en su sección lo desactiva.

**Resultado 2026-10-30:** Dexter 2157 productos con talles (14 sin talles) en 3733 s; los talles publicados como rango (`40-40.5`) se expanden a sus dos valores (`expandir_rangos`, también en `--renormalizar-tipos`, que además recalcula `talles_norm`). Tras unificar, el filtro "Prenda / tipo" tiene 38 valores (antes ~130).

**Puma (2026-10-30):** corrida #164 ahora con 8 tiendas. Con Puma la corrida diaria suma ~7 min más.

## Guardado tienda por tienda y estado de la corrida (2026-11-01)
Antes todo se guardaba en MySQL al final (~3 h de espera). Ahora, en cada corrida nueva (no `--fecha`, no `--reanudar`, no `--no-db`), `descuentos_ropa.py::main` llama a `guardar_en_mysql(items_tienda, ahora, reporte_tienda, "en_curso")` **al terminar todas las secciones de cada tienda** (es idempotente por `(rubro, fecha_hora)`; el estado solo se fija al crear la corrida). La corrida nace `en_curso` y **el sitio no la ve** (`Corrida::completas()`).

Al final (`cerrar_corrida`): control de calidad como antes (`--umbral-secciones`, `--force-db`) → `completa` (pasa a ser la "última corrida" de golpe, con todas las tiendas) o `incompleta` (queda guardada pero oculta; exit 3). Si el proceso se corta (tope de 5 h, apagado) la corrida queda `en_curso`, oculta, y el sitio sigue mostrando la anterior; `--reanudar` (checkpoint) vuelca lo que falte y la cierra como `completa`. Si falla el guardado de una tienda se reintenta con todo al final. Con `--fecha` (completar una corrida existente) se guarda al final y no se cambia el estado. Si falta la columna `estado` (migración sin aplicar) el scraper guarda como antes.

Efecto: los datos de las tiendas ya terminadas están en la base antes de que acabe la corrida, pero **se publican todos juntos** al cerrarla. Probado el 2026-11-01 con corridas de prueba de Wrangler (fechas 2099 → `incompleta` y 2000 → `completa`): `Corrida::ultima('ropa')` siguió siendo #173; las corridas de prueba y su efecto sobre `productos.ultima_vez` se revirtieron.

## Checkpoint y `--reanudar` (2026-10-30)
El guardado en MySQL era **al final** de la corrida (para no mostrar una corrida a medias; desde 2026-11-01 se guarda por tienda en una corrida `en_curso`, ver arriba; el checkpoint sigue siendo el respaldo). Como la corrida completa dura ~2,5 h, en las corridas completas (sin `--solo`, `--seccion`, `--fecha` ni `--no-db`) se escribe `excel_ropa/checkpoint_ropa.json` (fecha, reporte por sección e items) después de cada sección. Si el proceso se corta, `python descuentos_ropa.py --reanudar` guarda en MySQL lo ya leído (misma `fecha_hora`) sin volver a scrapear; el checkpoint se borra al guardar bien. Las secciones que no llegaron a leerse se pueden completar después con `--solo <tienda> --fecha <la misma>`.
