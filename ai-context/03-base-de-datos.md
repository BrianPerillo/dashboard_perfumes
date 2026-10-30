# 03 · Base de datos: tablas, relaciones y dueños

Base: MySQL/MariaDB `perfumes_descuentos` (utf8mb4). Esquema verificado contra la base local el **2026-10-29** (`SHOW CREATE TABLE`). Conviven **dos grupos de tablas con distinto dueño**:

| Grupo | Dueño / quién crea el esquema | Tablas |
|---|---|---|
| **A. Aplicación Laravel** | Migraciones en `database/migrations/` | `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `alertas`, `planes`, `suscripciones`, `migrations` |
| **B. Datos de scraping** | El **scraper Python** (y/o SQL manual) — **sin migraciones de Laravel** | `tiendas`, `productos`, `corridas`, `corrida_sitios`, `observaciones`, `favoritos`, `usuarios` (legacy), vista `v_descuentos` |

> ⚠️ Si se cambia el esquema del grupo B hay que coordinar scraper (Python) **y** Laravel (modelos/queries). Nunca crear migraciones de Laravel que choquen con estas tablas sin revisar cómo las crea el scraper (ver [scraper-03-modelo-de-datos-y-normalizacion.md](scraper-03-modelo-de-datos-y-normalizacion.md)).

## Diagrama de relaciones

```
                          ┌──────────┐ 1      N ┌─────────────┐
                          │  planes  │──────────│suscripciones│ N      1 ┌───────┐
                          └──────────┘          └─────────────┘──────────│ users │
                                                                          └───┬───┘
                                                                          1   │   N
                                                                          ┌───┴────┐
                                                                          │alertas │   (valor = texto libre, NO FK a datos de scraping)
                                                                          └────────┘

 ┌─────────┐ 1    N ┌───────────┐ 1       N ┌───────────────┐ N      1 ┌──────────┐ 1      N ┌────────────────┐
 │ tiendas │────────│ productos │───────────│ observaciones │──────────│ corridas │──────────│ corrida_sitios │
 └─────────┘        └───────────┘           └───────────────┘          └──────────┘          └────────────────┘
                                             (UNIQUE corrida_id+producto_id)                   (corrida_sitios.tienda = clave "tienda_genero", texto)

 ┌──────────┐ 1   N ┌───────────┐
 │ users    │───────│ favoritos │      ← FK a `users` (desde 2026-10-29; antes apuntaba a la tabla legacy `usuarios`)
 └──────────┘       └───────────┘
```

---

## Grupo A — Tablas de la aplicación (migraciones)

### `users`
Migración base `0001_01_01_000000` + `2026_06_28_000001` + `2026_07_03_030214`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string UNIQUE | |
| `is_admin` | bool default 0 | agregada luego (`after email`) |
| `email_verified_at` | timestamp null | |
| `email_verification_code` | string(6) null | código de verificación vigente |
| `email_verification_code_expires_at` | timestamp null | vence a los 15 min |
| `password` | string | hash |
| `remember_token` | string null | |
| `created_at`/`updated_at` | | |

Otras tablas de la misma migración: `password_reset_tokens` (email PK, token, created_at), `sessions` (id, user_id idx, ip, user_agent, payload, last_activity). `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`: estándar de Laravel.

### `alertas`
Detalle completo de columnas y semántica en [07-alertas-y-notificaciones.md](07-alertas-y-notificaciones.md). Resumen: `user_id` FK→users (cascade, **ya no único**), `tipo` enum(marca,tienda,producto), `valor`, `canal` enum(email,telegram), `email_destino`, `telegram_chat_id`, `activo`, `ultima_notificacion_at`, `prueba_enviada`, `solo_mejoras`, `tamano_ml`, `marca_filtro`. Hoy: 1 registro.

### `planes` y `suscripciones`
Detalle en [08-planes-y-pagos.md](08-planes-y-pagos.md). `suscripciones.user_id` FK→users (cascade), `suscripciones.plan_id` FK→planes. `planes` tiene 3 filas (gratis, pro, premium), `suscripciones` está vacía.

### Historial de migraciones propias (orden)

| Migración | Qué hace |
|---|---|
| `0001_01_01_000000_create_users_table` | users, password_reset_tokens, sessions |
| `0001_01_01_000001_create_cache_table` | cache, cache_locks |
| `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs |
| `2026_06_28_000000_create_alertas_table` | alertas (con `unique(user_id)`) |
| `2026_06_28_000001_add_is_admin_to_users_table` | `users.is_admin` |
| `2026_06_30_000000_create_planes_table` | planes |
| `2026_06_30_000001_create_suscripciones_table` | suscripciones |
| `2026_07_03_030214_add_email_verification_code_to_users_table` | código de verificación |
| `2026_07_03_035815_add_solo_mejoras_tamano_to_alertas_table` | `solo_mejoras`, `tamano_ml` |
| `2026_07_03_041603_add_marca_filtro_to_alertas_table` | `marca_filtro` |
| `2026_07_03_043540_add_prueba_enviada_to_alertas_table` | `prueba_enviada` |
| `2026_07_03_045921_remove_unique_user_id_from_alertas_table` | permite varias alertas por usuario |
| `2026_10_29_190000_repoint_favoritos_fk_to_users` | re-apunta la FK de `favoritos.usuario_id` de `usuarios` (legacy) a `users` |

(Estas 13 migraciones están aplicadas en la base local.)

---

## Grupo B — Datos de scraping (esquema real)

### `tiendas`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | int PK AI | |
| `nombre` | varchar(80) **UNIQUE** | nombre canónico en minúsculas |

Valores actuales (12): `ambrosia, beauty24, farmalife, fiorani, getthelook, juleriaque, normy, parfumerie, pigmento, rouge, ruizyroca, unishop`. Los IDs no son consecutivos (hay saltos: 1–8, 84, 96, 102, 114) por el uso de upsert del scraper. Modelo: `App\Models\Tienda` (`hasMany Producto`).

### `productos`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | int PK AI | |
| `tienda_id` | int FK→tiendas (ON DELETE CASCADE) | |
| `nombre` | varchar(255) | nombre mostrado |
| `nombre_norm` | varchar(255) | nombre normalizado (clave de identidad) |
| `marca` | varchar(120) null, índice | |
| `genero` | enum(`masculino`,`femenino`,`unisex`) default `masculino` | |
| `tamano_ml` | int null | |
| `presentacion` | varchar(150) null | texto original (ej. "100 ML", "100ML") |
| `url` | varchar(600) null | |
| `primera_vez` / `ultima_vez` | datetime null | primera/última corrida donde apareció |

Clave única: `(tienda_id, nombre_norm)` → un producto por tienda y nombre normalizado. ~15.4k filas. Modelo: `Producto` (`belongsTo Tienda`, `hasMany Observacion`).

### `corridas`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | int PK AI | |
| `fecha_hora` | datetime **UNIQUE** | momento de la corrida |
| `total_productos` | int default 0 | |
| `min_descuento`/`max_descuento` | decimal(5,2) null | |
| `creado_en` | timestamp default now | |

> `min_descuento`/`max_descuento`: hasta la corrida 156 guardaban los argumentos CLI (0 y 90); desde 2026-10-29 el scraper guarda los valores reales de las rebajas de cada corrida.

131 corridas entre 2026-06-26 y 2026-10-29 (último id 156; hay saltos de id por corridas incompletas eliminadas). Modelo: `Corrida` (`hasMany CorridaSitio`, `hasMany Observacion`, `Corrida::ultima()`).

### `corrida_sitios`
Resultado del scraping **por sitio/categoría** en cada corrida (telemetría).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | int PK | |
| `corrida_id` | int FK→corridas (cascade) | |
| `tienda` | varchar(80) | **clave `<tienda>_<genero>`**, ej. `normy_femenino`, `rouge_unisex` (no es FK a `tiendas`) |
| `plataforma` | varchar(10) null | observados: `vtex`, `html`, `tiendanube`, `unishop` |
| `total_encontrados` | int | productos encontrados |
| `con_descuento` | int | de esos, cuántos con descuento |
| `estado` | varchar(10) | observados: `ok`, `vacio` |
| `mensaje` | varchar(500) null | error / detalle |
| `segundos` | decimal(7,2) null | duración |

Única `(corrida_id, tienda)`. Modelo: `CorridaSitio`. La app Laravel **no muestra** esta tabla todavía (útil para un futuro panel de salud del scraper; ej. `ambrosia_*` devolvió `vacio` entre las corridas 149 y 156 por un cambio de HTML (corregido el 2026-10-29, ver [12](12-problemas-conocidos-y-pendientes.md) #22).

### `observaciones`
Un precio observado de un producto en una corrida. Tabla grande (~435k filas).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `corrida_id` | int FK→corridas (cascade) | |
| `producto_id` | int FK→productos (cascade) | |
| `precio_lista` | decimal(12,2) | precio sin descuento |
| `precio_actual` | decimal(12,2) | precio vigente |
| `descuento_pct` | decimal(5,2), índice | |
| `ahorro` | decimal(12,2) **columna generada** `precio_lista - precio_actual` (STORED) | no se escribe, solo se lee |
| `fuente` | varchar(20) null | cómo se detectó. Valores actuales: `api`, `tachado`, `tiendanube` (históricamente `dos_precios`, `badge_*` según el código de la app) |
| `tipo_descuento` | enum(`rebaja`,`transferencia`,`ninguno`) default `rebaja` | |
| `precio_transferencia` | decimal(12,2) null | precio pagando por transferencia/efectivo |

Única `(corrida_id, producto_id)`; índices en `descuento_pct` y `producto_id`. Distribución actual (todas las corridas): `rebaja/api` 253.890; `transferencia/tiendanube` 109.602; `rebaja/tachado` 71.248; `rebaja/tiendanube` 41. Modelo `Observacion` con scopes `rebajas()` (`tipo_descuento='rebaja'` y `fuente NOT IN ('dos_precios','badge_%')`) y `deCorrida($id)`.

> En la última corrida: tiendas con fuente `api` (VTEX/Unishop): beauty24, farmalife, fiorani, getthelook, pigmento, rouge, unishop; con `tachado` (HTML): juleriaque, normy, parfumerie, ruizyroca.

### Vista `v_descuentos`
`observaciones ⨝ corridas ⨝ productos ⨝ tiendas` → columnas `corrida_id, fecha, tienda, producto_id, marca, producto, tamano_ml, precio_lista, precio_actual, descuento_pct, ahorro, fuente, tipo_descuento, precio_transferencia, url`. La usa el dashboard Python/Streamlit antiguo; el código Laravel **no** la usa (arma los joins a mano).

### `favoritos`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | int PK | |
| `usuario_id` | bigint unsigned FK→**`users`**(id) cascade (`fk_fav_user`) | re-apuntada el 2026-10-29 (antes: int FK→`usuarios`) |
| `tipo` | varchar(30) default `tabla` | |
| `nombre` | varchar(100) | |
| `config` | text | JSON de filtros/orden de la tabla |
| `creado`/`actualizado` | datetime | `Favorito` los setea a mano (`public $timestamps=false`) |

Única `(usuario_id, tipo, nombre)`. Modelo `Favorito` (métodos estáticos `listar`, `guardar`, `borrar`).

### `usuarios` (legacy)
`id, username, password_hash, salt, creado` — tabla de usuarios del **dashboard Python/Streamlit anterior** (login propio con salt). No la usa el login de Laravel (que usa `users`).

> ✅ **Corregido 2026-10-29** (migración `2026_10_29_190000_repoint_favoritos_fk_to_users`): la FK de `favoritos.usuario_id` apuntaba a la tabla legacy `usuarios` pero Laravel guarda `users.id`, por lo que el usuario con id 2 no podía guardar favoritos. Ahora apunta a `users(id)` (`ON DELETE CASCADE`). La fila existente (usuario 1, 'Favs1') se conservó. El dashboard Streamlit legado ya no podría guardar favoritos para usuarios que no existan en `users`.

---

## Reglas de consulta de uso frecuente

* **Última corrida**: `Corrida::orderByDesc('fecha_hora')->first()` o `MAX(id)` (las `fecha_hora` son únicas).
* **Descuentos reales**: `tipo_descuento='rebaja'` (+ excluir `fuente='dos_precios'`). `ahorro` ya viene calculado.
* **Identidad de producto entre corridas**: `producto_id` (único por `tienda_id + nombre_norm`). En el dashboard, para "Cambios/Seguir" se usa `lower(tienda)||lower(nombre)`.
* **Comparar entre tiendas** se hace por `LOWER(nombre)` exacto (no hay un identificador de producto "universal" entre tiendas).
* Las queries sobre `observaciones` sin filtrar por `corrida_id` escanean ~435k filas: usar siempre `corrida_id` o `producto_id` en el WHERE cuando se pueda.
* Consultar el esquema real con `php artisan tinker --execute='...DB::select("SHOW CREATE TABLE x")...'` (no hace falta credencial manual).

## Multi-rubro: cambios de esquema (migración escrita, **pendiente de aplicar**)
`tiendas.rubro`, `corridas.rubro` (unicidad `(rubro, fecha_hora)`), `alertas.rubro`, `productos` + `categoria/tipo/color/talles/referencia/imagen`, `productos.genero` + `'ninos'`. Todo lo existente queda como `perfumes`. Detalle y decisiones en [14-multirubro.md](14-multirubro.md).
> **Incidente 2026-10-29:** la tabla `tiendas` quedó dañada (tablespace) tras un doble arranque de MySQL; ver [12](12-problemas-conocidos-y-pendientes.md) (A0). Esquema y filas originales (12 tiendas con sus ids) están en este documento y en el respaldo físico `C:\xampp\mysql\data_backup_20261029_prerepair`.

> **`alertas` (2026-10-30):** además de `rubro`, columnas `talles` y `descuento_min` (migración `2026_10_30_010000_add_talles_descuento_min_to_alertas`). Ver [07](07-alertas-y-notificaciones.md).

> **`productos.disciplina`** (2026-10-30, migración `2026_10_30_020000_add_disciplina_to_productos`): VARCHAR(60) null, deporte/disciplina del producto (ropa/calzado). Ver [05](05-dashboard-secciones.md).

> **`productos.linea`** (2026-10-30, migración `2026_10_30_030000_add_linea_to_productos`): VARCHAR(80) null, línea de producto (Nike "Icono": Air Max, Mercurial…). Ver [05](05-dashboard-secciones.md).

> **`productos.talles_norm`** (2026-10-30, migración `2026_10_30_040000_add_talles_norm_to_productos`): VARCHAR(255) null, talles de calzado normalizados (H = hombre, M = mujer). El scraper la mantiene; la migración rellena los existentes. Ver [05](05-dashboard-secciones.md).

## Esquema vigente de `productos` y columna `rubro` (verificado 2026-10-30 contra MySQL)

| Columna | Tipo | Null |
|---|---|---|
| `id` | int(11) PK | no |
| `tienda_id` | int(11) FK → tiendas | no |
| `nombre` | varchar(255) | no |
| `nombre_norm` | varchar(255) | no (perfumes: nombre normalizado; ropa: `referencia` en minúsculas) |
| `marca` | varchar(120) | sí |
| `genero` | enum('masculino','femenino','unisex','ninos') | no |
| `tamano_ml` | int(11) | sí (solo perfumes) |
| `presentacion` | varchar(150) | sí (perfumes) |
| `categoria` | varchar(40) | sí (ropa: ropa / calzado / accesorios) |
| `tipo` | varchar(80) | sí (ropa: Remera, Campera, Calzado, Botines…) |
| `disciplina` | varchar(60) | sí (ropa: Fútbol, Running, Moda…) |
| `linea` | varchar(80) | sí (ropa: Air Max, Mercurial…) |
| `color` | varchar(80) | sí |
| `talles` | varchar(255) | sí (talles con stock, texto original: "XS, S, M") |
| `talles_norm` | varchar(255) | sí (calzado normalizado: H10 / M7) |
| `referencia` | varchar(80) | sí (ropa: modelo-color, ej. IH0745-010) |
| `imagen` | varchar(600) | sí (URL; hotlink al CDN de la tienda) |
| `url` | varchar(600) | sí |
| `primera_vez`, `ultima_vez` | datetime | sí |

Índices/claves: `UNIQUE (tienda_id, nombre_norm)`, índice en `marca`, FK `tienda_id → tiendas(id) ON DELETE CASCADE`. `rubro` VARCHAR(20) NOT NULL DEFAULT `'perfumes'` existe en `tiendas`, `corridas` y `alertas`. Las migraciones que agregaron estas columnas están listadas arriba; los scrapers (`descuentos_master.py` no toca las columnas de ropa; `descuentos_ropa.py` las llena todas) mantienen los datos.

## `observaciones.fuente` en ropa (2026-10-30)
`vtex_state` (Nike, estado de VTEX leído con navegador) y `vtex_api` (Levi's, Topper: API pública de catálogo). `Observacion::filtrarFuentesValidas` es una lista negra, así que ambas pasan. `corrida_sitios.plataforma` guarda `vtex_io` o `vtex_api` según el sitio.
