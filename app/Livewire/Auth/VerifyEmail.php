<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class VerifyEmail extends Component
{
    public array $digits = ['', '', '', '', '', ''];
    public string $mensaje = '';
    public string $error = '';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: false);
            return;
        }

        if (!$user->hasValidVerificationCode()) {
            $user->sendVerificationCode();
        }
    }

    public function updatedDigits(): void
    {
        $this->error = '';
    }

    public function verificar(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: false);
            return;
        }

        // Anti fuerza bruta: máx. 5 códigos incorrectos cada 15 min (misma vida que el código)
        $key = $this->attemptsKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $min = (int) ceil(RateLimiter::availableIn($key) / 60);
            $this->error = "Demasiados intentos. Probá de nuevo en {$min} minuto(s) o solicitá un código nuevo.";
            return;
        }

        $code = implode('', $this->digits);

        if (strlen($code) < 6 || in_array('', $this->digits, true)) {
            $this->error = 'Ingresá los 6 dígitos del código.';
            return;
        }

        if (!$user->hasValidVerificationCode()) {
            $this->error = 'El código expiró. Solicitá uno nuevo.';
            return;
        }

        if (!hash_equals((string) $user->email_verification_code, $code)) {
            RateLimiter::hit($key, 900);
            $this->error = 'Código incorrecto. Revisá tu email.';
            return;
        }

        RateLimiter::clear($key);

        $user->markEmailAsVerified();
        $user->forceFill([
            'email_verification_code'            => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function reenviar(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: false);
            return;
        }

        // Anti spam de mails: máx. 3 reenvíos cada 10 minutos
        $resendKey = 'verify-resend:' . $user->id;
        if (RateLimiter::tooManyAttempts($resendKey, 3)) {
            $seg = RateLimiter::availableIn($resendKey);
            $this->error = "Ya reenviamos varios códigos. Esperá {$seg} segundos para pedir otro.";
            return;
        }
        RateLimiter::hit($resendKey, 600);

        $user->sendVerificationCode();
        RateLimiter::clear($this->attemptsKey()); // un código nuevo reinicia los intentos
        $this->digits = ['', '', '', '', '', ''];
        $this->error = '';
        $this->mensaje = 'Nuevo código enviado. Revisá tu bandeja de entrada.';
    }

    private function attemptsKey(): string
    {
        return 'verify-email:' . auth()->id();
    }

    public function checkVerified(): void
    {
        if (auth()->user()?->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: false);
        }
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}
