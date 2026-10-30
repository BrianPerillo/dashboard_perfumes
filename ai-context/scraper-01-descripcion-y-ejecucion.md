# Scraper Python: descripcion y ejecucion

> Documento 1 de 3 sobre el scraper. Los otros: `scraper-02-sitios-y-extraccion.md` (una seccion por tienda) y `scraper-03-modelo-de-datos-y-normalizacion.md` (registro de producto, esquema MySQL).
> Estado verificado: 2026-10-29, contra el codigo de `descuentos_master.py` (1976 lineas) y contra la base local `perfumes_descuentos` (consulta de solo lectura).
> Las referencias `archivo:linea` apuntan a `C:\xampp\htdocs\perfumes-dashboard\descuentos_master.py` salvo que se indique otro archivo.

---

## 1. Que es y que rol cumple

`descuentos_master.py` es el **unico scraper activo** del sistema. Es un script Python independiente de Laravel que:

1. Recorre 12 perfumerias argentinas (ver doc 02) para las categorias de fragancias masculinas, femeninas y unisex.
2. Detecta los productos **con descuento real** (precio lista mayor que precio actual) y, en el caso de Ambrosia, productos **mas baratos por transferencia**.
3. Genera un Excel nuevo por corrida (carpeta `excel_perfumes/`), comparado contra el Excel anterior.
4. Guarda la corrida en MySQL (base `perfumes_descuentos`), que es lo que **lee la app Laravel** (el dashboard siempre muestra la corrida mas reciente; "Cambios" compara dos corridas).

Es el **productor de datos** del sistema; Laravel es solo consumidor (ver seccion 7).

```
                 (Task Scheduler Windows, diario 13:00)
                               |
                               v
   descuentos_master_bat.bat --> python descuentos_master.py  --> excel_perfumes/*.xlsx
                                         |
                                         +--> MySQL perfumes_descuentos (corridas, productos, observaciones, ...)
                                                          |
                                                          v
                                           Laravel / Livewire (solo lectura de estas tablas)
```

## 2. Como se ejecuta

### 2.1 Comando

```bash
cd C:\xampp\htdocs\perfumes-dashboard
python descuentos_master.py                         # corrida completa: todas las tiendas, Excel + MySQL
python descuentos_master.py --solo juleriaque normy # solo esas tiendas (por defecto NO guarda en MySQL)
python descuentos_master.py --no-db                 # solo Excel
python descuentos_master.py --headful               # muestra el navegador (debug de sitios HTML)
```

### 2.2 Argumentos CLI (`main()`, lineas 1748-1770)

| Argumento | Default | Efecto |
|---|---|---|
| `-o / --output` | `descuentos_perfumes.xlsx` | Nombre base del Excel. El script lo mueve a `excel_perfumes/` (carpeta junto a la ruta indicada) y le agrega `_D-M-YYYY_HH-MM-SS`. |
| `--solo T1 T2 ...` | (todas) | Filtra por `site["name"]` (nombre canonico, minusculas; ej. `juleriaque`). Incluye todos los generos de esa tienda. Si ninguna coincide, termina con codigo 1. |
| `--min-descuento` | `0.0` | Umbral minimo de descuento % para entrar al Excel / DB (efectivo: `max(valor, 0.01)`). |
| `--max-descuento` | `90.0` | Descarta descuentos mayores (falsos positivos). |
| `--max-items` | `800` | Tope de productos por entrada de SITES para VTEX / Unishop / Tiendanube. No aplica al scraping HTML. |
| `--no-playwright` | off | Saltea los sitios `platform: "html"`. |
| `--headful` | off | Chromium visible (por defecto headless). |
| `--no-db` | off | No guardar en MySQL. |
| `--force-db` | off | Guarda en MySQL aun con `--solo` o con corrida incompleta. |
| `--umbral-tiendas` | `0.70` | Fraccion minima de tiendas con datos para considerar completa la corrida (`UMBRAL_TIENDAS_OK`, linea 508). |
| `--sin-chequeo-red` | off | No esperar conectividad al inicio. |

### 2.3 Codigos de salida

| Codigo | Significado |
|---|---|
| 0 | OK |
| 1 | `--solo` no coincide con ninguna tienda |
| 2 | Sin red tras 6 intentos (no genera Excel ni corrida) |
| 3 | Corrida incompleta (menos del 70% de tiendas con datos). **El Excel igual se genera**, solo se omite el guardado en MySQL. |
| otro (traceback) | Excepcion no atrapada (ver seccion 6.3); no hay Excel ni DB |

### 2.4 Variables de entorno y constantes de configuracion

| Nombre | Donde | Default | Uso |
|---|---|---|---|
| `PERF_DB_HOST` | `DB_CONFIG` (l. 317) | `127.0.0.1` | Host MySQL |
| `PERF_DB_PORT` | idem | `3306` | Puerto |
| `PERF_DB_USER` | idem | `root` | Usuario |
| `PERF_DB_PASS` | idem | (vacio) | Password (valor NO documentado aqui) |
| `PERF_DB_NAME` | idem | `perfumes_descuentos` | Base |
| `SITES` | l. 76-265 | lista de 33 entradas | Config de tiendas/generos (ver doc 02) |
| `USER_AGENTS` | l. 267 | 2 UA (Chrome Win, Safari Mac) | Se elige uno al azar por sesion/pagina |
| `HOSTS_CONTROL` | l. 502 | google.com, pigmento, juleriaque | Chequeo de red |
| `UMBRAL_TIENDAS_OK` | l. 508 | `0.70` | Corrida completa |
| `_FUENTES_HTML_DEFAULT` | l. 1389 | `["tachado"]` | Fuentes aceptadas en sitios HTML |

**Nota:** el scraper NO usa las variables `DB_*` de Laravel ni lee el `.env` del proyecto. Funciona con los defaults XAMPP (root sin password) salvo que se definan las `PERF_DB_*` en el entorno del proceso. Laravel usa `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` (config/database.php) y, por las tablas halladas, ambas apuntan a la misma base `perfumes_descuentos` (inferido: las tablas Laravel `users`, `alertas`, etc. conviven en la misma base con las del scraper; no se leyo `.env`).

### 2.5 Dependencias (versiones instaladas verificadas)

| Paquete | Version | Uso |
|---|---|---|
| Python | 3.13.12 (hay `__pycache__/descuentos_master.cpython-313.pyc`) | |
| requests | 2.32.5 | VTEX, Unishop, Tiendanube |
| openpyxl | 3.1.5 | Excel (opcional: sin el, loguea error y no genera Excel) |
| playwright | 1.60.0 (+ `playwright install chromium`) | Sitios HTML (sync API) |
| PyMySQL | 1.1.3 | Guardado en MySQL (import perezoso: sin el, loguea error y omite DB) |

No hay `requirements.txt` ni virtualenv en el proyecto: se usa el Python global.

## 3. Como se programa

### 3.1 Windows Task Scheduler (verificado con `Get-ScheduledTask`)

| Campo | Valor |
|---|---|
| Nombre de tarea | `descuentos_master_perfumes` (carpeta raiz `\`) |
| Accion | `C:\xampp\htdocs\Clases\descuentos\descuentos_master_bat.bat`: desde 2026-10-29 es un **redirector** que llama a `C:\xampp\htdocs\perfumes-dashboard\scripts\descuentos_master.bat` (versionado en el proyecto). Repuntar la tarea directamente al `.bat` del proyecto requiere la contraseña de Windows: ver doc 11 |
| Disparador | Diario, inicio 2026-06-22 13:00, cada 1 dia |
| Ajustes | `StartWhenAvailable=True` (si la PC estaba apagada, corre al encender), `WakeToRun=True`, `MultipleInstances=IgnoreNew`, `ExecutionTimeLimit=1h`, `DisallowStartIfOnBatteries=True`, `RunOnlyIfNetworkAvailable=False` |
| Ultimo resultado | codigo 0; ultima ejecucion 2026-10-29 13:00:02; proxima 2026-10-30 13:00 |

**¿Qué copia del script se ejecuta? (verificado 2026-10-29)** La del **proyecto Laravel** (`C:\xampp\htdocs\perfumes-dashboard\descuentos_master.py`, la más reciente: 31/8/2026 + correcciones del 29/10). El `.bat` vive en `C:\xampp\htdocs\Clases\descuentos\` pero hace `cd /d C:\xampp\htdocs\perfumes-dashboard` y ejecuta `python descuentos_master.py` desde ahí; el log y los Excel también se escriben en el proyecto. La copia de `C:\xampp\htdocs\Clases\descuentos\descuentos_master.py` es de junio (idéntica byte a byte a `descuentos_master.2026-06-26.py`) y **no se ejecuta**; en `C:\xampp\htdocs\Clases\` no hay otra versión del scraper. Lo único externo al proyecto es el `.bat` lanzador.

**Lanzador actual** (`scripts/descuentos_master.bat`, en el proyecto): `cd /d "%~dp0.."` → arranca MySQL solo si `mysqld.exe` no está corriendo → `python descuentos_master.py %* >> logs_scraper.txt 2>&1` → `exit /b %errorlevel%`. Probado el 2026-10-29 vía el redirector con `--solo ambrosia --no-db` (HTTP) y `--solo parfumerie --no-db` (Playwright).

Contenido del `.bat` **original** (histórico, ya reemplazado):

```bat
start "" /B "C:\xampp\mysql\bin\mysqld.exe"        :: arranca MySQL si no esta activo
timeout /t 5 /nobreak >nul                          :: espera 5 s
cd /d C:\xampp\htdocs\perfumes-dashboard
python descuentos_master.py >> logs_scraper.txt 2>&1   :: append del log
```

Como la tarea puede arrancar junto con Windows (sin Wi-Fi/DNS todavia), el script espera red (seccion 6.1).

### 3.2 Cadencia observada

* Tabla `corridas`: 131 filas, de 2026-06-26 01:19 a 2026-10-29 13:00 (ids hasta 156; hay huecos en los ids, no verificado por que).
* Corrida programada: **todos los dias ~13:00:10**; duracion total ~25-40 min (la del 29-10 termino a las 13:26:08). Con `StartWhenAvailable` pueden salir corridas fuera de horario.
* Hay dias con 2 corridas (ej. 2026-10-26 10:30 y 13:00; 10-23 13:00 y 15:01). Las extra son **manuales o recuperaciones**; el motivo exacto de cada una no esta registrado (no verificado).
* La web Laravel dice "cada 12-24 horas" (`resources/views/livewire/notificaciones.blade.php:334`); la realidad actual es 1 vez al dia.
* Laravel **no ejecuta** el scraper: no hay `exec`/`Process`/`Schedule` que lo invoque (grep en `app/`, `routes/`, `config/`, `bootstrap/`). Las alertas (`alertas:procesar`) las lanza otra tarea de Windows (`alertas_perfumes`, cada 3 h) — ver [07](07-alertas-y-notificaciones.md).

## 4. Flujo de una corrida

```
main()
 |
 |-- [1] esperar_red()  (hasta 6 intentos x 20 s; si falla -> exit 2)         l.1773
 |-- [2] ahora = datetime.now()   <- misma marca para Excel y corrida DB       l.1779
 |-- [3] --solo sin --force-db  -> fuerza no guardar en DB                     l.1785
 |-- [4] filtrar SITES (--solo / omitir "disabled")                            l.1791-1801
 |
 |-- [5] SITIOS SIN NAVEGADOR (requests), en orden de SITES:                   l.1808-1847
 |        vtex -> scrap_vtex | unishop -> scrap_unishop | tiendanube -> scrap_tiendanube
 |        por cada entrada (tienda+genero): items, p.genero = genero,
 |        reporte_sitios.append({tienda: "nombre_genero", estado ok|vacio|error,...})
 |        sleep 0.8-1.6 s entre entradas
 |
 |-- [6] SITIOS HTML (un Chromium headless compartido, una page por entrada):  l.1850-1895
 |        scrap_html -> listado paginado + scroll -> JS_EXTRAER (DOM heuristico)
 |        -> _cards_a_items -> (opcional) _scrap_detalle_variantes
 |        sleep 1-2 s entre entradas
 |
 |-- [7] CONSOLIDACION                                                         l.1898-1910
 |        unicos[it.clave] = it     (dedupe tienda+nombre+precio; gana el ultimo)
 |        res      = rebajas (tipo != transferencia) con min <= dto <= max, orden dto desc
 |        transfer = items tipo "transferencia" (no van al Excel)
 |        res_db   = res + transfer
 |
 |-- [8] EXCEL: carga el .xlsx mas reciente de excel_perfumes/ (previos),      l.1913-1921
 |        escribir_excel(res, ruta_con_timestamp, previos)  (verde/amarillo)
 |
 |-- [9] ALERTAS por sitio (log WARNING "ALERTA tienda [vacio|error]")         l.1924-1929
 |-- [10] SALUD: _salud_corrida -> % de tiendas con datos; si < umbral         l.1935-1951
 |         y no --solo/--force-db -> no_db = True ("CORRIDA INCOMPLETA")
 |-- [11] guardar_en_mysql(res_db, ahora, args, reporte_sitios)                l.1954
 |-- [12] logs finales ("LISTO: N con descuento real -> ruta", top 8)          l.1957-1971
 |-- return 3 si corrida_incompleta, si no 0                                   l.1973
```

Detalles relevantes:

* **El Excel se escribe siempre (paso 8) antes de decidir si se guarda en DB**, incluso en corridas incompletas.
* `res` excluye `transferencia`; `transfer` va solo a la base (con `tipo_descuento='transferencia'`).
* Los items del scraping **sin descuento** (VTEX/Unishop los devuelven igual, con `descuento_pct=0`) se **descartan** en el paso 7 (`>= max(min_descuento, 0.01)`): la base solo contiene ofertas (y transferencias). El log "N productos (M con descuento)" por sitio cuenta antes de ese filtro.
* La corrida DB y el Excel comparten `ahora`, por lo que `corridas.fecha_hora` = marca del nombre del Excel (inicio de la corrida, no el fin).

## 5. Concurrencia e hilos

**Ninguna.** Todo es secuencial en un solo hilo: primero todas las entradas por `requests` (una `requests.Session` nueva por entrada, `nueva_session()` l.562), despues las HTML con Playwright **sync API** (un solo `chromium.launch`, una `page` por entrada que se cierra al terminar). Las demoras `time.sleep(random.uniform(...))` entre paginas (0.4-0.9 s), entradas (0.8-1.6 s; 1-2 s en HTML) son el unico control de ritmo. La task del Scheduler tiene `MultipleInstances=IgnoreNew`, asi que no se solapan corridas.

## 6. Manejo de errores, reintentos y corridas incompletas

### 6.1 Chequeo de red inicial
`esperar_red()` (l.527): hasta 6 intentos separados por 20 s; `hay_red()` (l.511) resuelve DNS y abre socket 443 a `HOSTS_CONTROL`. Si nunca hay red: loguea error y `return 2` sin generar nada. Se agrego el 2026-08-31 tras un incidente (ver 6.4).

### 6.2 Errores por sitio
* `_get()` (l.572): 1 solo intento, timeout 25 s, acepta 200/206; ante error devuelve `None` (log nivel DEBUG, que no se ve). **No hay reintentos HTTP ni manejo especifico de 403/429.**
* Cada entrada de SITES esta dentro de `try/except Exception`: ante excepcion se registra `estado="error"` con `mensaje=str(e)[:500]` y se sigue.
* 0 productos -> `estado="vacio"`, mensaje "0 productos: posible error de scraping".
* En HTML, un `page.goto` fallido solo hace `continue` con la siguiente URL (WARNING `no se pudo abrir ...`); en la practica produce `vacio`.
* Estados posibles en `corrida_sitios.estado`: `ok`, `vacio`, `error`. En la base solo existen `ok` (4076) y `vacio` (247); no hay `error` registrado.

### 6.3 Punto fragil no cubierto
~~`browser.new_page(...)` fuera del `try`~~ **CORREGIDO 2026-10-29**: `new_page` está ahora dentro del `try` (~l.1870 de `descuentos_master.py`); si Chromium se cae en una entrada, esa entrada se registra con `estado='error'` y la corrida continúa (antes abortaba con traceback sin Excel ni DB; ocurrió 2 veces en `logs_scraper.txt`).

### 6.4 Corridas incompletas (desde 2026-08-31)
* `_salud_corrida()` (l.545) agrupa por tienda (quita el sufijo `_genero`) y cuenta cuantas tiendas tuvieron `total > 0`. Si `ratio < --umbral-tiendas` (0.70) y no hay `--solo`: loguea `CORRIDA INCOMPLETA`, **no guarda en MySQL**, devuelve exit 3.
* Motivo: una corrida lanzada con la red caida (DNS `ERR_NAME_NOT_RESOLVED`, 2026-08-31 ~08:13) quedo guardada con 971 productos y dejaba el dashboard mostrando 5 de 12 tiendas. Esa corrida sigue en la base (`corridas.id=102`, `fecha_hora=2026-08-31 08:13:56`, `total_productos=971`) y su Excel esta en `excel_perfumes/corridas_incompletas/descuentos_perfumes_31-8-2026_08-13-56.xlsx`. Mover ese Excel a la subcarpeta fue **manual** (ningun codigo lo hace; inferido) y se hizo para que no sea tomado como "anterior" en la comparacion (`_excel_mas_reciente` usa `glob` no recursivo).
* En `logs_scraper.txt` hay 53 resumenes de "Salud de la corrida": 44 con 12/12, 8 con 11/12 y 1 con 10/12; ningun `CORRIDA INCOMPLETA` registrado.
* Estado (2026-10-29): Ambrosia estuvo vacía desde la corrida id 149 (2026-10-22) por un cambio de HTML; **regex corregida el 2026-10-29** (se verá reflejado desde la próxima corrida, ~13:00 del 2026-10-30). Ver doc 02.
* Normy tuvo `vacio` en 79 corridas hasta el 2026-08-31 (paginacion por scroll no funcionaba); se arreglo con `pag_path` (doc 02).

### 6.5 Excel bloqueado
`_guardar_seguro()` (l.1592): si `wb.save` da `PermissionError` (Excel abierto), guarda con sufijo `_YYYYMMDD_HHMMSS`. No hay archivos con ese patron en `excel_perfumes/` hoy.

### 6.6 Transaccionalidad en DB
`guardar_en_mysql` usa una sola transaccion (`autocommit=False`): ante error hace `rollback`, loguea y devuelve `False`; el script igual termina con codigo 0 (el fallo de DB **no** cambia el exit code; solo se ve en el log: `Error guardando en MySQL`, `No pude conectar a MySQL`).

## 7. Como se conecta Laravel con el scraper

* **Unico punto de contacto: la base MySQL.** El scraper escribe directo con PyMySQL en `corridas`, `tiendas`, `productos`, `observaciones`, `corrida_sitios`. Laravel lee esas tablas con modelos Eloquent (`app/Models/Corrida.php`, `Tienda.php`, `Producto.php`, `Observacion.php`, `CorridaSitio.php`) y consultas `DB::table(...)` (ej. `app/Console/Commands/ProcesarAlertas.php`, `app/Livewire/Dashboard.php`).
* Laravel **no** ejecuta Python, no programa el scraper, no lee los Excel ni `logs_scraper.txt`.
* El scraper **no** crea el esquema: no hay `CREATE TABLE` en el script ni migraciones Laravel para estas tablas (las migraciones de `database/migrations/` solo cubren `users`, `cache`, `jobs`, `alertas`, `planes`, `suscripciones`). El esquema se transcribio de la base viva en el doc 03.
* Sincronia: Laravel considera "ultima corrida" a `Corrida::orderByDesc('fecha_hora')->first()` (`app/Models/Corrida.php`, `Livewire/Dashboard.php:123`). Por eso el scraper evita guardar corridas parciales/rotas (6.4).

## 8. Logging

* `logging.basicConfig(level=INFO, format="%(asctime)s  %(levelname)-7s  %(message)s", datefmt="%H:%M:%S")` (l.274). Logger `master`. Sale por **stderr**; el `.bat` redirige `>> logs_scraper.txt 2>&1` (append, sin rotacion).
* **El formato no incluye fecha**, solo `HH:MM:SS`. Para ubicar una corrida hay que mirar la linea `LISTO: ... -> ...descuentos_perfumes_D-M-YYYY_HH-MM-SS.xlsx` (trae la fecha en el nombre) o la linea `MySQL: corrida #N guardada`.
* `logs_scraper.txt` en la raiz: ~1.3 MB, 20.548 lineas, **123 corridas** (primera: 2026-06-26 ~23:22; las anteriores quedaron en un log viejo en `C:\xampp\htdocs\Clases\descuentos\`). Los acentos aparecen como `?`/mojibake (consola cp1252).
* Lineas tipicas de una corrida:

```
23:22:42  INFO     == pigmento (vtex) ==
23:22:54  INFO       352 productos (83 con descuento)
23:29:07  INFO     == ambrosia (tiendanube) ==
23:29:18  INFO       290 productos (0 con descuento, 290 por transferencia)
23:29:49  INFO     == juleriaque (html) ==
23:29:59  INFO       pagina 1: 96 tarjetas, 93 nuevos (total 93)
13:21:05  INFO       expandiendo variantes en 54 productos con descuento...
13:25:59  INFO     Comparando contra corrida anterior: descuentos_perfumes_28-10-2026_13-00-10.xlsx
13:26:05  WARNING  ALERTA ambrosia_masculino [vacio]: 0 productos: posible error de scraping (0 encontrados)
13:26:05  INFO     Salud de la corrida: 11/12 perfumerias con datos (92%).
13:26:08  INFO     MySQL: corrida #156 guardada (2643 observaciones, 2643 productos).
13:26:08  INFO     Mejor negocio que antes (verde): 10 | productos nuevos (amarillo): 80
13:26:08  INFO     LISTO: 2656 con descuento real -> C:\...\excel_perfumes\descuentos_perfumes_29-10-2026_13-00-10.xlsx
```

* Nota: "2656 con descuento real" (Excel) vs "2643 observaciones" (DB): la DB deduplica por `(tienda, nombre_norm)` (doc 03).

## 9. Archivos generados y ubicacion

| Archivo / carpeta | Quien lo genera | Descripcion |
|---|---|---|
| `excel_perfumes/descuentos_perfumes_<D>-<M>-<YYYY>_<HH>-<MM>-<SS>.xlsx` | scraper | 1 por corrida. D y M **sin cero a la izquierda** (ej. `1-9-2026_13-00-10`), hora con 2 digitos. ~240 KB c/u. Hoy: 132 archivos en la carpeta principal + 1 en la subcarpeta (~32 MB en total). 13 estan versionados en git (los primeros). |
| `excel_perfumes/corridas_incompletas/` | **manual** | Contiene el Excel de la corrida rota del 2026-08-31 08:13:56. Se aparta para que no sirva de base de comparacion. |
| `logs_scraper.txt` | `.bat` (redireccion) | Log acumulativo (ver 8). |
| `descuentos_master.py.bak-20260831` | manual | Copia previa a los cambios del 2026-08-31 (ver 11). |
| `descuentos_master.2026-06-26.py` | manual | Backup del 2026-06-26 (ver 11). |
| `descuentos_master_cambios.md` | manual | Changelog **incompleto** (solo 2 entradas, 2026-06-26). |
| `__pycache__/descuentos_master.cpython-313.pyc` | Python | Se genera cuando otro script hace `import descuentos_master` (ej. `cargar_excels_db.py` en `Clases/descuentos`); sigue **trackeado en git por error** (modificado en el working tree); conviene `git rm --cached` (`__pycache__/` y `*.pyc` ya están en `.gitignore`). |

`.gitignore`: desde 2026-10-29 **`descuentos_master.py` y `descuentos_master_cambios.md` SÍ se versionan** (excepciones `!descuentos_master.py`, `!descuentos_master_cambios.md`). Siguen ignorados el resto de `*.py` (`dashboard_descuentos.py`, backups) y `*.md` (salvo `README.md`, `AGENTS.md`, `CLAUDE.md`, `ai-context/**/*.md`). Los lanzadores están en `scripts/` (no ignorados).

Hoja y columnas del Excel (verificado abriendo `descuentos_perfumes_29-10-2026_13-00-10.xlsx`):

* **Hoja `Descuentos`** (dimension A1:I2658): fila 1 = titulo combinado ("Perfumes con descuento - N productos - de mayor a menor"), fila 2 = encabezados, datos desde la fila 3, ordenados por descuento desc.

| Col | Encabezado | Contenido |
|---|---|---|
| A | Descuento | **Formula** `=(E-F)/E` (sin valor cacheado: quien lea con `data_only=True` obtiene `None`; hay que recalcular) |
| B | Tienda | nombre canonico (`beauty24`, ...) |
| C | Marca | marca tal como la entrega el sitio |
| D | Producto | nombre (con tamano ml cuando se pudo determinar) |
| E | Precio lista | valor |
| F | Precio actual | valor |
| G | Ahorro | **Formula** `=E-F` |
| H | Fuente | `api` / `tachado` / `tiendanube` |
| I | Link | texto "ver producto" con hipervinculo a la URL |

  Formato: filas VERDE (B..I) = ya estaba en el Excel anterior y ahora es mejor negocio (descuento > anterior + 0.1 pp, o precio menor al minimo anterior); AMARILLO = producto nuevo (por nombre normalizado, sin importar la tienda); resto gris/blanco alternado; escala de color en col A; filtro y panel congelado en fila 3.
* **Hoja `Resumen por tienda`**: por tienda `COUNTIF`, `AVERAGEIF`, `SUMPRODUCT(MAX(...))`, `SUMIF` sobre `Descuentos`, mas fila TOTAL. Todo son formulas.
* El Excel **no tiene columna de genero, ml, presentacion ni tipo_descuento**, y **no incluye** productos por transferencia (Ambrosia nunca aparece en el Excel; el de 2026-10-29 tiene 11 tiendas en el resumen).

## 10. Dashboard Streamlit anterior: `dashboard_descuentos.py`

* **Que es:** dashboard Streamlit (2045 lineas, `streamlit run dashboard_descuentos.py`) de "Evolucion de descuentos": serie temporal, seguimiento de un perfume, nuevos/desaparecidos, subas/bajas, marcas, comparador de precios entre tiendas, "joyas", conclusiones por reglas y opcionalmente por LLM. Lee los Excel de `excel_perfumes/` (o subidos a mano) **o** la vista MySQL `v_descuentos` (`cargar_desde_mysql`, l.422), con la misma config `PERF_DB_*`.
* Tiene login propio (tablas `usuarios` y `favoritos`, hash PBKDF2) pensado para uso local.
* **Es el predecesor del dashboard Laravel**: la seccion "joyas" (catalogo de marcas/lineas, umbral 30%), los favoritos y los filtros por ml/genero se portaron a `app/Livewire/Dashboard.php` (por ej. `CATALOGO` en l.85, `joyasUmbral = 30`).
* **Esta en uso?** No hay evidencia de uso automatizado: ninguna tarea programada ni codigo lo invoca; ultima modificacion 2026-06-23 (antes del port a Laravel). Es una copia identica a la de `C:\xampp\htdocs\Clases\descuentos\dashboard_descuentos.py`. Se puede considerar **legado**; no se verifica si el usuario lo abre manualmente. Si se usa, depende de `streamlit` (1.56.0 instalado), pandas, plotly.
* Efecto lateral importante: **la tabla `favoritos` de la base tiene FK a `usuarios` (tabla del Streamlit), no a `users` de Laravel** (ver doc 03, discrepancias).

## 11. Versiones del scraper

| Archivo | Lineas | Que es | En uso |
|---|---|---|---|
| `descuentos_master.py` | 1976 | Version activa (modificada 2026-08-31 13:39) | **Si** (la corre el Scheduler) |
| `descuentos_master.py.bak-20260831` | 1839 | Copia previa a los cambios del 31-8 (13:36) | No |
| `descuentos_master.2026-06-26.py` | 1486 | Backup previo a "genero" y filtro de fuentes (identico byte a byte a `Clases/descuentos/descuentos_master.py`) | No |

Diferencias verificadas con `diff`:

* **`.bak-20260831` -> actual** (cambios del 2026-08-31): (a) Normy pasa de scroll (`scrolls: 12`) a paginacion por path WooCommerce (`pag_path: "page/{n}/"`, 20 paginas, `scrolls: 4`, `variantes_detalle: False`); (b) en `JS_EXTRAER` se agrega la lectura directa del patron `<del>/<ins>` (WooCommerce); (c) `_urls_de_sitio` soporta `pag_path` y `scrap_html` corta tambien con `pag_path`; (d) chequeo de red (`hay_red`, `esperar_red`, `HOSTS_CONTROL`, `--sin-chequeo-red`); (e) control de calidad de corrida (`_salud_corrida`, `--umbral-tiendas`, exit code 3). Estos cambios **no estan en `descuentos_master_cambios.md`**.
* **`2026-06-26` -> actual:** la version vieja no tiene `genero` (solo masculino), ni `presentacion`, ni `variantes_detalle`/`_scrap_detalle_variantes`, ni `_nombre_con_tamano`; ademas tenia Fiorani con `buscar_todo` y Normy con URL de `/promociones/`. El changelog si documenta el cambio de genero y el filtro de fuentes (`_FUENTES_HTML_DEFAULT = ["tachado"]`).
* Otros scripts viejos viven en `C:\xampp\htdocs\Clases\descuentos\` (no analizados): `descuentos_master_2.py`, `_3.py`, `descuentos_marcas.py`, `perfumes_sin_descuento.py`, `scraper_fragrantica.py`, y `cargar_excels_db.py` (backfill de Excel historicos a MySQL reusando `guardar_en_mysql`; por eso `import descuentos_master` genera el `.pyc`).

## 12. `nbp_inventory.json`: NO es parte del scraper de perfumes

* 1.7 MB, modificado 2026-07-06 11:02, **sin versionar** (`??` en git), sin ninguna referencia en el codigo Python/PHP del proyecto.
* Estructura: `{"Nubeprint-apiv1-inventory": [ ... 2462 objetos ... ]}`. Cada objeto es un equipo de impresion (campos: `Project`, `Company`, `SerialNumber`, `Variant`, `Manufacturer`, `Status`, `Class`, `Cloud`, `Country`, `City`, `Address`, `Contact`, `Email`, `IP`, `Hostname`, `InstallDate`, `LastDate`, `TechAlerts`, `ConsAlerts`, ...). "NBP" = respuesta de la API **Nubeprint** (inventario de impresoras).
* Es un **volcado de otro proyecto** ajeno a perfumes; probablemente un archivo suelto. Contiene datos de contacto de terceros (emails, IPs): **no usarlo ni publicarlo**. Ningun proceso del scraper o de Laravel lo genera ni lo consume (conclusion basada en grep; no se pudo identificar con certeza quien lo creo).
* Otros archivos de la raiz sin relacion con el scraper: `ngrok-start.bat` (levanta `php artisan serve --port=8000` y un tunel ngrok para compartir el dashboard) y `.claude/settings.local.json` (permisos de Claude Code); no intervienen en el scraping.
