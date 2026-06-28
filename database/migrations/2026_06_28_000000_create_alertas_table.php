<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['marca', 'tienda', 'producto'])->default('producto');
            $table->string('valor', 200);
            $table->enum('canal', ['email', 'telegram'])->default('email');
            $table->string('email_destino', 200)->nullable();
            $table->string('telegram_chat_id', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_notificacion_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
