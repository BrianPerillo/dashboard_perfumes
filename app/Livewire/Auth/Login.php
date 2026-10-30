<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|string')]
    public string $password = '';

    public bool $remember = false;

    public ?string $error = null;

    private function throttleKey(): string
    {
        return Str::lower($this->email) . '|' . request()->ip();
    }

    public function submit(): void
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('login-failed');
            throw $e;
        }

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->error = "Demasiados intentos. Intentá de nuevo en {$seconds} segundos.";
            $this->dispatch('login-failed');
            return;
        }

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->error = 'Email o contraseña incorrectos.';
            $this->dispatch('login-failed');
            return;
        }

        RateLimiter::clear($key);

        session()->regenerate();

        // Si el email no está verificado, redirigir al aviso de verificación
        if (!Auth::user()->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: false);
            return;
        }

        // Si vino desde el link de verificación u otra URL, redirigir allí
        $this->redirect(session()->pull('url.intended', route('dashboard')), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
