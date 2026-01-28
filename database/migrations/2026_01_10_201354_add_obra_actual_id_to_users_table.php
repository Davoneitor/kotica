<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'obra_actual_id')) {
                $table->unsignedBigInteger('obra_actual_id')
                      ->nullable()
                      ->after('id');

                $table->foreign('obra_actual_id')
                      ->references('id')
                      ->on('obras');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'obra_actual_id')) {
                $table->dropForeign(['obra_actual_id']);
                $table->dropColumn('obra_actual_id');
            }
        });
    }
};
