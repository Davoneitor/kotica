<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencias_entre_obras_detalle', function (Blueprint $table) {
            $table->decimal('precio_unitario', 18, 6)->nullable()->after('cantidad_recibida');
        });
    }

    public function down(): void
    {
        Schema::table('transferencias_entre_obras_detalle', function (Blueprint $table) {
            $table->dropColumn('precio_unitario');
        });
    }
};
