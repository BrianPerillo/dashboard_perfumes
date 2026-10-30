<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tienda extends Model
{
    protected $table = 'tiendas';
    public $timestamps = false;

    protected $fillable = ['nombre', 'rubro'];

    public function scopeDeRubro($query, string $rubro)
    {
        return $query->where('rubro', $rubro);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'tienda_id');
    }
}
