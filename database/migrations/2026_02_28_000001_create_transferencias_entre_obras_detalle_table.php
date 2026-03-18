<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transferencias_entre_obras_detalle')) {
            return;
        }

        Schema::create('transferencias_entre_obras_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transferencia_id');
            $table->string('insumo_id', 50)->nullable();
            $table->string('descripcion', 150);
            $table->string('unidad', 50)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('origen_stock_antes', 12, 2);
            $table->decimal('origen_stock_despues', 12, 2);
            $table->decimal('destino_stock_antes', 12, 2);
            $table->decimal('destino_stock_despues', 12, 2);
            $table->timestamps();

            $table->foreign('transferencia_id')
                  ->references('id')
                  ->on('transferencias_entre_obras')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_entre_obras_detalle');
    }
};
