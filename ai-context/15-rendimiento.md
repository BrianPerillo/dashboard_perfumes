# 15 · Rendimiento: medición, cuellos de botella y mejoras

Auditoría de velocidad de las pantallas del 2026-11-01 (medida con requests reales en proceso y SQL cronometrado; BD con ~421k observaciones, 24k productos, 134 corridas, MariaDB 10.4 de XAMPP).

## Resultados (tiempo de la request, sin caché → con caché)

| Pantalla | Antes | Después | Qué se hizo |
|---|---|---|---|
| `/joyas` (Destacados, perfumes) | 10,9 s · 897 KB | **0,8 s · 371 KB** | frecuencia/antigüedad por `producto_id` + cards paginadas con scroll infinito |
| `/evolucion` (perfumes) | 6,2 s | 4,6 s la 1ª vez → **0,16 s** | caché por filtros + última corrida |
| `/seguir` | 8,6 s · 4,6 MB | **0,9 s → 0,15 s · 183 KB** | búsqueda en el servidor (ya no se envían ~21.000 productos) |
| `/ropa/tabla` | 1,4 s | 1,35 s → **0,48 s** | caché de total y páginas |
| `/tabla`, `/cambios`, `/marcas`, `/distribucion`, `/tiendas` | 0,25–0,3 s | sin cambio | ya eran rápidas |

(El "piso" por request vía Apache es ~0,6 s: ver "Pendiente de configuración".)

## Causas encontradas

1. **`innodb_buffer_pool_size = 16M`** (default de XAMPP) con 76 MB de datos+índices en `observaciones`/`productos`: cada consulta que recorría la tabla leía del disco. Subirlo a 256 MB redujo la misma consulta de 3,4 s a 0,15 s. Se aplicó en caliente con `SET GLOBAL innodb_buffer_pool_size = 268435456` (**se pierde al reiniciar MySQL**; ver abajo).
2. **`cargarJoyas`**: dos consultas (`COUNT(DISTINCT corrida)` y `MIN(fecha)`) filtraban `LOWER(p.nombre) IN (...)` y arrancaban por `corridas`, recorriendo toda `observaciones` (~5 s c/u). Ahora `Dashboard::statsJoyas` busca los `producto_id` de esos nombres y consulta `observaciones` por `idx_obs_producto`. Verificado contra la versión anterior: mismos valores para 415/415 nombres (ahora la clave ignora mayúsculas y acentos con `claveNombre`).
3. **`cargarEvolucion`**: agrega todo el historial (~4 s). No se pudo acelerar con índices sin tocar tablas del scraper; se cachea.
4. **Seguir**: `cargarSeguirOpciones` unía todas las observaciones y mandaba ~21.000 `<option>` (en el HTML y otra vez en el snapshot de Livewire).

## Cambios en el código

* `app/Livewire/Dashboard.php`
  * `JOYAS_POR_PAGINA = 30`, `$joyasVisibles`, `cargarMasJoyas()`; la vista (`tab-joyas.blade.php`) renderiza solo las primeras cards (orden: prioridad de marca, mayor descuento) y un `#joyas-sentinel` (`data-mas`) con `IntersectionObserver` pide más al acercarse al final, igual que la tabla. Los KPI siguen calculándose sobre el total.
  * `statsJoyas()` / `claveNombre()`.
  * `cacheado($query, $tipo, $fn)`: `Cache::remember` con clave = SQL + parámetros + id de la última corrida **completa** (los filtros están en el SQL y una corrida nueva cambia la clave: no sirve datos viejos; TTL 6 h solo limpia). Usado en el total y las páginas de la tabla; `cargarEvolucion` y `cargarSeguirOpciones` usan el mismo esquema. `marcasDisponibles`/`mlDisponibles` se cachean 1 h (`claveDisponibles`).
  * Seguir: `SEGUIR_OPCIONES = 40`, `queryOpcionesSeguir()`, `buscarSeguir($texto)` (`#[Renderless]`; 2 pasos: texto y luego "tuvo rebaja"). `tab-seguir.blade.php` busca con `$wire.buscarSeguir` y pausa de 250 ms al tipear.
* Caché: driver `file` (`CACHE_STORE`); la primera visita tras una corrida nueva paga la consulta pesada, el resto no. No hay "calentamiento" automático (pendiente, ver abajo).

## Pendiente de configuración (no aplicado: requiere reiniciar servicios de XAMPP)

1. **MySQL**: en `C:\xampp\mysql\bin\my.ini` fijar `innodb_buffer_pool_size=256M` (hoy `16M`) para que el cambio sobreviva a reinicios. No cambiar `innodb_log_file_size` sin vaciar los logs. Reinicio con el procedimiento seguro de `11-…` (nunca arrancar MySQL dos veces).
2. **PHP**: `opcache` está desactivado en `C:\xampp\php\php.ini` (`;zend_extension=opcache`, `;opcache.enable=1`). Activarlo (`zend_extension=opcache`, `opcache.enable=1`, `opcache.memory_consumption=128`, `opcache.max_accelerated_files=10000`) suele recortar bastante el ~0,6 s base de cada request de Laravel. Requiere reiniciar Apache. No medido todavía.
3. **Apache**: `mod_deflate` desactivado (`#LoadModule deflate_module` en `httpd.conf`); en local no importa, en un servidor real conviene comprimir (las páginas pesan 0,2–1,3 MB).

## Otras ideas (no hechas)

* Calentar la caché al cerrar la corrida (comando artisan llamado desde `alertas_procesar.bat`).
* La tabla de ropa envía ~1,2 MB por página (100 filas con muchos campos): reducir columnas del HTML/snapshot.
* Índice cubridor en `observaciones` (p. ej. `(tipo_descuento, producto_id, corrida_id, descuento_pct)`) para `cargarEvolucion`; toca una tabla del scraper (coordinar + doc `03`).
* Error de consola preexistente en `/evolucion` y `/ropa/evolucion`: `Element not found` de ApexCharts desde `buildEvolucion` (los gráficos igual se dibujan); no relacionado con estos cambios, sin investigar.

## Cómo medir

Para perfilar una pantalla: bootear el kernel HTTP en un script PHP, escuchar `DB::listen` y hacer `Request::create('/joyas')` (se usó `prof2.php` en el scratchpad de la sesión, no versionado).
