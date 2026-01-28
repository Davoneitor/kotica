<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha')->useCurrent();

            // Obra actual del usuario (id real de obras)
            $table->foreignId('obra_id')->constrained('obras');

            // Usuario que registró
            $table->foreignId('user_id')->constrained('users');

            $table->string('nombre_cabo')->nullable();

            // Destino desde ERP (guardamos id + nombre)
            $table->unsignedBigInteger('erp_proyecto_id');
            $table->string('destino_nombre');

            // Estatus: 1 = salida (como pediste)
            $table->unsignedTinyInteger('estatus')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
