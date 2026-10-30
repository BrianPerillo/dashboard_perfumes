<?php

use App\Http\Controllers\PagoController;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login',    \App\Livewire\Auth\Login::class)->name('login');
    Route::get('/register', \App\Livewire\Auth\Register::class)->name('register');

    // Recuperar contraseña
    Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)->name('password.reset');
});

// Verificación de email
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', \App\Livewire\Auth\VerifyEmail::class)->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('dashboard');
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/resend', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back();
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/joyas');
})->name('logout')->middleware('auth');

// Raíz → redirige a la pestaña por defecto
Route::get('/', fn() => redirect('/joyas'))->name('dashboard');

// Notificaciones (accesible a todos, auth controlado en el componente)
Route::get('/notificaciones', \App\Livewire\Notificaciones::class)->name('notificaciones');

// Planes y pagos
Route::get('/planes', \App\Livewire\Planes::class)->name('planes');
Route::get('/pago/exito',     [PagoController::class, 'exito'])->name('pago.exito');
Route::get('/pago/pendiente', [PagoController::class, 'pendiente'])->name('pago.pendiente');
Route::get('/pago/error',     [PagoController::class, 'error'])->name('pago.error');

// Webhook MP — sin CSRF (MP no envía token)
Route::post('/webhook/mercadopago', [PagoController::class, 'webhook'])
    ->name('webhook.mercadopago')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Admin (requiere auth + is_admin)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
});

// Rutas de pestañas del dashboard, por rubro (config/rubros.php).
// Perfumes (rubro por defecto) conserva las URLs sin prefijo (/joyas, /tabla...); el resto va con prefijo (/ropa/tabla).
foreach (config('rubros.rubros') as $slug => $cfg) {
    if ($cfg['prefijo']) {
        Route::redirect('/' . $cfg['prefijo'], '/' . $cfg['prefijo'] . '/' . $cfg['tab_inicial']);
        Route::get('/' . $cfg['prefijo'] . '/{tab}', fn($tab) => view('dashboard', ['activeTab' => $tab, 'rubro' => $slug]))
            ->where('tab', implode('|', $cfg['tabs_rutas']))
            ->name($slug . '.tab');
    }
}

// Rubro por defecto (perfumes): debe ir al final porque /{tab} captura cualquier primer segmento válido
Route::get('/{tab}', fn($tab) => view('dashboard', ['activeTab' => $tab, 'rubro' => config('rubros.default')]))
    ->where('tab', implode('|', config('rubros.rubros.' . config('rubros.default') . '.tabs_rutas')))
    ->name('tab');
