<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimiento_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('movimiento_id');
            $table->unsignedBigInteger('inventario_id')->nullable(); // referencia al producto si existe

            $table->string('familia', 80);
            $table->string('subfamilia', 80);
            $table->string('descripcion', 150);
            $table->string('unidad', 50);

            $table->decimal('cantidad', 12, 2);

            $table->timestamps();

            $table->foreign('movimiento_id')->references('id')->on('movimientos')->onDelete('cascade');
            $table->foreign('inventario_id')->references('id')->on('inventarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_detalles');
    }
};
