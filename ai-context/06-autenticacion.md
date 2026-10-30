# 06 · Autenticación y cuentas de usuario

Auth **custom con Livewire** (sin Breeze/Jetstream/Fortify). Componentes en `app/Livewire/Auth/`, vistas en `resources/views/livewire/auth/`, layout `components.layouts.auth` (`#[Layout('components.layouts.auth')]`).

## Modelo `User` (`app/Models/User.php`)

* `implements MustVerifyEmail` (+ `HasFactory`, `Notifiable`).
* `$fillable`: `name`, `email`, `password`. Ocultos: `password`, `remember_token`.
* Casts: `email_verified_at` datetime, `password` hashed, `is_admin` boolean, `email_verification_code_expires_at` datetime.
* Columnas extra por migraciones: `is_admin` (bool, default false, `2026_06_28_000001`), `email_verification_code` (string 6) y `email_verification_code_expires_at` (timestamp) (`2026_07_03_030214`).
* Métodos propios:
  * `sendVerificationCode()` → genera código aleatorio de 6 dígitos (`random_int(0,999999)` con ceros a la izquierda), lo guarda con vencimiento **15 min** (usa `forceFill`, ya que no están en `$fillable`) y envía `Mail::to($this->email)->send(new VerificacionCodigo($code))`.
  * `hasValidVerificationCode()` → código no nulo y no vencido.
  * `isAdmin()`.
  * `alertas()` → `HasMany`; `suscripciones()` → `HasMany`; `suscripcionActiva()` (aprobada y no vencida, la más reciente); `limitesPlan()` → `['max_alertas','telegram','plan']` según el plan vigente (sin suscripción vigente = plan Gratis; los **admins no tienen límites**). Lo usan `Notificaciones` y `ProcesarAlertas` (ver [08](08-planes-y-pagos.md)).

## Registro (`/register` · `Auth\Register`)

1. Validación: `name` requerido ≤60; `email` requerido, válido, ≤120, único en `users`; `password` requerido, min 8, `confirmed`.
2. `User::create` (email en minúsculas y trim; password hasheado con `Hash::make`; el cast `hashed` no lo duplica).
3. `$user->sendVerificationCode()` (email con código de 6 dígitos, vista `emails/verificacion_codigo.blade.php`, Mailable `App\Mail\VerificacionCodigo`).
4. `Auth::login($user, remember: true)` + `session()->regenerate()`.
5. Redirige a `/email/verify`.

## Verificación de email por código (`/email/verify` · `Auth\VerifyEmail`)

* Requiere sesión (`auth`). En `mount()`: si ya está verificado → dashboard; si no tiene código vigente → genera y envía uno nuevo.
* UI: 6 casillas (`digits[]`), acciones:
  * `verificar()` → valida que estén los 6 dígitos, que el código no haya vencido y que coincida con `email_verification_code`; luego `markEmailAsVerified()` y limpia código + vencimiento; redirige al dashboard.
  * `reenviar()` → nuevo código, limpia casillas, mensaje "Nuevo código enviado…".
  * `checkVerified()` → polling (si se verificó en otra pestaña, redirige).
* **Anti fuerza bruta** (agregado 2026-10-29): máx. **5 códigos incorrectos** por usuario cada 15 min (`RateLimiter`, clave `verify-email:{id}`, comparación con `hash_equals`); un código nuevo reinicia los intentos. Reenvío limitado a **3 por 10 min** (`verify-resend:{id}`). El throttle `6,1` de la ruta legada `/email/resend` ya no se usa en la UI.
* Las rutas del flujo **anterior** (link firmado por Laravel: `verification.verify`, `verification.send`) siguen definidas pero la UI actual no las usa.

## Login (`/login` · `Auth\Login`)

* Validación: `email` requerido y con formato email, `password` requerido (sin mínimo, para no bloquear contraseñas viejas más cortas que los 8 del registro); `remember` checkbox.
* **Rate limiting**: `RateLimiter` con clave `lower(email)|ip`; máximo **5 intentos**, ventana de 60 s por fallo; mensaje "Demasiados intentos. Intentá de nuevo en N segundos."
* Falla → `error = 'Email o contraseña incorrectos.'` y evento Livewire `login-failed` (la vista lo usa para animar/shake).
* Éxito → `RateLimiter::clear`, `session()->regenerate()`; si el email no está verificado → `/email/verify`; si sí → `session()->pull('url.intended', route('dashboard'))`.
* Nota: `trustProxies('*')` en `bootstrap/app.php` hace que el rate limiting por IP sea falsificable vía `X-Forwarded-For` (ver checklist de producción).

## Recuperar contraseña

* `/forgot-password` (`Auth\ForgotPassword`): valida `email`, llama `Password::sendResetLink`. **Siempre** muestra el mismo mensaje de éxito (no revela si el email existe).
* `/reset-password/{token}` (`Auth\ResetPassword`): recibe `token` (ruta) y `email` (query, `#[Url]`); valida password min 8 `confirmed`; `Password::reset(...)` actualiza password, regenera `remember_token`, dispara `PasswordReset`, hace `Auth::login($user)` y redirige a `route('dashboard')`.
* Usa la tabla `password_reset_tokens` de la migración base y la notificación nativa de Laravel (mail con link de 60 min). El mail se envía con el mailer configurado (`smtp`/`log`).

## Logout

`POST /logout` (closure): `Auth::logout()`, invalida sesión, regenera token CSRF, redirige a `/joyas`. El layout lo expone como formulario.

## Administradores

* Campo `users.is_admin`.
* Middleware `AdminMiddleware` (alias `admin`): invitado → `login`; no admin → 403.
* Dar/quitar admin:
  * CLI: `php artisan admin:grant {email}` (`app/Console/Commands/AdminGrant.php`) — **alterna** (toggle) el rol; informa "ADMIN" o "usuario normal".
  * UI: panel `/admin` → tabla de usuarios → `toggleAdmin($userId)`; no permite quitarse el rol a uno mismo.
* El link 'Admin' del layout aparece solo si el usuario es admin **y** tiene el email verificado; la ruta `/admin` ahora también exige `verified` (middleware `auth`, `verified`, `admin`).

## Sesiones

`SESSION_DRIVER=file` (local). La migración base también crea la tabla `sessions` por si se cambia a `database`. Sesión usada también para guardar filtros del dashboard (`dashboard_filtros`).

## Mapa de archivos

| Archivo | Rol |
|---|---|
| `app/Livewire/Auth/{Login,Register,VerifyEmail,ForgotPassword,ResetPassword}.php` | Lógica |
| `resources/views/livewire/auth/*.blade.php` | Vistas |
| `resources/views/components/layouts/auth.blade.php` | Layout de auth |
| `app/Mail/VerificacionCodigo.php` + `resources/views/emails/verificacion_codigo.blade.php` | Mail del código |
| `app/Models/User.php` | Código, verificación, `isAdmin` |
| `app/Http/Middleware/AdminMiddleware.php` | Protección admin |
| `app/Console/Commands/AdminGrant.php` | CLI admin |
