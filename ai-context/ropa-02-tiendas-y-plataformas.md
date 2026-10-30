# ropa-02 · Tiendas de ropa/calzado: plataformas y adaptadores

> Investigación del 2026-10-30 (solo lectura: una petición normal a la portada de cada sitio + sondeos puntuales). Complementa [ropa-01](ropa-01-scraper-nike.md) (Nike) y [14](14-multirubro.md).

## Adaptadores de `descuentos_ropa.py`

Cada sitio de `SITES` declara `platform`; `main()` despacha según ella (el navegador Playwright solo se abre si hay algún sitio `vtex_io`).

| `platform` | Cómo lee | Sitios | Función |
|---|---|---|---|
| `vtex_io` | Navegador real; estado `__STATE__` embebido (VTEX IO + Cloudflare, no admite HTTP directo) | Nike | `scrap_seccion` / `productos_de_state` |
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
| Puma (`ar.puma.com`), Dexter (`www.dexter.com.ar`) | Salesforce Commerce Cloud tras Cloudflare | Pendiente: portada responde 200; falta ver si el listado se puede leer (necesita adaptador `sfcc`). |
| Kevingston (`www.kevingston.com`) | VTEX + Cloudflare; `/api/catalog_system` devuelve 404 | Pendiente (**siguiente tienda en el orden del usuario**: Lacoste, Kevingston, Montagne, Adidas, Puma, Dexter, Wrangler, Prototype, Stone; Levi's y Topper se adelantaron por ser baratas de integrar). **No es VTEX IO**: sitio propio (rutas `/categoria/hombres/jeans`…) con tarjetas `producto-hover` en el HTML renderizado y un `POST /api/v1/products` interno; necesita un adaptador HTML a medida. |
| Prototype (`www.prototype.com.ar`) | Tiendanube + Cloudflare | Pendiente: existe un lector Tiendanube en `descuentos_master.py` (reutilizable); falta ubicar el listado de ofertas (`/ofertas/` da 404). |
| Stone (`stone.com.ar`) | WooCommerce | Pendiente: `/wp-json/wc/store/v1/products` responde 404 (API de WooCommerce no expuesta); habría que leer el HTML del listado. |
| Montagne (`www.montagne.com.ar`) | Apache, sin marcador de plataforma conocido | Pendiente: sitio propio en PHP (`/categoria/104-zapatillas`, tema alojado en CloudFront), sin API: adaptador HTML a medida. |
| Wrangler (`wranglerjeans.com.ar`, dominio confirmado por el usuario) | Sitio propio (nginx), sin marcador de plataforma; tarjetas de producto en el HTML renderizado con precio de lista, precio y "con transferencia" | Pendiente: adaptador HTML a medida (ojo: tiene precio con transferencia → `tipo_descuento = transferencia`). |

## Talles de calzado con numeración argentina/europea
Topper publica calzado con talle argentino (`44`, `36.5`). `Talles::normalizar` / `normalizar_talles` (mantener iguales) **no agregan prefijo H/M a números ≥ 30**; en el filtro de talle (`Dashboard::grupoTalle`) forman el grupo **"Calzado (talle AR / EU)"** (los talles US de Nike siguen siendo `H…`/`M…`).

## Pendiente (ver [12](12-problemas-conocidos-y-pendientes.md))
* Comparador entre tiendas por `referencia` y pestaña "Por tienda": solo tiene sentido cuando dos tiendas vendan la **misma marca** (p. ej. Nike en Dexter); con Nike/Levi's/Topper (marcas propias) no hay cruces todavía.
* Cadencia/ritmo por sitio (hoy un solo horario de las 15:00 para todos; Levi's+Topper agregan ~3 min).

## Lacoste: alternativas y criterio (2026-10-30)
Las técnicas que suelen proponerse para Akamai (impersonación TLS, navegadores *stealth*, proxies residenciales, APIs *unlocker*) son formas de **burlar una medida anti-bots** y no se usan en este proyecto. Vías aceptables: (1) pedir a Lacoste Argentina un feed/acceso o usar un programa de afiliados; (2) leer los precios de Lacoste desde un revendedor que sí permita lectura (p. ej. Dexter, si vende Lacoste) o desde el catálogo oficial de Mercado Libre vía su API; (3) flujo **asistido**: el usuario guarda a mano el HTML de cada listado desde su navegador (ya existe un ejemplo en `Clases\descuentos\lacoste html para claude.txt`) y un parser lo convierte a la corrida. Sin definir.
