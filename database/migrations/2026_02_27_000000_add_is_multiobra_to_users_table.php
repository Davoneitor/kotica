<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_multiobra')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('is_multiobra')->default(0)->after('is_admin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_multiobra')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_multiobra');
            });
        }
    }
};
