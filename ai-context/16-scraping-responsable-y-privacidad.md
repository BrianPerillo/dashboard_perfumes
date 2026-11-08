# 16 · Scraping responsable (ritmo, robots, bloqueos) y privacidad de los enlaces

Auditoría del 2026-11-02. Objetivo: que ningún sitio nos bloquee por scraping y que las tiendas no puedan saber que el tráfico sale de nuestro sitio.

## Enlaces salientes: sin `Referer`

* Todos los enlaces a tiendas (`resources/views/livewire/dashboard/tab-{tabla,joyas,comparar,conclusiones,transfer}.blade.php`, 11 enlaces) llevan `target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"`; el botón "Ver en tienda" del mail de alertas (`emails/alerta_descuento.blade.php`) lleva `rel="noreferrer" referrerpolicy="no-referrer"`.
* Las fotos (`<img>`) ya usaban `referrerpolicy="no-referrer"` (hotlink al CDN de cada tienda: la tienda ve el pedido de la imagen pero **sin** `Referer`).
* No se usó `<meta name="referrer">` global para no afectar a terceros que sí lo necesitan (MercadoPago, Google Analytics).
* Límite honesto: `noreferrer` evita el encabezado `Referer`, pero la tienda igual puede ver la IP del usuario y, si el enlace trae parámetros propios, esos parámetros. Los enlaces salen tal cual los publica la tienda (sin parámetros nuestros).

## Reglas de ritmo (throttling) por scraper

Principios aplicados: pausa aleatoria entre pedidos al mismo sitio, **sin paralelismo** (un sitio a la vez, una petición a la vez), espera creciente y **ritmo adaptativo** ante 403/429/5xx (`descuentos_ropa.py::_RITMO`: ×1,6 por bloqueo hasta ×4; ×0,92 por éxito), pausa de 8–14 s entre secciones y de 0,8–2 s entre sitios.

| Scraper / adaptador | Pausa entre pedidos | Ante 429/403/5xx |
|---|---|---|
| Nike (`vtex_io`, navegador) | 6–10 s por página + 3 s de espera | espera larga y reintentos, ritmo ×1,6 |
| `vtex_api` (Levi's, Topper, Tascani) | 2,5–4,5 s | 45 s × intento (403/429/500/502/503/504) |
| Kevingston (navegador, API interna) | 2–3,5 s | reintentos con espera |
| Dexter (navegador) | 3–5 s por página; fichas 1–1,8 s | 30 s × intento |
| Puma (navegador) | 4–7 s por página | 40 s × intento |
| `tiendanube`, `magento`, `sfcc_http`, `prestashop`, `montagne` | 2,5–4,5 s | 30 s × intento y ritmo adaptativo |
| `shopify` (Briganti, Columbia) | **10–15 s** (+30 s por reintento) | 4 intentos; Shopify responde `429 local_rate_limited` con pocas páginas seguidas |
| Perfumes (`descuentos_master.py`: VTEX, Unishop, Tiendanube) | **1–1,8 s** (`_pausa()`; antes 0,4–0,9 s) | `_get` reintenta 429/503 con 20 s × intento (antes los daba por "página vacía") |
| Perfumes HTML (Playwright) | 1–2 s entre páginas | — |
| Tecnología: Compragamer (`cg_json`) | 4 pedidos en total (catálogo JSON estático), 1–2 s | 30 s × intento y ritmo adaptativo |
| Tecnología: Maximus (`maximus_ws`) | 2,5–4,5 s entre pedidos (≈55) | idem |
| Tecnología: Fullh4rd (`fh_html`) | 2,5–4,5 s entre páginas (≈160, 650 KB c/u) | idem |
| Tecnología: Gezatek (`gz_html`) | 2,5–4,5 s entre páginas (≈50, 650 KB c/u) | idem |
| Tecnología: Wiztech (`wt_json`) | 1 pedido (JSON de 193 KB) | idem |
| Tecnología: Armytech (`at_html`) | 2,5–4,5 s entre páginas (11, ≈1 MB c/u) | idem |
| Tecnología: Gamingcity (`gc_html`) | 2,5–4,5 s entre páginas (≈110 de 250 KB) | idem |

Cambios de esta auditoría: pausas de perfumes más largas y reintento ante 429/503; Shopify de 6–9 s a 10–15 s; fichas de Dexter de 0,6–1,1 s a 1–1,8 s (+~15 min en la corrida); Moov corta tras 4 páginas seguidas sin descuentos (su orden "mayor descuento" no es estricto).

Ritmo total de la corrida de ropa: ~3 h estimadas (límite de la tarea: 5 h). La tarea programada corre una vez por día; las pruebas manuales de la misma tienda en poco tiempo **sí** pueden provocar 429 (pasó con Briganti y Columbia el 2026-11-01): evitar repetirlas.

## `robots.txt` (revisión 2026-11-02)

Se leyó el `robots.txt` de cada sitio (un pedido) y se comprobó que las rutas que pedimos estén permitidas:
* **Permitido** en todas las perfumerías y en Levi's, Topper, Tascani, Kevingston, Dexter, Prototype, Puma, Catriel, Mistral, Stone, Cueros Mi Campo, Old Bridge (2026-11-02), Devre, Moov, Briganti, Columbia y Brooksfield. Ninguno declara `Crawl-delay`.
* **Wrangler: `User-agent: *  Disallow: /`** (solo Googlebot permitido) → **desactivada** (`"activo": False` en `SITES`). Reactivar solo con autorización del sitio.
* Nike: `robots.txt` devuelve 403 a una petición simple (Cloudflare); se lee con navegador real y pausas largas, sin evasión.
* **Tecnología (2026-11-08):** Compragamer no publica `robots.txt` (devuelve su SPA) y el JSON que leemos es el que descarga cualquier visitante; Maximus veta login/checkout/perfil/seguimiento y nosotros solo pedimos el listado público; Fullh4rd veta `/cart`, `/order`, `/checkout`, `/tag?` y pedimos `/productos?page=N`. Sin `Crawl-delay` ni protección anti-bot en esas rutas. Mexx estaba en mantenimiento.
  * **Agregadas el 2026-11-09:** Gezatek veta cuenta/carrito/checkout/login/admin y `?orderby=`/`?add-to-cart=` (pedimos `/<categoria>/?page=N`); Wiztech no publica `robots.txt` (devuelve su SPA) y leemos el mismo JSON que descarga la página; Armytech (PrestaShop) veta `?order=`, `?q=`, `?n=`, `controller=…` (pedimos `/2-productos?page=N`); Gamingcity veta `/productos.php?`, `/detalle.php?`, `/nov_detalle.php?`, `/control/`: **leemos las URLs amigables públicas** (`…--prod--<id>`), que son las del menú del sitio y no las rutas dinámicas vetadas (`Crawl-delay: 1` solo para Bingbot; respetamos más de 2,5 s igual).

## Protecciones anti-bot (Akamai, Lacoste/Adidas)

Una pregunta frecuente: "¿se pueden llamar directamente las APIs internas (XHR/JSON) con los headers y cookies correctos?". Criterio del proyecto:
* **Sí** cuando el sitio nos deja abrir la página normalmente: es lo que hacen Kevingston, Puma, Dexter, Levi's/Topper/Tascani, Moov o Briganti (leer el JSON/endpoint que usa el propio sitio, desde el mismo origen, con pausas). Ver `ropa-02`.
* **No** para Lacoste/Adidas: Akamai Bot Manager bloquea **la entrada** (cualquier cliente automatizado recibe `403` en la portada, incluidos los endpoints). Llamar a esas APIs "con los headers correctos y las cookies de sesión" implicaría **reproducir o falsificar las señales/cookies del sensor anti-bot** (o robarlas de un navegador real): es evadir una medida de seguridad, y es justo lo que los puede llevar a bloquear nuestra IP o a reclamar. No se hace.
* Alternativas legítimas: (1) pedir a Lacoste/Adidas Argentina un feed de productos o un programa de afiliados; (2) leerlos desde un revendedor que sí lo permita: se revisaron Moov (Adidas ya entra), Dexter, y los demás sitios integrados: **ninguno tiene Lacoste**; (3) catálogo oficial en Mercado Libre vía su API (requiere cuenta de desarrollador y token; sin revisar); (4) carga asistida (el usuario guarda el HTML de un listado desde su navegador y un parser lo procesa; ver `ropa-02`).
