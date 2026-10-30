<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * productos.linea: línea de producto (Nike "Icono": Air Max, Air Force 1, Mercurial, Phantom, Dunk, Jordan…).
 * Afina el calzado, cuyo "tipo" es solo Calzado/Botines. `productos` la crea el scraper: se ignora si no existe
 * o si el driver no es MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('productos') || Schema::hasColumn('productos', 'linea')) {
            return;
        }
        DB::statement('ALTER TABLE productos ADD COLUMN linea VARCHAR(80) NULL AFTER disciplina');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('productos') && Schema::hasColumn('productos', 'linea')) {
            DB::statement('ALTER TABLE productos DROP COLUMN linea');
        }
    }
};
