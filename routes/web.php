<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login',    \App\Livewire\Auth\Login::class)->name('login');
    Route::get('/register', \App\Livewire\Auth\Register::class)->name('register');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/joyas');
})->name('logout')->middleware('auth');

// Raíz → redirige a la pestaña por defecto
Route::get('/', fn() => redirect('/joyas'))->name('dashboard');

// Rutas de pestañas del dashboard
Route::get('/{tab}', fn($tab) => view('dashboard', ['activeTab' => $tab]))
    ->where('tab', 'joyas|tabla|cambios|comparar|seguir|tiendas|distribucion|evolucion|transfer|marcas|conclusiones')
    ->name('tab');
