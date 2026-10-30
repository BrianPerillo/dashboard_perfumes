<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $table = 'observaciones';
    public $timestamps = false;

    /** Marcas que no son perfumes y se ocultan en dashboard, KPIs y alertas (en minúsculas). */
    public const MARCAS_EXCLUIDAS = ['rimmel'];

    /** Fuentes de descuento no confiables (falsos descuentos); `badge_` es un prefijo (LIKE 'badge_%'). */
    public const FUENTES_EXCLUIDAS = ['dos_precios'];
    public const PREFIJO_FUENTE_EXCLUIDO = 'badge_';

    /**
     * Excluye observaciones con fuente no confiable (`dos_precios`, `badge_*`).
     * Las filas con fuente NULL se consideran válidas.
     * Acepta Query\Builder o Eloquent\Builder; $col debe incluir el alias si hay joins (ej. 'o.fuente').
     */
    public static function filtrarFuentesValidas($query, string $col = 'fuente')
    {
        return $query->where(function ($q) use ($col) {
            $q->whereNull($col)->orWhere(function ($q2) use ($col) {
                $q2->whereNotIn($col, self::FUENTES_EXCLUIDAS)
                   ->where($col, 'not like', self::PREFIJO_FUENTE_EXCLUIDO . '%');
            });
        });
    }

    /** Excluye marcas que no son perfumes (conserva productos sin marca). $col = columna de marca (ej. 'p.marca'). */
    public static function excluirMarcas($query, string $col = 'p.marca')
    {
        return $query->where(function ($q) use ($col) {
            $q->whereNull($col)->orWhereNotIn(\Illuminate\Support\Facades\DB::raw("LOWER($col)"), self::MARCAS_EXCLUIDAS);
        });
    }

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
        $query->where('tipo_descuento', 'rebaja');

        return self::filtrarFuentesValidas($query, 'fuente');
    }

    /* Scope: de una corrida específica */
    public function scopeDeCorrida($query, int $corridaId)
    {
        return $query->where('corrida_id', $corridaId);
    }
}
