<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recepciones', function (Blueprint $table) {
            // 'oc' = recepción de orden de compra (flujo original)
            // 'manual' = entrada manual sin OC
            $table->string('tipo', 20)->nullable()->default(null)->after('foto_path');

            $table->string('observaciones', 500)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('oc_recepciones', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'observaciones']);
        });
    }
};
