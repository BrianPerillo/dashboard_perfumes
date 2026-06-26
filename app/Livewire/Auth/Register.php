<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Register extends Component
{
    #[Rule('required|string|max:60')]
    public string $name = '';

    #[Rule('required|email|max:120|unique:users,email')]
    public string $email = '';

    #[Rule('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function submit(): void
    {
        $this->validate();

        $user = User::create([
            'name'     => trim($this->name),
            'email'    => mb_strtolower(trim($this->email)),
            'password' => Hash::make($this->password),
        ]);

        Auth::login($user, remember: true);
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
