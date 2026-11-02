# 12 · Problemas conocidos, deuda técnica y pendientes

Registro vivo. Al corregir algo: moverlo a "Resueltos" y anotarlo en [13-changelog-documentacion.md](13-changelog-documentacion.md). Al detectar algo nuevo: agregarlo a "Abiertos" con severidad y archivo.

Leyenda: 🔴 funcional/crítico · 🟠 importante · 🟡 menor · ⚪ nota.

## Abiertos

| # | Sev | Descripción | Dónde | Qué falta |
|---|---|---|---|---|
| A11 | 🟡 | Dashboard `/ropa/*` verificado con datos reales (corrida #158, 485 productos) pero con **una sola corrida**: Cambios/Evolución cobrarán sentido desde la 2.ª. Pestañas específicas de ropa (por tienda, comparar entre sitios) por hacer. | `Dashboard`, `config/rubros.php` | Esperar 2.ª corrida; diseñar pestañas |
| A12 | ⚪ | Nike: la cobertura del modo `descuento` es completa por construcción (listado ordenado por mayor descuento); el modo `oportunidades` se conserva solo como referencia. `imagen` se llenará desde la próxima corrida. | `descuentos_ropa.py` | Verificar `imagen` tras la 2.ª corrida |
| A14 | ⚪ | Tarea `descuentos_ropa` **verificada**: corrió sola el 2026-10-30 15:00 (resultado 0, 25 min). Sigue en modo *Interactive* (solo con sesión iniciada). Vigilar bloqueos en el admin. | Task Scheduler | — |
| A15 | 🟠 | **Lacoste (Salesforce Commerce Cloud) devuelve 403 'Access Denied' a Playwright**: barrera anti-bots deliberada; no se intenta evadir. Cada tarjeta trae un JSON de tracking con `price`, `full_price`, `discount`, género y categoría (trivial de leer si hubiera acceso legítimo). | [ropa-01](ropa-01-scraper-nike.md) | Decisión del usuario: feed/afiliados, navegador normal con usuario presente, o descartar |
| A1 | ⚪ | **Verificado el 2026-10-30** con la corrida de perfumes de las 13:00 (#163, 33/33 sitios ok, incluido Ambrosia) y la de ropa de las 15:00: `min/max_descuento` reales (5,88–60), Ambrosia funciona, sin abortos por `new_page`. | `descuentos_master.py` | — |
| A2 | 🟠 | **Webhook de MercadoPago sin probar de punta a punta** tras el fix de `mp_external_reference`. | `MercadoPagoService`, `PagoController` | Pago de prueba en sandbox (`TEST-`) con webhook público (ngrok) y `MP_WEBHOOK_SECRET` |
| A3 | 🟡 | La tarea `descuentos_master_perfumes` aún apunta al redirector de `Clases\descuentos`; repuntarla al `.bat` del proyecto requiere la contraseña de Windows. | Task Scheduler | Ejecutar `scripts\instalar_tareas_windows.ps1` en una consola normal (ver [11](11-entorno-config-despliegue.md)); luego borrar el redirector |
| A4 | 🟡 | La tarea `alertas_perfumes` corre en modo *Interactive* (solo con la sesión de Windows iniciada) porque `S4U` requiere administrador. | Task Scheduler | Si la PC debe enviar alertas sin sesión iniciada, ejecutar el instalador como administrador |
| A5 | ⚪ | `__pycache__/descuentos_master.cpython-313.pyc` sigue trackeado en git (aparece modificado). | git | `git rm --cached __pycache__/descuentos_master.cpython-313.pyc` |
| A6 | 🟡 | `spatie/laravel-analytics` instalado y `config/analytics.php` presentes pero sin uso. | `composer.json` | Quitar el paquete si no se va a usar |
| A7 | ⚪ | Listas de menú de pestañas triplicadas en el layout; bloque de filtros duplicado (sidebar/móvil); orden de marcas de joyas duplicado (PHP y Blade). | layout, `dashboard.blade.php`, `tab-joyas.blade.php` | Refactor a componentes/config compartidos |
| A8 | ⚪ | La DB guarda solo ofertas y transferencias, **no el catálogo completo** (no se puede medir "% del catálogo en oferta"). | scraper | Decisión de producto |
| A9 | ⚪ | `dashboard_descuentos.py` (Streamlit) y la tabla `usuarios` son legado sin uso automático. `nbp_inventory.json` no pertenece a este proyecto (datos de terceros de una API de impresoras). | raíz | Archivar/eliminar cuando se confirme |
| A10 | ⚪ | Cambios previos a 2026-08-31 del scraper no estaban en `descuentos_master_cambios.md` (ya agregado resumen). | doc | — |
| A16 | 🟡 | **Fotos de perfumes (2026-11-01):** `Juleriaque` y `Parfumerie` (sitios `html`) devuelven 0 productos en el último log del scraper (`logs_scraper.txt`) y en una prueba con Chromium sin cabeza no renderizan precios en 10 s: sin datos nuevos no tendrán foto. Los productos viejos de todas las tiendas siguen sin foto hasta la próxima corrida diaria o hasta `python descuentos_master.py --rellenar-imagenes` (VTEX/Unishop/Tiendanube; ya se corrió para Ambrosia, Fiorani, GetTheLook y Unishop). | `descuentos_master.py` (`scrap_html`), doc scraper-02 | Revisar por qué Juleriaque/Parfumerie dan 0 (¿bloqueo o cambio del sitio?); correr `--rellenar-imagenes` completo si se quiere ver fotos ya. |

## Resueltos el 2026-10-29

| # | Antes | Solución |
|---|---|---|
| R28 🟠 | (2026-10-30) Corte prematuro de la paginación de Nike: una página con productos ilegibles cortaba el listado (hombre/ropa 160 de ~306) | El corte usa los productos que devolvió el sitio; `--fecha` para completar una corrida; totales recalculados desde `observaciones` |
| R25 🔴 | MySQL no arrancaba desde XAMPP y quedó dañada la tabla `tiendas` (doble arranque) | Se apagó el `mysqld` huérfano; respaldo físico `C:\xampp\mysql\data_backup_20261029_prerepair`; `tiendas` recreada con sus 12 filas e ids originales (0 huérfanos, `mysqlcheck` OK); `mysql.gtid_slave_pos` recreada |
| R26 🔴 | `/planes` daba 500: el seeder guardaba `features` doblemente codificado en JSON | `PlanesSeeder` pasa el array (lo codifica el cast) y se re-sembró |
| R27 🟠 | Migración multi-rubro sin aplicar / alertas sin rubro | Aplicada; alertas por rubro implementadas (formulario + `ProcesarAlertas`) |
| R22 🟠 | Las alertas no corrían solas (sin tarea del SO) | Tarea de Windows `alertas_perfumes` cada 3 h → `scripts\alertas_procesar.bat`; se quitó el `Schedule` de `routes/console.php` |
| R23 🟡 | El `.bat` del scraper vivía fuera del proyecto, forzaba `mysqld` y no pasaba argumentos | `scripts/descuentos_master.bat` (rutas relativas, MySQL solo si hace falta, `%*`); el `.bat` viejo quedó como redirector |
| R24 🟡 | El scraper no estaba versionado | `.gitignore`: `!descuentos_master.py`, `!descuentos_master_cambios.md` |
| R1 🔴 | El webhook de MP no podía acreditar pagos: `mp_external_reference` nunca se guardaba | `MercadoPagoService::crearPreferencia` guarda `mp_external_reference = suscripcion.id` |
| R2 🔴 | **Las páginas `/pago/exito|pendiente|error` fallaban con 500** (`Undefined variable $slot`): usaban `@extends` sobre un layout que es componente Blade | Vistas migradas a `<x-layouts.app activeTab="planes">`; test `PagoVistasTest` |
| R3 🔴 | Favoritos: FK a la tabla legacy `usuarios`; el usuario con id 2 no podía guardar | Migración `2026_10_29_190000_repoint_favoritos_fk_to_users` (FK a `users`) |
| R4 🔴 | Scraper: Ambrosia devolvía 0 productos desde la corrida 149 | `_RE_TN_ITEM` tolera espacios/saltos (`<div\s+class=...`) |
| R5 🟠 | Los planes no limitaban nada | `User::limitesPlan()`; `Notificaciones::guardar()` valida `max_alertas`/Telegram; `ProcesarAlertas` lo respeta (admins exentos) |
| R6 🟠 | Alertas sin schedule | `Schedule::command('alertas:procesar')->hourly()` en `routes/console.php` (ver A3) |
| R7 🟠 | `'badge_%'` en `whereNotIn` era literal (no LIKE), repetido ~15 veces | `Observacion::filtrarFuentesValidas()` centralizado (NOT LIKE `badge_%`, acepta NULL); usado en `Dashboard`, scope `rebajas()` y `ProcesarAlertas` |
| R8 🟠 | Verificación de email por código sin límite de intentos | `RateLimiter`: 5 intentos/15 min, reenvíos 3/10 min, `hash_equals` |
| R9 🟠 | Scraper: `min/max_descuento` guardaban los argumentos CLI (0 y 90) | Se guardan los valores reales de las rebajas de la corrida |
| R10 🟠 | Scraper: `browser.new_page` fuera del `try` abortaba la corrida | Movido al `try`; `page.close()` protegido |
| R11 🟡 | `User::alertas()` era `HasOne` | `HasMany` (+ `suscripciones()`, `suscripcionActiva()`, `limitesPlan()`) |
| R12 🟡 | Mojibake en `Notificaciones.php` | Re-decodificado a UTF-8 correcto |
| R13 🟡 | `ProcesarAlertas` no excluía `dos_precios`/`badge_*` ni marcas excluidas (rimmel) | Usa `Observacion::filtrarFuentesValidas()` y `excluirMarcas()` |
| R14 🟡 | Telegram Markdown podía romperse con `_ * ` [` en nombres | `Alerta::escaparMarkdown()` |
| R15 🟡 | Plan Gratis duplicaba suscripciones | `Planes::iniciarPago` informa si ya hay una vigente |
| R16 🟡 | `/planes` sin link; `DatabaseSeeder` creaba "Test User" y no sembraba planes | Botón "💳 Planes" (navbar + móvil); seeder llama a `PlanesSeeder` |
| R17 🟡 | Login: `email` sin formato y `password min:6` inconsistente | `email` con formato; password sin mínimo |
| R18 🟡 | Link Admin exigía verificado pero la ruta no | Ruta `/admin`: `auth`, `verified`, `admin` |
| R19 ⚪ | `TRUSTED_PROXIES` fijo en `'*'` | `bootstrap/app.php` lee `TRUSTED_PROXIES` (default `*`) |
| R20 ⚪ | `ExampleTest` esperaba 200 en `/` (redirige) y `APP_URL` con subcarpeta daba 404 en tests | Test corregido, `phpunit.xml` fija `APP_URL`; +tests nuevos (8 pasan) |
| R21 ⚪ | `AlertaDescuento` "no encolado" listado como bug | **No es bug**: es sincrónico a propósito (no hay `queue:work`); si se encola hay que levantar un worker |
| R22 ⚪ | La corrida de ropa se guardaba entera al final (~3 h sin datos nuevos; un corte perdía todo salvo el checkpoint) | Guardado por tienda en corrida `en_curso`, oculta hasta cerrarla; ver ropa-02 (2026-11-01) |

## Ideas de mejora para el rubro ropa

**Hechas el 2026-10-30:** (1) agrupar variantes de color por modelo; (3) alertas por talle y % mínimo; (4) filtro de rango de precio (el orden por defecto ya es % de descuento); (10) estado del scraper en el panel admin. Detalle en [05](05-dashboard-secciones.md), [07](07-alertas-y-notificaciones.md) y [09](09-panel-admin-y-analytics.md).

**También hechas (2026-10-30, 2.ª tanda):** (2) historial de precio / Seguir producto (por `producto_id`, un renglón por color); (6) Cambios de ropa correcto por color (probado con corrida simulada; falta verlo con datos reales el 30/10); (11 parcial) talles en email y Telegram.

**Hechas (2026-10-30, 10.ª tanda):** talles de calzado normalizados (H/M). **Hechas (6.ª tanda):** filtro por **línea** de zapatilla (Icono); panel de filtros en acordeón. **Hechas (5.ª tanda):** filtro por prenda visible y amigable, filtro por deporte/disciplina (datos desde la corrida del 31/10), búsqueda sin contenedor.

**Pendientes:**
7. **Pestañas por tienda y comparar entre sitios** cuando exista el 2.º sitio de ropa (comparar por `referencia`/modelo, no por nombre).
8. **UX móvil**: cajón de filtros dedicado con contador y botón "Ver N resultados" (conviene ver primero cómo se siente en el celular).
9. **Imágenes**: verificar tras la corrida del 30/10 que `productos.imagen` se llene; hotlink a `vtexassets.com` (evaluar caché propio o prescindir si Nike lo objeta).
11. Alertas: avisar cuando "vuelve" un talle; imagen en el email.

## Ideas de producto pendientes (de `Mejoras.md`)

1. **Precios por presentación/tamaño**: el scraper guarda un único precio por producto aunque la card tenga variantes (ej. Pigmento 75/125/200 ml). Propuesta: un registro por presentación o modelo producto→variantes.
2. **"Destacados por la gente"**: usuarios logueados destacan artículos; con más de X votos aparecen en Joyas o en una sección propia.
3. **Fuente `dos_precios`**: genera falsos descuentos (ej. Juleriaque); hoy ya no hay filas con esa fuente y el filtro la excluye igual.

## Checklist de producción pendiente
Ver [11-entorno-config-despliegue.md](11-entorno-config-despliegue.md) y `Cambios_Realizar_Antes_Subir_Produccion.md`.

- **Calzado: equivalencias H↔M** (M = H + 1,5): hoy una mujer que busca `M7` no ve el calzado de hombre `H5.5`. Se podría ofrecer "incluir equivalentes" (producto de hombre en talle H = M − 1,5 y viceversa). Decisión de producto.

## Tiendas de ropa: estado (2026-10-30)
- **Lacoste y Adidas bloqueadas por Akamai (403)**: no se sortea el bloqueo; requiere decisión del usuario (autorización/feed/afiliados). Detalle en [ropa-02](ropa-02-tiendas-y-plataformas.md).
- Filtro de calzado "incluir equivalentes H↔M" y aviso "vuelve un talle": siguen pendientes (decisión de producto).
- UX móvil (punto 8 de arriba): **resuelto** (botón Filtros + Ver resultados, ver 10).
- Reparar la tarea programada de perfumes (`descuentos_master_perfumes`) requiere la contraseña de Windows del usuario (`scripts\instalar_tareas_windows.ps1`).
