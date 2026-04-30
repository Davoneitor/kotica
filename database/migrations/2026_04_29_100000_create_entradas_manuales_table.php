<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_manuales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('obra_id');
            $table->unsignedBigInteger('user_id');

            $table->string('insumo_id', 50)->nullable();
            $table->string('descripcion', 200);
            $table->string('unidad', 50);
            $table->string('proveedor', 150)->nullable();

            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 18, 6)->default(0);

            $table->date('fecha_entrada');
            $table->text('observaciones')->nullable();

            $table->string('familia', 80)->default('SIN FAMILIA');
            $table->string('subfamilia', 80)->default('SIN SUBFAMILIA');

            $table->timestamps();

            $table->foreign('obra_id')->references('id')->on('obras');
            $table->foreign('user_id')->references('id')->on('users');

            $table->index('obra_id');
            $table->index('insumo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_manuales');
    }
};
