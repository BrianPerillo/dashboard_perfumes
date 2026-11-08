# 13 · Changelog de documentación y del sistema

Registro cronológico (más reciente arriba). Cada cambio al sitio, al esquema de DB o al scraper agrega una entrada: **fecha ISO · qué cambió · archivos · docs actualizados**.

Formato:

```
## AAAA-MM-DD — Título corto
- Qué cambió y por qué (archivos tocados).
- Docs actualizados: 0X-….md
```

---

## 2026-11-09 — Tecnología: filtro de categorías con selección múltiple

- `Dashboard.php` (`categoriasElegidas`, `setCategoria`, `filtrosRopa`, `cargarOpcionesRopa`, reset y favoritos) y `filtros-ropa.blade.php`: en rubros con `categorias_dinamicas` (Tecnología) las categorías **se suman** (antes la nueva reemplazaba a la anterior); "Todas" limpia la selección y cada chip se quita volviendo a tocarlo. Ropa conserva la selección única (`categoriaFiltro`: ropa/calzado/accesorios). Verificado en el navegador: Monitores 146 → + Gabinetes 221 → + Procesadores 327 → − Gabinetes 252 → Todas 1.691.
- Docs actualizados: 05, 14.

## 2026-11-09 — Tecnología: Gezatek, Wiztech, Armytech y Gamingcity

- `descuentos_tecno.py`: 4 adaptadores nuevos (`gz_html`, `wt_json`, `at_html`, `gc_html`; ver [tecno-01](tecno-01-tiendas-y-precios.md)) y entradas en `SITES`; el historial de 30 días ahora también se usa en Wiztech (y no pisa rebajas ya publicadas); clasificación sin tildes + búsqueda de la palabra de tipo en cualquier parte del nombre + categorías nuevas (Hogar y electro, Consolas y juegos, Celulares y wearables, Cámaras y smart home); `--reclasificar` (sin scrapear).
- Carga: `--solo wiztech armytech gezatek gamingcity --fecha 2026-11-08T13:19:19` dentro de la corrida visible #277 (de 4.790 a 8.675 productos; 1.691 con rebaja). Gezatek 1.227, Wiztech 176, Armytech 520, Gamingcity 1.962. `--reclasificar` corrigió 208 productos.
- Dashboard (`Dashboard.php`): `filtro_colores: false` ahora también desactiva el agrupado por modelo+precio (en Tecnología ocultaba 191 ofertas). Sin otros cambios de UI.
- Docs actualizados: tecno-01, 03, 11, 12, 14, 16.

## 2026-11-08 — Nuevo rubro Tecnología (PC y electro): Compragamer, Maximus y Fullh4rd

- **Scraper nuevo `descuentos_tecno.py`** (+ `scripts/descuentos_tecno.bat`, `.gitignore`): un adaptador por tienda (Compragamer: JSON estático del propio sitio; Maximus: WebMethod del listado con el `hidWSId` público; Fullh4rd: HTML paginado), guardado tienda por tienda en una corrida `en_curso` que se cierra `completa` (≥60 % de tiendas OK). Precios en base **contado/transferencia**; la rebaja de Fullh4rd (no publica descuentos) sale del historial propio de 30 días. Clasificación común de categoría/tipo/marca. Mexx no se agregó (en mantenimiento). Primera corrida real: #277.
- **Dashboard** (`config/rubros.php` → `tecnologia`, `Dashboard.php`, `filtros-ropa`, `dashboard`, `tab-tabla`, layout, `_alerta_form`): rubro `/tecnologia/*` con pestañas Ofertas/Cambios/Evolución/Seguir/Marcas; claves nuevas de config (`nombre_corto`, `categorias_dinamicas`, `tipo_label`, `usa_talles`, `filtro_colores`; género oculto con un solo valor); alertas con textos por rubro.
- **BUG de ropa corregido** (corridas `en_curso` desde el 2-nov por microsegundos en `fecha_hora`; 5 corridas reparadas): ver 12.
- **Candado de MySQL** (`scripts/mysql_asegurar.bat`, usado por los 4 lanzadores) tras el doble arranque de `mysqld` del 2026-11-08: ver 11.
- La tarea `descuentos_tecno` (diaria 12:00) quedó definida en `instalar_tareas_windows.ps1` pero **sin registrar** (requiere ejecutarlo a mano).
- Docs actualizados: tecno-01 (nuevo), README, 03, 04, 05, 07, 10, 11, 12, 14, 16.

## 2026-11-02 — Ropa: Old Bridge (Tiendanube)

- `descuentos_ropa.py`: nueva entrada `oldbridge` en `SITES` (adaptador `tiendanube` existente, catálogo `/productos/`, género `hombre`, `pag_max` 60) y dos patrones de `_TIPOS` (`denim` → Jean; `sock(s)` → Medias).
- Prueba y carga: `--solo oldbridge --fecha 2026-11-01T16:31:46` → 293 productos (56 con descuento, 293 con foto) dentro de la corrida visible #173 (total 16.757 → 17.050), sin tocar el resto de las tiendas. En Cambios figura como tienda nueva frente a la corrida anterior.
- `robots.txt` revisado (permite el catálogo); ritmo de Tiendanube (2,5–4,5 s por página, ~26 pedidos).
- Docs actualizados: ropa-02, 03, 12, 16.

## 2026-11-02 — Juleriaque/Parfumerie: diagnóstico corregido, reintento y `--fecha` en el scraper de perfumes

- **Diagnóstico**: Juleriaque nunca dejó de traer datos; Parfumerie dio 0 una sola vez (corrida #176, 2026-11-01 20:24) y por eso no figuraba en el snapshot visible. El A16 inicial ("devuelven 0") era erróneo y se corrigió.
- `descuentos_master.py`: (1) un sitio HTML que devuelve 0 productos se reintenta una vez tras ~1 min; (2) nuevo `--fecha` para guardar dentro de una corrida existente (implica `--force-db`); (3) `guardar_en_mysql` recalcula `total_productos/min/max` de la corrida desde las observaciones.
- Reparación de datos: Parfumerie (38 obs.), Juleriaque, Normy y Ruiz y Roca se leyeron de nuevo dentro de la corrida #176 (de 3.098 a 3.295 observaciones); todas las tiendas HTML tienen ahora foto salvo ~84 variantes de Juleriaque.
- La tarea `descuentos_master_perfumes` no corrió el 2026-10-31 ni el 2026-11-01 a las 13:00 (PC apagada; corrió al encenderse a las 16:31 y 20:24, `StartWhenAvailable`): los datos de perfumes de esos días son de las corridas de recuperación.
- Docs actualizados: 12 (A16), scraper-01 (`--fecha`), scraper-02.

## 2026-11-02 — Carga inmediata de las tiendas nuevas, auditoría de ritmo/robots y enlaces `noreferrer`

- **Carga ahora**: las 8 tiendas nuevas de ropa (Cueros Mi Campo, Devre, Catriel, Moov, Tascani, Mistral, Stone, Briganti) se cargaron **dentro de la corrida visible #173** con `descuentos_ropa.py --solo <tienda> --fecha 2026-11-01T16:31:46`, una por una (la corrida pasó de 8.066 a 16.757 productos). Efecto: en Cambios las tiendas nuevas figuran como "nuevas" frente a la corrida anterior. La corrida de las 15:00 las volverá a leer con normalidad.
- **Moov**: la primera carga cortó en 324 productos porque su orden "mayor descuento" no es estricto; ahora se corta tras 4 páginas seguidas sin descuentos (`"tolerancia": 4`): 2.036 productos.
- **Caché**: `Dashboard::huellaCorrida()` (id + `total_productos` de la última corrida) reemplaza al id solo en las claves de caché, para que completar una corrida ya publicada (`--fecha`) renueve la caché.
- **Fotos de perfumes**: se ejecutó `descuentos_master.py --rellenar-imagenes` (4.763 productos con foto nueva): Beauty24, Farmalife, Pigmento, Rouge, Fiorani, GetTheLook, Unishop y Ambrosia quedan ≈ 100 % con foto. Siguen sin foto Normy y Ruiz y Roca (sitios HTML: se completan en la corrida diaria) y Juleriaque/Parfumerie (devuelven 0 productos, A16).
- **Enlaces**: los 11 enlaces a tiendas del dashboard y el del mail llevan `rel="noopener noreferrer" referrerpolicy="no-referrer"`.
- **Auditoría de ritmo y robots** (doc nuevo [16](16-scraping-responsable-y-privacidad.md)): Shopify 10–15 s entre páginas; fichas de Dexter 1–1,8 s; perfumes `_pausa()` 1–1,8 s (antes 0,4–0,9 s) y `_get` reintenta 429/503. **Wrangler desactivada** (`robots.txt` `Disallow: /`).
- Docs actualizados: 16 (nuevo), README, 10, 12, ropa-02.

## 2026-11-01 — Ropa: Moov y Briganti agregadas; Columbia investigada y dejada desactivada

- `descuentos_ropa.py`: adaptadores nuevos `sfcc_http` (Moov: Salesforce Commerce Cloud por HTTP con `Search-UpdateGrid`, reusa `_tarjetas_sfcc`, que ahora toma el género de la sección y la marca por defecto de la tienda) y `shopify` (Briganti, Columbia: `/products.json` con pausas largas y espera ante 429; opciones por nombre; `excluir_tipos`, `dto_tags`). `observaciones.fuente`: `sfcc_html` y `shopify_json`.
- **Moov** (multimarca, `/hombre` + `/mujer` + `/sale`): 1.606 productos, 1.522 con descuento, ~4,5 min, sin talles. **Briganti** (calzado/cuero, outlet): 3.355 productos, 3.352 con descuento, ~11 min.
- **Columbia** queda `"activo": False`: su JSON no trae precio de lista; el descuento Cyber Monday es de colección y no coincide siempre con la tag (103 de 139 en la muestra). Detalle y opciones en ropa-02.
- Pruebas: catálogo completo de Moov y Briganti sin DB y guardado de prueba (60 observaciones en una corrida `en_curso` de 2099, limpiada por ids; la última corrida visible siguió siendo la #173).
- Docs actualizados: ropa-02, 03, 12.

## 2026-11-01 — Ropa: 6 tiendas nuevas (Tascani, Catriel, Mistral, Stone, Cueros Mi Campo, Devre) y 2 adaptadores

- `descuentos_ropa.py`: nuevas entradas en `SITES` y adaptadores `magento` (Devre) y `prestashop` (Brooksfield, **desactivada**: sin ofertas hoy). Tascani usa el adaptador `vtex_api` existente; Catriel, Mistral, Stone y Cueros Mi Campo, el `tiendanube`, que ahora soporta dos temas de tarjeta, **detecta por producto qué opción es talle y cuál color** (`_opciones_tn`, `_RE_TALLE`), toma el género de la sección (`/hombre/`, `/mujer/`, `/kids/`) y pide la imagen a 480 px. El guardado sigue siendo **tienda por tienda** (corrida `en_curso` hasta cerrarla).
- Adidas **no se agregó**: `403` de Akamai (no se sortea); sus productos ya entran vía Dexter. Stone resultó ser Tiendanube (no Mercado Libre).
- Pruebas: catálogo completo de cada tienda sin DB (Tascani 1.465 productos, Devre 719, Mistral 438, Stone 313, Catriel 199, Cueros 103) y guardado de prueba en una corrida `en_curso` de 2099 (210 observaciones, 7 tiendas) que se limpió por ids; `Corrida::ultima('ropa')` no cambió.
- Otros: tipos nuevos (Relojes, tapado/sobretodo/blazer → Campera, chalina, media, calzoncillo, perfumería), reintento ante 500/502/504 en la API VTEX, espacios de las URL de imagen codificados, `observaciones.fuente = magento_html`, y **corregido un bug previo**: cuatro patrones de `normalizar_disciplina` tenían `\b` convertido en un carácter de control y nunca coincidían.
- Docs actualizados: ropa-02, 03, 12, README.

## 2026-11-01 — Fotos de perfumes (scraper + UI)

- `descuentos_master.py` captura `Item.imagen` en VTEX, Unishop, Tiendanube y el motor HTML (helper `_url_imagen`: https, URL absoluta, sin `data:`; VTEX a 400 px, Tiendanube ≤ 640 px) y la guarda en `productos.imagen` con `COALESCE(VALUES(imagen), imagen)` (una corrida sin foto no borra la que ya hay).
- Nuevo flag `--rellenar-imagenes`: parche único (sin corrida, observaciones ni Excel) que completa fotos vacías en VTEX/Unishop/Tiendanube por HTTP. Ya se ejecutó acotado sobre la base real (Ambrosia, Fiorani, GetTheLook y Unishop: ~2.300 productos); el resto se completa en la corrida diaria de las 13:00.
- Destacados muestra la foto en cada card (72×72, `loading="lazy"`, ícono del rubro si falta); las URL se piden solo para las cards visibles (`Dashboard::imagenesProductos`, una consulta por clave primaria). La tabla de perfumes y Comparar muestran la miniatura (`object-contain` sobre blanco). Los mails de alerta ya incluían la foto.
- Pendiente (A16 en `12`): Juleriaque y Parfumerie devuelven 0 productos (sin foto hasta arreglar el scraper).
- Docs actualizados: 03, 05, 12, scraper-01, scraper-02, scraper-03.

## 2026-11-01 — Spinner de carga siempre visible y configuración de rendimiento aplicada

- El overlay de carga del dashboard (`livewire/dashboard.blade.php`) pasa a `position: sticky; top: 42vh` para la ruedita: aparece centrada en la pantalla aunque el usuario haya scrolleado (antes quedaba a 30vh del inicio de la card y se veía solo arriba). Verificado con Playwright (request lenta, scroll al fondo): spinner centrado en `/tabla`, `/joyas` y `/ropa/tabla`.
- Aplicado lo pendiente de rendimiento: `innodb_buffer_pool_size=256M` en `my.ini` (sin reiniciar MySQL) y `opcache` en `php.ini` con Apache reiniciado; respaldos `*.bak-20261101`. Tiempos vía Apache: `/tabla` 1,4 → 0,25 s, `/ropa/tabla` 1,9 → 0,7 s, `/cambios` 1,5 → 0,5 s, `/joyas` 2,5 → 1,3 s.
- Docs actualizados: 10, 11, 15.

## 2026-11-01 — Rendimiento: Destacados, Evolución, Seguir y tabla

- Auditoría de velocidad (nuevo [15-rendimiento.md](15-rendimiento.md)). `/joyas` 10,9 s → 0,8 s (frecuencia/antigüedad por `producto_id` en `statsJoyas`; cards con scroll infinito de a 30: HTML 897 → 371 KB); `/seguir` 8,6 s y 4,6 MB → 0,9 s y 183 KB (búsqueda en el servidor, `buscarSeguir`); `/evolucion` y la tabla con caché por consulta + última corrida (`Dashboard::cacheado`); marcas/ml disponibles cacheados. Verificado: mismos valores que la versión anterior (415/415 nombres), suite (24 ok), auditoría de filtros de ropa contra MySQL (pasa, 63 s vs 150 s) y navegador (scroll infinito, búsqueda, sin errores nuevos).
- Causa de fondo: `innodb_buffer_pool_size` de 16 MB; subido en caliente a 256 MB (no persistente). **Pendiente (requiere reiniciar servicios):** fijarlo en `my.ini` y activar opcache.
- Docs actualizados: 05, 11, 15 (nuevo) y README.

## 2026-11-01 — Tareas de Windows sin ventana de consola

- Las tareas `alertas_perfumes` (cada 3 h) y `descuentos_ropa` (Interactive) abrían una consola negra en el escritorio. Nuevo `scripts/ejecutar_oculto.vbs` (estilo de ventana 0, espera el fin, propaga el código de salida); ambas tareas ahora ejecutan `wscript.exe //B //Nologo ejecutar_oculto.vbs <bat>` (se cambió solo la acción; límites y horarios iguales) y `instalar_tareas_windows.ps1` registra así las dos. Probado: código de salida 3 propagado, argumentos pasados y `alertas_procesar.bat --dry-run` escribiendo en `storage\logs\alertas_cron.log` sin ventana. `descuentos_master_perfumes` no se tocó (Password: no abre ventana).
- Docs actualizados: 11.

## 2026-11-01 — Ropa: guardado tienda por tienda con estado de corrida

- Nueva columna `corridas.estado` (`en_curso`/`incompleta`/`completa`, default `completa`; migración defensiva `2026_11_01_000000_add_estado_to_corridas`, aplicada). `descuentos_ropa.py` guarda cada tienda al terminarla en una corrida `en_curso` y la cierra al final (`cerrar_corrida`) según el control de calidad. Sitio y alertas leen solo corridas `completa`: `Corrida::completas()`/`ultima()`, `KpiPanel`, `Dashboard` (selector de corridas, evolución, frecuencia/primera fecha de joyas, seguir producto), `ProcesarAlertas`. Perfumes no cambia.
- Verificado con corridas de prueba de Wrangler (2099 → incompleta, 2000 → completa; se borraron y se restauró `productos.ultima_vez`), suite (24 pasan, 2 skip) y auditoría de filtros de ropa contra MySQL. Corrida real de ropa del 2026-11-01: #173, 8 tiendas, 8066 productos (Dexter 69 min).
- Docs actualizados: 03, 05, 07, 11, 12, ropa-02.

## 2026-10-31 — Sidebar: el filtro abierto usa todo el alto y talles sin ceros

- Con un filtro abierto también se pliegan las cabeceras de los otros (y se quita su relleno): el abierto usa todo el alto. Para cambiar de filtro se cierra y se abre otro. Talles numéricos con cero a la izquierda (`05`) se unifican con `5` en el scraper y en `--renormalizar-tipos` (`sin_ceros`). Detalle en 10.

## 2026-10-31 — Menú lateral: acordeón a pantalla completa y opciones más legibles

- Al abrir un filtro del sidebar (p. ej. Talle) ocupa todo el alto disponible con scroll propio y al cerrarlo vuelve todo; corrige además el recorte por `max-height: 800px`. Chips y títulos con tipografía mayor. Verificado con Playwright (abrir/cerrar, sin errores de consola). Detalle en 10.

## 2026-10-30 (noche) — Comparador entre tiendas en ropa

- Pestaña "Comparar tiendas" en ropa (modelo = código de marca normalizado, `App\Support\Modelo`): ~50 modelos hoy (Nike/Puma/Topper en Dexter vs. la tienda oficial). Detalle en 05. Verificado con Playwright; test unitario nuevo.

## 2026-10-30 (noche) — Talle equivalente H↔M y rangos de talles

- Checkbox "incluir talle equivalente" en el filtro de talle (05) con test sobre la base real. Rangos de talles de calzado (`40-40.5`, `39/40`, `38.39`) expandidos a valores sueltos en el scraper y en `--renormalizar-tipos` (solo calzado).

## 2026-10-30 (noche) — Checkpoint y `--reanudar` en el scraper de ropa

- Una corrida cortada ya no pierde las ~2,5 h de lectura: se guarda un checkpoint por sección y `--reanudar` termina el guardado (ver ropa-02). Probado con un checkpoint sintético y `--no-db`.

## 2026-10-30 (noche) — Límite de la tarea de ropa a 5 h

- La corrida de ropa con 8 tiendas dura ~2,5 h (Nike 32 min, Kevingston 40, Dexter 62, Puma 7, Prototype 4, Levi's/Topper 3). Se subió el límite de la tarea `descuentos_ropa` de 2 h a 5 h (`Set-ScheduledTask`, tarea Interactive que creamos nosotros) y en `scripts/instalar_tareas_windows.ps1`. Nota: el guardado en MySQL se hace al final; si la corrida se corta, no se guarda nada.

## 2026-10-30 (noche) — Puma

- Adaptador `puma` en `descuentos_ropa.py` (estado `mobify-data` del listado paginado `?page=N&limit=32`): 1122 productos, 773 con descuento, cargados en la corrida #164. Sin talles ni género explícito (ver [ropa-02](ropa-02-tiendas-y-plataformas.md)).

## 2026-10-30 (noche) — Vista de tarjetas en Ofertas

- Switch tabla / tarjetas sobre los resultados (por defecto tabla; se recuerda en la sesión). Verificado con Playwright en escritorio, celular y perfumes (detalle en 05). Bug corregido durante la prueba: `Undefined array key "vista"` al restaurar filtros de sesión sin esa clave.

## 2026-10-30 (noche) — Talles de Dexter, tipos unificados, zoom de imagen y auditoría de filtros

- Dexter ahora trae talles (ficha de cada producto). La línea de detalle de la tabla se muestra para todas las tiendas.
- Tipos de producto y deportes unificados entre tiendas (`normalizar_tipo` / `normalizar_disciplina`, `--renormalizar-tipos`): el filtro "Prenda / tipo" pasa de ~130 valores casi repetidos a ~40.
- Zoom al pasar el cursor sobre la miniatura (visor global en el layout) y test de auditoría de filtros contra la base real. Detalle en 05, 10 y ropa-02.

## 2026-10-30 (noche) — Kevingston, Dexter, Wrangler, Prototype y Montagne (desactivada)

- Nuevos adaptadores en `descuentos_ropa.py`: `kevingston` (endpoint interno de listado + talles por producto), `sfcc_dexter` (Salesforce, multimarca), `wrangler` (fragmento HTML de LAST CHANCE), `tiendanube` (Prototype) y `montagne` (HTML; **desactivada** por el precio "Comunidad Montagne"). `tipo`/`color`/`disciplina` se normalizan con mayúscula inicial (`_cap`); talles `3XS/2XS` reconocidos (Python + `Dashboard::grupoTalle/ordenTalle`). `SITES` acepta `"activo": False`.
- Corrida #164 ahora con 7 tiendas / 6880 observaciones (ver [ropa-02](ropa-02-tiendas-y-plataformas.md)). Puma (scroll infinito) y Stone (Tienda Oficial de Mercado Libre) quedan pendientes; Lacoste/Adidas bloqueadas.

## 2026-10-30 (noche) — Adaptador `vtex_api` y tiendas Levi's y Topper

- `descuentos_ropa.py` pasa a **adaptadores por plataforma**: `vtex_io` (Nike, navegador) y nuevo `vtex_api` (catálogo público de VTEX por HTTP, sin navegador). Se agregaron **Levi's** (699 leídos, 464 con descuento) y **Topper** (647 / 548) a la corrida #164 con `--fecha`. `corrida_sitios` registra la plataforma real; `observaciones.fuente` = `vtex_api`.
- Talles de calzado ≥ 30 (numeración argentina de Topper) sin prefijo H/M (`Talles.php` + Python) y grupo propio en el filtro; test agregado.
- Investigación de plataformas de las demás tiendas y bloqueo de Lacoste/Adidas (Akamai): nuevo doc [ropa-02](ropa-02-tiendas-y-plataformas.md). No se sorteó el bloqueo.

## 2026-10-30 (noche) — Imagen del producto en el email de alertas

- `ProcesarAlertas` selecciona `p.imagen` y el mail la muestra (07). Verificado con `alertas:procesar --dry-run`; el render del mail no se probó con un envío real.

## 2026-10-30 (noche) — Filtros en celular: botón "Filtros" y "Ver resultados"

- Al probarlo en un navegador real (Playwright, 390 px) los filtros quedaban ocultos tras ☰. Se agregó el botón **⚙️ Filtros** con contador en la barra de búsqueda (solo móvil), el botón **Ver resultados** al final del panel móvil y `whitespace-nowrap` en los `ver →`. Se recompiló el CSS (`npm run build`). Detalle en 10.

## 2026-10-30 (noche) — Auditoría de documentación

- Verificación cruzada automática (migraciones, modelos/componentes/comandos, scripts, vistas y tests vs. docs): todo estaba mencionado salvo `app/Support/Talles.php`. Se agregó al mapa de carpetas (02), el **esquema vigente de `productos`** y de las columnas `rubro` (03, contrastado con `information_schema`) y el comportamiento de las tareas con MySQL/XAMPP (11). Índice (README) y changelog al día.

## 2026-10-30 (noche) — Talles de calzado normalizados (hombre / mujer)

- Con los datos reales (calzado masculino con números de hombre, femenino con números de mujer, unisex con `H x / M y`) se normalizó: nueva columna `productos.talles_norm` (migración con backfill, aplicada), helper PHP `App\Support\Talles` + copia en el scraper (paridad verificada con 8 casos; `TallesTest`, 7 tests), filtro de talles agrupado en *Calzado hombre (talle H)* / *Calzado mujer (talle M)*, y alertas por talle (`H10`, `M7`, o número simple). Probado: H10 → 238, M7 → 89 (incluye unisex `H 5.5 / M 7`); guardado real de prueba (borrado); 20 tests pasan. Docs: 03, 05, 07, 12, ropa-01.

## 2026-10-30 (tarde) — Primera corrida programada de ropa, bug de paginación corregido, verificación con 2 corridas reales

- La tarea `descuentos_ropa` corrió sola a las 15:00 (corrida #164). **Datos nuevos verificados:** imágenes 100 %, deporte, línea. Perfumes (13:00, #163): 33/33 sitios ok.
- **Bug mío detectado:** corte prematuro de paginación (hombre/ropa 160 contra ~306). Corregido (`n_crudos`), nueva opción `--fecha` para completar una corrida, totales recalculados; hombre/ropa completada dentro de #164 (306 productos). Detalle en [ropa-01](ropa-01-scraper-nike.md).
- **Verificado con 2 corridas reales** (29/10 20:31 y 30/10 15:00): Cambios (60 nuevos en oferta, 74 salieron), Seguir (historial de 2 puntos), KPI con deltas, filtros Deporte/Línea con datos, imágenes en la tabla, tarjeta de estado del admin. Docs: ropa-01, 12.

## 2026-10-30 (noche, 9.ª tanda) — Prenda/Deporte/Línea con todo marcado por defecto

- Por pedido del usuario: Prenda / tipo (y por coherencia Deporte y Línea) pasan a "todo seleccionado, desmarcar para quitar", como Género. Probado con base real (desmarcar Zapatillas 983→551; + Botines 482; Ninguno 0; favoritos restauran exclusiones; productos sin disciplina siguen apareciendo). Docs: 05.

## 2026-10-30 (noche, 8.ª tanda) — Panel de filtros en acordeón y filtro de línea

- Panel lateral: Género y Categoría fijos; el resto **cerrado por defecto y en acordeón** (abrir una sección cierra las demás). "Precio y colores" pasó a ser sección colapsable.
- **Línea de producto** (`productos.linea` ← spec "Icono" de Nike): migración aplicada, scraper la captura (probado con un guardado real de prueba, luego borrado), filtro "Línea" en el dashboard (probado con datos de prueba revertidos: Air Max 147; Air Max + Mercurial 274). Se llena con la corrida del 31/10 15:00.
- Docs: 03, 05, 12, ropa-01.

## 2026-10-30 (noche, 7.ª tanda) — Barra sin contenedor, filtro de prenda y de deporte

- Barra de búsqueda directamente sobre el fondo (sin caja gris). Filtro **Prenda / tipo** abierto por defecto y con nombres amigables; nuevo filtro **Deporte** (`productos.disciplina`, migración aplicada; scraper la captura desde la próxima corrida).
- **Bug evitado antes de la corrida de mañana:** al agregar `disciplina` a `ItemRopa` el dataclass quedó con un campo con valor por defecto antes de otros sin él (TypeError que habría roto `descuentos_ropa.py`); detectado en la prueba, corregido (campo al final) y verificado con un guardado real de prueba (registro `zz_test` creado y borrado).
- Probado con base real + datos de prueba en transacción revertida. Docs: 03, 05, 12, ropa-01.

## 2026-10-30 (noche, 6.ª tanda) — Desplegable de marcas

- El usuario reportó (capturas) que el desplegable de marcas no se cerraba al hacer clic fuera y quedaba fijo al scrollear. **Causa:** posición `fixed` calculada al enfocar + cierre manual con listener de `mousedown`. **Arreglo:** desplegable `absolute` dentro del campo (se mueve con la página), cierre con `@click.outside`, Esc o al elegir; muestra las marcas al enfocar (máx. 40, filtradas al escribir) y "Sin coincidencias". Archivo: `livewire/dashboard/barra-busqueda.blade.php`. No verificado visualmente.

## 2026-10-30 (noche, 5.ª tanda) — Ajuste de la barra de búsqueda y CSS compilado

- El usuario reportó (captura) que los campos de búsqueda salían apilados a todo lo ancho y el ícono pisaba el placeholder. **Causa:** `public/build` (CSS de Tailwind compilado) era del 30/06 y no incluía las clases usadas por las vistas nuevas. Se ejecutó `npm run build`, se fijaron anchos (`sm:w-72` producto, `sm:w-60` marca, uno al lado del otro) y un `padding-left` en línea para los inputs. Nota agregada en `AGENTS.md` (hay que recompilar tras agregar clases).

## 2026-10-30 (noche, 4.ª tanda) — Búsqueda global de producto y marcas

- Por pedido del usuario: los buscadores de producto y marca pasan del sidebar a una barra bajo "Indicadores", **globales** (filtran todas las secciones) y **persistentes** entre páginas (sesión por rubro). Marcas múltiples con autocompletado y chips. Probado con la base real (ropa: "jordan" → 105 de 983; Nike + texto → 360; la sesión restaura en otra página; limpiar vacía sesión). **No verificado visualmente.** Docs: 05, 10.

## 2026-10-30 (noche, 3.ª tanda) — Menú lateral solo con filtros

- Por pedido del usuario (capturas del sidebar): se quitó el bloque del logo y la lista de pestañas del sidebar para ganar espacio; el sidebar muestra solo los filtros de la página actual (aviso si no hay), y la navegación queda en el header, que ahora lleva una marca compacta "Descuentos ARG". Verificado el HTML de todas las páginas; **no verificado visualmente** (revisar en el navegador/celular). Docs: 04, 10, 14.

## 2026-10-30 (noche, 2.ª tanda) — Cambios y Seguir de ropa por producto, talles en mensajes

- **Bug latente evitado:** Cambios y Seguir identificaban productos por `tienda||nombre`; en ropa mezclaban los colores de un mismo modelo. Ahora usan `productos.id` fuera de perfumes (nombre con color). Pestaña **Seguir producto** visible en ropa.
- Probado con una **2.ª corrida simulada dentro de una transacción revertida** (110 nuevos, 15 subas, historial de 2 puntos; la base quedó sin residuos).
- Email y Telegram de alertas muestran los talles disponibles.
- Docs: 05, 07, 12, 14.

## 2026-10-30 (noche) — Mejoras de ropa: colores agrupados, rango de precio, alertas por talle, estado del scraper

- **Agrupar colores por modelo** en la tabla de ropa (1.124 → 983 renglones) y **rango de precio**; ambos probados con la base real.
- **Alertas por talle y % mínimo**: migración `2026_10_30_010000` (aplicada), formulario, chips en la lista y filtro en `ProcesarAlertas` (probado con `--dry-run`; alertas de prueba borradas).
- **Panel admin**: tarjeta "Estado de los scrapers" por rubro/sitio.
- Docs: 03, 05, 07, 09, 12.

## 2026-10-30 (noche) — Tarea programada de ropa registrada

- Con autorización del usuario se ejecutó `scripts/instalar_tareas_windows.ps1`: registrada `descuentos_ropa` (diaria 15:00, Interactive) y re-registrada `alertas_perfumes` (cada 3 h). La tarea del scraper de perfumes sigue apuntando al redirector (requiere contraseña, A3). Primera corrida automática de ropa: 30/10 15:00.

## 2026-10-30 (tarde) — Modo `descuento` de Nike, filtros de ropa, favoritos por rubro

- **Scraper:** el usuario detectó (captura) que el calzado de hombre sí tiene ofertas. Causas: URL equivocada (`/oportunidades/hombre/calzado` está vacía; el listado real es `/hombre/calzado?order=OrderByBestDiscountDESC`) y **bug del parser** (el estado trae varias `productSearch`; se tomaba la primera, vacía). Nuevo modo por defecto `descuento` (categorías ordenadas por mayor descuento, corta en la primera página sin ofertas) y captura de `imagen`. Primeros resultados: hombre/calzado 429 productos (409 con descuento), hombre/ropa 324 (294), mujer/calzado 216 (188).
- **Dashboard de ropa:** favoritos **por rubro**; filtros de categoría, **tipo de producto** y **talles agrupados por sistema** (letras / niños / numérico / calzado / calzado infantil), aplicados a todas las pestañas; imagen del producto; textos "perfumes" corregidos. Detalle en [05](05-dashboard-secciones.md). Probado con la base real (talle M → 232; M o XL → 260; + tipo Remera → 91; favoritos separados).
- **Corrida completa modo `descuento`** (32 min, 7 secciones): **1.316 productos, 1.124 con descuento** → corrida #159 guardada por el propio script; corrida de prueba #158 eliminada. Talles: nuevo grupo *Calzado unisex (H / M)* y *Numérico (accesorios)*. Detalle por sección en [ropa-01](ropa-01-scraper-nike.md).
- Ideas de mejora de ropa documentadas en [12](12-problemas-conocidos-y-pendientes.md).

## 2026-10-30 (continuación) — MySQL reparado, migración aplicada, alertas por rubro, pruebas con base real

- **MySQL:** con autorización del usuario se recreó `tiendas` (12 filas con ids originales; 0 huérfanos; `mysqlcheck` OK) y `mysql.gtid_slave_pos`; respaldo físico previo en `C:\xampp\mysql\data_backup_20261029_prerepair`. Migración `2026_10_30_000000_add_rubro_multirubro` **aplicada**.
- **Verificado con la base real:** las 11 pestañas de perfumes y las de ropa cargan; `/ropa`, `/ropa/tabla`, `/planes`, etc. responden bien; KPI por rubro OK.
- **Bug corregido:** `/planes` daba 500 (`features` doblemente codificado en `PlanesSeeder`).
- **Alertas por rubro:** selector de rubro en el formulario, sugerencias por rubro, `ProcesarAlertas` filtra por rubro (probado con una alerta de prueba, luego borrada).
- **Scraper de ropa → MySQL:** probado (corrida de 1 sección: 18 productos con talles, tipo, color y referencia; luego borrada) y lanzada la corrida completa de 6 secciones de Nike vía `scripts/descuentos_ropa.bat`. Ritmo adaptativo agregado al scraper (pausa × factor que sube tras cada 429).
- **Lacoste:** Salesforce Commerce Cloud, datos de precio muy limpios en el HTML, pero **403 Access Denied** a navegador automatizado → no se evade; decisión pendiente (A15).
- **Corrida completa de Nike** (22 min, 497 productos, 485 con descuento; 2 secciones de calzado legítimamente vacías) importada a MySQL como corrida #158 desde su Excel; control de calidad ajustado (vacía ≠ fallida). `/ropa/tabla` verificado con datos reales.
- **Tarea de alertas** ejecutada sola a las 20:00 (envió el mail de la alerta #4). La tarea `descuentos_ropa` **no se registró** (permiso denegado; ver A14).
- Instalador de tareas: agregada `descuentos_ropa` (15:00). Docs: 07, 11, 12, 14, ropa-01.

## 2026-10-30 — Inicio de multi-rubro: ropa (Nike), config de rubros y selector

- **Investigación Nike:** VTEX IO + Cloudflare (403 a `requests`; Playwright carga; 429 si se insiste); datos embebidos en `__STATE__`. Ver [ropa-01-scraper-nike.md](ropa-01-scraper-nike.md).
- **Scraper nuevo:** `descuentos_ropa.py` (+ `scripts/descuentos_ropa.bat`), probado con `--no-db`: 299 productos / 282 con descuento en `/oportunidades/hombre/ropa`. Versionado (`!descuentos_ropa.py`).
- **Multi-rubro Laravel:** `config/rubros.php`; rutas por rubro (`/ropa/{tab}`); layout con menús desde la config y selector de rubro; `Dashboard`/`KpiPanel` parametrizados; `Corrida::deRubro/ultima($rubro)`, `Tienda::deRubro`; tabla con talles/tipo/color y filtro de categoría; migración `2026_10_30_000000_add_rubro_multirubro` **escrita, no aplicada**. Tests: 13 pasan (`RubrosConfigTest`). Ver [14-multirubro.md](14-multirubro.md).
- **Incidente MySQL:** `mysqld` huérfano + doble arranque desde el panel dañaron `tiendas` (y `mysql.gtid_slave_pos`). Se hizo respaldo físico (`data_backup_20261029_prerepair`); la reparación quedó pendiente de autorización (A0). Los `.bat` ahora arrancan MySQL con `--defaults-file` de XAMPP.
- Docs: 02, 03, 04, 05, 10, 11, 12, 14 (nuevo), ropa-01 (nuevo), README, AGENTS.

## 2026-10-29 (noche) — Tareas de Windows, `.bat` al proyecto y scraper versionado

- **Alertas:** tarea de Windows `alertas_perfumes` cada 3 h (desde las 14:00) → `scripts/alertas_procesar.bat` → `php artisan alertas:procesar` (log en `storage/logs/alertas_cron.log`). Se **quitó** el `Schedule` de `routes/console.php`. Modo *Interactive* (S4U requiere administrador).
- **`.bat` del scraper al proyecto:** `scripts/descuentos_master.bat` (rutas relativas, arranca MySQL solo si hace falta, acepta argumentos, propaga el exit code). El `.bat` viejo de `C:\xampp\htdocs\Clases\descuentos\` quedó como **redirector** (backup `descuentos_master_bat.bat.bak-20261029`) porque la tarea `descuentos_master_perfumes` está guardada con contraseña y Windows no permitió repuntarla sin ella. Instalador: `scripts/instalar_tareas_windows.ps1`.
- **Scraper versionado:** `.gitignore` con `!descuentos_master.py` y `!descuentos_master_cambios.md` (+ `__pycache__/`, `*.pyc`).
- **Verificado de punta a punta** (redirector → `.bat` del proyecto → Python, con `--no-db` y Excel de salida en carpeta temporal): Ambrosia (HTTP) ~143 productos por género tras el fix; Parfumerie (Playwright) 28 ofertas. Se detectó y detuvo a tiempo una corrida completa lanzada por error durante las pruebas (no guardó corrida ni Excel; solo dejó líneas parciales en `logs_scraper.txt` ~19:17).
- Docs actualizados: 02, 07, 11, 12, scraper-01, README, AGENTS.md.
- **Pendiente:** ejecutar `scripts/instalar_tareas_windows.ps1` en consola normal (pide la contraseña) para repuntar la tarea del scraper; luego borrar el redirector.

## 2026-10-29 (tarde) — Corrección de todos los bugs documentados + verificación de qué scraper se ejecuta

- **Scraper, qué se ejecuta:** el Task Scheduler (`descuentos_master_perfumes`, diario 13:00) lanza `C:\xampp\htdocs\Clases\descuentos\descuentos_master_bat.bat`, que hace `cd` al proyecto y corre `python descuentos_master.py` **del proyecto Laravel** (la versión más reciente). La copia de `Clases\descuentos` es de junio y no se usa. Solo el `.bat` está fuera del proyecto.
- **Código corregido** (detalle en `12-problemas-conocidos-y-pendientes.md`, R1–R21): MP `mp_external_reference`; vistas `pago/*` (error 500); FK de `favoritos` → `users` (migración nueva, aplicada); límites de plan en alertas (`User::limitesPlan`); filtros centralizados en `Observacion` (`filtrarFuentesValidas`, `excluirMarcas`, `MARCAS_EXCLUIDAS`); rate limit en verificación de email; `User::alertas()` HasMany; mojibake de `Notificaciones.php`; escape Markdown de Telegram; plan Gratis sin duplicados; link a `/planes`; `DatabaseSeeder` → `PlanesSeeder`; validación de login; `/admin` con `verified`; `TRUSTED_PROXIES`; schedule de `alertas:procesar` (hourly); tests.
- **Scraper corregido:** regex de Ambrosia, `min/max_descuento` reales, `new_page` dentro del `try`. Registrado también en `descuentos_master_cambios.md`.
- **Tests:** `php artisan test` → 8 pasan (`ExampleTest`, `PagoVistasTest`, `FiltrosOfertasTest`).
- Docs actualizados: 02, 03, 04, 05, 06, 07, 08, 11, 12, scraper-01/02/03.
- **Pendiente:** confirmar con la corrida del 2026-10-30; crear la tarea de Windows para `schedule:run`; decidir si el `.bat` se mueve al proyecto y si el scraper se versiona en git.

## 2026-10-29 — Creación de `ai-context/` y documentación integral inicial

- Se creó la carpeta `ai-context/` con documentación completa del sistema (01–13 + `scraper-01…03`), `README.md` (índice), `AGENTS.md` y `CLAUDE.md` en la raíz con las reglas para que las IAs lean y mantengan la documentación.
- Se verificó el esquema real de MySQL (`SHOW CREATE TABLE`) y se contrastó el código con la documentación previa (`ARQUITECTURA.md`, etc.).
- `.gitignore`: agregadas excepciones `!AGENTS.md`, `!CLAUDE.md`, `!ai-context/**/*.md` (el patrón `*.md` los ignoraba). Los `*.py` (incluido el scraper) siguen ignorados por git.
- `README.md` de la raíz (era el boilerplate de Laravel) reemplazado por un README del proyecto.
- Hallazgos registrados en `12-problemas-conocidos-y-pendientes.md` (webhook MP que no encuentra la suscripción, FK de `favoritos` a la tabla legacy `usuarios`, planes sin aplicar, alertas sin schedule, mojibake en `Notificaciones.php`, etc.). **No se corrigió ningún bug de código en esta tarea** (solo documentación).
- Documentación del scraper (`scraper-01…03`) generada por análisis dedicado de `descuentos_master.py`; hallazgos incorporados a `12-…` (#22–#27: Ambrosia rota desde la corrida 149, min/max_descuento erróneos, `new_page` fuera del `try`, etc.). `nbp_inventory.json` se documenta como ajeno al scraper (datos de terceros).
- Estado del sistema documentado: Laravel 12 / Livewire 4; verificación de email por código de 6 dígitos (cambió respecto de `ARQUITECTURA.md`); alertas múltiples por usuario con `solo_mejoras`, `tamano_ml`, `marca_filtro`; 131 corridas en DB (2026-06-26 → 2026-10-29).
