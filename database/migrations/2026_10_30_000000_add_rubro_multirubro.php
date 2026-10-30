<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-rubro: el sitio pasa de "solo perfumes" a perfumes + ropa (y los que vengan).
 *
 * Las tablas de scraping (tiendas, productos, corridas, observaciones) son compartidas; cada fila pertenece
 * a un rubro. Los datos existentes quedan como 'perfumes' (DEFAULT), por lo que el scraper de perfumes
 * (descuentos_master.py) sigue funcionando sin cambios.
 *
 *  - tiendas.rubro, corridas.rubro, alertas.rubro  (default 'perfumes')
 *  - corridas: la unicidad pasa de (fecha_hora) a (rubro, fecha_hora)
 *  - productos: columnas opcionales para ropa (categoria, tipo, color, talles, referencia, imagen)
 *  - productos.genero: se agrega 'ninos' al enum (ropa: hombre->masculino, mujer->femenino, ninos, unisex)
 *
 * Esas tablas las crea el scraper, no Laravel: se ignora todo si no existen (p. ej. en tests con sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('tiendas') && !Schema::hasColumn('tiendas', 'rubro')) {
            DB::statement("ALTER TABLE tiendas ADD COLUMN rubro VARCHAR(20) NOT NULL DEFAULT 'perfumes' AFTER nombre, ADD INDEX idx_tienda_rubro (rubro)");
        }

        if (Schema::hasTable('corridas') && !Schema::hasColumn('corridas', 'rubro')) {
            DB::statement("ALTER TABLE corridas ADD COLUMN rubro VARCHAR(20) NOT NULL DEFAULT 'perfumes' AFTER id");
            DB::statement('ALTER TABLE corridas DROP INDEX uq_corrida_fecha, ADD UNIQUE KEY uq_corrida_rubro_fecha (rubro, fecha_hora)');
        }

        if (Schema::hasTable('productos') && !Schema::hasColumn('productos', 'categoria')) {
            DB::statement(
                'ALTER TABLE productos '
                . 'ADD COLUMN categoria VARCHAR(40) NULL AFTER presentacion, '
                . 'ADD COLUMN tipo VARCHAR(80) NULL AFTER categoria, '
                . 'ADD COLUMN color VARCHAR(80) NULL AFTER tipo, '
                . 'ADD COLUMN talles VARCHAR(255) NULL AFTER color, '
                . 'ADD COLUMN referencia VARCHAR(80) NULL AFTER talles, '
                . 'ADD COLUMN imagen VARCHAR(600) NULL AFTER referencia'
            );
            DB::statement("ALTER TABLE productos MODIFY genero ENUM('masculino','femenino','unisex','ninos') NOT NULL DEFAULT 'masculino'");
        }

        if (Schema::hasTable('alertas') && !Schema::hasColumn('alertas', 'rubro')) {
            DB::statement("ALTER TABLE alertas ADD COLUMN rubro VARCHAR(20) NOT NULL DEFAULT 'perfumes' AFTER user_id");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('alertas') && Schema::hasColumn('alertas', 'rubro')) {
            DB::statement('ALTER TABLE alertas DROP COLUMN rubro');
        }

        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'categoria')) {
            // los productos de ropa con genero 'ninos' no caben en el enum original: se borran con sus observaciones (cascade)
            DB::statement("DELETE FROM productos WHERE genero = 'ninos'");
            DB::statement("ALTER TABLE productos MODIFY genero ENUM('masculino','femenino','unisex') NOT NULL DEFAULT 'masculino'");
            DB::statement('ALTER TABLE productos DROP COLUMN categoria, DROP COLUMN tipo, DROP COLUMN color, DROP COLUMN talles, DROP COLUMN referencia, DROP COLUMN imagen');
        }

        if (Schema::hasTable('corridas') && Schema::hasColumn('corridas', 'rubro')) {
            DB::statement("DELETE FROM corridas WHERE rubro <> 'perfumes'");
            DB::statement('ALTER TABLE corridas DROP INDEX uq_corrida_rubro_fecha, ADD UNIQUE KEY uq_corrida_fecha (fecha_hora)');
            DB::statement('ALTER TABLE corridas DROP COLUMN rubro');
        }

        if (Schema::hasTable('tiendas') && Schema::hasColumn('tiendas', 'rubro')) {
            DB::statement("DELETE FROM tiendas WHERE rubro <> 'perfumes'");
            DB::statement('ALTER TABLE tiendas DROP INDEX idx_tienda_rubro, DROP COLUMN rubro');
        }
    }
};
