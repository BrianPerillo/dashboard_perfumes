<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    protected $table = 'favoritos';

    protected $fillable = ['usuario_id', 'tipo', 'nombre', 'config'];

    public $timestamps = false;

    protected $casts = ['config' => 'array'];

    public static function listar(int $userId, string $tipo = 'tabla'): array
    {
        return static::where('usuario_id', $userId)
            ->where('tipo', $tipo)
            ->orderBy('nombre')
            ->get()
            ->pluck('config', 'nombre')
            ->toArray();
    }

    public static function guardar(int $userId, string $nombre, array $config, string $tipo = 'tabla'): void
    {
        $fav = static::firstOrNew(['usuario_id' => $userId, 'tipo' => $tipo, 'nombre' => $nombre]);
        if (!$fav->exists) {
            $fav->creado = now();
        }
        $fav->config     = $config;
        $fav->actualizado = now();
        $fav->save();
    }

    public static function borrar(int $userId, string $nombre, string $tipo = 'tabla'): void
    {
        static::where('usuario_id', $userId)
            ->where('tipo', $tipo)
            ->where('nombre', $nombre)
            ->delete();
    }
}
