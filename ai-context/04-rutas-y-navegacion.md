# 04 · Rutas y navegación

Fuente: `routes/web.php` (único archivo de rutas) y `bootstrap/app.php`. `routes/console.php` solo tiene el comando `inspire` de ejemplo.

## Tabla de rutas

| Método | URL | Handler | Middleware | Nombre | Descripción |
|---|---|---|---|---|---|
| GET | `/login` | `Livewire\Auth\Login` | guest | `login` | Formulario de login |
| GET | `/register` | `Livewire\Auth\Register` | guest | `register` | Registro |
| GET | `/forgot-password` | `Livewire\Auth\ForgotPassword` | guest | `password.request` | Pedir link de reset |
| GET | `/reset-password/{token}` | `Livewire\Auth\ResetPassword` | guest | `password.reset` | Nueva contraseña |
| GET | `/email/verify` | `Livewire\Auth\VerifyEmail` | auth | `verification.notice` | Pantalla "ingresá el código de 6 dígitos" |
| GET | `/email/verify/{id}/{hash}` | closure (`EmailVerificationRequest::fulfill`) | auth, signed | `verification.verify` | Verificación por link firmado de Laravel. **Legado**: el flujo actual usa código, pero la ruta sigue activa |
| POST | `/email/resend` | closure | auth, throttle:6,1 | `verification.send` | Reenvía la notificación nativa de Laravel (link). **Legado**, no usada por la UI actual |
| POST | `/logout` | closure | auth | `logout` | Cierra sesión → redirige a `/joyas` |
| GET | `/` | closure → `redirect('/joyas')` | — | `dashboard` | Raíz. **Ojo:** `route('dashboard')` apunta a esta redirección y se usa como "home" tras login/verificación |
| GET | `/notificaciones` | `Livewire\Notificaciones` | — (auth se controla dentro del componente/vista) | `notificaciones` | Gestión de alertas |
| GET | `/planes` | `Livewire\Planes` | — | `planes` | Cards de planes (botón '💳 Planes' en la navbar y en el menú móvil) |
| GET | `/pago/exito` | `PagoController@exito` | — | `pago.exito` | Retorno MP aprobado |
| GET | `/pago/pendiente` | `PagoController@pendiente` | — | `pago.pendiente` | Retorno MP pendiente |
| GET | `/pago/error` | `PagoController@error` | — | `pago.error` | Retorno MP rechazado |
| POST | `/webhook/mercadopago` | `PagoController@webhook` | sin CSRF (`withoutMiddleware(VerifyCsrfToken)`) | `webhook.mercadopago` | IPN/webhook MP con validación HMAC |
| GET | `/admin` | `Livewire\Admin\Dashboard` | auth, **verified**, admin | `admin.dashboard` | Panel de administración |
| GET | `/{tab}` | closure → `view('dashboard', ['activeTab' => $tab])` | — | `tab` | Dashboard. `{tab}` restringido por regex a: `joyas\|tabla\|cambios\|comparar\|seguir\|tiendas\|distribucion\|evolucion\|transfer\|marcas\|conclusiones` |
| GET | `/up` | health check Laravel | — | — | Definido en `bootstrap/app.php` |

> La ruta comodín `/{tab}` va **al final** para no pisar las demás. Si se agrega una ruta nueva de primer nivel, debe declararse antes.

## Menú de navegación (qué ve el usuario)

Definido en `resources/views/components/layouts/app.blade.php`. **Hay tres listas de pestañas independientes** (cada una es un array `@foreach` con ítems comentados con `{{-- --}}`); al mostrar/ocultar una sección hay que revisar las tres:

| Ruta | Sidebar (desktop, ~l.246) | Barra superior (desktop, ~l.366) | Barra de secciones móvil (~l.509) |
|---|---|---|---|
| `/joyas` | 💎 Destacados | Destacados | Destacados |
| `/tabla` | 📋 Tabla completa | Tabla | Tabla |
| `/cambios` | 🔁 Cambios recientes | Cambios | Cambios |
| `/distribucion` | — | Distribución | Distribución |
| `/evolucion` | 📈 Evolución | Evolución | Evolución |
| `/transfer` | — | Transferencia | Transferencia |
| `/marcas` | — (comentada) | Marcas | Marcas |
| `/comparar`, `/seguir`, `/tiendas`, `/conclusiones` | — (comentadas) | — (comentadas) | — (comentadas) |

Las secciones comentadas **no aparecen en ningún menú, pero sus rutas y vistas siguen funcionando** si se entra por URL.

Otros enlaces del layout:
* 🔥 **Alertas** → `/notificaciones` (visible para todos).
* 🛡️ **Admin** → `/admin` (solo si `is_admin` y email verificado).
* Login / Registro (invitados), Logout (autenticados), "✉ Verificar email" (autenticado sin verificar).
* Toggle de tema claro/oscuro (persistido en `localStorage.dark`; por defecto oscuro).
* 💳 **Planes** → `/planes` (navbar desktop y menú móvil; agregado 2026-10-29).

## Navegación entre pestañas

Los links del sidebar son `<a href="/{tab}">` con `location.href` → **cada pestaña es una carga de página completa** (se re-monta el componente Livewire `Dashboard` con `activeTab`). Los filtros globales se restauran desde sesión (`dashboard_filtros`). `@entangle('tabActiva')` permite cambiar `tab` del lado Alpine y dispara `updatedTabActiva()` → carga lazy de datos.

## Redirecciones relevantes

* Login OK: si el email no está verificado → `verification.notice`; si sí → `session('url.intended')` o `route('dashboard')` (→ `/joyas`).
* Registro OK → `verification.notice`.
* Verificación OK → `route('dashboard')`.
* Reset de contraseña OK → `route('dashboard')` (y queda logueado).
* Logout → `/joyas`.

## Multi-rubro (desde 2026-10-29)
Las rutas de pestañas se generan por rubro desde `config/rubros.php`: perfumes conserva `/{tab}` (sin prefijo); ropa agrega `/ropa` (→ `/ropa/tabla`) y `/ropa/{tab}` (`ropa.tab`). Las **listas de menú ya no están triplicadas**: sidebar, navbar y barra móvil salen de la config (`tabs`), y hay un selector de rubro (🧴 / 👕) en el sidebar y en la barra móvil. Ver [14](14-multirubro.md).

## Menú lateral = filtros de la página (desde 2026-10-30)
El sidebar ya **no lista pestañas** ni lleva logo: la navegación está en el header (barra superior en escritorio, barra de secciones y selector de rubro en móvil) y el sidebar muestra solo los **filtros de la página actual** (más selector de rubro, cuenta y tema). La marca "Descuentos ARG" con el icono del rubro se muestra en el header (escritorio ≥ xl y móvil). Las tablas de menús de arriba aplican ahora solo a la **barra superior** y a la **barra móvil**.
