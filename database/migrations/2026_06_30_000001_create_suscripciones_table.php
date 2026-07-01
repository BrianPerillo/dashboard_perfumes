<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planes');
            // pendiente | aprobado | rechazado | cancelado | en_proceso
            $table->string('estado')->default('pendiente');
            $table->string('mp_preference_id')->nullable();
            $table->string('mp_payment_id')->nullable();
            $table->string('mp_external_reference')->nullable()->index();
            $table->decimal('monto', 10, 2)->nullable();
            $table->string('moneda', 3)->default('ARS');
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamp('vence_at')->nullable();   // fin del período pagado
            $table->json('mp_data')->nullable();         // respuesta raw de MP
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
