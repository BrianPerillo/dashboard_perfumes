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

    public static function ultima(): ?self
    {
        return static::orderByDesc('fecha_hora')->first();
    }
}
