# 07 · Alertas y notificaciones (email / Telegram)

## Concepto

Un usuario crea **alertas** para que el sistema le avise cuando el scraper detecte descuentos que coinciden con lo que sigue (una **marca**, una **tienda** o un **perfume/producto**). El envío lo hace el comando `php artisan alertas:procesar`, por **email** o **Telegram**.

Archivos:
* UI: `app/Livewire/Notificaciones.php` + `resources/views/livewire/notificaciones.blade.php` + parcial `resources/views/livewire/_alerta_form.blade.php` (ruta `/notificaciones`)
* Modelo: `app/Models/Alerta.php`
* Procesamiento: `app/Console/Commands/ProcesarAlertas.php`
* Mail: `app/Mail/AlertaDescuento.php` + `resources/views/emails/alerta_descuento.blade.php`
* Guías previas (setup de Gmail/Telegram): `notificaciones.md` en la raíz.

## Tabla `alertas`

Migración inicial `2026_06_28_000000` + 4 posteriores. Estado actual:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → `users.id` | `cascadeOnDelete`. **Ya no es único** (migración `2026_07_03_045921`): un usuario puede tener varias alertas |
| `tipo` | enum(`marca`,`tienda`,`producto`) default `producto` | El código también tolera el typo `produto` (alias legacy) |
| `valor` | string(200) | Texto a buscar (ej. "Dior", "Juleriaque", "Sauvage") |
| `canal` | enum(`email`,`telegram`) default `email` | |
| `email_destino` | string(200) null | si canal = email |
| `telegram_chat_id` | string(100) null | si canal = telegram |
| `activo` | bool default true | |
| `ultima_notificacion_at` | timestamp null | evita reenvíos; se actualiza al enviar |
| `prueba_enviada` | bool default false | se marca true al enviar un mensaje de prueba |
| `solo_mejoras` | bool default false | solo para `tipo=producto`: avisar solo si el precio mejora |
| `tamano_ml` | smallint unsigned null | solo `tipo=producto`: filtrar por tamaño exacto |
| `marca_filtro` | string(120) null | solo `tipo=producto`: filtro adicional de marca |
| `created_at`, `updated_at` | | |

## Pantalla de usuario (`Notificaciones`)

* Accesible a todos; la vista debe manejar el caso invitado (los métodos de escritura hacen `if (!auth()->check()) return;`).
* Layout app con `activeTab='notificaciones'`. Título "Alertas · Descuentos Perfumes".
* Estado: lista de alertas del usuario (`orderBy created_at`), `editandoId`, `creandoNueva` (si no tiene ninguna alerta, abre el formulario de creación automáticamente), `mensajeError`.
* Formulario: `tipo`, `valor`, `canal`, `emailDestino` (default = email del usuario), `telegramChatId`, y para productos `soloMejoras`, `tamanoMl`, `marcaFiltro`.
* **Autocompletado/sugerencias** desde la DB de scraping:
  * `tipo=marca` → `productos.marca` distintas (LIKE, máx 60)
  * `tipo=tienda` → `tiendas.nombre`
  * `tipo=producto` → `productos.nombre` (desde 3 caracteres, máx 20); además `tamanosDisponibles` (ml distintos de productos que matchean el nombre) y `marcasSugerencias`.
* `guardar()` valida (`valor` 2–200, canal, email requerido si canal=email, chat id requerido si canal=telegram, `tamanoMl` 1–9999) y crea/actualiza (siempre `activo=true`; al crear `ultima_notificacion_at=null`). `solo_mejoras`, `tamano_ml`, `marca_filtro` solo se guardan si el tipo es producto.
* `eliminar($id)`, `activarEdicion($id)`, `cancelarEdicion()`, `nuevaAlerta()`.
* `enviarPrueba($id)`: manda un mensaje de prueba (email con `new AlertaDescuento($alerta, true)` o Telegram) y marca `prueba_enviada=true`.
* Feedback con evento `toast` (la vista/layout lo muestra).
* **Límites del plan** (aplicados desde 2026-10-29, `User::limitesPlan()`): al crear una alerta se verifica `max_alertas` del plan vigente (Gratis = 1; Pro = 10; Premium = ilimitadas; admins sin límite) y el canal Telegram solo se permite si el plan lo incluye. Si se excede, `guardar()` no guarda y muestra el mensaje en `mensajeError` con referencia a `/planes`. `alertas:procesar` también los respeta (ver abajo), por si el plan venció o bajó.

## Comando `alertas:procesar`

`php artisan alertas:procesar [--dry-run]` — `--dry-run` imprime qué enviaría sin enviar ni actualizar `ultima_notificacion_at`.

Algoritmo (`ProcesarAlertas::procesarAlerta`):

1. Carga `Alerta::with('user')->where('activo', true)`.
2. Query base: `observaciones o ⨝ productos p ⨝ tiendas t ⨝ corridas c` con `o.tipo_descuento = 'rebaja'` y `o.descuento_pct BETWEEN 5 AND 100`, **más los mismos filtros que el dashboard** (desde 2026-10-29): `Observacion::filtrarFuentesValidas()` (sin `dos_precios` / `badge_*`) y `Observacion::excluirMarcas()` (sin 'rimmel'). Antes de procesar, por usuario: se atienden solo las primeras N alertas (por id) que permite su plan y se saltean las de Telegram si el plan no lo incluye.
3. Si `tipo=producto` y `tamano_ml` → `p.tamano_ml = tamano_ml`.
4. **Ventana de novedades**:
   * Con `ultima_notificacion_at`: solo corridas posteriores (`c.fecha_hora > ultima_notificacion_at`) y dedupe:
     * `solo_mejoras && tipo=producto`: excluye observaciones si ya existía (en corridas hasta la última notificación) una con `precio_actual <= actual` para ese producto (solo avisa si el precio es **estrictamente menor** a todo lo visto).
     * resto: excluye si ya hubo una observación del mismo producto con precio ≈ igual (`|Δ| < 1`) en corridas previas a la última notificación (evita repetir el mismo producto al mismo precio).
   * Sin `ultima_notificacion_at` (primera vez): toma solo la **corrida completa más reciente** (`MAX(corridas.id)` con `estado = 'completa'`).
   * Desde 2026-11-01 la query base filtra `c.estado = 'completa'`: nunca se notifica por una corrida de ropa `en_curso`/`incompleta` (el scraper guarda tienda por tienda).
5. Filtro por tipo: `marca` → `p.marca LIKE %valor%`; `tienda` → `t.nombre LIKE %valor%`; `producto` → `p.nombre LIKE %valor%` (+ `marca_filtro` → `p.marca LIKE %…%`).
6. Orden `descuento_pct DESC`, `LIMIT 10`.
7. Si hay resultados y no es dry-run:
   * `email` → `Mail::to(email_destino)->send(new AlertaDescuento($alerta, $resultados))` (**sincrónico**, no encolado)
   * `telegram` → `Http::post("https://api.telegram.org/bot{TOKEN}/sendMessage", chat_id, text (Markdown), parse_mode)`; muestra solo los 5 primeros; lanza excepción si Telegram responde error o falta `TELEGRAM_BOT_TOKEN` (en ese caso solo imprime error y retorna).
   * `ultima_notificacion_at = now()`. Los errores se capturan por alerta (una falla no corta el resto).
8. El email de prueba y el real usan la misma vista `emails/alerta_descuento.blade.php` (parámetro `$resultados`: `true`/`false` para modo prueba/ejemplo, o `Collection` real).

### Cuándo se ejecuta

* **Manual**: terminal, o desde el panel `/admin` → botón "Enviar alertas a usuarios" (`Admin\Dashboard::procesarAlertasEmail()` usa `Artisan::call('alertas:procesar')` y muestra la salida).
* **Automático**: la tarea de Windows **`alertas_perfumes`** (creada el 2026-10-29) ejecuta `scripts\alertas_procesar.bat` → `php artisan alertas:procesar` **cada 3 horas** (primera a las 14:00, 30 min después del scraper de las 13:00; el log va a `storage\logs\alertas_cron.log`). No usa el scheduler de Laravel (se quitó el `Schedule` de `routes/console.php`). Es seguro correrlo seguido: solo envía novedades desde `ultima_notificacion_at`. Modo de la tarea: *Interactive* (corre solo con la sesión de Windows iniciada, porque `S4U` exige administrador). Manual: `scripts\alertas_procesar.bat --dry-run`, o desde `/admin`.
* Idealmente debería ejecutarse **después de cada corrida del scraper** (ver cadencia en [scraper-01](scraper-01-descripcion-y-ejecucion.md)).

## Telegram

* Sin paquetes: `Http::post` a la API de Bot de Telegram. Token en `.env` `TELEGRAM_BOT_TOKEN` → `config('services.telegram.bot_token')`.
* El usuario debe iniciar conversación con el bot y pasar su **Chat ID** (obtenible vía `getUpdates`). Pasos en `notificaciones.md`.
* El texto usa `parse_mode=Markdown`; desde 2026-10-29 los nombres de producto/tienda/valor se escapan con `Alerta::escaparMarkdown()` (`_ * ` [`) para que no rompan el mensaje.

## Email

* Mailer configurable por `.env` (`MAIL_MAILER=smtp` con Gmail App Password; `log` para desarrollo). Remitente `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.
* Asunto de la alerta: `🔔 Nuevos descuentos en {Marca|Tienda|Perfume}: {valor}` (`Alerta::tipoLabel()`).
* Otros mails del sistema: código de verificación (`VerificacionCodigo`), reset de contraseña (nativo de Laravel), test del admin (`Mail::raw`).

## Alertas por rubro (desde 2026-10-29)
`alertas.rubro` (`perfumes` por defecto). En `/notificaciones` el formulario tiene un selector de **Rubro**; las sugerencias de marca/tienda/producto salen solo de las tiendas de ese rubro, el filtro de tamaño en ml solo aplica a perfumes y la etiqueta dice "¿Qué producto?" para ropa. `ProcesarAlertas` agrega `c.rubro = alerta.rubro` a la consulta y, en la primera pasada, toma la última corrida **de ese rubro**. Probado con una alerta de marca "Nike" en ropa (creada y borrada).

## Talles y % mínimo (desde 2026-10-30)
Columnas nuevas en `alertas` (migración `2026_10_30_010000`): `talles` (varchar 255, "M, XL, 42"; solo rubro ropa) y `descuento_min` (tinyint, todos los rubros). El formulario los pide como opcionales; `talles` se normaliza (mayúsculas, sin repetidos). `ProcesarAlertas`: `o.descuento_pct >= max(5, descuento_min)` y, si hay talles, el producto debe tener alguno disponible (`UPPER(CONCAT(', ', p.talles, ', ')) LIKE '%, M, %'`). La lista de alertas muestra chips "Talles …" y "≥ N %". Limitación: los talles de calzado adulto de Nike vienen como `7` o `H 10 / M 11.5`; hoy se compara por texto, sin conversión (ver [12](12-problemas-conocidos-y-pendientes.md)).

Los mensajes de alerta (email y Telegram) incluyen **"Talles disponibles: …"** cuando el producto tiene talles (ropa); los perfumes no cambian.

**Talles de calzado en alertas (2026-10-30):** `ProcesarAlertas` busca en `COALESCE(talles_norm, talles)`. En calzado se puede escribir `H10` (talle hombre), `M7` (talle mujer) o un número solo (`10`), que coincide con `H10`, `M10` y `10`. El formulario lo explica. Probado en seco: `M7` y `H5.5` encuentran el Nike Alphafly 3 (`H 5.5 / M 7`); `10` no.

## Imagen en el email (2026-10-30)
`ProcesarAlertas::procesarAlerta` ahora también trae `p.imagen`; `emails/alerta_descuento.blade.php` la muestra (96 px, a la derecha de la tarjeta) cuando es una URL http(s). Telegram sigue siendo solo texto. Pendiente (no hecho): aviso "vuelve un talle" (hay que guardar los talles por observación para compararlos entre corridas; hoy `productos.talles` solo tiene el valor actual). La opción "incluir equivalentes H↔M" ya existe en el filtro del dashboard (ver 05), no en las alertas.

**Tecnología (2026-11-08):** el formulario de alertas (`livewire/_alerta_form.blade.php`) ofrece el rubro Tecnología; la card "¿Qué querés monitorear?" pasó de "Perfume" a "Producto" y el texto/placeholder dependen del rubro; "Tamaño (ml)" solo para perfumes y "Talles" solo para ropa. `ProcesarAlertas` ya filtraba por `alertas.rubro`, así que no cambió.
