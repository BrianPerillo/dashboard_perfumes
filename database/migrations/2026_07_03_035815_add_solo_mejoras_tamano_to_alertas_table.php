<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->boolean('solo_mejoras')->default(false)->after('activo');
            $table->unsignedSmallInteger('tamano_ml')->nullable()->after('solo_mejoras');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn(['solo_mejoras', 'tamano_ml']);
        });
    }
};
