<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 50)->nullable()->after('email');
            $table->tinyInteger('estatus')->default(1)->after('rol');
        });

        // Todos los usuarios existentes quedan activos
        DB::statement("UPDATE users SET estatus = 1 WHERE estatus IS NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'estatus']);
        });
    }
};
