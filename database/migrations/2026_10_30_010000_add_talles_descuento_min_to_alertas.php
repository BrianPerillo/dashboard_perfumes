<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            // ropa: talles de interés separados por coma ("M, XL, 42"); la alerta solo avisa productos con alguno disponible
            $table->string('talles', 255)->nullable()->after('marca_filtro');
            // % de descuento mínimo para avisar (todos los rubros); null = el piso general (5 %)
            $table->unsignedTinyInteger('descuento_min')->nullable()->after('talles');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn(['talles', 'descuento_min']);
        });
    }
};
