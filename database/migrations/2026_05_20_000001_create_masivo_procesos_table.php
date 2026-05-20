<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masivo_procesos', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('pendiente'); // pendiente|procesando|completado|fallido
            $table->json('parametros')->nullable();
            $table->integer('total')->default(0);
            $table->integer('procesados')->default(0);
            $table->integer('actualizados')->default(0);
            $table->integer('omitidos')->default(0);
            $table->text('error')->nullable();
            $table->integer('tiempo_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masivo_procesos');
    }
};
