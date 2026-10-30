<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * productos.disciplina: deporte/disciplina del producto (Nike "Disciplina Extendida": Fútbol, Running, Moda,
 * Básquet, Tenis y Pádel…). Sirve para afinar el calzado, cuyo "tipo" es solo Calzado/Botines.
 * `productos` la crea el scraper, no Laravel: se ignora si no existe o si el driver no es MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('productos') || Schema::hasColumn('productos', 'disciplina')) {
            return;
        }
        DB::statement('ALTER TABLE productos ADD COLUMN disciplina VARCHAR(60) NULL AFTER tipo');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('productos') && Schema::hasColumn('productos', 'disciplina')) {
            DB::statement('ALTER TABLE productos DROP COLUMN disciplina');
        }
    }
};
