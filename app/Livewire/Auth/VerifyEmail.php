<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class VerifyEmail extends Component
{
    public string $mensaje = '';

    public function reenviar(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: false);
            return;
        }

        $user->sendEmailVerificationNotification();
        $this->mensaje = 'Nuevo enlace enviado. Revisá tu bandeja de entrada.';
    }

    public function render()
    {
        if (auth()->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('livewire.auth.verify-email');
    }
}
