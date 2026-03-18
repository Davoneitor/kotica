<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salida_camiones_escombro', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('obra_id');
            $table->date('fecha');
            $table->string('hora_entrada', 10)->nullable();
            $table->string('hora_salida', 10)->nullable();
            $table->string('chofer', 150)->nullable();
            $table->string('camion', 150)->nullable();
            $table->string('placas', 30)->nullable();
            $table->decimal('metros_cubicos', 8, 2)->nullable();
            $table->string('folio_recibo', 100)->nullable();
            $table->string('foto_vale', 255)->nullable();
            $table->string('foto_camion', 255)->nullable();
            $table->timestamps();

            $table->foreign('obra_id')->references('id')->on('obras');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salida_camiones_escombro');
    }
};
