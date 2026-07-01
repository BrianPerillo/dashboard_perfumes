<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suscripcion extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'estado',
        'mp_preference_id', 'mp_payment_id', 'mp_external_reference',
        'monto', 'moneda', 'aprobado_at', 'vence_at', 'mp_data',
    ];

    protected $casts = [
        'monto'       => 'decimal:2',
        'aprobado_at' => 'datetime',
        'vence_at'    => 'datetime',
        'mp_data'     => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function estaActiva(): bool
    {
        if ($this->estado !== 'aprobado') return false;
        if ($this->vence_at && $this->vence_at->isPast()) return false;
        return true;
    }
}
