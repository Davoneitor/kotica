<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transferencias_entre_obras')) {
            return;
        }

        Schema::create('transferencias_entre_obras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('obra_origen_id');
            $table->unsignedBigInteger('obra_destino_id');
            $table->unsignedBigInteger('user_id');
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('obra_origen_id')->references('id')->on('obras');
            $table->foreign('obra_destino_id')->references('id')->on('obras');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_entre_obras');
    }
};
