<?php

// database/migrations/xxxx_add_firma_path_to_movimientos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('firma_recibe_path')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('firma_recibe_path');
        });
    }
};
