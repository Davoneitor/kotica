<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('destinos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('erp_proyecto_id'); // ej 63,64,65,66
            $table->unsignedBigInteger('erp_unidad_negocio_id')->nullable();
            $table->string('erp_unidad_negocio', 50)->nullable();
            $table->string('destino', 150); // texto a mostrar (Proyecto)
            $table->timestamps();

            $table->index(['erp_proyecto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinos');
    }
};
