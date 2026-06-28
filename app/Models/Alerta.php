<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'valor',
        'canal',
        'email_destino',
        'telegram_chat_id',
        'activo',
        'ultima_notificacion_at',
    ];

    protected $casts = [
        'activo'                  => 'boolean',
        'ultima_notificacion_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            'marca'    => 'Marca',
            'tienda'   => 'Tienda',
            'producto' => 'Perfume',
            default    => $this->tipo,
        };
    }

    public function canalLabel(): string
    {
        return match ($this->canal) {
            'email'    => 'Email',
            'telegram' => 'Telegram',
            default    => $this->canal,
        };
    }
}
