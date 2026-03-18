<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salida_camiones_escombro', function (Blueprint $table) {
            if (!Schema::hasColumn('salida_camiones_escombro', 'tipo_material')) {
                $table->string('tipo_material', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        // No rollback automático
    }
};
