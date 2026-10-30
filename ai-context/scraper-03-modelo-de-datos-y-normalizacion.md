# Scraper Python: modelo de datos, normalizacion y salida a base de datos

> Documento 3 de 3. Ver `scraper-01-descripcion-y-ejecucion.md` y `scraper-02-sitios-y-extraccion.md`.
> Referencias `:linea` = `C:\xampp\htdocs\perfumes-dashboard\descuentos_master.py`. Esquema transcripto de la base viva `perfumes_descuentos` (consulta `SHOW CREATE TABLE` de solo lectura, 2026-10-29), porque **ni el scraper ni las migraciones de Laravel crean estas tablas** (no hay `CREATE TABLE` en el repo para ellas).

---

## 1. Registro de producto que maneja el scraper (`Item`, l.283-300)

Dataclass que producen todos los adaptadores:

| Campo | Tipo | Default | Significado |
|---|---|---|---|
| `tienda` | str | - | Nombre canonico (`site["name"]`): `pigmento`, `beauty24`, `rouge`, `fiorani`, `getthelook`, `farmalife`, `unishop`, `ambrosia`, `juleriaque`, `parfumerie`, `ruizyroca`, `normy` |
| `marca` | str | - | Marca tal cual la entrega el sitio (puede ser `""`) |
| `nombre` | str | - | Nombre del producto/SKU (idealmente con ml) |
| `precio_lista` | float | - | Precio original (antes de la rebaja). Si no hay rebaja = `precio_actual` |
| `precio_actual` | float | - | Precio de venta vigente |
| `descuento_pct` | float | - | `_dto(lista, actual)`, 1 decimal; `0.0` si no hay rebaja |
| `fuente` | str | - | `"api"` (VTEX, Unishop) / `"tachado"` (HTML) / `"tiendanube"`; tambien `"dos_precios"` y `"badge_%"` (se generan pero se descartan por defecto) |
| `url` | str | - | Link al producto (VTEX: con `?skuId=`) |
| `tipo_descuento` | str | `"rebaja"` | `"rebaja"` o `"transferencia"` (solo Ambrosia) |
| `precio_transferencia` | float\|None | `None` | Precio pagando por transferencia (solo si es menor que el actual) |
| `genero` | str | `"masculino"` | `"masculino"` / `"femenino"` / `"unisex"`; lo pone `main()` segun la entrada de `SITES` |
| `presentacion` | str | `""` | Texto del tamano/variante: "100 ML", "50 ML" |

Propiedad `clave` = `(tienda, nombre.lower().strip(), round(precio_actual, 2))` (l.298): clave de deduplicacion en memoria. Notar que **incluye el precio**, a diferencia de la clave de la base.

---

## 2. Normalizacion

El scraper hace **normalizacion minima**. Casi toda la "inteligencia" (marcas canonicas, emparejar productos entre tiendas, joyas) vive aguas abajo (Streamlit legado y Laravel).

| Aspecto | Que hace el scraper | Donde |
|---|---|---|
| Texto base `_norm` | NFKD, quita diacriticos, minusculas | l.307 |
| `nombre_norm` | `_norm` + colapsa espacios + `strip` + corta a 255 caracteres. Es la clave de unicidad del producto por tienda | `_nombre_norm` l.339 |
| Tamano ml (`tamano_ml`) | Regex `(\d+(?:[.,]\d+)?)\s*ml\b` sobre el nombre normalizado; **primer** match; coma->punto; `int(round())`; `NULL` si no hay. No entiende `cc`, `oz`, `gr`, ni "2 x 50 ml" (toma 2) | `_tamano_ml` l.328 |
| Nombre con tamano | Si el nombre ya trae ml, se deja; si la variante contiene al nombre y trae ml, se usa la variante; si solo aporta "90 ML", se agrega al final en mayusculas | `_nombre_con_tamano` l.344 |
| `presentacion` | Texto de la variante si contiene ml (max 150 caracteres), si no el "NN ML" extraido del nombre (mayusculas); `""` si no hay | `_presentacion` l.367 |
| Genero | De la config (`SITES[i]["genero"]`), no del producto | l.1821-1823, 1869-1871 |
| Marca | **Sin normalizar**: VTEX `brand`, Unishop `brand.name`, HTML por JSON-LD/selectores, vacia en Normy y Ambrosia. En DB se guarda `marca or NULL` (sin truncar; la columna es `varchar(120)`) | `guardar_en_mysql` l.436-441 |
| Concentracion (EDT/EDP/Parfum...) | **No se extrae**: queda dentro del texto del nombre | - |
| "Joya" / premium | **No existe en el scraper.** Aparece (a) como path de URL en Farmalife (`fragancias/premium/...`) y filtro `category-2=premium` en Juleriaque (solo seleccion de categoria), y (b) como concepto de dashboard: catalogo de marcas/lineas con umbral de descuento (Streamlit `buscar_joyas`/`_tier_joya`: >=40% doble fuego, >=30% fuego, resto check; Laravel `Livewire/Dashboard.php`, `CATALOGO` l.85, `joyasUmbral = 30`) | - |

Cobertura real de campos en la base (corrida 156, ofertas): `tamano_ml` NULL en Juleriaque 137/152, Ruiz y Roca 60/61, Normy 107/269, Unishop 41/290, Farmalife 8/250; casi completo en VTEX (0-4). Marca vacia: Normy 269/269. En toda la tabla `productos`: 2608 de 15416 sin `tamano_ml` (17%), 1631 sin marca (11%), 2715 sin `presentacion` (18%). Distribucion de `tamano_ml` mas comun: 100 (6269), 50 (1857), 80 (745), 30 (673), 90 (599).

### 2.1 Reglas de deteccion de oferta/descuento

| Origen | Regla |
|---|---|
| VTEX | `ListPrice > Price > 0` => rebaja. `fuente="api"` |
| Unishop | `pvPrice.prevPrice > pvPrice.price` => rebaja. `fuente="api"` |
| Tiendanube | `promotional_price < price` o `compare_at_price > price` => rebaja; solo mas barato por medio de pago => `transferencia` (lista = actual, dto 0). `fuente="tiendanube"` |
| HTML | solo precio tachado explicito (`tachado`); `dos_precios`/`badge_%` descartados; si hay badge y difiere >3 pp del calculado, se usa el del badge |
| Filtro final (`main` l.1902) | `tipo != "transferencia"` y `max(--min-descuento, 0.01) <= descuento_pct <= --max-descuento (90)`. **Rebajas fuera de rango y productos sin descuento no se guardan.** Maximo observado en la base: 70.2% |
| Transferencia | pasan sin filtro de rango (`transfer`, l.1908) a la DB, no al Excel |

Consecuencia importante para consumidores: **la base solo contiene ofertas y transferencias, no el catalogo completo**. Un producto que deja de estar en oferta simplemente deja de tener observaciones en las corridas siguientes (no hay fila "sin descuento"). El valor enum `tipo_descuento='ninguno'` existe en el esquema pero el scraper nunca lo escribe.

### 2.2 Deduplicacion y matching

1. **En memoria** (l.1898): `unicos[it.clave] = it` (tienda + nombre en minusculas + precio actual). Si el mismo producto/precio aparece en dos generos (ej. masculino y unisex), **gana el ultimo procesado** (orden de `SITES`: masculino, femenino, unisex).
2. **Para el Excel**: emparejamiento con el Excel anterior por **nombre normalizado, sin importar la tienda** (`_clave_comp` l.1495): "mejor negocio" (verde) si el descuento sube mas de 0.1 pp o el precio actual baja del minimo previo; "nuevo" (amarillo) si el nombre no estaba.
3. **En la base** (`guardar_en_mysql`): un producto = `(tienda_id, nombre_norm)` (indice unico `uq_producto`). Productos que difieren solo en mayusculas/acentos/espacios se fusionan. Dos tamanos del mismo perfume solo se distinguen si el nombre incluye el tamano. Una observacion por `(corrida_id, producto_id)`: si el mismo producto aparece repetido en la corrida (distinto precio o genero) se conserva el **primero de la lista** `res_db`, que esta ordenada por descuento descendente, o sea la oferta de mayor descuento; el `genero` del producto es el de ese primer item (el UPSERT lo actualiza en cada corrida: un producto que cambia de categoria cambia de genero).
4. **Entre tiendas**: el scraper **no** empareja productos de distintas tiendas. Eso lo hacen los dashboards: Streamlit con `clave_match` (huella = nombre sin tamano, sin tokens de la marca, sin palabras ruido `vap/vaporizador/spray/perfume/ml/x/und/unidad`, tokens ordenados; tamano como dimension aparte, asumiendo 100 ml si no es explicito) y alias de marca (`ALIAS_MARCAS`, `canon_marca` en `dashboard_descuentos.py` l.259-319). Como lo resuelve Laravel no se verifico en este analisis (ver docs de Laravel).

---

## 3. Salida a base de datos MySQL

### 3.1 Conexion
* Libreria: PyMySQL, `charset="utf8mb4"`, `autocommit=False` (una transaccion por corrida; `rollback` ante error). Config `DB_CONFIG` (l.317) desde variables de entorno **`PERF_DB_HOST`, `PERF_DB_PORT`, `PERF_DB_USER`, `PERF_DB_PASS`, `PERF_DB_NAME`**, con defaults de XAMPP local (host 127.0.0.1, puerto 3306, usuario root, base `perfumes_descuentos`). Los valores no se documentan aqui.
* No depende del `.env` de Laravel (que usa `DB_*`). Se verifico que la base `perfumes_descuentos` existe y contiene tanto las tablas del scraper como las de Laravel (`users`, `alertas`, `planes`, `suscripciones`, `sessions`, `cache`, `jobs`, `migrations`...), por lo que ambos sistemas comparten base.
* Orden de escritura en `guardar_en_mysql` (l.381-493), todo en una transaccion y un unico `commit`:

```
1) corridas          INSERT ... ON DUPLICATE KEY UPDATE (clave fecha_hora)  -> corrida_id
2) tiendas           UPSERT por nombre                                      -> cache tienda_id
3) productos         UPSERT por (tienda_id, nombre_norm)                    -> cache producto_id (1 por tienda+nombre_norm)
4) observaciones     executemany UPSERT por (corrida_id, producto_id)       (1 por producto)
5) corrida_sitios    executemany UPSERT por (corrida_id, tienda)            (1 por entrada tienda_genero)
```

### 3.2 Como crea una corrida
```sql
INSERT INTO corridas (fecha_hora, total_productos, min_descuento, max_descuento)
VALUES (%s,%s,%s,%s)
ON DUPLICATE KEY UPDATE total_productos=VALUES(total_productos),
  min_descuento=VALUES(min_descuento), max_descuento=VALUES(max_descuento),
  id=LAST_INSERT_ID(id)
```
* `fecha_hora` = `datetime.now()` tomado **al inicio** del script (misma marca que el nombre del Excel). Es UNIQUE: re-ejecutar con la misma marca actualiza la corrida en lugar de duplicarla (idempotente; las observaciones se upsertean, no se borran las previas).
* Trampas conocidas:
  * `total_productos` = `len(res_db)` = rebajas + transferencias **antes** de deduplicar observaciones; por eso puede ser mayor que `COUNT(observaciones)` de esa corrida (corrida 156: 2656 vs 2643).
  * `min_descuento` y `max_descuento`: **hasta la corrida 156 (2026-10-29) guardaban los argumentos CLI** (`--min-descuento` 0 / `--max-descuento` 90), no valores reales. **Corregido el 2026-10-29**: desde la próxima corrida se guardan el mínimo y máximo reales de las `rebaja` de la corrida (`None` si no hay). Las 131 corridas existentes conservan 0.00/90.00: no usarlas como estadística para fechas anteriores.
  * El patron `id=LAST_INSERT_ID(id)` en los UPSERT consume valores de AUTO_INCREMENT en cada ejecucion: `tiendas` tiene 12 filas pero `AUTO_INCREMENT=1712`; `corridas` tiene 131 filas y AUTO_INCREMENT 157 (ids hasta 156). No es un error; no asumir ids contiguos.

### 3.3 Esquema real (transcripto de la base viva)

```sql
CREATE TABLE `tiendas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tienda_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tienda_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `nombre_norm` varchar(255) NOT NULL,
  `marca` varchar(120) DEFAULT NULL,
  `genero` enum('masculino','femenino','unisex') NOT NULL DEFAULT 'masculino',
  `tamano_ml` int(11) DEFAULT NULL,
  `presentacion` varchar(150) DEFAULT NULL,
  `url` varchar(600) DEFAULT NULL,
  `primera_vez` datetime DEFAULT NULL,
  `ultima_vez` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_producto` (`tienda_id`,`nombre_norm`),
  KEY `idx_prod_marca` (`marca`),
  CONSTRAINT `fk_prod_tienda` FOREIGN KEY (`tienda_id`) REFERENCES `tiendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `corridas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL,
  `total_productos` int(11) NOT NULL DEFAULT 0,
  `min_descuento` decimal(5,2) DEFAULT NULL,
  `max_descuento` decimal(5,2) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corrida_fecha` (`fecha_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `observaciones` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `corrida_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `precio_lista` decimal(12,2) NOT NULL,
  `precio_actual` decimal(12,2) NOT NULL,
  `descuento_pct` decimal(5,2) NOT NULL,
  `ahorro` decimal(12,2) GENERATED ALWAYS AS (`precio_lista` - `precio_actual`) STORED,
  `fuente` varchar(20) DEFAULT NULL,
  `tipo_descuento` enum('rebaja','transferencia','ninguno') NOT NULL DEFAULT 'rebaja',
  `precio_transferencia` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obs` (`corrida_id`,`producto_id`),
  KEY `idx_obs_desc` (`descuento_pct`),
  KEY `idx_obs_producto` (`producto_id`),
  CONSTRAINT `fk_obs_corrida` FOREIGN KEY (`corrida_id`) REFERENCES `corridas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_obs_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `corrida_sitios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `corrida_id` int(11) NOT NULL,
  `tienda` varchar(80) NOT NULL,
  `plataforma` varchar(10) DEFAULT NULL,
  `total_encontrados` int(11) NOT NULL DEFAULT 0,
  `con_descuento` int(11) NOT NULL DEFAULT 0,
  `estado` varchar(10) NOT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `segundos` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs` (`corrida_id`,`tienda`),
  KEY `idx_cs_estado` (`estado`),
  CONSTRAINT `fk_cs_corrida` FOREIGN KEY (`corrida_id`) REFERENCES `corridas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE VIEW `v_descuentos` AS
SELECT c.id AS corrida_id, c.fecha_hora AS fecha, t.nombre AS tienda, p.id AS producto_id,
       p.marca, p.nombre AS producto, p.tamano_ml, o.precio_lista, o.precio_actual,
       o.descuento_pct, o.ahorro, o.fuente, o.tipo_descuento, o.precio_transferencia, p.url
FROM observaciones o
JOIN corridas c ON c.id = o.corrida_id
JOIN productos p ON p.id = o.producto_id
JOIN tiendas t ON t.id = p.tienda_id;
```

Tablas legado del dashboard Streamlit que comparten la base (no las escribe el scraper): `usuarios` (`id`, `username` UNIQUE, `password_hash`, `salt`, `creado`; collation `utf8mb4_general_ci`) y `favoritos` (`id`, `usuario_id` FK a `usuarios.id`, `tipo` default `'tabla'`, `nombre`, `config` text JSON, `creado`, `actualizado`; UNIQUE `(usuario_id, tipo, nombre)`).

### 3.4 Que escribe el scraper en cada tabla

| Tabla | Columna | Valor escrito |
|---|---|---|
| `tiendas` | `nombre` | `Item.tienda` (nombre canonico). Solo tiendas con al menos un item en la corrida |
| `productos` | `tienda_id` | id de la tienda |
| | `nombre` | `Item.nombre[:255]` (se actualiza en cada corrida) |
| | `nombre_norm` | `_nombre_norm(nombre)` |
| | `marca` | `Item.marca or NULL` |
| | `genero` | `Item.genero` (se actualiza en cada corrida) |
| | `tamano_ml` | `_tamano_ml(nombre)` |
| | `presentacion` | `Item.presentacion[:150]`, o `_presentacion("", nombre)`, o `NULL` |
| | `url` | `Item.url or NULL` (se actualiza; **no se trunca**, columna 600) |
| | `primera_vez` | `fecha_hora` de la corrida **en el INSERT inicial**; el `ON DUPLICATE KEY` no lo modifica |
| | `ultima_vez` | `fecha_hora` de la corrida (se actualiza siempre que el producto aparece) |
| `observaciones` | `corrida_id`, `producto_id` | ids |
| | `precio_lista`, `precio_actual` | `round(x, 2)` |
| | `descuento_pct` | `Item.descuento_pct` (1 decimal) |
| | `fuente` | `api` / `tachado` / `tiendanube` (en la base solo existen estos 3) |
| | `tipo_descuento` | `rebaja` o `transferencia` |
| | `precio_transferencia` | `round(x, 2)` o `NULL` |
| | `ahorro` | **no se escribe**: columna generada `precio_lista - precio_actual` |
| `corridas` | ver 3.2 | |
| `corrida_sitios` | `tienda` | **`"{nombre}_{genero}"`** (ej. `fiorani_masculino`), NO el nombre canonico: evita colision con el UNIQUE `(corrida_id, tienda)` cuando una tienda tiene varias entradas |
| | `plataforma` | `vtex` / `unishop` / `tiendanube` / `html` |
| | `total_encontrados` | items de la entrada (incluye los que luego se descartan por no tener descuento) |
| | `con_descuento` | items con `descuento_pct > 0` |
| | `estado` | `ok` / `vacio` / `error` |
| | `mensaje` | texto del error (<=500) o "0 productos: posible error de scraping" |
| | `segundos` | duracion de la entrada |

Historial no se pisa: cada corrida agrega sus observaciones; el historico crece (hoy 131 corridas, ~435 mil observaciones, 15.4 mil productos; 12 tiendas). `productos.ultima_vez` permite saber si un producto sigue en oferta: 12773 de 15416 productos no aparecieron en la ultima corrida.

Ejemplo de fila en `productos` (VTEX): `nombre='1989 Blue EDT 100 Ml'`, `nombre_norm='1989 blue edt 100 ml'`, `marca='Kevingston'`, `presentacion='100 Ml'`, `url='https://www.perfumeriaspigmento.com.ar/1989-blue-edt/p?skuId=1975'`.

Distribucion por genero (`productos`): femenino 7451, masculino 6456, unisex 1509. Combinaciones `fuente`/`tipo` en `observaciones`: `api/rebaja` 253.890, `tachado/rebaja` 71.248, `tiendanube/transferencia` 109.602, `tiendanube/rebaja` 41. No hay filas con `dos_precios` ni `badge_%`.

---

## 4. Relacion con lo que lee Laravel (`app/Models/*.php`)

| Modelo Eloquent | Tabla | Coincide con el scraper? |
|---|---|---|
| `Corrida` | `corridas` | Si. `$timestamps=false`; casts `fecha_hora`, `total_productos`, `min_descuento`, `max_descuento`. Relaciones `sitios()`, `observaciones()`. `Corrida::ultima()` = mayor `fecha_hora`. No castea `creado_en` (no se usa) |
| `CorridaSitio` | `corrida_sitios` | Si (casts `total_encontrados`, `con_descuento`, `segundos`). **Ninguna vista/consulta de `app/`, `resources/`, `routes/` usa este modelo hoy** (grep); es info de auditoria |
| `Tienda` | `tiendas` | Si (`$fillable=['nombre']`) |
| `Producto` | `productos` | Si. Casts `primera_vez`, `ultima_vez`, `tamano_ml`, `presentacion`. No declara `genero`/`marca`/`url` (no hace falta; se leen como atributos) |
| `Observacion` | `observaciones` | Si. Casts de floats incluyen `ahorro` (generada). `scopeRebajas()`: `tipo_descuento='rebaja'` y `fuente NOT IN ('dos_precios','badge_%')` (esa exclusion fue un parche retroactivo del 2026-06-26; hoy la base ya no tiene esas fuentes) |
| `Favorito` | `favoritos` | Tabla heredada del Streamlit (ver discrepancia 2) |

Las lecturas principales de Laravel usan `DB::table('observaciones as o')->join('productos as p')->join('tiendas as t')->join('corridas as c')` (`Livewire/Dashboard.php`, `Console/Commands/ProcesarAlertas.php`) y filtran por `o.corrida_id = <corrida actual>`, `p.genero` (`Dashboard.php:315` y otras), `p.tamano_ml`, `o.tipo_descuento`, `o.precio_transferencia`. La vista `v_descuentos` **no** la usa Laravel (solo Streamlit) y **no incluye `genero` ni `presentacion`**.

### 4.1 Discrepancias y puntos de atencion

1. **Esquema no versionado:** las 5 tablas del scraper y la vista no tienen migraciones Laravel ni DDL en el repo. Una instalacion nueva no puede reconstruir la base desde el codigo; usar la transcripcion de 3.3 (fuente: base viva). `php artisan migrate` no las crea.
2. **`favoritos` apunta a la tabla equivocada:** `favoritos.usuario_id` tiene FK a `usuarios.id` (tabla del Streamlit; 1 fila, usuario `brian95`), pero `app/Models/Favorito.php` y `Livewire/Dashboard.php:1112` guardan `auth()->id()`, que es el id de la tabla Laravel `users` (2 filas). Un usuario Laravel cuyo id no exista en `usuarios` provocaria violacion de FK al guardar un favorito, y uno cuyo id coincida (id 1) heredaria favoritos de otro usuario. En la base hay 1 favorito (`usuario_id=1`). Comportamiento exacto en runtime: no verificado.
3. ~~`corridas.min_descuento` / `max_descuento` son parámetros CLI~~ **Corregido 2026-10-29** para corridas nuevas (ver arriba); el histórico (hasta la corrida 156) sigue con 0.00 / 90.00. Ningún view de Laravel usa estos campos.
4. **`corrida_sitios.tienda` es `nombre_genero`** (ej. `ambrosia_masculino`) y no coincide con `tiendas.nombre`. Cualquier join entre ambas fallaria; hoy no se usa.
5. **`observaciones.tipo_descuento` admite `'ninguno'`** pero el scraper nunca lo escribe, y la base no contiene productos sin descuento (solo ofertas + transferencias). Si Laravel necesitara "catalogo completo" no esta disponible.
6. **`ProcesarAlertas` filtra `tipo_descuento='rebaja'` y `descuento_pct BETWEEN 5 AND 100`** sin excluir `dos_precios`/`badge_%` (a diferencia de `scopeRebajas`): hoy no afecta porque esas fuentes ya no se guardan, pero si se habilitaran en `SITES` (`fuentes_validas`) las alertas las tomarian como reales.
7. **Cobertura de `tamano_ml`/`marca` desigual:** filtros por ml (`whereIn('p.tamano_ml', ...)`, `Dashboard.php:350` y otros) excluyen los productos con `tamano_ml NULL` (Juleriaque y Ruiz y Roca tienen casi todos sus productos con `tamano_ml` NULL; ~17% global). Filtros por marca no ven los productos de Normy/Ambrosia (marca NULL).
8. **Posibles fusiones/colisiones de producto:** la unicidad `(tienda_id, nombre_norm)` fusiona variantes con igual nombre y distinto tamano si el nombre no trae ml; ademas `genero` de un producto presente en varias categorias es el del item de mayor descuento de cada corrida (puede oscilar entre corridas).
9. ~~Ambrosia sin datos desde 2026-10-22~~ regex corregida el 2026-10-29 (ver doc 02); se confirmará con la corrida del 30/10.
10. **Dashboard debe usar la ultima corrida valida:** la corrida `id=102` (2026-08-31 08:13, 971 productos) es una corrida incompleta que quedo guardada antes de existir el control de salud; si fuese la "ultima" distorsionaria, pero hoy no lo es. Para analisis historicos conviene excluirla o filtrar por `total_productos` razonable.
11. **Collation distinta:** `favoritos`/`usuarios` usan `utf8mb4_general_ci`; el resto `utf8mb4_unicode_ci` (relevante solo para joins entre ellas).
12. **Excel vs DB:** el Excel excluye transferencias y no trae genero/ml; la DB es la fuente completa. El Excel guarda `Descuento` y `Ahorro` como formulas sin valor cacheado (hay que recalcular `(E-F)/E` al leerlo con `data_only=True`, como hace el Streamlit).
