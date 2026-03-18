<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transferencias_entre_obras', 'firma_path')) {
            return;
        }

        Schema::table('transferencias_entre_obras', function (Blueprint $table) {
            $table->string('firma_path')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('transferencias_entre_obras', function (Blueprint $table) {
            $table->dropColumn('firma_path');
        });
    }
};
