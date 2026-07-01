<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();             // gratis | pro | premium
            $table->string('nombre');                     // Gratis | Pro | Premium
            $table->decimal('precio', 10, 2)->default(0);
            $table->string('moneda', 3)->default('ARS');
            $table->integer('max_alertas')->default(1);  // -1 = ilimitadas
            $table->boolean('canal_telegram')->default(false);
            $table->json('features')->nullable();         // lista de bullets para la UI
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
