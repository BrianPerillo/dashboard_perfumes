<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorridaSitio extends Model
{
    protected $table = 'corrida_sitios';
    public $timestamps = false;

    protected $casts = [
        'total_encontrados' => 'integer',
        'con_descuento'     => 'integer',
        'segundos'          => 'float',
    ];

    public function corrida()
    {
        return $this->belongsTo(Corrida::class, 'corrida_id');
    }
}
