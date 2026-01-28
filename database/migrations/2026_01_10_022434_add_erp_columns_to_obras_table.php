<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private function hasColumn(string $table, string $column): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(*) as total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ? AND COLUMN_NAME = ?
        ", [$table, $column]);

        return (int)($row->total ?? 0) > 0;
    }

    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {

            if (! $this->hasColumn('obras', 'erp_proyecto_id')) {
                $table->unsignedBigInteger('erp_proyecto_id')->nullable();
            }

            if (! $this->hasColumn('obras', 'erp_unidad_negocio_id')) {
                $table->unsignedBigInteger('erp_unidad_negocio_id')->nullable();
            }

            if (! $this->hasColumn('obras', 'erp_unidad_negocio')) {
                $table->string('erp_unidad_negocio')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {

            if (Schema::hasColumn('obras', 'erp_proyecto_id')) {
                $table->dropColumn('erp_proyecto_id');
            }

            if (Schema::hasColumn('obras', 'erp_unidad_negocio_id')) {
                $table->dropColumn('erp_unidad_negocio_id');
            }

            if (Schema::hasColumn('obras', 'erp_unidad_negocio')) {
                $table->dropColumn('erp_unidad_negocio');
            }
        });
    }
};
