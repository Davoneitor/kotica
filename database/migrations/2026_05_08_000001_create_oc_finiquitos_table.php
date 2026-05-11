<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oc_finiquitos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('obra_id');
            $table->unsignedBigInteger('user_id');

            $table->integer('id_pedido');
            $table->integer('pedido_det_id');

            $table->string('insumo',      100)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('unidad',       50)->nullable();

            $table->decimal('cantidad_pedida',   14, 4);
            $table->decimal('cantidad_recibida', 14, 4);
            $table->decimal('diferencia',        14, 4);

            $table->string('observaciones', 500)->nullable();

            $table->timestamps();

            // Un detalle de pedido solo puede finiquitarse una vez por obra
            $table->unique(['obra_id', 'pedido_det_id'], 'uq_finiquito_det');

            $table->foreign('obra_id')->references('id')->on('obras')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_finiquitos');
    }
};
