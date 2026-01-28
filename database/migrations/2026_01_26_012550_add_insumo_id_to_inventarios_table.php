<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            // si ya tienes datos, lo mejor es permitir null temporalmente
            $table->string('insumo_id', 50)->nullable()->after('id');

            // índice para búsquedas
            $table->index('insumo_id');

            // ✅ único por obra (permite mismo insumo en diferentes obras)
            $table->unique(['obra_id', 'insumo_id'], 'inv_obra_insumo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropUnique('inv_obra_insumo_unique');
            $table->dropIndex(['insumo_id']);
            $table->dropColumn('insumo_id');
        });
    }
};
