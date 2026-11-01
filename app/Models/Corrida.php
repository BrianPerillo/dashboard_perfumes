<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corrida extends Model
{
    protected $table = 'corridas';
    public $timestamps = false;

    protected $casts = [
        'fecha_hora'       => 'datetime',
        'total_productos'  => 'integer',
        'min_descuento'    => 'float',
        'max_descuento'    => 'float',
    ];

    public function sitios()
    {
        return $this->hasMany(CorridaSitio::class, 'corrida_id');
    }

    public function observaciones()
    {
        return $this->hasMany(Observacion::class, 'corrida_id');
    }

    /** Corridas de un rubro (perfumes, ropa...). Cada rubro tiene sus propias corridas/snapshots. */
    public function scopeDeRubro($query, string $rubro)
    {
        return $query->where('rubro', $rubro);
    }

    /** Solo corridas válidas: el scraper de ropa guarda tienda por tienda y deja la corrida 'en_curso' hasta cerrarla (ver migración estado). */
    public function scopeCompletas($query)
    {
        return $query->where('estado', 'completa');
    }

    public static function ultima(string $rubro = 'perfumes'): ?self
    {
        return static::deRubro($rubro)->completas()->orderByDesc('fecha_hora')->first();
    }
}
