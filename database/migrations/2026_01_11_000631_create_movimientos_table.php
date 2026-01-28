<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('obra_id');        // obra donde está el usuario
            $table->unsignedBigInteger('user_id');        // quien hizo la salida
            $table->dateTime('fecha');                   // fecha/hora del movimiento

            $table->string('destino', 150);              // por ahora texto
            $table->string('nombre_cabo', 150)->nullable();

            $table->unsignedTinyInteger('estatus');      // 1=salida, 2=entrada, etc

            $table->timestamps();

            $table->foreign('obra_id')->references('id')->on('obras');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
