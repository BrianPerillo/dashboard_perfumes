# 13 · Changelog de documentación y del sistema

Registro cronológico (más reciente arriba). Cada cambio al sitio, al esquema de DB o al scraper agrega una entrada: **fecha ISO · qué cambió · archivos · docs actualizados**.

Formato:

```
## AAAA-MM-DD — Título corto
- Qué cambió y por qué (archivos tocados).
- Docs actualizados: 0X-….md
```

---

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
