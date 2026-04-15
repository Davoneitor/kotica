<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salida_camiones_escombro', function (Blueprint $table) {
            // UUID para deduplicación en modo offline (nullable para registros existentes)
            $table->string('uuid', 36)->nullable()->after('id');
        });

        // Índice único filtrado para SQL Server (ignora NULLs)
        \DB::statement(
            'CREATE UNIQUE INDEX ix_salida_camiones_escombro_uuid
             ON salida_camiones_escombro (uuid)
             WHERE uuid IS NOT NULL'
        );
    }

    public function down(): void
    {
        \DB::statement('DROP INDEX IF EXISTS ix_salida_camiones_escombro_uuid ON salida_camiones_escombro');
        Schema::table('salida_camiones_escombro', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
