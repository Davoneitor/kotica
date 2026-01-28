<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('observaciones', 500)->nullable()->after('nombre_cabo');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};
