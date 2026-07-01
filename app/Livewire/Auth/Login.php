<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    #[Rule('required|string')]
    public string $email = '';

    #[Rule('required|string|min:6')]
    public string $password = '';

    public bool $remember = false;

    public ?string $error = null;

    public function submit(): void
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->error = 'Email o contraseña incorrectos.';
            return;
        }

        session()->regenerate();

        // Si el email no está verificado, redirigir al aviso de verificación
        if (!Auth::user()->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: false);
            return;
        }

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
