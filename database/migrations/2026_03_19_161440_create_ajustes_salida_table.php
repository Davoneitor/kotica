<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_salida', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_id');
            $table->unsignedBigInteger('movimiento_detalle_id');
            $table->unsignedBigInteger('inventario_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('descripcion', 200);
            $table->string('unidad', 50)->nullable();
            $table->decimal('cantidad_devuelta', 12, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('movimiento_id')->references('id')->on('movimientos');
            $table->foreign('movimiento_detalle_id')->references('id')->on('movimiento_detalles');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_salida');
    }
};
