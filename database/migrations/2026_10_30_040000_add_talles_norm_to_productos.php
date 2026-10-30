<?php

use App\Support\Talles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * productos.talles_norm: talles normalizados para filtrar/alertar (ver App\Support\Talles). En calzado interpreta
 * el número según el género (H = talle hombre, M = talle mujer) y parte el formato "H 10 / M 11.5". Ropa y
 * accesorios quedan igual que `talles`. El scraper la mantiene desde ahora; acá se rellenan los productos existentes.
 * `productos` la crea el scraper: se ignora si no existe o si el driver no es MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('productos') || Schema::hasColumn('productos', 'talles_norm')) {
            return;
        }

        DB::statement('ALTER TABLE productos ADD COLUMN talles_norm VARCHAR(255) NULL AFTER talles');

        DB::table('productos')->whereNotNull('talles')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('productos')->where('id', $r->id)->update([
                    'talles_norm' => Talles::normalizar($r->genero, $r->categoria, $r->talles) ?: null,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('productos') && Schema::hasColumn('productos', 'talles_norm')) {
            DB::statement('ALTER TABLE productos DROP COLUMN talles_norm');
        }
    }
};
