<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $table = 'observaciones';
    public $timestamps = false;

    protected $casts = [
        'precio_lista'         => 'float',
        'precio_actual'        => 'float',
        'descuento_pct'        => 'float',
        'ahorro'               => 'float',
        'precio_transferencia' => 'float',
    ];

    public function corrida()
    {
        return $this->belongsTo(Corrida::class, 'corrida_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /* Scope: solo rebajas reales (excluye transferencia y fuentes falsas) */
    public function scopeRebajas($query)
    {
        return $query->where('tipo_descuento', 'rebaja')
                     ->whereNotIn('fuente', ['dos_precios', 'badge_%']);
    }

    /* Scope: de una corrida específica */
    public function scopeDeCorrida($query, int $corridaId)
    {
        return $query->where('corrida_id', $corridaId);
    }
}
