<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── transferencias_entre_obras: estatus + receptor + fecha ─────
        Schema::table('transferencias_entre_obras', function (Blueprint $table) {
            if (! Schema::hasColumn('transferencias_entre_obras', 'estatus')) {
                $table->string('estatus', 20)->default('pendiente')->after('observaciones');
            }
            if (! Schema::hasColumn('transferencias_entre_obras', 'user_receptor_id')) {
                $table->unsignedBigInteger('user_receptor_id')->nullable()->after('estatus');
            }
            if (! Schema::hasColumn('transferencias_entre_obras', 'fecha_recepcion')) {
                $table->dateTime('fecha_recepcion')->nullable()->after('user_receptor_id');
            }
        });

        // ── detalle: cantidad realmente recibida ───────────────────────
        Schema::table('transferencias_entre_obras_detalle', function (Blueprint $table) {
            if (! Schema::hasColumn('transferencias_entre_obras_detalle', 'cantidad_recibida')) {
                $table->decimal('cantidad_recibida', 12, 4)->nullable()->after('cantidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transferencias_entre_obras', function (Blueprint $table) {
            $table->dropColumn(['estatus', 'user_receptor_id', 'fecha_recepcion']);
        });
        Schema::table('transferencias_entre_obras_detalle', function (Blueprint $table) {
            $table->dropColumn('cantidad_recibida');
        });
    }
};
