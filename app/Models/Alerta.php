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
        'prueba_enviada',
        'solo_mejoras',
        'tamano_ml',
        'marca_filtro',
        'rubro',
        'talles',
        'descuento_min',
    ];

    protected $casts = [
        'activo'                  => 'boolean',
        'prueba_enviada'          => 'boolean',
        'solo_mejoras'            => 'boolean',
        'ultima_notificacion_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Escapa los caracteres especiales del Markdown "legacy" de Telegram
     * (_ * ` [) para que un nombre de producto no rompa el mensaje.
     */
    public static function escaparMarkdown(string $texto): string
    {
        return preg_replace_callback('/[_*`\[]/', fn($m) => '\\' . $m[0], $texto);
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            'marca'              => 'Marca',
            'tienda'             => 'Tienda',
            'producto', 'produto' => 'Perfume',
            default              => $this->tipo,
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
