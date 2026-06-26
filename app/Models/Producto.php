<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    public $timestamps = false;

    protected $casts = [
        'primera_vez' => 'datetime',
        'ultima_vez'  => 'datetime',
        'tamano_ml'   => 'integer',
    ];

    public function tienda()
    {
        return $this->belongsTo(Tienda::class, 'tienda_id');
    }

    public function observaciones()
    {
        return $this->hasMany(Observacion::class, 'producto_id');
    }
}
