<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('movimiento_detalles', 'insumo_id')) {
                $table->string('insumo_id', 50)->nullable()->after('inventario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('movimiento_detalles', 'insumo_id')) {
                $table->dropColumn('insumo_id');
            }
        });
    }
};
