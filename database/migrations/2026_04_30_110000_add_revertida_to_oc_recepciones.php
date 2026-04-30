<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recepciones', function (Blueprint $table) {
            $table->timestamp('revertida_at')->nullable()->after('observaciones');
            $table->unsignedBigInteger('revertida_por')->nullable()->after('revertida_at');
            $table->string('motivo_reversion', 500)->nullable()->after('revertida_por');
        });
    }

    public function down(): void
    {
        Schema::table('oc_recepciones', function (Blueprint $table) {
            $table->dropColumn(['revertida_at', 'revertida_por', 'motivo_reversion']);
        });
    }
};
