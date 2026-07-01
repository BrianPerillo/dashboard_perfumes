<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'slug', 'nombre', 'precio', 'moneda',
        'max_alertas', 'canal_telegram', 'features', 'activo',
    ];

    protected $casts = [
        'precio'         => 'decimal:2',
        'canal_telegram' => 'boolean',
        'activo'         => 'boolean',
        'features'       => 'array',
        'max_alertas'    => 'integer',
    ];

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function esGratis(): bool
    {
        return $this->precio == 0;
    }

    public function alertasIlimitadas(): bool
    {
        return $this->max_alertas === -1;
    }

    public function precioFormateado(): string
    {
        if ($this->precio == 0) return 'Gratis';
        return '$' . number_format($this->precio, 0, ',', '.') . '/mes';
    }

    public static function gratis(): self
    {
        return static::where('slug', 'gratis')->firstOrFail();
    }
}
