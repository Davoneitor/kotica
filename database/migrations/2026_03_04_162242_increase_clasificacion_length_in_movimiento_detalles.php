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
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            $table->string('clasificacion', 20)->nullable()->change();
            $table->string('clasificacion_d', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            $table->string('clasificacion', 10)->nullable()->change();
            $table->string('clasificacion_d', 10)->nullable()->change();
        });
    }
};
