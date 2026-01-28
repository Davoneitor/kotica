<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            // ✅ Para saber si ya fue recuperado
            $table->dateTime('devuelto_at')->nullable()->after('devolvible');
            $table->unsignedBigInteger('devuelto_user_id')->nullable()->after('devuelto_at');

            $table->index(['devolvible', 'devuelto_at']);
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_detalles', function (Blueprint $table) {
            $table->dropIndex(['devolvible', 'devuelto_at']);
            $table->dropColumn(['devuelto_at', 'devuelto_user_id']);
        });
    }
};
