<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Alerta;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'                  => 'datetime',
            'password'                           => 'hashed',
            'is_admin'                           => 'boolean',
            'email_verification_code_expires_at' => 'datetime',
        ];
    }

    public function sendVerificationCode(): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_verification_code'            => $code,
            'email_verification_code_expires_at' => now()->addMinutes(15),
        ])->save();

        \Mail::to($this->email)->send(new \App\Mail\VerificacionCodigo($code));
    }

    public function hasValidVerificationCode(): bool
    {
        return $this->email_verification_code !== null
            && $this->email_verification_code_expires_at !== null
            && $this->email_verification_code_expires_at->isFuture();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    /**
     * Suscripción aprobada y vigente más reciente (null = usa el plan Gratis implícito).
     */
    public function suscripcionActiva(): ?Suscripcion
    {
        return $this->suscripciones()
            ->where('estado', 'aprobado')
            ->where(fn($q) => $q->whereNull('vence_at')->orWhere('vence_at', '>', now()))
            ->with('plan')
            ->latest('aprobado_at')
            ->latest('id')
            ->first();
    }

    /**
     * Límites efectivos según el plan vigente: ['max_alertas' => int (-1 = ilimitadas), 'telegram' => bool].
     * Los admins no tienen límites. Sin suscripción vigente aplica el plan Gratis.
     */
    public function limitesPlan(): array
    {
        if ($this->isAdmin()) {
            return ['max_alertas' => -1, 'telegram' => true, 'plan' => 'Admin'];
        }

        $plan = $this->suscripcionActiva()?->plan
            ?? Plan::where('slug', 'gratis')->first();

        return [
            'max_alertas' => $plan?->max_alertas ?? 1,
            'telegram'    => (bool) ($plan?->canal_telegram ?? false),
            'plan'        => $plan?->nombre ?? 'Gratis',
        ];
    }
}
