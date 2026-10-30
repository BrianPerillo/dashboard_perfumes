<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `favoritos` es una tabla creada por el scraper/dashboard Streamlit y su FK apuntaba a la tabla
 * legacy `usuarios`, pero Laravel guarda `users.id` en `usuario_id`. Se re-apunta la FK a `users`
 * para que cualquier usuario de la app pueda guardar favoritos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('favoritos') || DB::getDriverName() !== 'mysql') {
            return;
        }

        // Si la FK vieja no existe (ya migrada o esquema distinto) se ignora el error
        try {
            DB::statement('ALTER TABLE favoritos DROP FOREIGN KEY fk_fav_usuario');
        } catch (\Throwable $e) {
            //
        }

        DB::statement('ALTER TABLE favoritos MODIFY usuario_id BIGINT UNSIGNED NOT NULL');

        // Favoritos huérfanos (usuario inexistente en users) no pueden cumplir la nueva FK
        DB::statement('DELETE FROM favoritos WHERE usuario_id NOT IN (SELECT id FROM users)');

        DB::statement('ALTER TABLE favoritos ADD CONSTRAINT fk_fav_user FOREIGN KEY (usuario_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        if (!Schema::hasTable('favoritos') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE favoritos DROP FOREIGN KEY fk_fav_user');
        DB::statement('ALTER TABLE favoritos MODIFY usuario_id INT(11) NOT NULL');
        DB::statement('DELETE FROM favoritos WHERE usuario_id NOT IN (SELECT id FROM usuarios)');
        DB::statement('ALTER TABLE favoritos ADD CONSTRAINT fk_fav_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE');
    }
};
