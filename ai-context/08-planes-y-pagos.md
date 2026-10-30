# 08 · Planes de suscripción y pagos (MercadoPago)

Archivos:
* Modelos: `app/Models/Plan.php`, `app/Models/Suscripcion.php`
* UI: `app/Livewire/Planes.php` + `resources/views/livewire/planes.blade.php` (ruta `/planes`)
* Servicio: `app/Services/MercadoPagoService.php`
* Controlador: `app/Http/Controllers/PagoController.php` (retornos y webhook)
* Vistas de retorno: `resources/views/pago/{exito,pendiente,error}.blade.php`
* Seeder: `database/seeders/PlanesSeeder.php`
* Guía de credenciales/sandbox: `mercadopago.md` (raíz)
* Config: `config/services.php` → `services.mercadopago.{access_token,public_key,webhook_secret}` (env `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`, `MP_WEBHOOK_SECRET`)

## Tablas

### `planes` (migración `2026_06_30_000000`)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | PK | |
| `slug` | string UNIQUE | `gratis` \| `pro` \| `premium` |
| `nombre` | string | Gratis / Pro / Premium |
| `precio` | decimal(10,2) default 0 | |
| `moneda` | char(3) default `ARS` | |
| `max_alertas` | int default 1 | `-1` = ilimitadas |
| `canal_telegram` | bool default false | ¿incluye Telegram? |
| `features` | json null | lista de bullets para la UI (el seeder guarda `json_encode(array)` + cast `array`) |
| `activo` | bool default true | |
| timestamps | | |

Datos sembrados por `PlanesSeeder` (`php artisan db:seed --class=PlanesSeeder`, usa `updateOrCreate` por `slug`):

| slug | precio ARS/mes | max_alertas | Telegram | Features |
|---|---|---|---|---|
| gratis | 0 | 1 | no | 1 alerta activa · Notificación por email · Acceso al dashboard |
| pro | 1500 | 10 | sí | 10 alertas · Email + Telegram · Historial de precios · Soporte prioritario |
| premium | 3500 | -1 | sí | Alertas ilimitadas · Email + Telegram · Historial completo · Acceso anticipado · Soporte prioritario |

`DatabaseSeeder` no llama automáticamente a `PlanesSeeder` (verificar al desplegar).

### `suscripciones` (migración `2026_06_30_000001`)

| Columna | Tipo | Notas |
|---|---|---|
| `id` | PK | Se usa como `external_reference` en MP |
| `user_id` | FK users | cascadeOnDelete |
| `plan_id` | FK **planes** | (tabla explícita) |
| `estado` | string default `pendiente` | `pendiente` \| `aprobado` \| `rechazado` \| `cancelado` \| `en_proceso` |
| `mp_preference_id` | string null | se guarda al crear la preferencia |
| `mp_payment_id` | string null | se guarda al procesar el pago |
| `mp_external_reference` | string null, index | **ver problema conocido abajo** |
| `monto` | decimal(10,2) null | |
| `moneda` | char(3) default `ARS` | |
| `aprobado_at` | timestamp null | |
| `vence_at` | timestamp null | fin del período pagado (aprobado + 1 mes); `null` para plan gratis |
| `mp_data` | json null | respuesta cruda de MP (auditoría) |
| timestamps | | |

Relaciones: `Plan hasMany Suscripcion`; `Suscripcion belongsTo User, Plan`. `Suscripcion::estaActiva()` = `estado==='aprobado'` y (`vence_at` nulo o futuro). Helpers de `Plan`: `esGratis()`, `alertasIlimitadas()`, `precioFormateado()`, `Plan::gratis()`.

## Flujo de pago

```
/planes (Planes)         — lista planes activos ordenados por precio + suscripción aprobada más reciente del usuario
   │  click "Suscribirme"
   ▼
Planes::iniciarPago($planId)
   ├─ invitado → redirect a /login
   ├─ plan gratis → crea Suscripcion(estado=aprobado, monto=0, aprobado_at=now, vence_at=null) SIN pasar por MP
   └─ plan pago → crea Suscripcion(estado=pendiente, monto=plan.precio)
                  → MercadoPagoService::crearPreferencia(plan, user, suscripcion)
                        PreferenceClient::create({
                          items[{id: slug, title: "Plan X — Descuentos Perfumes", unit_price, quantity 1}],
                          payer{email,name},
                          back_urls{success: /pago/exito, failure: /pago/error, pending: /pago/pendiente},
                          auto_return: approved,
                          notification_url: /webhook/mercadopago,
                          external_reference: (string) suscripcion.id,
                          statement_descriptor: 'Descuentos Perfumes' })
                        guarda mp_preference_id
                        devuelve sandbox_init_point si el token contiene 'TEST', si no init_point
                  → redirect()->away(checkoutUrl)

MercadoPago ── redirect del usuario ──▶ /pago/exito|pendiente|error?external_reference=…   (solo informativo)
MercadoPago ── POST /webhook/mercadopago ──▶ PagoController::webhook
        1) valida firma HMAC (x-signature, x-request-id, data.id) con MP_WEBHOOK_SECRET → 400 si inválida
              manifest = "id:{data.id};request-id:{x-request-id};ts:{ts};"  → HMAC-SHA256 == v1
              (sin secret configurado → rechaza TODO)
        2) type === 'payment' → MercadoPagoService::procesarPago($paymentId)
              PaymentClient::get → busca Suscripcion por mp_external_reference
              idempotencia (mismo payment ya aprobado → ignora)
              mapea estado MP (approved→aprobado, rejected→rechazado, cancelled→cancelado, in_process→en_proceso, pending/otros→pendiente)
              si aprobado valida que transaction_amount ≈ plan.precio (±0.01)
              actualiza mp_payment_id, estado, monto, mp_data; si aprobado: aprobado_at=now, vence_at=now+1 mes
```

* `PagoController::exito` muestra la suscripción por `external_reference` (id) filtrando por usuario autenticado si hay sesión (evita enumeración). Es **solo informativo**: la acreditación real depende del webhook.
* La ruta del webhook está excluida de CSRF. Sin webhook configurado en el panel de MP (URL pública, p. ej. vía ngrok) los pagos no se acreditan.
* Credenciales: `TEST-…` → sandbox; `APP_USR-…` → producción. Detalle paso a paso en `mercadopago.md`.

## Qué falta / no está conectado (importante)

* ~~`mp_external_reference` nunca se escribía~~ **Corregido 2026-10-29**: `crearPreferencia()` ahora guarda `mp_external_reference = suscripcion.id` junto con `mp_preference_id`, por lo que el webhook encuentra la suscripción. **Falta probar el ciclo completo en sandbox** (pago de prueba + webhook con `MP_WEBHOOK_SECRET`).
* **Los planes ahora se aplican** (2026-10-29): `User::limitesPlan()` resuelve el plan vigente (suscripción `aprobado` con `vence_at` nulo o futuro; si no hay, plan Gratis; admins sin límites). `Notificaciones::guardar()` valida `max_alertas` y `canal_telegram`; `ProcesarAlertas` respeta ambos. No hay renovación automática: es un pago mensual único (no suscripción recurrente de MP); al vencer el usuario vuelve a Gratis.
* Plan gratis: `Planes::iniciarPago` ya no duplica suscripciones; si el usuario tiene una suscripción vigente informa el estado (mensaje `info`) en lugar de crear otra.
* Hay link a `/planes` en la navbar (desktop) y en el menú móvil.
* Las vistas `pago/*.blade.php` usaban `@extends` sobre un layout que es componente Blade y fallaban con 500 (`Undefined variable $slot`); **corregidas 2026-10-29** con `<x-layouts.app activeTab="planes">` (cubierto por `tests/Feature/PagoVistasTest`).
* Test desde el admin: botón "MercadoPago" crea una suscripción Pro pendiente para el admin y redirige al checkout (ver [09](09-panel-admin-y-analytics.md)).
