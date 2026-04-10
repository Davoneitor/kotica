<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'puede_editar_desc_auxiliar')) {
                $table->boolean('puede_editar_desc_auxiliar')->default(false)->after('solo_explore');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'puede_editar_desc_auxiliar')) {
                $table->dropColumn('puede_editar_desc_auxiliar');
            }
        });
    }
};
