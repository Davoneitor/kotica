<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {

            if (!Schema::hasColumn('inventarios', 'familia')) {
                $table->string('familia', 80);
            }

            if (!Schema::hasColumn('inventarios', 'subfamilia')) {
                $table->string('subfamilia', 80)->nullable();
            }

            if (!Schema::hasColumn('inventarios', 'descripcion')) {
                $table->string('descripcion', 150);
            }

            if (!Schema::hasColumn('inventarios', 'unidad')) {
                $table->string('unidad', 50);
            }

            if (!Schema::hasColumn('inventarios', 'obra_id')) {
                $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('inventarios', 'proveedor')) {
                $table->string('proveedor', 150);
            }

            if (!Schema::hasColumn('inventarios', 'cantidad')) {
                $table->decimal('cantidad', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('inventarios', 'cantidad_teorica')) {
                $table->decimal('cantidad_teorica', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('inventarios', 'en_espera')) {
                $table->decimal('en_espera', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('inventarios', 'costo_promedio')) {
                $table->decimal('costo_promedio', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('inventarios', 'destino')) {
                $table->string('destino', 100);
            }
        });
    }

    public function down(): void
    {
        // No rollback automático para evitar broncas en SQL Server
    }
};
