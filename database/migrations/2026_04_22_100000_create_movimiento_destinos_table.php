<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_destinos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detalle_id');
            $table->decimal('cantidad', 10, 4);
            $table->string('nivel', 50)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->timestamps();

            $table->foreign('detalle_id')
                  ->references('id')
                  ->on('movimiento_detalles')
                  ->onDelete('cascade');
        });

        // Seed one row per existing detalle (backward compat)
        DB::statement("
            INSERT INTO movimiento_destinos (detalle_id, cantidad, nivel, departamento, created_at, updated_at)
            SELECT id, cantidad, clasificacion, clasificacion_d, created_at, updated_at
            FROM movimiento_detalles
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_destinos');
    }
};
