<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * corridas.estado: el scraper de ropa guarda tienda por tienda, así que una corrida puede estar a medias.
 *
 *  - 'en_curso'   : el scraper todavía está cargando tiendas (o se cortó antes de terminar).
 *  - 'incompleta' : terminó pero no pasó el control de calidad (demasiadas secciones bloqueadas/con error).
 *  - 'completa'   : válida. Es la única que lee el sitio (Corrida::completas / ultima) y ProcesarAlertas.
 *
 * DEFAULT 'completa': las corridas existentes y las que inserta descuentos_master.py (perfumes, sin cambios) quedan válidas.
 * La tabla la crea el scraper, no Laravel: se ignora si no existe (p. ej. en tests con sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('corridas') && ! Schema::hasColumn('corridas', 'estado')) {
            DB::statement("ALTER TABLE corridas ADD COLUMN estado VARCHAR(12) NOT NULL DEFAULT 'completa' AFTER rubro");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('corridas') && Schema::hasColumn('corridas', 'estado')) {
            DB::statement('ALTER TABLE corridas DROP COLUMN estado');
        }
    }
};
