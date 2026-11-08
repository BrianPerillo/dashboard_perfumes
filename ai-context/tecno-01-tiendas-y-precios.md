# tecno-01 · Rubro Tecnología (PC y electro): tiendas, precios y scraper

Rubro `tecnologia` (agregado el 2026-11-08). Scraper propio: [`descuentos_tecno.py`](../descuentos_tecno.py), lanzador `scripts/descuentos_tecno.bat`, log `logs_tecno.txt`. Datos en las mismas tablas que perfumes y ropa (`tiendas.rubro`, `corridas.rubro` = `'tecnologia'`). Configuración del rubro: `config/rubros.php` → `tecnologia` (ver [14](14-multirubro.md)).

## Tiendas

| Tienda | Cómo se lee | Qué trae | Ritmo |
|---|---|---|---|
| **Compragamer** (`compragamer.com`) | JSON estático **que la propia página descarga**: `https://static.compragamer.com/productos` (≈2,4 MB, el catálogo completo) + `marcas`, `categorias_sub`, `subcategoriasAgrupadores` | ≈1.640 productos con stock; precio de efectivo (`precioEspecial`), precio anterior (`precioEspecialAnterior`), foto, marca, SKU | 4 pedidos, 1–2 s entre ellos |
| **Maximus** (`www.maximus.com.ar`) | WebMethod del propio listado: `POST /wfmWebSite2.aspx/wsNRW_Script` (`web.MAX.GetItemList4Search_v3_V6`), con el `hidWSId` que el sitio publica en su HTML. Una categoría por vez (21), 28 productos por página | ≈1.250 productos; precio de lista, precio tachado (campañas), mejor precio de contado (`bestPriceData`), número de parte | ≈55 pedidos, 2,5–4,5 s (≈3,5 min) |
| **Fullh4rd** (`fullh4rd.com.ar`) | HTML del servidor, `/productos?sort=name_asc&page=N` (12 por página, ≈160 páginas, 650 KB c/u) | ≈1.900 productos; **solo precio de contado y precio lista (tarjeta)**: no publica descuentos | ≈160 pedidos, 2,5–4,5 s (≈10 min) |
| Mexx (`www.mexx.com.ar`) | **No agregada**: el 2026-11-08 el sitio estaba en mantenimiento (HTTP 400, "MEXX \| Mantenimiento", ni `robots.txt`) | — | — |

* `robots.txt`: permitidas las rutas que pedimos en las tres (Compragamer no publica `robots.txt`: devuelve su SPA; Maximus veta login/checkout/perfil; Fullh4rd veta `/cart`, `/order`, `/checkout`, `/tag?`…). Ninguna protección anti-bot en la ruta que usamos (no se evade nada). Ver [16](16-scraping-responsable-y-privacidad.md).
* Identidad por tienda (`productos.nombre_norm`): `cg-<id_producto>`, `mx-<item_id>`, `fh-<id>`. `productos.referencia` guarda el **número de parte del fabricante** cuando la tienda lo publica (Compragamer `codigo_principal` "SKU:", Maximus `item_code4web`; Fullh4rd no): sirve para comparar entre tiendas (pendiente).
* Cada tienda se guarda **al terminar** (corrida `en_curso`); al final se cierra como `completa` si al menos el 60 % de las tiendas dio datos, si no `incompleta` (el sitio solo lee `completa`). `--solo` sin `--fecha` deja la corrida `incompleta` (para no publicar un snapshot con tiendas faltantes); `--fecha AAAA-MM-DDTHH:MM:SS` suma una tienda a una corrida existente.

## Modelo de precios (decisión de diseño)

Las tres tiendas muestran dos precios: **contado/transferencia/débito** (el más bajo) y **tarjeta** (≈ +11 %: Compragamer `precioLista = precioEspecial × 1,111`; Maximus 10 % de descuento por medio de pago; Fullh4rd igual). Para que las tiendas sean comparables:

* `precio_actual` = **precio de contado/transferencia** de la tienda (Compragamer `precioEspecial`; Maximus `bestPriceData.disc_price`; Fullh4rd "precio especial").
* `precio_lista` = ese mismo precio **antes de la rebaja de la tienda**, en la misma base de contado:
  * Compragamer: `precioEspecialAnterior` (si es mayor que el actual; si no, = actual).
  * Maximus: `strikeThroughPrice` (precio de lista antes de la campaña, p. ej. Cyber Week −20 %) × (1 − 10 % por medio de pago); si no hay tachado, = actual. Así el % mostrado es la **campaña real**, no el 10 % fijo por pagar con transferencia.
  * Fullh4rd: no hay precio anterior. `aplicar_historial` lo calcula con **nuestras propias observaciones**: precio máximo de los últimos 30 días del mismo producto; si el actual es >1 % menor, queda como rebaja (`lista` = ese máximo). La primera corrida no tiene historial, así que no marca rebajas; desde la segunda sí. **Es una "baja de precio" medida por nosotros, no un descuento publicado por la tienda.**
* `observaciones.tipo_descuento` = `rebaja` si hay % > 0, `ninguno` si no (el dashboard solo lista `rebaja`; se guarda todo el catálogo para el historial). `fuente`: `compragamer_json`, `maximus_ws`, `fullh4rd_html`. `corrida_sitios.plataforma` (varchar 10): `cg_json`, `maximus_ws`, `fh_html`.
* Verificado contra la ficha de Compragamer (Cougar DUST 2: $425.000 → $335.750 → $89.250 con transferencia; el 79 % es lo que muestra el sitio) y contra la ficha de Maximus (FURY SHOBO SH4: lista $46.100, Cyber Week −20 % → $36.880 con tarjeta, $33.192 de contado).
* Precios de **outlet**: se conservan y el nombre lleva "(Outlet)" si la tienda lo marca y el nombre no lo dice.
* Se excluyen servicios publicados como producto (`_RE_EXCLUIR`: "Armado de PC", "Instalación…").

## Clasificación común (`clasificar`, `detectar_marca`)

* `productos.categoria` (≤40 car.) = categoría **común a las tres tiendas**: Periféricos, Motherboards, Procesadores, Placas de video, Memorias RAM, Almacenamiento, Fuentes, Gabinetes, Refrigeración, Monitores, Notebooks, Tablets, Televisores, PC armadas, Combos y kits, Conectividad, Cables y adaptadores, Sillas y muebles, Impresoras, UPS y estabilizadores, Proyectores, Accesorios y otros, Otros. Se decide mirando el **comienzo del nombre** ("Notebook…", "Mother…", "MONITOR 24…") y, si no alcanza, la categoría que publica la tienda (`_REGLAS_NOMBRE` / `_REGLAS_ETIQUETA`; el orden importa).
* `productos.tipo` = subtipo solo donde aporta: Periféricos (Teclados, Mouses, Auriculares, Mouse pads, Webcams, Parlantes…), Placas de video (GeForce/Radeon/Intel Arc), Procesadores (AMD Ryzen/Intel).
* `productos.marca`: la de la tienda (Compragamer `id_marca`) o, si no, la **primera marca conocida que aparece en el nombre** (`_MARCAS`, ≈120 marcas; "no verificado" que cubra todas: ≈14 % de Maximus queda sin marca).
* Nombres en MAYÚSCULAS (Fullh4rd) se pasan a mayúscula inicial conservando siglas y modelos (`_bonito`).
* Fotos: Compragamer `imagenes.compragamer.com/productos/…-grn.jpg`; Maximus `…/Temp/App_WebSite/App_PictureFiles/Items/<PN>_600.jpg`; Fullh4rd `/img/productos/…`. Todas con `referrerpolicy="no-referrer"` como el resto del sitio.

## Dashboard (qué cambió para este rubro)

* Pestañas: Ofertas (`tabla`), Cambios recientes, Evolución, Seguir producto, Marcas. **Sin "Comparar tiendas"** (pendiente: hace falta cruzar productos entre tiendas por número de parte; Fullh4rd no lo publica).
* Sin filtro de género (un solo valor `unisex` → la sección se oculta), sin talles ni "agrupar colores". Filtro **Categoría** con chips armados con los datos (`Dashboard::$categoriasDisponibles`, config `categorias_dinamicas`) y filtro **Tipo de producto**. Ver [05](05-dashboard-secciones.md).

## Cómo ejecutarlo

```bash
python descuentos_tecno.py --no-db                    # prueba completa sin tocar la base
python descuentos_tecno.py --solo compragamer --no-db # una tienda
python descuentos_tecno.py                            # corrida normal (guarda en MySQL, tienda por tienda)
python descuentos_tecno.py --solo maximus --fecha 2026-11-08T13:19:19   # sumar/rehacer una tienda en una corrida ya guardada
scripts\descuentos_tecno.bat                          # lo mismo que la tarea programada (log en logs_tecno.txt)
```

Tarea de Windows `descuentos_tecno` (diaria 12:00; dura ≈15 min): **definida en `scripts/instalar_tareas_windows.ps1`; registrarla es un paso manual** (`powershell -ExecutionPolicy Bypass -File scripts\instalar_tareas_windows.ps1`). Ver [11](11-entorno-config-despliegue.md).

## Pendiente / no verificado

* Mexx cuando salga de mantenimiento (revisar plataforma y `robots.txt`).
* Comparar tiendas por número de parte (Compragamer y Maximus lo publican; Fullh4rd no, habría que extraerlo del título).
* Fullh4rd depende de nuestro historial: hasta la segunda corrida no hay rebajas; si falta un día de corrida el máximo de 30 días igual alcanza.
* Primera corrida programada (12:00) todavía no observada.
