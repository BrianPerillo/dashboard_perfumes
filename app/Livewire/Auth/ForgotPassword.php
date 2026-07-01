<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class ForgotPassword extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    public string $mensaje    = '';
    public string $tipoMensaje = '';
    public bool   $enviado    = false;

    public function submit(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->enviado    = true;
            $this->mensaje    = 'Te enviamos el enlace para restablecer tu contraseña. Revisá tu email.';
            $this->tipoMensaje = 'success';
        } else {
            // No revelar si el email existe o no por seguridad
            $this->enviado    = true;
            $this->mensaje    = 'Si ese email está registrado, recibirás el enlace en breve.';
            $this->tipoMensaje = 'success';
        }
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
