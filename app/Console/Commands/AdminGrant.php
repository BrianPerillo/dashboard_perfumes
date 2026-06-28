<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminGrant extends Command
{
    protected $signature   = 'admin:grant {email : Email del usuario a promover}';
    protected $description = 'Otorga (o quita) el rol admin a un usuario';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No existe un usuario con email: {$email}");
            return self::FAILURE;
        }

        $user->update(['is_admin' => !$user->is_admin]);

        $estado = $user->is_admin ? 'ADMIN' : 'usuario normal';
        $this->info("✓ {$user->name} ({$email}) → {$estado}");

        return self::SUCCESS;
    }
}
